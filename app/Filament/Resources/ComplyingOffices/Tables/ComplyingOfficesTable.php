<?php

namespace App\Filament\Resources\ComplyingOffices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ComplyingOfficesTable
{
    public static function configure(Table $table): Table
    {
        // dd(auth()->user()->department_code);
        return $table
                ->modifyQueryUsing(function (Builder $query) {
                    // Filter to only show records that match the user's department_code
                    $user = auth()->user();

                    // Prevent null errors if user is not authenticated
                    if ($user && $user->department_code != 25) {
                        $query->where('complying_offices.department_code', $user->department_code);
                    }
                })
                ->defaultGroup('office.office')
                ->columns([
                    // TextColumn::make('department_code')
                    //     ->searchable(),
                    TextColumn::make('office.office')
                        ->label('Complying Office') // Optional custom label
                        ->searchable()
                        ->sortable()
                        ->wrap(),
                    TextColumn::make('requiredDocument.requirement')
                        ->label('Requirement')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('requirement_id')
                        ->searchable(),
                    TextColumn::make('requiredDocument.agency_name')
                        ->label('Requiring Agency')
                        ->sortable()
                        ->searchable() 
                        ->wrap(),
                    TextColumn::make('status')
                        ->label('Status')
                        ->formatStateUsing(function ($state) {
                            return match ($state) {
                                '-1' => 'Not Complied',
                                '0'  => 'Partially Complied',
                                '1'  => 'Complied',
                                default => 'Unknown',
                            };
                        })
                        ->badge() // optional: shows as colored badge
                        ->colors([
                            'danger' => '-1',
                            'warning' => '0',
                            'success' => '1',
                        ])
                        ->html()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('requiredDocument.due_date')
                        ->label('Due Date')
                        ->sortable()
                        // ->getStateUsing(fn ($record) => $record->due_date ?? $record->requiredDocument->due_date)
                        ->formatStateUsing(fn ($state, $record) => $record->requirement?->due_date)
                        ->date()
                        ->searchable(),
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
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]);
    }
}
