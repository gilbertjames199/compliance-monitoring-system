<?php

namespace App\Filament\Resources\RequiredDocuments\Schemas;

use App\Jobs\CreateRecurringDocuments;
use App\Models\ComplyingOffice;
use App\Models\DocumentCategory;
use App\Models\Office;
use App\Models\RequiredDocumentDivision;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

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
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),
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
                            // ->disabled(function ($record) {
                            //     if (self::isNotRequiringAgency($record)) return true;

                            //     // Disable if any complying office has status 0 (Partially Complied) or 1 (Complied)
                            //     return $record?->complyingOffices()
                            //                 ->whereIn('status', [0, 1])
                            //                 ->exists();
                            // })
                            ->disabled(function ($record) {
                                $user = auth()->user();

                                // Allow super_admin to always edit
                                if ($user?->hasRole('super_admin')) {
                                    return false;
                                }

                                // Disable if not requiring agency
                                if (self::isNotRequiringAgency($record)) {
                                    return true;
                                }

                                // Disable if any complying office has status 0 or 1
                                return $record?->complyingOffices()
                                    ->whereIn('status', [0, 1])
                                    ->exists();
                            })
                            ->helperText(function ($record) {
                                if (!$record) return '';

                                $user = auth()->user();

                                if ($user->hasRoleSafe('super_admin')) {
                                    return 'As superadmin, you can edit this field.';
                                }

                                $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                    ->value('department_code');

                                if ($user->department_code !== $agencyDepartmentCode) {
                                    return 'Only the requiring agency (' . $record->agency_name . ') can edit this field.';
                                }

                                if ($record->complyingOffices()->whereIn('status', [0, 1])->exists()) {
                                    return 'This field cannot be edited because one or more offices have already started complying.';
                                }
                                
                                return null;
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
                                $user = auth()->user();

                                // Allow super_admin to always edit
                                if ($user?->hasRole('super_admin')) {
                                    return false;
                                }

                                // Disable if not requiring agency
                                if (self::isNotRequiringAgency($record)) {
                                    return true;
                                }

                                // Disable if any complying office has status 0 or 1
                                return $record?->complyingOffices()
                                    ->whereIn('status', [0, 1])
                                    ->exists();
                            })
                            ->helperText(function ($record) {
                                if (!$record) return '';

                                $user = auth()->user();

                                if ($user->hasRoleSafe('super_admin')) {
                                    return 'As superadmin, you can edit this field.';
                                }

                                $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                    ->value('department_code');

                                if ($user->department_code !== $agencyDepartmentCode) {
                                    return 'Only the requiring agency (' . $record->agency_name . ') can edit this field.';
                                }

                                if ($record->complyingOffices()->whereIn('status', [0, 1])->exists()) {
                                    return 'This field cannot be edited because one or more offices have already started complying.';
                                }
                                
                                return null;
                            }),

                        Select::make('category')
                            ->label('Category')
                            ->required()
                            ->options(function () {
                                $user = auth()->user();

                                $query = DocumentCategory::orderBy('category');

                                if (!$user->hasRoleSafe('super_admin')) {
                                    $userIds = DB::connection('mysql2')
                                        ->table('systemusers')
                                        ->where('department_code', $user->department_code)
                                        ->pluck('recid')
                                        ->toArray();

                                    $query->whereIn('created_by', $userIds); // only their department, no nulls
                                }

                                return $query->pluck('category', 'id')->toArray();
                            })
                            ->searchable()
                            ->reactive()
                            ->createOptionForm([
                                TextInput::make('category')
                                    ->label('New Category')
                                    ->required()
                                    ->maxLength(255),
                            ])

                            ->createOptionUsing(function (array $data) {
                                $category = DocumentCategory::create([
                                    'category' => $data['category'],
                                ]);

                                return $category->id; // Important: return the ID
                            })
                            ->afterStateUpdated(function ($state, $set) {
                                $set('document_category_id', $state); // update ID input
                            })
                            ->afterStateHydrated(function ($state, $set, $record) {
                                if ($record) {
                                    $set('category', $record->document_category_id); 
                                    $set('document_category_id', $record->document_category_id);
                                }
                            })
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),

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
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),

                        Select::make('agency_name')
                            ->label('Requiring Agency')
                            ->searchable()
                            ->reactive()
                            ->options(function ($get) {
                                $type = $get('agency_type');
                                
                                if (!$type) {
                                    return [];
                                }

                                $query = Office::on('mysql2');

                                if ($type === 'internal') {
                                    $query->whereBetween('id', [1, 26]);
                                }

                                if ($type === 'external') {
                                    $query->where('id', '>=', 27);
                                }

                                return $query->get()
                                    ->mapWithKeys(function ($office) {
                                        $label = $office->office;

                                        if (!empty($office->short_name)) {
                                            $label .= ' (' . $office->short_name . ')';
                                        }

                                        return [$office->office => $label];
                                    })
                                    ->toArray();
                            })
                            ->required()
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),
                        
                        Toggle::make('is_confidential')
                            ->label('Confidential')
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),

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
                                    })
                                    ->disabled(fn ($record) => self::isNotRequiringAgency($record)), 

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
                                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)), 

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
                                        Office::on('mysql2')
                                            ->orderBy('office')
                                            ->get()
                                            ->mapWithKeys(function ($office) {
                                                $label = $office->office;

                                                if (!empty($office->short_name)) {
                                                    $label .= ' (' . $office->short_name . ')';
                                                }

                                                return [$office->department_code => $label];
                                            })
                                            ->toArray()
                                    )

                                    ->preload()
                                    ->searchable()
                                    ->reactive()
                                    ->live()
                                    ->disabled(fn ($record) => self::isNotRequiringAgency($record))
                                    ->afterStateHydrated(function ($component, $state, $record) {
                                        if ($record?->exists) {
                                            $component->state(
                                                $record->complyingOffices()
                                                    ->pluck('department_code')
                                                    ->unique()      // ← deduplicate
                                                    ->values()
                                                    ->toArray()
                                            );
                                        }
                                    })
                                    ->dehydrateStateUsing(function ($state, $record) {
                                        static $notified = false;

                                        $user = auth()->user();

                                        if ($user?->hasRole('super_admin')) {
                                            return collect($state)->unique()->values()->toArray();
                                        }

                                        if (!$record?->exists) return $state;

                                        // Get locked dept codes (deduplicated — division rows have same dept_code)
                                        $lockedOffices = $record->complyingOffices()
                                            ->whereIn('status', [0, 1])
                                            ->get()
                                            ->unique('department_code'); // ← deduplicate by dept_code

                                        $lockedCodes = $lockedOffices->pluck('department_code')->toArray();

                                        $removedLocked = array_diff($lockedCodes, $state ?? []);

                                        if (!empty($removedLocked) && !$notified) {
                                            $names = $lockedOffices
                                                ->whereIn('department_code', $removedLocked)
                                                ->map(fn ($office) => $office->office?->office ?? $office->department_code)
                                                ->toArray();

                                            Notification::make()
                                                ->title('Cannot remove some offices')
                                                ->body('Already complied: ' . implode(', ', $names))
                                                ->danger()
                                                ->send();

                                            $notified = true;
                                        }

                                        // Merge and deduplicate before returning
                                        return collect(array_merge($state ?? [], $lockedCodes))
                                            ->unique()
                                            ->values()
                                            ->toArray();
                                    })
                                    ->helperText('Select one or more offices that must comply with this requirement.')
                                    ->suffixActions([
                                        Action::make('selectAll')
                                            ->label('Select All')
                                            ->icon('heroicon-o-check-circle')
                                            ->action(fn (callable $set) =>
                                                $set('complying_offices', Office::pluck('department_code')->toArray())
                                            )
                                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),

                                        Action::make('clearAll')
                                            ->label('Clear')
                                            ->icon('heroicon-o-x-circle')
                                            ->color('danger')
                                            ->action(fn (callable $set) =>
                                                $set('complying_offices', [])
                                            )
                                            ->disabled(fn ($record) => self::isNotRequiringAgency($record)),
                                        ]),

                        Toggle::make('requires_division_tracking')
                            ->label('Track by Division?')
                            ->helperText('Enable if specific divisions within an office must comply separately.')
                            ->reactive()
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record))
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('required_divisions', []);
                                }
                            }),

                        Select::make('required_divisions')
                            ->label('Required Divisions (optional)')
                            ->multiple()
                            ->options(function (Get $get) {
                                $selectedOffices = $get('complying_offices') ?? [];
                                if (empty($selectedOffices)) return [];

                                return DB::connection('mysql2')
                                    ->table('fms.divisions')
                                    ->whereIn('department_code', $selectedOffices)
                                    ->orderBy('division_name1')
                                    ->get()
                                    ->mapWithKeys(fn ($d) => [
                                        $d->department_code . '|' . $d->division_code =>
                                            $d->division_name1 .
                                            (!empty($d->division_short_name) ? ' (' . $d->division_short_name . ')' : '')
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->live()
                            ->visible(fn (Get $get) => $get('requires_division_tracking') && !empty($get('complying_offices')))
                            ->helperText('Select specific divisions. Leave empty to require the whole office.')
                            ->disabled(fn ($record) => self::isNotRequiringAgency($record))
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record?->exists) {
                                    $divisions = $record->requiredDocumentDivisions()
                                        ->get()
                                        ->map(fn ($d) => $d->department_code . '|' . $d->division_code)
                                        ->toArray();
                                    $component->state($divisions);
                                }
                            })
                            ->dehydrateStateUsing(function ($state, $record) {
                                static $notified = false;

                                $user = auth()->user();

                                if ($user?->hasRole('super_admin')) {
                                    return collect($state ?? [])->unique()->values()->toArray();
                                }

                                if (!$record?->exists) {
                                    return $state ?? [];
                                }

                                $lockedDivisions = $record->complyingOffices()
                                    ->whereNotNull('division_code')
                                    ->whereIn('status', [0, 1])
                                    ->get();

                                $lockedKeys = $lockedDivisions
                                    ->map(fn ($co) => "{$co->department_code}|{$co->division_code}")
                                    ->unique()
                                    ->values();

                                $removedLocked = $lockedKeys->diff($state ?? []);

                                if ($removedLocked->isNotEmpty() && !$notified) {
                                    $labels = collect();

                                    foreach ($removedLocked as $entry) {
                                        [$deptCode, $divCode] = explode('|', $entry);

                                        $division = DB::connection('mysql2')
                                            ->table('fms.divisions')
                                            ->where('department_code', $deptCode)
                                            ->where('division_code', $divCode)
                                            ->first();

                                        if ($division) {
                                            $labels->push(
                                                $division->division_name1 .
                                                (!empty($division->division_short_name)
                                                    ? " ({$division->division_short_name})"
                                                    : '')
                                            );
                                        } else {
                                            $labels->push($entry);
                                        }
                                    }

                                    $labels = $labels->implode(', ');

                                    Notification::make()
                                        ->title('Cannot remove some divisions')
                                        ->body('Already complied: ' . $labels)
                                        ->danger()
                                        ->send();

                                    $notified = true;
                                }

                                return collect($state ?? [])
                                    ->merge($lockedKeys)
                                    ->unique()
                                    ->values()
                                    ->toArray();
                            })
                            ->dehydrated(true),


                            // Placeholder::make('saved_divisions_display')
                            //     ->label('Currently Saved Divisions')
                            //     ->content(function ($record) {
                            //         if (!$record?->exists) return 'None';

                            //         $divisions = $record->requiredDocumentDivisions()->get();

                            //         if ($divisions->isEmpty()) return 'None';

                            //         return $divisions->map(function ($d) {
                            //             $division = DB::connection('mysql2')
                            //                 ->table('fms.divisions')
                            //                 ->where('division_code', $d->division_code)
                            //                 ->first();

                            //             $name = $division
                            //                 ? ($division->division_name1 . (!empty($division->division_short_name) ? ' (' . $division->division_short_name . ')' : ''))
                            //                 : $d->division_code;

                            //             return "• {$name}";
                            //         })->implode("\n");
                            //     })
                            //     ->visible(fn ($record) =>
                            //         $record?->exists && $record->requires_division_tracking &&
                            //         $record->requiredDocumentDivisions()->exists()
                            //     )
                            //     ->columnSpanFull(),

                         ])->columnSpanFull(),

            ]);
    }

    private static function isNotRequiringAgency($record): bool
    {
        if (!$record) return false;

        $user = auth()->user();

        // Super admin can always edit
        if ($user->hasRoleSafe('super_admin')) return false;

        $requiringOffice = Office::on('mysql2')
            ->where('office', $record->agency_name)
            ->first();

        return $user->department_code !== optional($requiringOffice)->department_code;
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['_selected_offices'] = collect($data['complying_offices'] ?? [])
            ->unique()
            ->values()
            ->toArray();

        $data['_selected_divisions'] = $data['required_divisions'] ?? [];
        $data['created_by'] = auth()->id();

        if (!isset($data['is_recurring'])) $data['is_recurring'] = false;
        if (!isset($data['recurrence_type'])) $data['recurrence_type'] = null;
        if (!isset($data['recurrence_interval'])) $data['recurrence_interval'] = null;

        unset($data['complying_offices'], $data['divisions'], $data['required_divisions']);

        return $data;
    }

    /**
     * Hook after creating the RequiredDocument
     */
    public static function afterCreate($record, array $data): void
    {
        $selectedOffices = $data['_selected_offices'] ?? [];
        $selectedDivisions = $data['_selected_divisions'] ?? [];
        $status = $data['_status'] ?? -1;

        // ✅ Always one row per office regardless of division tracking
        foreach ($selectedOffices as $deptCode) {
            ComplyingOffice::create([
                'department_code'      => $deptCode,
                'required_document_id' => $record->id,
                'status'               => $status,
            ]);
        }

        // Save divisions pivot for filtering reference
        foreach ($selectedDivisions as $entry) {
            [$deptCode, $divCode] = explode('|', $entry);
            RequiredDocumentDivision::create([
                'required_document_id' => $record->id,
                'department_code'      => $deptCode,
                'division_code'        => $divCode,
            ]);
        }

        if ($record->is_recurring && $record->recurrence_type) {
            \App\Jobs\CreateRecurringDocuments::dispatch(
                $record->fresh(),
                $record->recurrence_type,
                $record->recurrence_interval
            )->afterCommit();
        }
    }


    public static function afterSave($record, array $data): void
    {
        $selected = $data['_selected_offices'] ?? [];
        $selectedDivisions = $data['_selected_divisions'] ?? []; 

        $existing = $record->complyingOffices()->pluck('department_code')->toArray();

        $toAdd = array_diff($selected, $existing);
        foreach ($toAdd as $deptCode) {
            ComplyingOffice::create([
                'department_code'      => $deptCode,
                'required_document_id' => $record->id,
                'status'               => -1,
                'due_date'             => $record->due_date,
            ]);
        }

        $toRemove = array_diff($existing, $selected);
        foreach ($toRemove as $deptCode) {
            $record->complyingOffices()
                ->where('department_code', $deptCode)
                ->first()
                ?->delete();
        }

        // 👇 add this block — sync divisions
        $record->requiredDocumentDivisions()->delete();
        foreach ($selectedDivisions as $entry) {
            [$deptCode, $divCode] = explode('|', $entry);
            RequiredDocumentDivision::create([
                'required_document_id' => $record->id,
                'department_code'      => $deptCode,
                'division_code'        => $divCode,
            ]);
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
