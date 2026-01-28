<?php

namespace App\Filament\Resources\RequiredDocuments\Schemas;

use Carbon\Carbon;
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
                            ->required()
                            ->disabled(function ($record) {
                                if (!$record) {
                                    return false; // Allow creation
                                }
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;
                                
                                // Disable if user is NOT from the requiring agency
                                return $userOfficeName !== $record->agency_name;
                            }),
                        TextInput::make('year')
                            ->numeric()
                            ->default(date('Y')) // automatically sets the current year
                            ->readOnly(),
                        DatePicker::make('date_from')  
                            ->label('Start Date')
                            ->required()
                            ->live() // Make it reactive
                            ->locale('en-US') // Force US format
                            ->native(false)   // Use JS picker instead of browser native
                            ->displayFormat('m/d/Y') 
                            ->placeholder('mm/dd/yyyy') 
                            ->afterStateUpdated(function (Set $set) {
                                $set('due_date', null); // Optional: clear due_date when date_from changes
                            })
                            ->disabled(function ($record) {
                                if (!$record) return false;
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;

                                // Disable if the user is NOT from the requiring agency
                                if ($userOfficeName !== $record->agency_name) {
                                    return true;
                                }
                                // Disable if any complying office has status 0 (Partially Complied) or 1 (Complied)
                                return $record->complyingOffices()
                                            ->whereIn('status', [0, 1])
                                            ->exists();
                            }),
                        DatePicker::make('due_date')
                            ->label('Deadline')
                            ->required()
                            ->locale('en-US') // Force US format
                            ->native(false)   // Use JS picker instead of browser native
                            ->displayFormat('m/d/Y') 
                            ->placeholder('mm/dd/yyyy') 
                            ->afterOrEqual('date_from') // Validation rule
                            ->minDate(fn (Get $get) => $get('date_from'))
                            ->disabled(function ($record) {
                                if (!$record) return false;
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;
                                // Disable if the user is NOT from the requiring agency
                                if ($userOfficeName !== $record->agency_name) {
                                    return true;
                                }
                                // Disable if any complying office has status 0 (Partially Complied) or 1 (Complied)
                                return $record->complyingOffices()
                                            ->whereIn('status', [0, 1])
                                            ->exists();
                            }), // Disables dates before date_from in picker

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
                            })
                            ->disabled(function ($record) {
                                if (!$record) return false;
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;
                                return $userOfficeName !== $record->agency_name;
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
                            ->required()
                            ->disabled(function ($record) {
                                if (!$record) return false;
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;
                                return $userOfficeName !== $record->agency_name;
                            }),

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
                                    
                            })
                            ->disabled(function ($record) {
                                if (!$record) return false;
                                $user = auth()->user();
                                $userOfficeName = optional($user->office)->office;
                                return $userOfficeName !== $record->agency_name;
                            }),
                        
                        Toggle::make('is_confidential')
                            ->label('Confidential')->disabled(function ($record) {
                            if (!$record) return false;
                            $user = auth()->user();
                            $userOfficeName = optional($user->office)->office;
                            return $userOfficeName !== $record->agency_name;
                        }),
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
                                            ])->columns(2)
                                            ->columnSpanFull(),


                        Section::make('Complying Offices')
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
                                        ->disabled(function ($record) {
                                            if (!$record) return false;
                                            $user = auth()->user();
                                            $userOfficeName = optional($user->office)->office;
                                            return $userOfficeName !== $record->agency_name;
                                        })
                                        ->afterStateHydrated(function ($component, $state, $record) {
                                            if ($record?->exists) {
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
                                                )
                                                ->disabled(function ($record) {
                                                    if (!$record) return false;
                                                    $user = auth()->user();
                                                    $userOfficeName = optional($user->office)->office;
                                                    return $userOfficeName !== $record->agency_name;
                                                }),

                                            Action::make('clearAll')
                                                ->label('Clear')
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
                                            ]),
                                    

                                ])->columnSpanFull(),

            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        // $data['_selected_offices'] = $data['complying_offices'] ?? [];
        // $data['_status'] = $data['status'] ?? -1;

        // unset($data['complying_offices'], $data['status']);

        $data['_selected_offices'] = $data['complying_offices'] ?? [];
        
        // Keep these for the model - don't unset them!
        // They should be saved to the required_documents table
        if (!isset($data['is_recurring'])) {
            $data['is_recurring'] = false;
        }

        if (!isset($data['recurrence_type'])) {
            $data['recurrence_type'] = null;
        }
        if (!isset($data['recurrence_interval'])) {
            $data['recurrence_interval'] = null;
        }

        unset($data['complying_offices']);

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
                'required_document_id'  => $record->id,
                'status'          => $status,
                'due_date'        => $record->due_date, // original due date
            ]);
        }

        // 🔥 IMPORTANT: Dispatch the job with a delay to ensure 
        // complying offices are fully saved before the job runs
        if ($record->is_recurring && $record->recurrence_type) {
            \App\Jobs\CreateRecurringDocuments::dispatch(
                $record->fresh(),
                $record->recurrence_type,
                $record->recurrence_interval
            )->afterCommit(); // Small delay to ensure DB consistency
        }
    }



   

    /**
     * Calculate the next due date based on recurrence type
     */
    private static function calculateNextDueDate(Carbon $baseDate, string $recurrenceType, ?int $interval, int $occurrence): Carbon
    {
        $date = $baseDate->copy();

        switch ($recurrenceType) {
            case 'yearly':
                return $date->addYears($occurrence);
            case 'quarterly':
                return $date->addMonths(3 * $occurrence);
            case 'semester':
                return $date->addMonths(6 * $occurrence);
            case 'custom':
                return $date->addDays($interval * $occurrence);
            default:
                return $date;
        }
    }

}
