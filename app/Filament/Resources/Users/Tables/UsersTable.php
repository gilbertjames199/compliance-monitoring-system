<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Office;
use App\Models\Pis\Division;
use App\Models\UserDivision;
use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
             ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                
                // If user has superadmin role, show all records
                if ($user->hasRoleSafe('super_admin')) {
                    return $query;
                }
                
                // If user doesn't have a department_code, show no records
                if (!$user->department_code) {
                    return $query->whereRaw('1 = 0');
                }
                
                // Otherwise, only show users from the same department
                return $query->where('department_code', $user->department_code);
            })
            ->columns([
                // TextColumn::make('recid'),
                TextColumn::make('FullName')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'align-top']),
                TextColumn::make('UserName')
                    ->label('Username')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'align-top']),
                BadgeColumn::make('is_active')
                    ->label('Active Status')
                    ->extraCellAttributes(['class' => 'align-top'])
                    ->colors([
                        'success' => fn ($state) => $state,
                        'danger' => fn ($state) => ! $state,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->searchable(), // now works because it's using the database column
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'align-top']),
                // TextColumn::make('department_code')
                //     ->label('Department Code')
                //     ->searchable(),
                TextColumn::make('department_code')
                    ->label('Department')
                    ->searchable()
                    ->wrap()
                    ->formatStateUsing(function ($state) {
                        $office = Office::where('department_code', $state)->first();

                        return $office
                            ? "{$office->office} ({$office->short_name})"
                            : $state;
                    })
                    ->extraCellAttributes(['class' => 'align-top']),
                    
                // TextColumn::make('Designation')
                //     ->label('Designation')
                //     ->searchable()
                //     ->extraCellAttributes(['class' => 'align-top']),
                TextColumn::make('divisions')
                    ->label('Division(s)')
                    ->badge()
                    ->state(function ($record) {
                        $divisionCodes = UserDivision::where('user_id', $record->recid)
                            ->pluck('division_code')
                            ->toArray();

                        if (empty($divisionCodes)) {
                            return [];
                        }

                        return Division::whereIn('division_code', $divisionCodes)
                            ->get()
                            ->map(fn ($d) => $d->division_name1 . (!empty($d->division_short_name) ? " ({$d->division_short_name})" : ''))
                            ->toArray(); // array, not imploded string
                    })
                    ->limitList(2) // shows first 2 badges + "+N more" expandable
                    ->expandableLimitedList()
                    ->searchable(query: function (Builder $query, string $search) {
                        $divisionCodes = Division::where('division_name1', 'like', "%{$search}%")
                            ->orWhere('division_short_name', 'like', "%{$search}%")
                            ->pluck('division_code')
                            ->toArray();

                        if (empty($divisionCodes)) {
                            return $query->whereRaw('1 = 0');
                        }

                        $userIds = UserDivision::whereIn('division_code', $divisionCodes)
                            ->pluck('user_id')
                            ->toArray();

                        return $query->whereIn('recid', $userIds);
                    })
                    ->extraCellAttributes(['class' => 'align-top'])
                    ->wrap(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state) => collect([
                        'primary',
                        'success',
                        'warning',
                        'danger',
                        'info',
                        'gray',
                    ])[abs(crc32($state)) % 6])
                    ->extraCellAttributes(['class' => 'align-top']),
            ])
            ->defaultSort('recid', 'asc')
            ->filters([
                // Active/Inactive filter
                SelectFilter::make('is_active')
                    ->label('Active Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),

                // Department filter
                // SelectFilter::make('department_code')
                //     ->label('Department')
                //     ->options(fn () => Office::pluck('department_code', 'department_code')
                //     ->toArray())
                //     ->searchable(),
                SelectFilter::make('department_code')
                    ->label('Department')
                    ->options(
                        fn () => Office::query()
                            ->get()
                            ->mapWithKeys(fn ($office) => [
                                $office->department_code => "{$office->short_name} - {$office->office}"
                            ])
                            ->toArray()
                    )
                    ->searchable(),

                SelectFilter::make('division')
                    ->label('Division')
                    ->options(fn () => Division::orderBy('division_name1')
                        ->get()
                        ->mapWithKeys(fn ($d) => [
                            $d->division_code => $d->division_name1 . (!empty($d->division_short_name) ? " ({$d->division_short_name})" : ''),
                        ])
                        ->toArray())
                    ->searchable()
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) return;

                        $userIds = UserDivision::where('division_code', $data['value'])
                            ->pluck('user_id')
                            ->toArray();

                        $query->whereIn('recid', $userIds);
                    }),

                // Designation filter
                SelectFilter::make('Designation')
                    ->label('Designation')
                    ->options(fn () => \App\Models\User::select('Designation')
                        ->whereNotNull('Designation')
                        ->distinct()
                        ->pluck('Designation', 'Designation')
                        ->toArray())
                    ->searchable(),

                // Role filter — uses two separate queries to avoid cross-server join
                SelectFilter::make('role')
                    ->label('Role')
                    ->options(fn () => DB::connection('mysql')
                        ->table('roles')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) return;

                        // Get user IDs that have this role (queried from compliance_monitoring DB)
                        $userIds = DB::connection('mysql')
                            ->table('model_has_roles')
                            ->where('role_id', $data['value'])
                            ->where('model_type', \App\Models\User::class)
                            ->pluck('model_id')
                            ->toArray();

                        $query->whereIn('recid', $userIds);
                    }),
            ])
            ->recordActions([
                Impersonate::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
