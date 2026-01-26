<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Models\Office;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
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
                        Section::make('Category Information') // Add a section title
                            ->schema([
                                TextInput::make('category')
                                    ->label('Category')
                                    ->required()
                            ])
                    ])
                    ->columnSpanFull(),

                Repeater::make('requiredDocuments')
                    ->label('Required Documents')
                    ->relationship('requiredDocuments') // 🔑 must match the model method exactly
                    ->columnSpanFull()
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->deletable(false)
                    ->schema([

                    Section::make('Required Documents Details')
                        ->columns(2)
                        ->schema([
                            TextInput::make('requirement')
                                ->required(),
                            TextInput::make('year')
                                ->numeric()
                                ->default(date('Y')) // automatically sets the current year
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

                            DatePicker::make('date_from')  
                                ->label('Start Date')
                                ->required()
                                ->live() // Make it reactive
                                ->displayFormat('m/d/Y') // Show as Month/Day/Year
                                ->afterStateUpdated(function (Set $set) {
                                    $set('due_date', null); // Optional: clear due_date when date_from changes
                                }),

                            DatePicker::make('due_date')
                                ->label('Deadline')
                                ->required()
                                ->afterOrEqual('date_from') // Validation rule
                                ->minDate(fn (Get $get) => $get('date_from')), // Disables dates before date_from in picker

                            Toggle::make('is_confidential')
                                ->label('Confidential'),
                            
                            Grid::make(1) // parent grid: 1 column 
                                ->schema([ 
                                    // Toggle for recurring
                                    Toggle::make('is_recurring') 
                                        ->label('Recurring?') 
                                        ->reactive() 
                                        // ->required() 
                                        ->afterStateUpdated(function ($state, $set) { 
                                            if (!$state) { 
                                                // Clear recurrence fields when toggle is off 
                                                $set('recurrence_type', null); 
                                                $set('recurrence_interval', null); 
                                            } 
                                        })->disabled(function ($record) {
                                            if (!$record) return false;
                                            $user = auth()->user();
                                            $userOfficeName = optional($user->office)->office;
                                            return $userOfficeName !== $record->agency_name;
                                        }), 

                                    // Nested grid for recurrence fields
                                    Grid::make(2) // one column grid to stack the fields vertically 
                                        ->schema([ 
                                            Select::make('recurrence_type') 
                                                ->label('Recurrence Type') 
                                                ->options([ 
                                                    'yearly' => 'Yearly', 
                                                    'quarterly' => 'Quarterly', 
                                                    'semester' => 'Per Semester (Jan-June, July-Dec)', 
                                                    'custom' => 'Custom (Days)', 
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
                                                ->dehydrated(true) 
                                                ->dehydrateStateUsing(fn($state, $get) => $get('is_recurring') ? $state : null)
                                                ->disabled(function ($record) {
                                                    if (!$record) return false;
                                                    $user = auth()->user();
                                                    $userOfficeName = optional($user->office)->office;
                                                    return $userOfficeName !== $record->agency_name;
                                                }), 

                                            TextInput::make('recurrence_interval') 
                                                ->label('Custom Interval (days)') 
                                                ->numeric() 
                                                ->minValue(1) 
                                                ->suffix('days')
                                                ->visible(fn($get) => $get('is_recurring') && $get('recurrence_type') === 'custom') 
                                                ->required(fn($get) => $get('is_recurring') && $get('recurrence_type') === 'custom') 
                                                ->dehydrated(true) 
                                                ->dehydrateStateUsing(fn($state, $get) =>  
                                                    ($get('is_recurring') && $get('recurrence_type') === 'custom') ? $state : null 
                                                ), 
                                        ]), 
                            ]),
                        ])
                        ->visible(function ($record) {
                                    if (!$record) {
                                        return true; // Allow viewing during creation
                                    }
                                    $user = auth()->user();
                                    $userOfficeName = optional($user->office)->office;
                                    
                                    // Show only if user is from the requiring agency
                                    return $userOfficeName === $record->agency_name;
                                }),

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
                                ->disabled(function ($record) {
                                    if (!$record) return false;
                                    $user = auth()->user();
                                    $userOfficeName = optional($user->office)->office;
                                    return $userOfficeName !== $record->agency_name;
                                })
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
                                            'required_document_id'  => $record->id,
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
                                        )
                                        ->disabled(function ($record) {
                                            if (!$record) return false;
                                            $user = auth()->user();
                                            $userOfficeName = optional($user->office)->office;
                                            return $userOfficeName !== $record->agency_name;
                                        }),

                                    Action::make('clear')
                                        ->icon('heroicon-o-x-circle')
                                        ->color('danger')
                                        ->action(fn (callable $set) =>
                                            $set('complying_offices', [])
                                        )
                                        ->disabled(function ($record) {
                                            if (!$record) return false;
                                            $user = auth()->user();
                                            $userOfficeName = optional($user->office)->office;
                                            return $userOfficeName !== $record->agency_name;
                                        }),
                                    ])
                                ])
                                ->visible(function ($record) {
                                    if (!$record) {
                                        return true; // Allow viewing during creation
                                    }
                                    
                                    $user = auth()->user();
                                    $userOfficeName = optional($user->office)->office;
                                    
                                    // Show only if user is from the requiring agency
                                    return $userOfficeName === $record->agency_name;
                                }),
                            ])
                ]);
    }
}