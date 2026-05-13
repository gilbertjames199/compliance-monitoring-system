<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Office;
use App\Models\Permission;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

//FILAMENT
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
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
                TextInput::make('FullName')
                    ->required(fn ($livewire, $record) => !$record),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(fn ($livewire, $record) => !$record)
                    ->required(fn ($livewire, $record) => !$record),
                Select::make('is_active')
                    ->label('Active')
                    ->options([
                        1 => 'Yes',
                        0 => 'No',
                    ])
                    ->required(fn ($livewire, $record) => !$record),
                TextInput::make('cats')
                    ->required(fn ($livewire, $record) => !$record),
               Select::make('department_code')
                    ->label('Department Code')
                    ->options(fn () => Office::pluck('department_code', 'department_code'))
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set, $state) {
                        $office = Office::where('department_code', $state)->first();
                        $set('office', $office?->office);
                    }),

                Select::make('department_code')
                    ->label('Office')
                    ->options(
                        Office::orderBy('office')
                            ->get()
                            ->mapWithKeys(function ($office) {
                                $label = $office->office;

                                if (!empty($office->short_name)) {
                                    $label .= ' (' . $office->short_name . ')';
                                }

                                return [
                                    $office->department_code => $label
                                ];
                            })
                            ->toArray()
                    )
                    ->searchable()
                    ->preload(),

                Select::make('accessible_department_codes')
                    ->label('Additional Office Access')
                    ->options(
                        Office::orderBy('office')
                            ->get()
                            ->mapWithKeys(function ($office) {
                                $label = $office->office;

                                if (!empty($office->short_name)) {
                                    $label .= ' (' . $office->short_name . ')';
                                }

                                return [
                                    $office->department_code => $label,
                                ];
                            })
                            ->toArray()
                    )
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('Optional. Use this for department heads who need to act for more than one office.')
                    ->afterStateHydrated(function ($component, ?Model $record) {
                        if (! $record) {
                            return;
                        }

                        $component->state(
                            $record->officeAssignments()
                                ->pluck('department_code')
                                ->toArray()
                        );
                    }),

                TextInput::make('UserName')
                    ->required(fn ($livewire, $record) => !$record),
    
                TextInput::make('UserPassword')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required(fn ($livewire, $record) => !$record) // required on create only
                    ->dehydrated(true)
                    ->dehydrateStateUsing(function ($state, $record) {
                        if ($state) {
                            return md5($state); // create MD5 hash
                        }

                        // if editing and left empty, keep existing password
                        if ($record) {
                            return $record->UserPassword;
                        }

                        return null;
                    })
                    ->afterStateHydrated(fn ($component) => $component->state('')),
                
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
            ]);
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
