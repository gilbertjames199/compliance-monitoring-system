<?php

namespace App\Console\Commands;

use App\Mail\ComplianceReminderMail;
use App\Models\AuditLog;
use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendComplianceReminders extends Command
{
    protected $signature = 'compliance:send-reminders';
    protected $description = 'Send daily email reminders to offices that have not yet complied';

    public function handle(): void
    {
        Log::channel('daily')->info('=== Compliance Reminder Job Started ===', [
            'ran_at' => now()->toDateTimeString(),
        ]);

        $this->info('Checking pending compliance offices...');

        $pending = ComplyingOffice::query()
            ->with('requiredDocument')
            ->where(function ($q) {
                $q->where('status', -1)->orWhereNull('status');
            })
            ->whereHas('requiredDocument', function ($q) {
                $q->whereDate('due_date', '<', today()); // ✅ only overdue
            })
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending offices found. No reminders sent.');
            Log::channel('daily')->info('No pending offices found. No reminders sent.');
            return;
        }

        Log::channel('daily')->info("Found {$pending->count()} pending complying office(s).");

        // ✅ Get all user IDs that have any role assigned
        $usersWithRoles = DB::connection('mysql')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->toArray();

        $sentCount    = 0;
        $skippedCount = 0;

        foreach ($pending as $complyingOffice) {
            $document   = $complyingOffice->requiredDocument;
            $officeName = Office::on('mysql2')
                ->where('department_code', $complyingOffice->department_code)
                ->value('office') ?? "Department Code {$complyingOffice->department_code}";

            // ✅ Base query — only users in this office who have a role
            $usersQuery = User::where('department_code', $complyingOffice->department_code)
                ->whereNotNull('email')
                ->whereIn('recid', $usersWithRoles);

            // ✅ Confidential filter — only users with ViewConfidential permission
            if ($document->is_confidential) {
                // Direct permission holders
                $directIds = DB::connection('mysql')
                    ->table('model_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                    ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                    ->where('model_has_permissions.model_type', User::class)
                    ->pluck('model_has_permissions.model_id')
                    ->toArray();

                // Role-based permission holders
                $roleIds = DB::connection('mysql')
                    ->table('model_has_roles')
                    ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
                    ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                    ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                    ->where('model_has_roles.model_type', User::class)
                    ->pluck('model_has_roles.model_id')
                    ->toArray();

                $allowedUserIds = array_unique(array_merge($directIds, $roleIds));
                $usersQuery->whereIn('recid', $allowedUserIds);
            }

            $recipients = $usersQuery->distinct()->get();

            if ($recipients->isEmpty()) {
                $this->warn("Skipped — no eligible recipients for: {$officeName}");

                Log::channel('daily')->warning('Skipped — no eligible recipients found', [
                    'office'               => $officeName,
                    'department_code'      => $complyingOffice->department_code,
                    'required_document_id' => $complyingOffice->required_document_id,
                    'is_confidential'      => $document->is_confidential,
                ]);

                AuditLog::create([
                    'event'                 => 'reminder_skipped',
                    'requirement_id'        => $complyingOffice->required_document_id,
                    'requirement_name'      => $document->requirement,
                    'complying_office_id'   => $complyingOffice->id,
                    'office_name'           => $officeName,
                    'requiring_agency_name' => $document->agency_name,
                    'user_id'               => null,
                    'acted_by'              => null,
                    'old_status'            => $complyingOffice->status,
                    'new_status'            => $complyingOffice->status,
                    'old_validation_status' => $complyingOffice->validation_status,
                    'new_validation_status' => $complyingOffice->validation_status,
                    'remarks'               => 'Automated reminder skipped — no eligible recipients found for this office.',
                    'action_at'             => now(),
                ]);

                $skippedCount++;
                continue;
            }

            foreach ($recipients as $recipient) {

                // ✅ Skip invalid emails
                if (empty($recipient->email) || ! filter_var(trim($recipient->email), FILTER_VALIDATE_EMAIL)) {
                    Log::channel('daily')->warning('Skipped — invalid email', [
                        'user_id' => $recipient->recid,
                        'email'   => $recipient->email,
                    ]);
                    $this->warn("Skipped invalid email → {$recipient->email}");
                    continue;
                }

                // ✅ Cache duplicate check — prevent sending twice in same day
                $cacheKey = "compliance_reminder_{$complyingOffice->id}_user_{$recipient->recid}";
                if (Cache::has($cacheKey)) {
                    Log::channel('daily')->info('Skipped — already reminded recently (cache)', [
                        'user_id' => $recipient->recid,
                        'office'  => $officeName,
                    ]);
                    continue;
                }

                // ✅ DB duplicate check — in case cache was cleared
                $alreadyNotified = AuditLog::where('requirement_id', $complyingOffice->required_document_id)
                    ->where('user_id', $recipient->recid)
                    ->where('event', 'reminder_sent')
                    ->whereDate('action_at', today())
                    ->exists();

                if ($alreadyNotified) {
                    Log::channel('daily')->info('Skipped — already reminded today (db)', [
                        'user_id' => $recipient->recid,
                        'office'  => $officeName,
                    ]);
                    continue;
                }

                try {
                    Mail::to($recipient->email)
                        ->send(new ComplianceReminderMail(
                            document: $document,
                            officeName: $officeName,
                        ));

                    Log::channel('daily')->info('Reminder email sent', [
                        'office'               => $officeName,
                        'recipient_email'      => $recipient->email,
                        'requirement'          => $document->requirement,
                        'required_document_id' => $complyingOffice->required_document_id,
                        'is_confidential'      => $document->is_confidential,
                        'sent_at'              => now()->toDateTimeString(),
                    ]);

                    AuditLog::create([
                        'event'                 => 'reminder_sent',
                        'requirement_id'        => $complyingOffice->required_document_id,
                        'requirement_name'      => $document->requirement,
                        'complying_office_id'   => $complyingOffice->id,
                        ...(array) AuditLogger::resolveDivisionData($complyingOffice),
                        'office_name'           => $officeName,
                        'requiring_agency_name' => $document->agency_name,
                        'user_id'               => $recipient->recid,
                        'acted_by'              => null,
                        'old_status'            => $complyingOffice->status,
                        'new_status'            => $complyingOffice->status,
                        'old_validation_status' => $complyingOffice->validation_status,
                        'new_validation_status' => $complyingOffice->validation_status,
                        'remarks'               => "Automated daily reminder sent to {$recipient->email}."
                            . ($document->is_confidential ? ' [Confidential]' : ''),
                        'action_at'             => now(),
                    ]);

                    // ✅ Mark in cache so it won't send again today
                    Cache::put($cacheKey, true, now()->addDay());

                    $this->info("Email sent → {$recipient->email} ({$officeName})");
                    $sentCount++;

                } catch (\Exception $e) {
                    Log::channel('daily')->error('Failed to send reminder email', [
                        'office'          => $officeName,
                        'recipient_email' => $recipient->email,
                        'error'           => $e->getMessage(),
                    ]);

                    AuditLog::create([
                        'event'                 => 'reminder_failed',
                        'requirement_id'        => $complyingOffice->required_document_id,
                        'requirement_name'      => $document->requirement,
                        'complying_office_id'   => $complyingOffice->id,
                        'office_name'           => $officeName,
                        'requiring_agency_name' => $document->agency_name,
                        'user_id'               => $recipient->recid,
                        'acted_by'              => null,
                        'old_status'            => $complyingOffice->status,
                        'new_status'            => $complyingOffice->status,
                        'old_validation_status' => $complyingOffice->validation_status,
                        'new_validation_status' => $complyingOffice->validation_status,
                        'remarks'               => "Automated reminder FAILED for {$recipient->email}. Error: {$e->getMessage()}",
                        'action_at'             => now(),
                    ]);

                    $this->error("Failed → {$recipient->email} | {$e->getMessage()}");
                }
            }

            $complyingOffice->update(['last_notified_at' => now()]);
        }

        Log::channel('daily')->info('=== Compliance Reminder Job Finished ===', [
            'total_sent'    => $sentCount,
            'total_skipped' => $skippedCount,
            'finished_at'   => now()->toDateTimeString(),
        ]);

        $this->info("Done. Sent: {$sentCount} | Skipped: {$skippedCount}");
    }
}