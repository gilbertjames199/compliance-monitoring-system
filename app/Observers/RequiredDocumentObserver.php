<?php

namespace App\Observers;

use App\Models\User;
use App\Models\RequiredDocument;
use App\Notifications\RequiredDocumentCreatedNotification;

class RequiredDocumentObserver
{
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

        // If confidential, only send to super_admin and department_head
        if ($requiredDocument->is_confidential) {
            $usersQuery->whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'department_head']);
            });
        }

        $users = $usersQuery->get();

        // Send notification to each user
        foreach ($users as $user) {
            $user->notify(new RequiredDocumentCreatedNotification($requiredDocument));
        }
    }

    /**
     * Handle the RequiredDocument "updated" event.
     */
    public function updated(RequiredDocument $requiredDocument): void
    {
        //
    }

    /**
     * Handle the RequiredDocument "deleted" event.
     */
    public function deleted(RequiredDocument $requiredDocument): void
    {
        //
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
