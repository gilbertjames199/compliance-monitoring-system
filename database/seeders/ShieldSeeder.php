<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use Illuminate\Database\Seeder;
use BezhanSalleh\FilamentShield\Support\Utils;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $rolesWithPermissions = '[{"name":"super_admin","guard_name":"web","permissions":["ViewAny:Role","View:Role","Create:Role","Update:Role","Delete:Role","Restore:Role","ForceDelete:Role","ForceDeleteAny:Role","RestoreAny:Role","Replicate:Role","Reorder:Role","ViewAny:User","View:User","Create:User","Update:User","Delete:User","Restore:User","ForceDelete:User","ForceDeleteAny:User","RestoreAny:User","Replicate:User","Reorder:User","ViewAny:Report","View:Report","Create:Report","Update:Report","Delete:Report","Restore:Report","ForceDelete:Report","ForceDeleteAny:Report","RestoreAny:Report","Replicate:Report","Reorder:Report"]},{"name":"create reports","guard_name":"web","permissions":["ViewAny:Report","View:Report","Create:Report","Update:Report"]}]';
        $directPermissions = '{"11":{"name":"ViewAny:Customer","guard_name":"web"},"12":{"name":"View:Customer","guard_name":"web"},"13":{"name":"Create:Customer","guard_name":"web"},"14":{"name":"Update:Customer","guard_name":"web"},"15":{"name":"Delete:Customer","guard_name":"web"},"16":{"name":"Restore:Customer","guard_name":"web"},"17":{"name":"ForceDelete:Customer","guard_name":"web"},"18":{"name":"ForceDeleteAny:Customer","guard_name":"web"},"19":{"name":"RestoreAny:Customer","guard_name":"web"},"20":{"name":"Replicate:Customer","guard_name":"web"},"21":{"name":"Reorder:Customer","guard_name":"web"},"22":{"name":"ViewAny:Reports","guard_name":"web"},"23":{"name":"View:Reports","guard_name":"web"},"24":{"name":"Create:Reports","guard_name":"web"},"25":{"name":"Update:Reports","guard_name":"web"},"26":{"name":"Delete:Reports","guard_name":"web"},"27":{"name":"Restore:Reports","guard_name":"web"},"28":{"name":"ForceDelete:Reports","guard_name":"web"},"29":{"name":"ForceDeleteAny:Reports","guard_name":"web"},"30":{"name":"RestoreAny:Reports","guard_name":"web"},"31":{"name":"Replicate:Reports","guard_name":"web"},"32":{"name":"Reorder:Reports","guard_name":"web"},"33":{"name":"ViewAny:Task","guard_name":"web"},"34":{"name":"View:Task","guard_name":"web"},"35":{"name":"Create:Task","guard_name":"web"},"36":{"name":"Update:Task","guard_name":"web"},"37":{"name":"Delete:Task","guard_name":"web"},"38":{"name":"Restore:Task","guard_name":"web"},"39":{"name":"ForceDelete:Task","guard_name":"web"},"40":{"name":"ForceDeleteAny:Task","guard_name":"web"},"41":{"name":"RestoreAny:Task","guard_name":"web"},"42":{"name":"Replicate:Task","guard_name":"web"},"43":{"name":"Reorder:Task","guard_name":"web"}}';

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
