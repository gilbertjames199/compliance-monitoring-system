<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use App\Notifications\RequiredDocumentCreatedNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequiredDocumentObserver
{
    private array $watchedFields = [
        'requirement', 'due_date', 'date_from',
        'agency_name', 'agency_type', 'category',
        'is_recurring', 'recurrence_type', 'recurrence_interval',
        'is_confidential',
    ];

    /**
     * Handle the RequiredDocument "created" event.
     */
    public function created(RequiredDocument $requiredDocument): void
    {
        $complyingOfficeCodes = $requiredDocument->complyingOffices()->pluck('department_code');
        if ($complyingOfficeCodes->isEmpty()) return;

        // Get eligible users (with roles + confidential check)
        $usersQuery = User::whereIn('department_code', $complyingOfficeCodes)
            ->whereHas('roles'); // only users with roles

        if ($requiredDocument->is_confidential) {
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

        $users = $usersQuery->get();
        $actor = auth()->user();
        $actorName = $actor?->FullName ?? $actor?->name ?? 'System';

        $officeMap = Office::whereIn('department_code', $complyingOfficeCodes)
            ->pluck('office', 'department_code');

        foreach ($users as $user) {
            try {
                // Duplicate checks FIRST
                $duplicateKey = "requirement_created_notification_{$requiredDocument->id}_user_{$user->recid}";
                if (Cache::has($duplicateKey)) {
                    Log::info("Skipping duplicate notification for user {$user->id}");
                    continue;
                }

                $alreadyNotified = AuditLog::where('requirement_id', $requiredDocument->id)
                    ->where('user_id', $user->recid)
                    ->where('event', 'due date reminder sent') // ← CHANGED HERE
                    ->whereDate('action_at', today())
                    ->exists();

                if ($alreadyNotified) {
                    Log::info("Already notified today for user {$user->id}");
                    continue;
                }

                // Skip invalid emails
                if (empty($user->email) || !filter_var(trim($user->email), FILTER_VALIDATE_EMAIL)) {
                    Log::warning("User {$user->id} has invalid or empty email, skipped", [
                        'email' => $user->email
                    ]);
                    continue;
                }

                // Send the notification
                $user->notify(new RequiredDocumentCreatedNotification($requiredDocument));

                // Audit log – using due date reminder sent
                $officeName = $officeMap[$user->department_code] ?? $user->department_code;

                AuditLog::create([
                    'event'                  => 'due date reminder sent', // ← CHANGED HERE
                    'user_id'                => $user->recid,
                    'acted_by'               => $actorName,
                    'action_at'              => now(),
                    'requirement_id'         => $requiredDocument->id,
                    'requirement_name'       => $requiredDocument->requirement,
                    'complying_office_id'    => null,
                    'office_name'            => $officeName,
                    'requiring_agency_name'  => $requiredDocument->agency_name,
                    'remarks'                => "Automatic notification sent on document creation to {$user->email}",
                ]);

                Cache::put($duplicateKey, true, now()->addDay());

            } catch (\Exception $e) {
                Log::error("Failed to send creation notification to user {$user->id}", [
                    'error' => $e->getMessage(),
                    'requirement_id' => $requiredDocument->id,
                ]);
            }
        }

        // Optional: log the document creation itself
        AuditLogger::logDocument('required document created', $requiredDocument);
    }

    /**
     * Handle the RequiredDocument "updated" event.
     */
    public function updated(RequiredDocument $requiredDocument): void
    {
        if ($requiredDocument->wasRecentlyCreated) {
            return;
        }

        // Only log if watched fields actually changed
        $changedWatched = array_intersect(
            array_keys($requiredDocument->getDirty()),
            $this->watchedFields
        );

        if (empty($changedWatched)) {
            return;
        }

        $old = [];
        $new = [];
        foreach ($changedWatched as $field) {
            $old[$field] = $requiredDocument->getOriginal($field);
            $new[$field] = $requiredDocument->$field;
        }

        AuditLogger::logDocument('required document updated', $requiredDocument, $old, $new
        );
    }

    /**
     * Handle the RequiredDocument "deleted" event.
     */
    public function deleted(RequiredDocument $requiredDocument): void
    {
        AuditLogger::logDocument('required document deleted', $requiredDocument);
    }

    /**
     * Handle the RequiredDocument "restored" event.
     */
    public function restored(RequiredDocument $requiredDocument): void
    {
        //
    }

    /**
     * Handle the RequiredDocument "force deleted" event.
     */
    public function forceDeleted(RequiredDocument $requiredDocument): void
    {
        //
    }
}

