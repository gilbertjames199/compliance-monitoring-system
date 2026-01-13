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

    public function view(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('View:RequiredDocument');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RequiredDocument');
    }

    public function update(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        return $authUser->can('Update:RequiredDocument');
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