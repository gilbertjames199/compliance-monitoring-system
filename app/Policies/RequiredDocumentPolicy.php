<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RequiredDocument;
use Illuminate\Auth\Access\HandlesAuthorization;

class RequiredDocumentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RequiredDocument');
    }

    // public function view(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    // {
    //     return $authUser->can('View:RequiredDocument');
    // }

     public function view(AuthUser $user, RequiredDocument $requiredDocument): bool
    {
        // Super admin and department head can view everything
        if ($user->hasRole(['super_admin', 'department_head'])) {
            return true;
        }
        
        // AO and admin can view non-confidential documents in their department
        if ($user->hasRole(['AO', 'admin'])) {
            return !$requiredDocument->is_confidential && 
                $requiredDocument->complyingOffices()
                    ->where('department_code', $user->department_code)
                    ->exists();
        }
        
        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RequiredDocument');
    }

    // public function update(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    // {
    //     return $authUser->can('Update:RequiredDocument');
    // }

    public function update(AuthUser $user, RequiredDocument $requiredDocument): bool
    {
        // Same logic as view
        return $this->view($user, $requiredDocument);
    }


    public function delete(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('Delete:RequiredDocument');
    }

    public function restore(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('Restore:RequiredDocument');
    }

    public function forceDelete(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('ForceDelete:RequiredDocument');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RequiredDocument');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RequiredDocument');
    }

    public function replicate(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('Replicate:RequiredDocument');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RequiredDocument');
    }

}