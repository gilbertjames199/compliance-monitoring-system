<?php

namespace App\Observers;

use App\Models\RequiredDocument;
use App\Models\User;
use App\Notifications\RequiredDocumentCreatedNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

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
        // Get all complying offices for this requirement
        $complyingOfficeCodes = $requiredDocument->complyingOffices()->pluck('department_code');

        if ($complyingOfficeCodes->isEmpty()) {
            return;
        }

        // Get all users in those offices
        $usersQuery = User::whereIn('department_code', $complyingOfficeCodes);

         // If confidential, only send to users with permission to view confidential documents
        if ($requiredDocument->is_confidential) {
            $allowedUserIds = DB::connection('mysql')
                ->table('model_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
                ->where('permissions.name', 'ViewConfidential:RequiredDocument')
                ->where('model_has_permissions.model_type', User::class)
                ->pluck('model_has_permissions.model_id')
                ->toArray();
            $usersQuery->whereIn('recid', $allowedUserIds);
        }

        $users = $usersQuery->get();

        // Send notification to each user
        foreach ($users as $user) {
            $user->notify(new RequiredDocumentCreatedNotification($requiredDocument));
        }

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

