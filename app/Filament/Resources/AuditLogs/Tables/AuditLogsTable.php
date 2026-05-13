<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // TextColumn::make('id')
                //     ->label('ID')
                //     ->sortable()
                //     ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('event')
                ->label('Event')
                ->badge()
                ->searchable()
                ->wrap()
                ->color(fn (string $state) => match ($state) {
                    'submitted' => 'info',
                    'validated' => 'success',
                    'returned'  => 'danger',
                    default     => 'gray',
                })
                ->sortable()
                ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('user_id')
                ->label('Action By')
                ->formatStateUsing(function ($state) {
                    $user = \App\Models\User::find($state);
                    return $user?->FullName ?? $user?->UserName ?? "User #{$state}";
                })
                ->searchable()
                ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('action_at')
                ->label('Action At')
                ->dateTime('M d, Y h:i A')
                ->sortable()
                ->searchable()
                ->extraCellAttributes(['class' => 'align-top']),

                   
                TextColumn::make('requirement_name')
                    ->label('Requirement')
                    ->wrap()
                    ->searchable()
                    ->tooltip(fn ($state) => $state)
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('office_name')
                    ->label('Complying Office')
                    ->wrap()
                    ->searchable()
                    ->toggleable()
                    ->tooltip(fn ($state) => $state)
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('requiring_agency_name')
                    ->label('Requiring Agency')
                    ->wrap()
                    ->searchable()
                    ->toggleable()
                    ->tooltip(fn ($state) => $state)
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('old_status')
                    ->label('Old Compliance Status')
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        '-1'    => 'Not Complied',
                        '0'     => 'Partially Complied',
                        '1'     => 'Complied',
                        default => '-',
                    })
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('new_status')
                    ->label('New Compliance Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ((string) $state) {
                        '-1'    => 'Not Complied',
                        '0'     => 'Partially Complied',
                        '1'     => 'Complied',
                        default => '-',
                    })
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('old_validation_status')
                    ->label('Old Validation Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => '-',
                    })
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('new_validation_status')
                    ->label('New Validation Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                        default          => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending_review' => 'warning',
                        'returned'       => 'danger',
                        'validated'      => 'success',
                        default          => 'gray',
                    })
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->extraCellAttributes(['class' => 'align-top']),

                   
            ])
            ->defaultSort('action_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                //EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

