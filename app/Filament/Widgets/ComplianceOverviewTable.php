<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\TableWidget;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ComplianceOverviewTable extends TableWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    /**
     * Cache of divisions keyed by "department_code|division_code" => stdClass row.
     * Queried directly against fms.divisions on the mysql2 connection,
     * matching the convention used elsewhere in the app.
     */
    protected static ?Collection $divisionsCache = null;

    protected static function divisionsMap(): Collection
    {
        if (static::$divisionsCache === null) {
            static::$divisionsCache = DB::connection('mysql2')
                ->table('fms.divisions')
                ->whereNotNull('division_code')
                ->get()
                ->keyBy(fn ($division) => $division->department_code . '|' . $division->division_code);
        }

        return static::$divisionsCache;
    }

    public function table(Table $table): Table
    {
        return $table
        
            ->query(fn (): Builder => ComplyingOffice::query())
            ->modifyQueryUsing(function (Builder $query) {

                $user = auth()->user();

                if (! $user) {
                // Not logged in → show nothing
                    $query->whereRaw('1 = 0');
                    return;
                }

                // ❗ If user has NO roles → show nothing
                if ($user->roles->isEmpty()) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                // Only show pending / partial
                $query->whereIn('status', [-1, 0]);
                $accessibleDepartmentCodes = $user->accessibleDepartmentCodes();

                // Join required for confidentiality filtering
                $query->join(
                    'required_documents',
                    'required_documents.id',
                    '=',
                    'complying_offices.required_document_id'
                );

                // ✅ ONLY super_admin can see all
                if (! $user->hasRoleSafe('super_admin')) {
                    $query->whereIn('complying_offices.department_code', $accessibleDepartmentCodes);

                    // 🔒 DIVISION SCOPING
                    // For requirements that are division-tracked, non-super_admin users
                    // should only see complying offices for divisions assigned to them,
                    // using the same User::divisionCodes() helper used elsewhere in the app.
                    $userDivisionCodes = collect(
                        method_exists($user, 'divisionCodes') ? $user->divisionCodes() : []
                    )->filter()->values();

                    $query->where(function (Builder $q) use ($userDivisionCodes) {
                        // Always allow non-division-tracked requirements
                        $q->where(function (Builder $q2) {
                            $q2->where('required_documents.requires_division_tracking', false)
                                ->orWhereNull('required_documents.requires_division_tracking');
                        });

                        // For division-tracked requirements, only allow the user's assigned divisions
                        if ($userDivisionCodes->isNotEmpty()) {
                            $q->orWhereIn('complying_offices.division_code', $userDivisionCodes->toArray());
                        }
                    });
                }

                // 🔒 CONFIDENTIAL CONTROL
                if (! $user->can('ViewConfidential:RequiredDocument')) {
                    $query->where('required_documents.is_confidential', false);
                }

                $query->select('complying_offices.*');
            })
            ->defaultGroup('office.office')
            
            ->columns([
                TextColumn::make('requiredDocument.requirement')
                    ->label('Required Document')
                    ->searchable()
                    ->wrap()
                    ->color(fn ($record) =>
                        $record->requiredDocument?->is_confidential
                            ? 'warning'
                            : null
                    )
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('office.office')
                    ->label('Complying Office')
                    ->listWithLineBreaks()
                    ->searchable()
                    ->wrap()
                    ->color(fn ($record) =>
                        $record->requiredDocument?->is_confidential
                            ? 'warning'
                            : null
                    )
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('division_code')
                    ->label('Division')
                    ->getStateUsing(function ($record) {
                        if (blank($record->division_code)) {
                            return null;
                        }

                        $key = $record->department_code . '|' . $record->division_code;
                        $division = static::divisionsMap()->get($key);

                        return $division?->division_name1 ?: $record->division_code;
                    })
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable()
                    ->extraCellAttributes(['class' => 'align-top']),

                TextColumn::make('requiredDocument.agency_name')
                    ->label('Requiring Agency')
                    ->searchable()
                    ->wrap()
                    ->color(fn ($record) =>
                        $record->requiredDocument?->is_confidential
                            ? 'warning'
                            : null
                    )
                    ->extraCellAttributes(['class' => 'align-top']),

                IconColumn::make('requiredDocument.is_confidential')
                    ->label('Confidential')
                    ->boolean()
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
                    ->date()
                    ->sortable()
                    ->searchable()
                    ->color(fn ($record) => 
                        // Only mark red if status is not 1 or not "complied" AND the due date is past
                        ($record->status != 1 && $record->status != 'complied' && now()->gt($record->requiredDocument->due_date))
                            ? 'danger'
                            : null // Default color
                    )
                    ->extraCellAttributes(['class' => 'align-top']),
            ])
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

                SelectFilter::make('division_code')
                    ->label('Division')
                    ->options(function () {
                        return static::divisionsMap()
                            ->values()
                            ->unique('division_code')
                            ->sortBy('division_name1')
                            ->mapWithKeys(fn ($division) => [
                                $division->division_code => $division->division_name1 ?: $division->division_code,
                            ])
                            ->toArray();
                    })
                    ->query(fn (Builder $query, array $data) =>
                        isset($data['value']) && $data['value'] !== ''
                            ? $query->where('complying_offices.division_code', $data['value'])
                            : null
                    ),

                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query) =>
                        $query->whereHas('requiredDocument', fn ($q) =>
                            $q->whereDate('due_date', '<', now())
                        )
                    ),

                // SelectFilter::make('department_code')
                //     ->label('Complying Office')
                //     ->relationship('office', 'office')
                //     ->searchable()
                //     ->preload(),

            ], 
            layout: FiltersLayout::AboveContentCollapsible)
            
            ->headerActions([
                
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-arrow-right')
                    ->url(fn ($record) => route('filament.admin.resources.complying-offices.edit', $record))
                    ->openUrlInNewTab(false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn ($records, $livewire) => auth()->user()->hasRoleSafe('super_admin')),
                ]),
            ]);
    }
}