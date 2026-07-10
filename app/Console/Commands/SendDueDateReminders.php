<?php

namespace App\Console\Commands;

use App\Mail\DueDateReminderMail;
use App\Models\AuditLog;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDueDateReminders extends Command
{
    protected $signature = 'reminders:due-documents';
    protected $description = 'Send email reminders for documents due in 2 days';

    public function handle()
    {
        while (true) {
            $documents = RequiredDocument::whereDate('due_date', now()->addDays(2)->toDateString())
                ->whereNull('reminder_sent_at')
                ->with('complyingOffices')
                ->get();

            if ($documents->isNotEmpty()) {
                Log::info("Due date reminder check", [
                    'document_count' => $documents->count(),
                    'timestamp' => now()
                ]);

                $usersWithRoles = DB::connection('mysql')
                    ->table('model_has_roles')
                    ->where('model_type', User::class)
                    ->pluck('model_id')
                    ->toArray();

                foreach ($documents as $document) {
                    $departmentCodes = $document->complyingOffices
                        ->where('status', '!=', 1)
                        ->pluck('department_code')
                        ->unique();

                    if ($departmentCodes->isEmpty()) continue;

                    $officeNames = Office::whereIn('department_code', $departmentCodes)
                        ->pluck('office', 'department_code');

                    $complyingOfficeMap = $document->complyingOffices->keyBy('department_code');

                    $usersQuery = User::whereIn('department_code', $departmentCodes)
                        ->whereIn('recid', $usersWithRoles);

                    if ($document->is_confidential) {
                        // Get direct permission holders
                        $directIds = DB::connection('mysql')
                            ->table('model_has_permissions')
                            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                            ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                            ->where('model_has_permissions.model_type', User::class)
                            ->pluck('model_has_permissions.model_id')
                            ->toArray();

                        // Get role-based permission holders
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
                        // Skip invalid emails
                        if (empty($user->email) || !filter_var(trim($user->email), FILTER_VALIDATE_EMAIL)) {
                            Log::warning("Skipped - invalid email", ['user' => $user->recid, 'email' => $user->email]);
                            continue;
                        }

                        // Cache duplicate check
                        $cacheKey = "due_date_reminder_{$document->id}_user_{$user->recid}";
                        if (Cache::has($cacheKey)) {
                            Log::info("Skipped - already reminded recently", ['user' => $user->recid]);
                            continue;
                        }

                        // DB duplicate check
                        $alreadyNotified = AuditLog::where('requirement_id', $document->id)
                            ->where('user_id', $user->recid)
                            ->where('event', 'due date reminder sent')
                            ->whereDate('action_at', today())
                            ->exists();

                        if ($alreadyNotified) {
                            Log::info("Skipped - already reminded today", ['user' => $user->recid]);
                            continue;
                        }

                        $officeName = $officeNames[$user->department_code] ?? $user->department_code;
                        $complyingOffice = $complyingOfficeMap[$user->department_code] ?? null;

                        try {
                            Mail::to($user->email)->send(
                                new DueDateReminderMail($document, $user, $officeName)
                            );

                            AuditLog::create([
                                'event'                 => 'due date reminder sent',
                                'user_id'               => $user->recid,
                                'acted_by'              => null,
                                'action_at'             => now(),
                                'requirement_id'        => $document->id,
                                'requirement_name'      => $document->requirement,
                                'complying_office_id'   => $complyingOffice?->id,
                                ...AuditLogger::resolveDivisionData($complyingOffice),
                                'office_name'           => $officeName,
                                'requiring_agency_name' => $document->agency_name,
                                'remarks'               => "Due date reminder sent to {$user->email}. Due on {$document->due_date->format('M d, Y')}.",
                            ]);

                            Cache::put($cacheKey, true, now()->addDay());
                            $emailCount++;

                            Log::info("Reminder sent to {$user->email} for {$document->requirement}");
                            $this->info("Sent to: {$user->email}");

                        } catch (\Exception $e) {
                            Log::error("Failed to send to {$user->email}", [
                                'error' => $e->getMessage()
                            ]);
                        }
                    }

                    if ($emailCount > 0) {
                        $document->update(['reminder_sent_at' => now()]);
                    }
                }
            }

            sleep(10); // check every 10 seconds
        }
    }
}