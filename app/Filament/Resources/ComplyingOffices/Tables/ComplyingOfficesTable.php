<?php

namespace App\Filament\Resources\ComplyingOffices\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class ComplyingOfficesTable
{
    public static function configure(Table $table): Table
    {
        // dd(auth()->user()->department_code);
        return $table
                ->modifyQueryUsing(function (Builder $query) {

                    // dd(auth()->user()->department_code);


                    // Filter to only show records that match the user's department_code
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    // Role-based access control
                    if ($user->hasRole('superadmin')) {
                        // Superadmin sees all - no filters
                    } 
                    elseif ($user->hasRole('department_head')) {
                        // Department head sees all within their department
                        $query->where('complying_offices.department_code', $user->department_code);
                    } 
                    elseif ($user->hasAnyRole(['AO', 'admin'])) {
                        // AO/Admin sees non-confidential within their department
                        $query
                            ->where('complying_offices.department_code', $user->department_code)
                            ->join('required_documents', 'required_documents.id', '=', 'complying_offices.requirement_id')
                            ->where('required_documents.is_confidential', false)
                            ->select('complying_offices.*');
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
                    // TextColumn::make('requirement_id')
                    //     ->searchable(),
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
                    // ViewAction::make(),
                    EditAction::make(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]);
    }
}
