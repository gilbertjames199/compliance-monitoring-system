<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\DB;

class DocumentCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DocumentCategory');
    }

    // public function view(AuthUser $authUser, DocumentCategory $documentCategory): bool
    // {
    //     return $authUser->can('View:DocumentCategory');
    // }
    public function view(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        if (!$authUser->can('View:DocumentCategory')) {
            return false;
        }

        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        // Only the department that created it can view it
        $creatorDeptCode = DB::connection('mysql2')
            ->table('systemusers')
            ->where('recid', $documentCategory->created_by)
            ->value('department_code');

        return (int) $authUser->department_code === (int) $creatorDeptCode;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DocumentCategory');
    }

    // public function update(AuthUser $authUser, DocumentCategory $documentCategory): bool
    // {
    //     return $authUser->can('Update:DocumentCategory');
    // }

    public function update(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        if (!$authUser->can('Update:DocumentCategory')) {
            return false;
        }

        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        $creatorDeptCode = DB::connection('mysql2')
            ->table('systemusers')
            ->where('recid', $documentCategory->created_by)
            ->value('department_code');

        return (int) $authUser->department_code === (int) $creatorDeptCode;
    }

    public function delete(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('Delete:DocumentCategory');
    }

    public function restore(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('Restore:DocumentCategory');
    }

    public function forceDelete(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('ForceDelete:DocumentCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DocumentCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DocumentCategory');
    }

    public function replicate(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('Replicate:DocumentCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DocumentCategory');
    }

}