<?php

namespace App\Filament\Resources\DocumentCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class DocumentCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                if ($user->hasRoleSafe('super_admin')) {
                    return $query;
                }

                // Fetch matching user IDs from mysql2 (systemusers) first
                $userIds = DB::connection('mysql2')
                    ->table('systemusers')
                    ->where('department_code', $user->department_code)
                    ->pluck('recid')
                    ->toArray();

                return $query->where(function ($q) use ($userIds) {
                    $q->whereIn('created_by', $userIds)
                    ->orWhereNull('created_by'); // existing records with no creator are visible to all
                });
            })
            ->columns([
                // TextColumn::make('id')
                //     ->searchable(),
                TextColumn::make('category')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();

                        if ($user->hasRoleSafe('super_admin')) {
                            return true;
                        }

                        // Also show to the office that created the category
                        $userIds = DB::connection('mysql2')
                            ->table('systemusers')
                            ->where('department_code', $user->department_code)
                            ->pluck('recid')
                            ->toArray();

                        return in_array($record->created_by, $userIds);
                    })
                    ->before(function (DeleteAction $action, $record) {
                        $user = auth()->user();

                        // Super admin can always delete
                        if ($user->hasRoleSafe('super_admin')) {
                            return;
                        }

                        // Check if any required document under this category
                        // has a complying office with compliance (status 0 or 1)
                        $hasCompliance = $record->requiredDocuments()
                            ->whereHas('complyingOffices', function ($q) {
                                $q->whereIn('status', [0, 1]);
                            })
                            ->exists();

                        if ($hasCompliance) {
                            Notification::make()
                                ->title('Deletion Not Allowed')
                                ->body('This category cannot be deleted because one or more offices have already submitted compliance on its linked required documents.')
                                ->danger()
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records, $livewire) => auth()->user()->hasRoleSafe('super_admin')),
                ]),
            ]);
    }
}
