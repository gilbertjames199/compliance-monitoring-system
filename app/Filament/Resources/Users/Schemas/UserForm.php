<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Permission;
use App\Models\Role;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

//FILAMENT
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $permissionGroups = Permission::all()
        ->groupBy(fn ($p) => ucfirst(explode(':', $p->name)[1] ?? explode('.', $p->name)[0]));
        // dd($permissionGroups);
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('username')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('cats_number')
                    ->required(),
                TextInput::make('department_code')
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->required(),

                // 🧩 Roles
                Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable()
                    ->afterStateUpdated(function (callable $set, $state) {
                        // When roles change, update the permissions list
                        $rolePermissions = Permission::whereHas('roles', function ($q) use ($state) {
                            $q->whereIn('roles.id', $state);
                        })->pluck('id')->toArray();

                        $set('permissions', $rolePermissions);
                    })
                    ->columns(1),

                CheckboxList::make('permissions')
                    ->label('Permissions')
                    ->relationship('permissions', 'name')
                    // ->options(function () {

                    //         $per=Permission::all()
                    //             ->groupBy(function ($permission) {
                    //                 // If names use "Action:Model" (e.g. "ViewAny:Role")
                    //                 if (str_contains($permission->name, ':')) {
                    //                     return ucfirst(explode(':', $permission->name)[1]); // "Role"
                    //                 }
                    //                 // If names use "model.action" (fallback)
                    //                 return ucfirst(explode('.', $permission->name)[0]); // "User"
                    //             })
                    //             ->map(function ($group) {
                    //                 // Return [ id => label_string, ... ] for each group
                    //                 return $group
                    //                     ->pluck('name', 'id') // [ id => 'ViewAny:Role', ... ]
                    //                     ->map(function ($name) {
                    //                         // Convert 'ViewAny:Role' or 'ViewAny.Role' -> 'ViewAny Role'
                    //                         return str_replace([':', '.'], ' ', $name);
                    //                     })
                    //                     ->toArray();
                    //             })

                    //             ->toArray();
                    // // dd($per);
                    //     return $per;
                    // })
                    ->columns(3)
                    ->searchable()
                    ->saveRelationshipsUsing(function (Model $record, $state) {
                        // Save user permissions
                        $record->permissions()->sync($state);
                    })
                    ->bulkToggleable()
                    ->reactive()
                    ->allowHtml()
                    ->columnSpanFull(),
                // CheckboxList::make('permissions')
                //     ->relationship(name: 'permissions', titleAttribute: 'name')
                //     ->saveRelationshipsUsing(function (Model $record, $state) {
                //         $record->roles()->syncWithPivotValues($state, [config('permission.column_names.team_foreign_key') => getPermissionsTeamId()]);
                //     })
                //     ->searchable(),




                // Direct Permissions
                // Permission Manager Section

                // ...collect($permissionGroups)
                // ->map(function ($group, $model) {
                //     return Section::make($model)
                //         ->schema([
                //             CheckboxList::make("permissions")
                //                 ->label("{$model} Permissions")
                //                 ->relationship('permissions', 'name')
                //                 ->options(
                //                     $group
                //                         ->pluck('name', 'id')
                //                         ->map(fn($name) => str_replace([':', '.'], ' ', $name))
                //                         ->toArray()
                //                 )
                //                 ->saveRelationshipsUsing(function (Model $record, $state) {
                //                     // Save user permissions
                //                     $record->permissions()->sync($state);
                //                 })
                //                 ->columns(3)
                //                 ->bulkToggleable() // adds built-in “Select All / Deselect All” per section
                //                 ->reactive(),
                //         ])
                //         /*->saveRelationshipsUsing(function (Model $record, $state) {
                //             // $state now contains 'permissions' => [ 'Role' => [...], 'User' => [...] ]
                //             $permissionMatrix = $state['permissions'] ?? [];

                //             // Flatten and make unique ints
                //             $allPermissionIds = collect($permissionMatrix)
                //                 ->flatten()
                //                 ->filter()   // remove falsy
                //                 ->map(fn($id) => (int) $id)
                //                 ->unique()
                //                 ->values()
                //                 ->all();

                //             // Sync to the model
                //             $record->permissions()->sync($allPermissionIds);
                //         })*/
                //         ->collapsible(); // optional: makes the section collapsible
                // })

                // ->values()
                // ->all(),

            ]);
    }
    // CheckboxList::make('permissions2')
                // ->options([
                //     'Role' => [
                //         1 => 'ViewAny Role',
                //         2 => 'View Role',
                //     ],
                //     'User' => [
                //         3 => 'ViewAny User',
                //         4 => 'View User',
                //     ],
                // ]),
    public static function afterSave(Form $form, Model $record): void
    {
        $permissions = collect($form->getState())
            ->filter(fn($value, $key) => str_starts_with($key, 'permissions_'))
            ->flatten()
            ->toArray();
        dd($permissions);
        $record->syncPermissions($permissions);
    }

    public function sampleFunction(){
        // PERMISSIONS GROUPED BY MODEL********************************************************************************************************************************************************
        // Section::make('Permissions')
        //             ->schema(function () {
        //                 // Fetch all permissions
        //                 $permissions = Permission::all();

        //                 // Group by model (detect last part after colon or dot)
        //                 $grouped = $permissions->groupBy(function ($permission) {
        //                     // Filament Shield format example: "view_any_role"
        //                     // or "view:role" depending on your version
        //                     $name = $permission->name;

        //                     // Normalize delimiters
        //                     $name = str_replace([':', '_'], '.', $name);

        //                     // Get model part (e.g. last part after dot)
        //                     return ucfirst(last(explode('.', $name)));
        //                 });

        //                 // Build checkbox lists per model
        //                 return $grouped->map(function ($permissions, $model) {
        //                     return Fieldset::make($model)
        //                         ->schema([
        //                             CheckboxList::make("{$model} permissions")
        //                                 ->label('')
        //                                 ->options(
        //                                     $permissions->mapWithKeys(function ($perm) {
        //                                         // Show only the ability part (before the model name)
        //                                         $label = ucfirst(
        //                                             preg_replace('/[:_].*/', '', $perm->name)
        //                                         );
        //                                         return [$perm->name => $label];
        //                                     })->toArray()
        //                                 ),
        //                         ]);
        //                 })->values()->toArray();
        //             }),

        // PERMISSIONS********************************************************************************************************************************************************
        // Section::make('Permissions')
        //             ->schema(function () {
        //                 // Fetch all permissions
        //                 $permissions = Permission::all();

        //                 // Group by model (detect last part after colon or dot)
        //                 $grouped = $permissions->groupBy(function ($permission) {
        //                     // Filament Shield format example: "view_any_role"
        //                     // or "view:role" depending on your version
        //                     $name = $permission->name;

        //                     // Normalize delimiters
        //                     $name = str_replace([':', '_'], '.', $name);

        //                     // Get model part (e.g. last part after dot)
        //                     return ucfirst(last(explode('.', $name)));
        //                 });

        //                 // Build checkbox lists per model
        //                 return $grouped->map(function ($permissions, $model) {
        //                     return Fieldset::make($model)
        //                         ->schema([
        //                             CheckboxList::make("{$model} permissions")
        //                                 ->label('')
        //                                 ->options(
        //                                     $permissions->mapWithKeys(function ($perm) {
        //                                         // Show only the ability part (before the model name)
        //                                         $label = ucfirst(
        //                                             preg_replace('/[:_].*/', '', $perm->name)
        //                                         );
        //                                         return [$perm->name => $label];
        //                                     })->toArray()
        //                                 ),
        //                         ]);
        //                 })->values()->toArray();
        //             });
    }

    // $permissions = Permission::all();

                        // $per= $permissions
                        //     ->groupBy(function ($permission) {
                        //         return ucfirst(explode('.', $permission->name)[0]); // e.g. "User"
                        //     })
                        //     ->map(function ($group) {
                        //         return $group->pluck('name', 'id'); // ['1' => 'user.view', ...]
                        //     })
                        //     ->toArray();
                        // dd($per);
                        // return $per;
                        // dd($per);
                        /*$per= $permissions
                            ->groupBy(function ($permission) {
                                // Adjust grouping depending on your permission naming pattern
                                // e.g. "ViewAny:Role" → "Role"
                                if (str_contains($permission->name, ':')) {
                                    return ucfirst(explode(':', $permission->name)[1]); // "Role"
                                }

                                // fallback: "user.view" → "User"
                                return ucfirst(explode('.', $permission->name)[0]);
                            })
                            ->map(function ($group) {
                                return $group->mapWithKeys(function ($permission) {
                                    // [id => label]
                                    return [
                                        $permission->id => str_replace([':', '.'], ' ', $permission->name),
                                    ];
                                })->toArray();
                            })
                            ->toArray();*/
}
