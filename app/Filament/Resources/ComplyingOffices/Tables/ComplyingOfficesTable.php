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
use Illuminate\Support\Facades\Auth;

class ComplyingOfficesTable
{
    public static function configure(Table $table): Table
    {
        // dd(auth()->user()->department_code);
        return $table
               
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
                    // if ($user->hasRoleSafe('AO', 'admin')) {
                    if (! $user->can('ViewConfidential:RequiredDocument')) {
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
                ->defaultGroup('requiredDocument.category.category')
                ->columns([
                    // TextColumn::make('department_code')
                    //     ->searchable(),
                    // TextColumn::make('office.office')
                    //     ->label('Complying Office') // Optional custom label
                    //     ->searchable()
                    //     ->sortable()
                    //     ->wrap(),
                    TextColumn::make('requiredDocument.requirement')
                        ->label('Requirement')
                        ->searchable()
                        ->sortable()
                        ->limit(100)
                        ->wrap()
                        ->tooltip(function (TextColumn $column): ?string {
                            $state = $column->getState();

                            if (strlen($state) <= $column->getCharacterLimit()) {
                                return null;
                            }

                            // Only render the tooltip if the column contents exceeds the length limit.
                            return $state;
                        }),
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
                        ->searchable()
                        ->color(fn ($record) => 
                        // Only mark red if status is not 1 or not "complied" AND the due date is past
                        ($record->status != 1 && $record->status != 'complied' && now()->gt($record->requiredDocument->due_date))
                            ? 'danger'
                            : null // Default color
                        ),
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
                        ->multiple()
                        ->options([
                            '-1' => 'Not Complied',
                            '0'  => 'Partially Complied',
                            '1'  => 'Complied',
                    ]),
                
                    SelectFilter::make('validation_status')
                        ->label('Validation Status')
                        ->multiple()
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
                    EditAction::make()
                        ->label('Review & Submit'),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        // DeleteBulkAction::make(),
                    ]),
                ]);
    }
}
