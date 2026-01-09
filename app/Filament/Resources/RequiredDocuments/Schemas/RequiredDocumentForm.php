<?php

namespace App\Filament\Resources\RequiredDocuments\Schemas;

use App\Models\Office;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use App\Models\DocumentCategory;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class RequiredDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                
                Section::make('Document Details')
                    ->schema([
                        TextInput::make('requirement')
                            ->required(),
                        TextInput::make('year')
                            // ->required()
                            ->numeric()
                            ->default(date('Y')) // automatically sets the current year
                            ->readOnly(),
                        DatePicker::make('date_from')  
                            ->label('Date From')
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Due Date')
                            // ->after('date_from')
                            ->required(),


                      Select::make('category')
                            ->label('Category')
                            ->required()
                            ->options(
                                DocumentCategory::orderBy('category')
                                    ->pluck('category', 'id')
                                    ->toArray()
                            )
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set) {
                                $set('document_category_id', $state); // update ID input
                            })
                            ->afterStateHydrated(function ($state, $set, $record) {
                                if ($record) {
                                    $set('category', $record->document_category_id); 
                                    $set('document_category_id', $record->document_category_id);
                                }
                            }),

                        TextInput::make('document_category_id')
                            ->label('Category ID')
                            ->readOnly(),

                        Select::make('agency_type')
                                ->label('Agency Type')
                                ->options([
                                    'internal' => 'Internal',
                                    'external' => 'External',
                                ])
                                ->reactive()
                                ->required(),

                        Select::make('agency_name')
                            ->label('Requiring Agency')
                            ->searchable()
                            ->reactive()
                            ->options(function ($get) {
                                $type = $get('agency_type');

                                if ($type === 'internal') {
                                    return Office::on('mysql2')
                                        ->whereBetween('id', [1, 26]) // adjust your range if needed
                                        ->pluck('office', 'office'); // key and value are the name itself
                                }

                                if ($type === 'external') {
                                    return Office::on('mysql2')
                                        ->where('id', '>=', 27)
                                        ->pluck('office', 'office');
                                }

                                return [];
                            })
                            ->required()
                            ->afterStateHydrated(function ($component, $get, $state) {
                                if (!$state) return;
                                // If editing, pre-select agency name
                                $component->state($state);
                            })
                            ->createOptionForm([
                                TextInput::make('agency_name')
                                    ->label('New External Agency Name')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data) {
                                // Save new external agency to FMS database
                                return Office::on('mysql2')->create([
                                    'office' => $data['agency_name'],
                                ])->office; // return the office name so it gets saved in required_documents
                                    
                            }),
                        
                        Toggle::make('is_confidential')
                            ->label('Confidential')
                            ->required(),
                        Grid::make(1) // parent grid: 2 columns
                            ->schema([
                                // Left column
                                Toggle::make('is_recurring')
                                    ->label('Recurring?')
                                    ->reactive()
                                    ->required()
                                    ->afterStateUpdated(function ($state, $set) {
                                        if (!$state) {
                                            // Clear both recurrence fields when toggle is off
                                            $set('recurrence_type', null);
                                            // $set('recurrence_interval', null);
                                        }
                                    }),

                                // Right column: nested grid
                                Grid::make(1) // one column grid to stack the two fields vertically
                                    ->schema([
                                        Select::make('recurrence_type')
                                            ->label('Recurrence Type')
                                            ->options([
                                                'quarterly' => 'Quarterly',
                                                'yearly' => 'Yearly',
                                            ])
                                            ->reactive()
                                            ->visible(fn($get) => $get('is_recurring'))
                                            ->required(fn($get) => $get('is_recurring'))
                                            ->afterStateUpdated(function ($state, $set) {
                                                // Reset recurrence_interval if not custom
                                                if ($state !== 'custom') {
                                                    $set('recurrence_interval', null);
                                                }
                                            })
                                            ->dehydrated(true) // Always dehydrate
                                            ->dehydrateStateUsing(fn($state, $get) => $get('is_recurring') ? $state : null), // Force null when not recurring


                                        // TextInput::make('recurrence_interval')
                                        //     ->label('Custom Interval (months)')
                                        //     ->numeric()
                                        //     ->minValue(1)
                                        //     ->visible(fn($get) => $get('is_recurring') && $get('recurrence_type') === 'custom')
                                        //     ->required(fn($get) => $get('recurrence_type') === 'custom')
                                        //     ->dehydrated(true) // Always dehydrate
                                        //     ->dehydrateStateUsing(fn($state, $get) => 
                                        //         ($get('is_recurring') && $get('recurrence_type') === 'custom') ? $state : null
                                        //     ),
                                    ]),
                                ]),
                                            ])->columns(2)
                                            ->columnSpanFull(),


                        Section::make('Complying Offices')
                                    // ->columns(2)
                                    ->schema([
        
                                Select::make('complying_offices')
                                        ->label('Complying Offices')
                                        ->required()
                                        ->multiple()
                                        ->options(
                                            Office::orderBy('office')
                                                ->pluck('office', 'department_code')
                                                ->toArray()
                                        )
                                        ->preload()
                                        ->searchable()
                                        ->afterStateHydrated(function ($component, $state, $record) {
                                            if ($record) {
                                                $component->state(
                                                    $record->complyingOffices()->pluck('department_code')->toArray()
                                                );
                                            }
                                        })

                                        ->helperText('Select one or more offices that must comply with this requirement.')
                                        ->suffixActions([
                                            Action::make('selectAll')
                                                ->label('Select All')
                                                ->icon('heroicon-o-check-circle')
                                                ->action(fn (callable $set) =>
                                                    $set('complying_offices', Office::pluck('department_code')->toArray())
                                                ),

                                            Action::make('clearAll')
                                                ->label('Clear')
                                                ->icon('heroicon-o-x-circle')
                                                ->color('danger')
                                                ->action(fn (callable $set) =>
                                                    $set('complying_offices', [])
                                                ),
                                            ]),
                                    

                                ])->columnSpanFull(),

            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // $data['_selected_offices'] = $data['complying_offices'] ?? [];
        // $data['_status'] = $data['status'] ?? -1;

        // unset($data['complying_offices'], $data['status']);

        return $data;
    }

    /**
     * Hook after creating the RequiredDocument
     */
    public static function afterCreate($record, array $data): void
    {
        $selectedOffices = $data['_selected_offices'] ?? [];
        $status = $data['_status'] ?? -1;

        foreach ($selectedOffices as $deptCode) {
            ComplyingOffice::create([
                'department_code' => $deptCode,
                'requirement_id'  => $record->id,
                'status'          => $status,
                'due_date'        => $record->due_date, // original due date
            ]);
        }
    }

}
