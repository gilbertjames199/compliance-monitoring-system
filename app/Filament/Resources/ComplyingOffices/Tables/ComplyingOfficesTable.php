<?php

namespace App\Filament\Resources\ComplyingOffices\Tables;

use App\Models\Office;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ComplyingOfficesTable
{
    public static function configure(Table $table): Table
    {
         $user = auth()->user();

            return $table
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    if (! $user) return;

                    if ($user->hasRoleSafe('super_admin')) return;

                    if ($user->can('ViewAllOffices:RequiredDocument')) return;

                    if (! $user->can('ViewConfidential:RequiredDocument')) {
                        $query->whereHas('requiredDocument', fn ($q) =>
                            $q->where('is_confidential', false)
                        );
                    }

                    $userDivisionCodes = $user->divisionCodes();

                    $query->where('department_code', $user->department_code)
                        ->where(function ($q) use ($user, $userDivisionCodes) {

                            // Case 1: Document does NOT use division tracking
                            // → show the office-level row (division_code is null) to everyone in the office
                            // $q->where(function ($noTracking) use ($user) {
                            //     $noTracking->whereNull('division_code')
                            //         ->whereHas('requiredDocument', fn ($rd) =>
                            //             $rd->where('requires_division_tracking', false)
                            //         );
                            // });
                            $q->whereNull('division_code');

                            // Case 2: Document DOES use division tracking
                            // → only show rows where division_code matches user's assigned divisions
                            if (!empty($userDivisionCodes)) {
                                $q->orWhere(function ($withTracking) use ($userDivisionCodes) {
                                    $withTracking->whereNotNull('division_code')
                                        ->whereIn('division_code', $userDivisionCodes)
                                        ->whereHas('requiredDocument', fn ($rd) =>
                                            $rd->where('requires_division_tracking', true)
                                        );
                                });
                            }
                        });
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
                        ->extraCellAttributes(['class' => 'align-top'])
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
                        ->wrap()
                        ->extraCellAttributes(['class' => 'align-top']),

                    TextColumn::make('office.office')
                        ->label('Complying Office')
                        ->sortable()
                        ->searchable()
                        ->wrap()
                        ->extraCellAttributes(['class' => 'align-top']),

                    TextColumn::make('division_code')
                        ->label('Division')
                        ->getStateUsing(function ($record) {
                            if (!$record->division_code) return '—';

                            $division = DB::connection('mysql2')
                                ->table('fms.divisions')
                                ->where('division_code', $record->division_code)
                                ->first();

                            return $division
                                ? ($division->division_name1 . (!empty($division->division_short_name) ? ' (' . $division->division_short_name . ')' : ''))
                                : $record->division_code;
                        })
                        ->placeholder('—')
                        ->wrap()
                        ->extraCellAttributes(['class' => 'align-top']),

                    IconColumn::make('requiredDocument.is_confidential')
                        ->label('Confidential')
                        ->boolean()
                        ->sortable()
                        ->searchable()
                        ->trueColor('warning')   // yellow
                        ->falseColor('gray')     // dark / black-ish
                        ->trueIcon('heroicon-o-lock-closed')
                        ->falseIcon('heroicon-o-lock-open')
                        ->extraCellAttributes(['class' => 'align-top']),
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
                        ->searchable()
                        ->extraCellAttributes(['class' => 'align-top']),

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
                        ->searchable()
                        ->extraCellAttributes(['class' => 'align-top']),

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
                        )
                        ->extraCellAttributes(['class' => 'align-top']),

                    TextColumn::make('created_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->extraCellAttributes(['class' => 'align-top']),

                    TextColumn::make('updated_at')
                        ->dateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->extraCellAttributes(['class' => 'align-top']),
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

                    Filter::make('has_division')
                        ->label('Division Submissions Only')
                        ->query(fn (Builder $query) =>
                            $query->whereNotNull('division_code')
                        ),

                    // --- New: Office filter ---
                    // Only shown to super_admin. Everyone else is already hard-scoped
                    // to their own department_code/division via modifyQueryUsing above,
                    // so the filter UI would be redundant (and misleading, since it
                    // couldn't actually widen what they see).
                    SelectFilter::make('department_code')
                        ->label('Office')
                        ->options(fn () => Office::query()->pluck('office', 'department_code'))
                        ->searchable()
                        ->visible(fn () => $user && $user->hasRoleSafe('super_admin'))
                        ->modifyFormFieldUsing(fn (Select $field) => $field
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('division_code', null))
                        )
                        ->query(fn (Builder $query, array $data) =>
                            isset($data['value']) && $data['value'] !== ''
                                ? $query->where('department_code', $data['value'])
                                : null
                        )
                        ->default(fn () => static::resolveSelectedDepartmentCode($user)),

                    // --- New: Division filter ---
                    // Also super_admin-only (see note above). Options are scoped to
                    // whichever office is currently selected in the Office filter. We
                    // deliberately avoid Filament's Get $get injection here — in this
                    // Filament version, using Get inside a SelectFilter's options()
                    // closure throws "Component::$container must not be accessed
                    // before initialization" because the closure runs before the
                    // filter's schema container is attached. Instead we read the
                    // selected office straight out of the request's filters query
                    // array (the same place Filament persists it — visible in the URL
                    // as filters[department_code][value]=...), via
                    // resolveSelectedDepartmentCode(), falling back to the same
                    // default used by the department_code filter above when nothing's
                    // been submitted yet.
                    //
                    // NOTE: department_code on the `mysql` connection (Office) and on
                    // `mysql2`'s fms.divisions have previously mismatched due to
                    // zero-padding/type differences (same issue fixed in the PPA
                    // reporting feature). We normalize both sides to a zero-padded
                    // string before comparing — adjust the pad length below if your
                    // codes use a different width.
                    SelectFilter::make('division_code')
                        ->label('Division')
                        ->options(function () use ($user) {
                            $departmentCode = static::resolveSelectedDepartmentCode($user);

                            $query = DB::connection('mysql2')
                                ->table('fms.divisions')
                                ->orderBy('division_name1');

                            if ($departmentCode) {
                                // Normalize to match fms.divisions.department_code format.
                                // Adjust the pad length (currently 3) to your actual code width.
                                $normalized = str_pad((string) $departmentCode, 3, '0', STR_PAD_LEFT);

                                $query->where(DB::raw("LPAD(department_code, 3, '0')"), $normalized);
                            }

                            return $query->pluck('division_name1', 'division_code');
                        })
                        ->searchable()
                        ->visible(fn () => $user && $user->hasRoleSafe('super_admin'))
                        ->default(function () use ($user) {
                            if (! $user) return null;

                            $userDivisionCodes = $user->divisionCodes();

                            return count($userDivisionCodes) === 1
                                ? $userDivisionCodes[0]
                                : null;
                        }),
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

    /**
     * Resolves which department_code (office) is currently in effect for the
     * filters form: whatever the user has selected in the Office filter, or —
     * if nothing's been submitted yet — the same default the Office filter
     * itself falls back to.
     *
     * Reads directly from the request's filters query array instead of using
     * Filament's Get $get injection, since Get isn't safely usable inside a
     * SelectFilter's options() closure in this Filament version (throws
     * "Component::$container must not be accessed before initialization").
     */
    protected static function resolveSelectedDepartmentCode($user): ?string
    {
        $requested = request()->input('filters.department_code.value');

        if (filled($requested)) {
            return (string) $requested;
        }

        if (! $user) return null;

        return $user->department_code;
    }
}