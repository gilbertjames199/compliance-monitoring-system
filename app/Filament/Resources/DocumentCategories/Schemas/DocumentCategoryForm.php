<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Models\Office;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //     TextInput::make('category')
                //         ->required()
                View::make('forms.components.sticky-category')
                ->schema([
                        TextInput::make('category')
                        ->required()
                ]),
                // ->columnSpanFull(),
                // Section::make()
                //     ->schema([
                //         TextInput::make('category')
                //             ->label('Category')
                //             ->columnSpanFull()
                //             ->required(),
                //     ])
                //     ->extraAttributes([
                //         'class' => 'sticky top-0 bg-white z-50 p-4 shadow-sm',
                //         // 🧠 "sticky" keeps it fixed,
                //         // "top-0" pins it to the top,
                //         // "bg-white" avoids transparency overlap
                //         // "z-50" ensures it's above other elements
                //         // "p-4" adds spacing, "shadow-sm" gives a subtle border look
                //     ]),
                // 🔁 Repeater for Required Documents

                Repeater::make('requiredDocuments')
                    ->label('Required Documents')
                    ->relationship('requiredDocuments') // 🔑 must match the model method exactly
                    ->schema([
                        Section::make('Details')
                        ->columns(2)
                        ->schema([
                            TextInput::make('requirement')
                                ->required(),
                            TextInput::make('year')
                                // ->required()
                                ->numeric()
                                ->default(date('Y')) // automatically sets the current year
                                ->readOnly(),
                            // TextInput::make('requiring_agency_internal')
                            //     ->required(),
                            // TextInput::make('agency_name')
                            //     ->label('Requiring Agency')
                            //     ->required(),
                            // Select::make('agency_name')
                            //     ->label('Requiring Agency')
                            //     ->searchable()
                            //     ->preload()
                            //     ->options(
                            //         Office::query()->pluck('office', 'office') // key = office name, value = office name
                            //     )
                            //     ->createOptionForm([
                            //         TextInput::make('office')
                            //             ->label('Office Name')
                            //             ->required(),
                            //     ])
                            //     ->createOptionUsing(function (array $data) {
                            //         // Creates new office inside the fms database
                            //         $office = Office::create([
                            //             'office' => $data['office'],
                            //         ]);

                            //         // return the value that will be saved to the field
                            //         return $office->office; 
                            //     })
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


                            DatePicker::make('date_from')  
                                ->label('Date From')
                                ->required(),
                            DatePicker::make('due_date')
                                ->label('Due Date')
                                ->after('date_from')
                                ->required(),
                            Toggle::make('is_confidential')
                                ->label('Confidential')
                                ->required(),
                            // Toggle::make('is_external')
                            //     ->label('External')
                            //     ->required(),
                            Toggle::make('is_recurring')
                                ->label('Recurring')
                                ->required(),
                        ]),

                        Section::make('Complying Offices')
                            // ->columns(2)
                            ->schema([
 
                          Select::make('complying_offices')
                            ->label('Complying Offices')
                            ->multiple()
                            ->required()
                            ->options(
                                Office::orderBy('office')->pluck('office', 'department_code')
                            )
                            ->preload()
                            ->searchable()

                            ->loadStateFromRelationshipsUsing(fn ($component, $record) =>
                                $component->state(
                                    $record->complyingOffices
                                        ->pluck('department_code')
                                        ->toArray()
                                )
                            )

                            ->saveRelationshipsUsing(function ($component, $record, $state) {
                                $record->complyingOffices()->delete();

                                foreach ($state ?? [] as $departmentCode) {
                                    ComplyingOffice::create([
                                        'requirement_id'  => $record->id,
                                        'department_code' => $departmentCode,
                                        'status'          => -1,
                                    ]);
                                }
                            })

                            ->suffixActions([
                                Action::make('selectAll')
                                    ->icon('heroicon-o-check-circle')
                                    ->action(fn (callable $set) =>
                                        $set('complying_offices', Office::pluck('department_code')->toArray())
                                    ),

                                Action::make('clear')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->action(fn (callable $set) =>
                                        $set('complying_offices', [])
                                    ),
                            ])


                                ]),
                                ])
                                ->columnSpanFull()
                                ->reorderable()
                                ->collapsible()
                                ->cloneable()
                            ]);
    }
}
// ->visible(fn ($get) => blank($get('id')))
    //     Repeater::make('complyingOffices')
            //         ->relationship()
            //         ->label('Complying Offices')
            //         ->schema([
            //             // Select::make('department_code')
            //             //     ->label('Office')
            //             //     ->options(fn () => Office::pluck('office', 'department_code'))
            //             //     ->required(),
            //             Select::make('selected_offices')
            //         ->label('Complying Offices')
            //         ->multiple()
            //         ->searchable()
            //         ->options(fn () => Office::pluck('office', 'department_code'))
            //         ->helperText('Select the offices required to comply with this document.'),

            //             Select::make('status')
            //                 ->label('Status')
            //                 ->options([
            //                     -1 => 'Not Complied',
            //                     0 => 'Partially Complied',
            //                     1 => 'Complied',
            //                 ])
            //                 ->default(-1)
            //                 ->required(),
            //             Actions::make([
            //                 Action::make('add_all_offices')
            //                     ->label('Add All Offices')
            //                     ->icon('heroicon-o-building-office-2')
            //                     ->action(function (callable $set) {
            //                         $allOffices = Office::pluck('department_code')->toArray();
            //                         $set('selected_offices', $allOffices);
            //                     }),
            //                 ]),
            //         ])
            //         ->orderable(false)
            //         ->collapsible()
            //         ->createItemButtonLabel('Add Office')
            //         ->afterStateHydrated(function ($state, Set $set) {
            //             // Ensure each has default status if not set
            //             $set('status', $state['status'] ?? -1);
            //         }),

            //     // // ✅ Multi-select for offices (not repeater)
            //     // Select::make('selected_offices')
            //     //     ->label('Complying Offices')
            //     //     ->multiple()
            //     //     ->searchable()
            //     //     ->options(fn () => Office::pluck('office', 'department_code'))
            //     //     ->helperText('Select the offices required to comply with this document.'),

            //     // // ✅ Shared status for all selected offices
            //     // Select::make('status')
            //     //     ->label('Status (applies to all selected offices)')
            //     //     ->options([
            //     //         'Pending' => 'Pending',
            //     //         'Complied' => 'Complied',
            //     //         'Not Applicable' => 'Not Applicable',
            //     //     ])
            //     //     ->default('Pending'),

            //     // // ⚡ "Add All Offices" button
            //     // Actions::make([
            //     //     Action::make('add_all_offices')
            //     //         ->label('Add All Offices')
            //     //         ->icon('heroicon-o-building-office-2')
            //     //         ->action(function (callable $set) {
            //     //             $allOffices = Office::pluck('department_code')->toArray();
            //     //             $set('selected_offices', $allOffices);
            //     //         }),
            //     //     ]),
// }
