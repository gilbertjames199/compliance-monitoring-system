<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["ViewAny:Role","View:Role","Create:Role","Update:Role","Delete:Role","Restore:Role","ForceDelete:Role","ForceDeleteAny:Role","RestoreAny:Role","Replicate:Role","Reorder:Role","ViewAny:ComplyingOffice","View:ComplyingOffice","Create:ComplyingOffice","Update:ComplyingOffice","Delete:ComplyingOffice","Restore:ComplyingOffice","ForceDelete:ComplyingOffice","ForceDeleteAny:ComplyingOffice","RestoreAny:ComplyingOffice","Replicate:ComplyingOffice","Reorder:ComplyingOffice","ViewAny:DocumentCategory","View:DocumentCategory","Create:DocumentCategory","Update:DocumentCategory","Delete:DocumentCategory","Restore:DocumentCategory","ForceDelete:DocumentCategory","ForceDeleteAny:DocumentCategory","RestoreAny:DocumentCategory","Replicate:DocumentCategory","Reorder:DocumentCategory","ViewAny:RequiredDocument","View:RequiredDocument","Create:RequiredDocument","Update:RequiredDocument","Delete:RequiredDocument","Restore:RequiredDocument","ForceDelete:RequiredDocument","ForceDeleteAny:RequiredDocument","RestoreAny:RequiredDocument","Replicate:RequiredDocument","Reorder:RequiredDocument","ViewAny:User","View:User","Create:User","Update:User","Delete:User","Restore:User","ForceDelete:User","ForceDeleteAny:User","RestoreAny:User","Replicate:User","Reorder:User"]},{"name":"user","guard_name":"web","permissions":["ViewAny:ComplyingOffice","View:ComplyingOffice","Create:ComplyingOffice","Update:ComplyingOffice","Delete:ComplyingOffice","Restore:ComplyingOffice","ForceDelete:ComplyingOffice","ForceDeleteAny:ComplyingOffice","RestoreAny:ComplyingOffice","Replicate:ComplyingOffice","Reorder:ComplyingOffice","ViewAny:RequiredDocument","View:RequiredDocument","Create:RequiredDocument","Update:RequiredDocument","Delete:RequiredDocument","Restore:RequiredDocument","ForceDelete:RequiredDocument","ForceDeleteAny:RequiredDocument","RestoreAny:RequiredDocument","Replicate:RequiredDocument","Reorder:RequiredDocument"]},{"name":"department_head","guard_name":"web","permissions":["ViewAny:ComplyingOffice","View:ComplyingOffice","Create:ComplyingOffice","Update:ComplyingOffice","Delete:ComplyingOffice","Restore:ComplyingOffice","ForceDelete:ComplyingOffice","ForceDeleteAny:ComplyingOffice","RestoreAny:ComplyingOffice","Replicate:ComplyingOffice","Reorder:ComplyingOffice","ViewAny:DocumentCategory","View:DocumentCategory","Create:DocumentCategory","Update:DocumentCategory","Delete:DocumentCategory","Restore:DocumentCategory","ForceDelete:DocumentCategory","ForceDeleteAny:DocumentCategory","RestoreAny:DocumentCategory","Replicate:DocumentCategory","Reorder:DocumentCategory","ViewAny:RequiredDocument","View:RequiredDocument","Create:RequiredDocument","Update:RequiredDocument","Delete:RequiredDocument","Restore:RequiredDocument","ForceDelete:RequiredDocument","ForceDeleteAny:RequiredDocument","RestoreAny:RequiredDocument","Replicate:RequiredDocument","Reorder:RequiredDocument"]}]';
        $directPermissions = '[]';

        static::makeRolesWithPermissions($rolesWithPermissions);
        static::makeDirectPermissions($directPermissions);

        $this->command->info('Shield Seeding Completed.');
    }

    protected static function makeRolesWithPermissions(string $rolesWithPermissions): void
    {
        if (! blank($rolePlusPermissions = json_decode($rolesWithPermissions, true))) {
            /** @var Model $roleModel */
            $roleModel = Utils::getRoleModel();
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($rolePlusPermissions as $rolePlusPermission) {
                $role = $roleModel::firstOrCreate([
                    'name' => $rolePlusPermission['name'],
                    'guard_name' => $rolePlusPermission['guard_name'],
                ]);

                if (! blank($rolePlusPermission['permissions'])) {
                    $permissionModels = collect($rolePlusPermission['permissions'])
                        ->map(fn ($permission) => $permissionModel::firstOrCreate([
                            'name' => $permission,
                            'guard_name' => $rolePlusPermission['guard_name'],
                        ]))
                        ->all();

                    $role->syncPermissions($permissionModels);
                }
            }
        }
    }

    public static function makeDirectPermissions(string $directPermissions): void
    {
        if (! blank($permissions = json_decode($directPermissions, true))) {
            /** @var Model $permissionModel */
            $permissionModel = Utils::getPermissionModel();

            foreach ($permissions as $permission) {
                if ($permissionModel::whereName($permission)->doesntExist()) {
                    $permissionModel::create([
                        'name' => $permission['name'],
                        'guard_name' => $permission['guard_name'],
                    ]);
                }
            }
        }
    }
}
