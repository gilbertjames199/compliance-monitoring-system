<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Office;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Office');
    }

    public function view(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('View:Office');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Office');
    }

    public function update(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('Update:Office');
    }

    public function delete(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('Delete:Office');
    }

    public function restore(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('Restore:Office');
    }

    public function forceDelete(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('ForceDelete:Office');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Office');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Office');
    }

    public function replicate(AuthUser $authUser, Office $office): bool
    {
        return $authUser->can('Replicate:Office');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Office');
    }

}