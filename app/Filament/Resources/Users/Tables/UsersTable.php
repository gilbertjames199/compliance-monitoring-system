<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Office;
use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use STS\FilamentImpersonate\Actions\Impersonate;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
             ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                
                // If user has superadmin role, show all records
                if ($user->hasRole('super_admin')) {
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
                    ->searchable(),
                TextColumn::make('UserName')
                    ->label('Username')
                    ->searchable(),
                BadgeColumn::make('is_active')
                    ->label('Active Status')
                    ->colors([
                        'success' => fn ($state) => $state,
                        'danger' => fn ($state) => ! $state,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive')
                    ->searchable(), // now works because it's using the database column
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('department_code')
                    ->label('Department Code')
                    ->searchable(),
                TextColumn::make('Designation')
                    ->label('Designation')
                    ->searchable(),
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
                    ])[abs(crc32($state)) % 6]),
            ])
            ->defaultSort('recid', 'asc')
            ->filters([
                //
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
