<?php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Actions\EditAction;
use Filament\Widgets\TableWidget;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ComplyingOffices\ComplyingOfficeResource;

class ComplianceOverviewTable extends TableWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
        
            ->query(fn (): Builder => ComplyingOffice::query())
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
                            ->join('required_documents', 'required_documents.id', '=', 'complying_offices.required_document_id')
                            ->where('required_documents.is_confidential', false)
                            ->select('complying_offices.*');
                    }  
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
                    ),

                TextColumn::make('office.office')
                    ->label('Complying Office')
                    ->listWithLineBreaks()
                    ->searchable()
                    ->wrap()
                    ->color(fn ($record) =>
                        $record->requiredDocument?->is_confidential
                            ? 'warning'
                            : null
                    ),

                TextColumn::make('requiredDocument.agency_name')
                    ->label('Requiring Agency')
                    ->searchable()
                    ->wrap()
                    ->color(fn ($record) =>
                        $record->requiredDocument?->is_confidential
                            ? 'warning'
                            : null
                    ),

                IconColumn::make('requiredDocument.is_confidential')
                    ->label('Confidential')
                    ->boolean()
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
                    ->label('Due Date')
                    ->date()
                    ->sortable()
                    ->searchable()
                    ->color(fn ($record) => 
                        // Only mark red if status is not 1 or not "complied" AND the due date is past
                        ($record->status != 1 && $record->status != 'complied' && now()->gt($record->requiredDocument->due_date))
                            ? 'danger'
                            : null // Default color
                    ),
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
                        ->visible(fn ($records, $livewire) => auth()->user()->hasRole('super_admin')),
                ]),
            ]);
    }
}
