<?php

namespace App\Filament\Resources\ComplyingOffices\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class ComplyingOfficesTable
{
    public static function configure(Table $table): Table
    {
        // dd(auth()->user()->department_code);
        return $table
                // ->modifyQueryUsing(function (Builder $query) {

                //     // dd(auth()->user()->department_code);

                //     // Filter to only show records that match the user's department_code
                //     $user = auth()->user();

                //     if (! $user) {
                //         return;
                //     }

                //     // Role-based access control
                //     if ($user->hasRole('superadmin')) {
                //         // Superadmin sees all - no filters
                //     } 
                //     elseif ($user->hasRole('department_head')) {
                //         // Department head sees all within their department
                //         $query->where('complying_offices.department_code', $user->department_code);
                //     } 
                //     elseif ($user->hasAnyRole(['AO', 'admin'])) {
                //         // AO/Admin sees non-confidential within their department
                //         $query
                //             ->where('complying_offices.department_code', $user->department_code)
                //             ->join('required_documents', 'required_documents.id', '=', 'complying_offices.required_document_id')
                //             ->where('required_documents.is_confidential', false)
                //             ->select('complying_offices.*');
                //     }  
                // })
                ->modifyQueryUsing(function (Builder $query) {
                    $user = auth()->user();

                    if (! $user) {
                        return;
                    }

                    /**
                     * GLOBAL RULE:
                     * Everyone (including superadmin) can ONLY see records
                     * where their office is the complying office.
                     */
                    $query->where(
                        'complying_offices.department_code',
                        $user->department_code
                    );

                    /**
                     * EXTRA RULES PER ROLE
                     */
                    if ($user->hasAnyRole(['AO', 'admin'])) {
                        // AO/Admin cannot see confidential requirements
                        $query
                            ->join(
                                'required_documents',
                                'required_documents.id',
                                '=',
                                'complying_offices.required_document_id'
                            )
                            ->where('required_documents.is_confidential', false)
                            ->select('complying_offices.*');
                    }

                    // superadmin & department_head:
                    // - still limited to their office
                    // - but no confidentiality restriction
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
                    TextColumn::make('requiredDocument.agency_name')
                        ->label('Requiring Agency')
                        ->sortable()
                        ->searchable() 
                        ->wrap(),
                    IconColumn::make('requiredDocument.is_confidential')
                        ->label('Confidential')
                        ->boolean()
                        ->sortable()
                        ->searchable()
                        ->trueColor('warning')   // yellow
                        ->falseColor('gray')     // dark / black-ish
                        ->trueIcon('heroicon-o-lock-closed')
                        ->falseIcon('heroicon-o-lock-open'),
                    TextColumn::make('status')
                        ->label('Compliance Status')
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

                    TextColumn::make('validation_status')
                        ->label('Validation Status')
                        ->formatStateUsing(function ($state) {
                            return match ($state) {
                                'returned' => 'Returned',
                                'pending_review'  => 'Pending Review',
                                'validated'  => 'Validated',
                                default => 'pending_review',
                            };
                        })
                        ->badge() // optional: shows as colored badge
                        ->colors([
                            'danger' => 'returned',
                            'warning' => 'pending_review',
                            'success' => 'validated',
                        ])
                        ->html()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('requiredDocument.due_date')
                        ->label('Deadline')
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
                    SelectFilter::make('status')
                    ->label('Compliance Status')
                    ->options([
                        '-1' => 'Not Complied',
                        '0'  => 'Partially Complied',
                        '1'  => 'Complied',
                    ]),
                
                    SelectFilter::make('validation_status')
                        ->label('Validation Status')
                        ->options([
                            'pending_review' => 'Pending Review',
                            'returned'       => 'Returned',
                            'validated'      => 'Validated',
                        ]),

                    SelectFilter::make('confidential')
                        ->label('Confidentiality')
                        ->options([
                            '1' => 'Confidential',
                            '0' => 'Non-Confidential',
                        ])
                        ->query(fn (Builder $query, array $data) =>
                            isset($data['value'])
                                ? $query->whereHas('requiredDocument', fn ($q) =>
                                    $q->where('is_confidential', $data['value'])
                                )
                                : null
                        ),

                    Filter::make('overdue')
                        ->label('Overdue')
                        ->query(fn (Builder $query) =>
                            $query->whereHas('requiredDocument', fn ($q) =>
                                $q->whereDate('due_date', '<', now())
                            )
                        ),
                ],
                layout: FiltersLayout::AboveContentCollapsible)

                ->recordActions([
                    // ViewAction::make(),
                    EditAction::make(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        // DeleteBulkAction::make(),
                    ]),
                ]);
    }
}
