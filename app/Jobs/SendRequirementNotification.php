<?php

namespace App\Jobs;

use App\Mail\DueDateReminderMail;
use App\Models\AuditLog;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;

class SendRequirementNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $requirementId;

    public function __construct($requirementId = null)
    {
        $this->requirementId = $requirementId;
    }

    public function handle(): void
    {
        if ($this->requirementId === null) {
            $this->multiSend();
            return;
        }

        $record = RequiredDocument::find($this->requirementId);
        if (!$record) return;

        // ✅ PASTE LOG HERE - Right after checking documents exist
            Log::info("Auto notification triggered (single)", [
            'trigger' => 'scheduled_job_handle',
            'requirement_id' => $record->id,
            'requirement_name' => $record->requirement,
            'due_date' => $record->due_date,
            'timestamp' => now()
        ]);

        $complyingOffices = ComplyingOffice::where('required_document_id', $record->id)->get();

        $departmentCodes = $complyingOffices->pluck('department_code')->unique();

        $usersWithRoles = DB::connection('mysql')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->toArray();

        $usersQuery = User::whereIn('department_code', $departmentCodes)
            ->whereIn('recid', $usersWithRoles);

        if ($record->is_confidential) {
            $directIds = DB::connection('mysql')
                ->table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                ->where('model_has_permissions.model_type', User::class)
                ->pluck('model_has_permissions.model_id')
                ->toArray();

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

        $users = $usersQuery->distinct()->get();

        // ✅ Resolve complying office info per user's department
        $officeMap = \App\Models\Office::whereIn('department_code', $departmentCodes)
            ->pluck('office', 'department_code');

        $complyingOfficeMap = $complyingOffices->keyBy('department_code');

        foreach ($users as $user) {
            try {
                if (empty($user->email) || !filter_var(trim($user->email), FILTER_VALIDATE_EMAIL)) {
                    Log::warning("User {$user->id} has invalid or empty email, skipped", [
                        'email' => $user->email
                    ]);
                    continue;
                }

                // ✅ Check for duplicate notification within last 24 hours
                $duplicateKey = "requirement_notification_{$record->id}_user_{$user->recid}";
                
                if (Cache::has($duplicateKey)) {
                    Log::info("Skipping - already notified recently", [
                        'user' => $user->id,
                        'requirement' => $record->id,
                        'email' => $user->email
                    ]);
                    continue;
                }

                // ✅ Check database for existing notification today
                $alreadyNotified = AuditLog::where('requirement_id', $record->id)
                    ->where('user_id', $user->recid)
                    ->where('event', 'requirement notification sent')
                    ->whereDate('action_at', today())
                    ->exists();

                if ($alreadyNotified) {
                    Log::info("Skipping - already notified today (database check)", [
                        'user' => $user->id,
                        'requirement' => $record->id
                    ]);
                    continue;
                }

                $officeName = $officeMap[$user->department_code] ?? $user->department_code;
                Mail::to($user->email)->queue(
                    new DueDateReminderMail($record, $user, $officeName)
                );

                Log::info("Email queued for user {$user->id}");

                // ✅ Audit log per user notified
                $complyingOffice = $complyingOfficeMap[$user->department_code] ?? null;

                AuditLog::create([
                    'event'                  => 'requirement notification sent',
                    'user_id'                => $user->recid,
                    'acted_by'               => null, // system-triggered
                    'action_at'              => now(),
                    'requirement_id'         => $record->id,
                    'requirement_name'       => $record->requirement,
                    'complying_office_id'    => $complyingOffice?->id,
                    'office_name'            => $officeMap[$user->department_code] ?? $user->department_code,
                    'requiring_agency_name'  => $record->agency_name,
                    'remarks'                => "Notification email sent to {$user->email}",
                ]);

                // ✅ Set cache to prevent duplicate for 24 hours
                Cache::put($duplicateKey, true, now()->addDay());

            } catch (\Exception $e) {
                Log::error("Failed for user {$user->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function multiSend(): void
    {
        $documents = RequiredDocument::whereDate('due_date', now()->addDays(2))
            ->whereNull('reminder_sent_at')
            ->with('complyingOffices')
            ->get();

        if ($documents->isEmpty()) {
            Log::info('No documents pending reminders');
            return;
        }

        // ✅ PASTE LOG HERE - Right after checking documents exist
        Log::info("Auto notification triggered (bulk)", [
            'trigger' => 'scheduled_job_multisend',
            'document_count' => $documents->count(),
            'due_date_range' => now()->addDays(2)->format('Y-m-d'),
            'timestamp' => now()
        ]);

        foreach ($documents as $document) {

            $departmentCodes = $document->complyingOffices
                ->where('status', '!=', 1) // skip already complied offices
                ->pluck('department_code')
                ->unique();

            $officeNames = \App\Models\Office::whereIn('department_code', $departmentCodes)
                ->pluck('office', 'department_code');

            $complyingOfficeMap = $document->complyingOffices->keyBy('department_code');

            $usersWithRoles = DB::connection('mysql')
                ->table('model_has_roles')
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->toArray();

            $usersQuery = User::whereIn('department_code', $departmentCodes)
                ->whereIn('recid', $usersWithRoles);

            if ($document->is_confidential) {
                $directIds = DB::connection('mysql')
                    ->table('model_has_permissions')
                    ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                    ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                    ->where('model_has_permissions.model_type', User::class)
                    ->pluck('model_has_permissions.model_id')
                    ->toArray();

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

            $users = $usersQuery->distinct()->get();

            $emailCount = 0;

            foreach ($users as $user) {
                try {
                    if (empty($user->email) || !filter_var(trim($user->email), FILTER_VALIDATE_EMAIL)) {
                        Log::warning("User {$user->id} has invalid or empty email, skipped", [
                            'email' => $user->email
                        ]);
                        continue;
                    }

                    // ✅ Check for duplicate reminder within last 24 hours
                    $duplicateKey = "due_date_reminder_{$document->id}_user_{$user->recid}";
                    
                    if (Cache::has($duplicateKey)) {
                        Log::info("Skipping - already reminded recently", [
                            'user' => $user->id,
                            'requirement' => $document->id,
                            'email' => $user->email
                        ]);
                        continue;
                    }

                    // ✅ Check database for existing reminder today
                    $alreadyNotified = AuditLog::where('requirement_id', $document->id)
                        ->where('user_id', $user->recid)
                        ->where('event', 'due date reminder sent')
                        ->whereDate('action_at', today())
                        ->exists();

                    if ($alreadyNotified) {
                        Log::info("Skipping - already reminded today (database check)", [
                            'user' => $user->id,
                            'requirement' => $document->id
                        ]);
                        continue;
                    }

                    $officeName = $officeNames[$user->department_code] ?? $user->department_code;
                    $complyingOffice = $complyingOfficeMap[$user->department_code] ?? null;

                    Mail::to($user->email)->queue(
                        new DueDateReminderMail($document, $user, $officeName)
                    );

                    Log::info("Reminder queued for {$user->id}");
                    $emailCount++;

                    // ✅ Audit log per user reminded
                    AuditLog::create([
                        'event'                  => 'due date reminder sent',
                        'user_id'                => $user->recid,
                        'acted_by'               => null, // system-triggered
                        'action_at'              => now(),
                        'requirement_id'         => $document->id,
                        'requirement_name'       => $document->requirement,
                        'complying_office_id'    => $complyingOffice?->id,
                        'office_name'            => $officeName,
                        'requiring_agency_name'  => $document->agency_name,
                        'remarks'                => "Due date reminder sent to {$user->email}. Due on {$document->due_date->format('M d, Y')}.",
                    ]);

                    // ✅ Set cache to prevent duplicate for 24 hours
                    Cache::put($duplicateKey, true, now()->addDay());

                } catch (\Exception $e) {
                    Log::error("Failed for {$user->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'email' => $user->email,
                    ]);
                }
            }

            if ($emailCount > 0) {
                $document->update(['reminder_sent_at' => now()]);
            }
        }
    }
}