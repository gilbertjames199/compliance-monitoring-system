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
    public function view(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        if (!$authUser->can('View:RequiredDocument')) {
            return false;
        }

        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        // Requiring agency can view their own requirements
        $agencyDeptCode = \App\Models\Office::on('mysql2')
            ->where('office', $requiredDocument->agency_name)
            ->value('department_code');

        if ($authUser->department_code === $agencyDeptCode) {
            return true;
        }

        // Complying offices can view requirements assigned to them
        return $requiredDocument->complyingOffices()
            ->where('department_code', $authUser->department_code)
            ->exists();
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RequiredDocument');
    }

    // public function update(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    // {
    //     return $authUser->can('Update:RequiredDocument');
    // }
    public function update(AuthUser $authUser, RequiredDocument $requiredDocument): bool
    {
        if (!$authUser->can('Update:RequiredDocument')) {
            return false;
        }

        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        // Only the requiring agency can edit
        $agencyDeptCode = \App\Models\Office::on('mysql2')
            ->where('office', $requiredDocument->agency_name)
            ->value('department_code');

        return $authUser->department_code === $agencyDeptCode;
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

    public function viewAllOffices(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAllOffices:RequiredDocument');
    }

    public function viewConfidential(AuthUser $authUser): bool
    {
        return $authUser->can('ViewConfidential:RequiredDocument');
    }

}