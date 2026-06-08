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

    // public function view(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    // {
    //     return $authUser->can('View:ComplyingOffice');
    // }
    public function view(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        if (!$authUser->can('View:ComplyingOffice')) {
            return false;
        }

        // Super admin sees all
        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        // Requiring agency can view records linked to their agency
        if ($authUser->hasRoleSafe('requiring_agency')) {
            return $complyingOffice->requiredDocument?->agency_name === $authUser->agency_name;
        }

        // Everyone else can only view their own department's record
        return $authUser->hasAccessToDepartment($complyingOffice->department_code);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ComplyingOffice');
    }

    // public function update(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    // {
    //     return $authUser->can('Update:ComplyingOffice');
    // }
    public function update(AuthUser $authUser, ComplyingOffice $complyingOffice): bool
    {
        if (!$authUser->can('Update:ComplyingOffice')) {
            return false;
        }

        if ($authUser->hasRoleSafe('super_admin')) {
            return true;
        }

        // if ($authUser->hasRoleSafe('requiring_agency')) {
        //     return $complyingOffice->requiredDocument?->agency_name === $authUser->agency_name;
        // }

        // return $authUser->hasAccessToDepartment($complyingOffice->department_code);
         // Check if user is the requiring agency of this requirement
        $agencyDeptCode = \App\Models\Office::on('mysql2')
            ->where('office', $complyingOffice->requiredDocument?->agency_name)
            ->value('department_code');

        if ($authUser->department_code === $agencyDeptCode) {
            return true;
        }

        // Complying office can only edit their own record
        return $authUser->hasAccessToDepartment($complyingOffice->department_code);
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

    public function addAttachments(AuthUser $authUser): bool
    {
        return $authUser->can('AddAttachments:ComplyingOffice');
    }

    public function updateComplianceStatus(AuthUser $authUser): bool
    {
        return $authUser->can('UpdateComplianceStatus:ComplyingOffice');
    }

    public function updateDepartmentComplianceStatus(AuthUser $authUser): bool
    {
        return $authUser->can('UpdateDepartmentComplianceStatus:ComplyingOffice');
    }

    public function updateOwnOfficeComplianceStatus(AuthUser $authUser): bool
    {
        return $authUser->can('UpdateOwnOfficeComplianceStatus:ComplyingOffice');
    }

}