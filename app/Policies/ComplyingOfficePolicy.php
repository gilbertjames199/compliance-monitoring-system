<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ComplyingOffice;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComplyingOfficePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ComplyingOffice');
    }

    public function view(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('View:ComplyingOffice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComplyingOffice');
    }

    public function update(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('Update:ComplyingOffice');
    }

    public function delete(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('Delete:ComplyingOffice');
    }

    public function restore(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('Restore:ComplyingOffice');
    }

    public function forceDelete(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('ForceDelete:ComplyingOffice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ComplyingOffice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ComplyingOffice');
    }

    public function replicate(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        return $authUser->can('Replicate:ComplyingOffice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ComplyingOffice');
    }

}