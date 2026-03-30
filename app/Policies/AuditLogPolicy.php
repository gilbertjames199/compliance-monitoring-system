<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AuditLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuditLogPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuditLog');
    }

    public function view(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('View:AuditLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AuditLog');
    }

    public function update(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('Update:AuditLog');
    }

    public function delete(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('Delete:AuditLog');
    }

    public function restore(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('Restore:AuditLog');
    }

    public function forceDelete(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('ForceDelete:AuditLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AuditLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AuditLog');
    }

    public function replicate(AuthUser $authUser, AuditLog $auditLog): bool
    {
        return $authUser->can('Replicate:AuditLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AuditLog');
    }

}