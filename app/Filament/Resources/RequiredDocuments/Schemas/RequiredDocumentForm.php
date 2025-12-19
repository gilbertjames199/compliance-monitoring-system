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
                            ->after('date_from')
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





                        

                        // Select::make('document_category_id')
                        //     ->required()
                        //     ->label('Document Category ID')
                        //     ->options(
                        //         DocumentCategory::orderBy('id') // or the column you use for category name
                        //             ->pluck('id', 'id') // 'name' will show, 'id' will be saved
                        //             ->toArray()
                        //     )
                        //     ->searchable() // optional: makes it searchable
                        //     ->preload(),    // optional: loads options immediately
                        // Toggle::make('is_external')
                        //     ->label('Is External?')
                        //     ->required(),
                        // TextInput::make('requiring_agency_internal')
                        //     ->label('Requiring Agency (Internal)')
                        //     ->required(),



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
                        // TextInput::make('agency_type')
                        //     ->label('Requiring Agency Type')
                        //     ->required(),
                        // TextInput::make('agency_name')
                        //     ->label('Requiring Agency')
                        //     ->required(),
                        
                        
                        
                        Toggle::make('is_confidential')
                            ->label('Confidential')
                            ->required(),
                        Toggle::make('is_recurring')
                            ->label('Recurring?')
                            ->required(),
                        
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

                // Section::make('Complying Offices')
                //     ->schema([
                //         Select::make('selected_offices')
                //             ->label('Select Offices')
                //             ->multiple()
                //             ->options(Office::pluck('office', 'department_code'))
                //             ->reactive()
                //             ->helperText('Select multiple offices or click "Select All Offices" below.'),

                //         Select::make('status')
                //             ->label('Compliance Status')
                //             ->options([
                //                 -1 => 'Not Complied',
                //                 0  => 'Partially Complied',
                //                 1  => 'Complied',
                //             ])
                //             ->default(-1)
                //             ->required(),

                //         Actions::make([
                //             Action::make('select_all')
                //                 ->label('Select All Offices')
                //                 ->button()
                //                 ->color('primary')
                //                 ->action(function (Get $get, Set $set) {
                //                     $set('selected_offices', Office::pluck('department_code')->toArray());
                //                 }),
                //         ]),
                //     ])->columnSpanFull(),
                /*TextInput::make('requirement')
                    ->required(),
                TextInput::make('is_external')
                    ->required(),
                TextInput::make('requiring_agency_internal'),
                TextInput::make('agency_name')
                    ->required(),
                TextInput::make('is_confidential')
                    ->required(),
                TextInput::make('date_from')
                    ->required(),
                TextInput::make('due_date')
                    ->required(),
                TextInput::make('year')
                    ->required(),
                TextInput::make('is_recurring')
                    ->required(),
                TextInput::make('document_category_id')
                    ->required(),*/
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
            ]);
        }
    }
}
