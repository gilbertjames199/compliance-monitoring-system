<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\Pis\Division;
use App\Models\RequiredDocumentDivision;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('forms.components.sticky-category')
                    ->schema([
                        Section::make('Category Information')
                            ->schema([
                                TextInput::make('category')
                                    ->label('Category')
                                    ->required()
                            ])
                    ])
                    ->columnSpanFull(),

                Repeater::make('requiredDocuments')
                    ->label('Required Documents')
                    ->relationship('requiredDocuments')
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
                                ->default(date('Y'))
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
                                ->disabled(function ($record) {
                                        if (!$record) return false;

                                        $user = auth()->user();

                                        if ($user->hasRoleSafe('super_admin')) {
                                            return false;
                                        }

                                        $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                            ->value('department_code');

                                        return $user->department_code !== $agencyDepartmentCode;
                                    }),

                            DatePicker::make('date_from')
                                ->label('Start Date')
                                ->required()
                                ->live()
                                ->locale('en-US')
                                ->native(false)
                                ->displayFormat('m/d/Y')
                                ->placeholder('mm/dd/yyyy')
                                ->afterStateUpdated(function (Set $set) {
                                    $set('due_date', null);
                                })
                                ->disabled(function ($record) {
                                    if (!$record) return false;

                                    $user = auth()->user();

                                    if ($user->hasRoleSafe('super_admin')) {
                                        return false;
                                    }

                                    $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                        ->value('department_code');

                                    if ($user->department_code !== $agencyDepartmentCode) {
                                        return true;
                                    }

                                    return $record->complyingOffices()
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
                                ->locale('en-US')
                                ->native(false)
                                ->displayFormat('m/d/Y')
                                ->placeholder('mm/dd/yyyy')
                                ->afterOrEqual('date_from')
                                ->minDate(fn (Get $get) => $get('date_from'))
                                ->disabled(function ($record) {
                                    if (!$record) return false;

                                    $user = auth()->user();

                                    if ($user->hasRoleSafe('super_admin')) {
                                        return false;
                                    }

                                    $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                        ->value('department_code');

                                    if ($user->department_code !== $agencyDepartmentCode) {
                                        return true;
                                    }

                                    return $record->complyingOffices()
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

                            Toggle::make('is_confidential')
                                ->label('Confidential'),

                            Grid::make(1)
                                ->schema([
                                    Toggle::make('is_recurring')
                                        ->label('Recurring?')
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if (!$state) {
                                                $set('recurrence_type', null);
                                                $set('recurrence_interval', null);
                                            }
                                        })
                                        ->disabled(function ($record) {
                                            if (!$record) return false;

                                            $user = auth()->user();

                                            if ($user->hasRoleSafe('super_admin')) {
                                                return false;
                                            }

                                            $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                                ->value('department_code');

                                            return $user->department_code !== $agencyDepartmentCode;
                                        }),

                                    Grid::make(2)
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
                                                    if ($state !== 'custom') {
                                                        $set('recurrence_interval', null);
                                                    }
                                                })
                                                ->dehydrated(true)
                                                ->dehydrateStateUsing(fn($state, $get) => $get('is_recurring') ? $state : null)
                                                ->disabled(function ($record) {
                                                    if (!$record) return false;

                                                    $user = auth()->user();

                                                    if ($user->hasRoleSafe('super_admin')) {
                                                        return false;
                                                    }

                                                    $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                                        ->value('department_code');

                                                    return $user->department_code !== $agencyDepartmentCode;
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
                                return true;
                            }

                            $user = auth()->user();

                            if ($user->hasRoleSafe('super_admin')) {
                                return true;
                            }

                            $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                ->value('department_code');

                            return $user->department_code === $agencyDepartmentCode;
                        }),

                    Section::make('Complying Offices')
                        ->schema([
                            Select::make('complying_offices')
                                ->label('Complying Offices')
                                ->multiple()
                                ->required()
                                ->options(
                                    Office::orderBy('office')
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
                                ->live()
                                ->disabled(function ($record) {
                                    if (!$record) return false;

                                    $user = auth()->user();
                                    if ($user->hasRoleSafe('super_admin')) return false;

                                    $agencyDepartmentCode = Office::where('office', $record->agency_name)
                                        ->value('department_code');

                                    return $user->department_code !== $agencyDepartmentCode;
                                })
                                ->loadStateFromRelationshipsUsing(fn ($component, $record) =>
                                    $component->state(
                                        $record?->complyingOffices
                                            ->pluck('department_code')
                                            ->toBase()
                                            ->unique()
                                            ->toArray() ?? []
                                    )
                                )
                                ->saveRelationshipsUsing(function ($component, $record, $state) {
                                    if ($record->requires_division_tracking) {
                                        return;
                                    }

                                    $state = collect($state ?? [])->unique()->values();

                                    $existing = $record->complyingOffices()->whereNull('division_code')->get();
                                    $existingCodes = $existing->pluck('department_code')->toBase();
                                    $lockedCodes = $existing->whereIn('status', [0, 1])->pluck('department_code')->toBase();

                                    $removedLocked = $lockedCodes->diff($state);

                                    if ($removedLocked->isNotEmpty()) {
                                        $names = Office::whereIn('department_code', $removedLocked)
                                            ->pluck('office')
                                            ->implode(', ');

                                        Notification::make()
                                            ->title('Cannot remove some complying offices')
                                            ->body('Already complying/complied: ' . $names)
                                            ->danger()
                                            ->send();

                                        $state = $state->merge($lockedCodes)->unique()->values();
                                    }

                                    $toAdd = $state->diff($existingCodes);
                                    $toRemove = $existingCodes->diff($state);

                                    if ($toRemove->isNotEmpty()) {
                                        $record->complyingOffices()
                                            ->whereNull('division_code')
                                            ->whereIn('department_code', $toRemove)
                                            ->delete();
                                    }

                                    foreach ($toAdd as $departmentCode) {
                                        ComplyingOffice::create([
                                            'required_document_id' => $record->id,
                                            'department_code'      => $departmentCode,
                                            'status'                => -1,
                                        ]);
                                    }
                                })
                                ->dehydrateStateUsing(function ($state, $record) {
                                    $user = auth()->user();

                                    if ($user?->hasRole('super_admin')) {
                                        return collect($state)->unique()->values()->toArray();
                                    }

                                    if (!$record?->exists) return $state;

                                    $lockedOffices = $record->complyingOffices()->whereIn('status', [0, 1])->get();
                                    $lockedCodes = $lockedOffices->pluck('department_code')->unique()->values()->toArray();
                                    $removedLocked = array_diff($lockedCodes, $state ?? []);

                                    if (!empty($removedLocked)) {
                                        $removedRows = $lockedOffices->whereIn('department_code', $removedLocked);
                                        $divisionRows = $removedRows->whereNotNull('division_code');
                                        $officeRows   = $removedRows->whereNull('division_code');

                                        $labels = collect();

                                        foreach ($divisionRows as $row) {
                                            $division = DB::connection('mysql2')
                                                ->table('fms.divisions')
                                                ->where('department_code', $row->department_code)
                                                ->where('division_code', $row->division_code)
                                                ->first();

                                            $labels->push($division
                                                ? $division->division_name1 . (!empty($division->division_short_name) ? " ({$division->division_short_name})" : '')
                                                : "{$row->department_code}|{$row->division_code}");
                                        }

                                        foreach ($officeRows as $row) {
                                            $labels->push($row->office?->office ?? $row->department_code);
                                        }

                                        Notification::make()
                                            ->title($divisionRows->isNotEmpty() ? 'Cannot remove some divisions' : 'Cannot remove some offices')
                                            ->body('Already complied: ' . $labels->unique()->implode(', '))
                                            ->danger()
                                            ->send();
                                    }

                                    return collect(array_merge($state ?? [], $lockedCodes))->unique()->values()->toArray();
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
                                ]),

                            Toggle::make('requires_division_tracking')
                                ->label('Track by Division?')
                                ->reactive()
                                ->afterStateUpdated(function ($state, $set) {
                                    if (!$state) {
                                        $set('required_divisions', []);
                                    }
                                })
                                ->disabled(function ($record) {
                                    if (!$record) return false;

                                    $user = auth()->user();
                                    if ($user->hasRoleSafe('super_admin')) return false;

                                    $agencyDepartmentCode = Office::where('office', $record->agency_name)
                                        ->value('department_code');

                                    if ($user->department_code !== $agencyDepartmentCode) {
                                        return true;
                                    }

                                    // Lock the toggle if any division has already started complying
                                    return $record->complyingOffices()
                                        ->whereNotNull('division_code')
                                        ->whereIn('status', [0, 1])
                                        ->exists();
                                })
                                ->helperText(function ($record) {
                                    if (!$record) return null;

                                    if ($record->complyingOffices()
                                        ->whereNotNull('division_code')
                                        ->whereIn('status', [0, 1])
                                        ->exists()) {
                                        return 'This cannot be disabled because one or more divisions have already started complying.';
                                    }

                                    return null;
                                }),

                            Select::make('required_divisions')
                                ->label('Required Divisions (optional)')
                                ->multiple()
                                ->live()
                                ->options(function (Get $get) {
                                    $departmentCodes = $get('complying_offices') ?? [];

                                    if (empty($departmentCodes)) {
                                        return [];
                                    }

                                    return DB::connection('mysql2')
                                        ->table('fms.divisions')
                                        ->whereIn('department_code', $departmentCodes)
                                        ->orderBy('division_name1')
                                        ->get()
                                        ->mapWithKeys(function ($d) {
                                            $label = $d->division_name1
                                                . (!empty($d->division_short_name) ? ' (' . $d->division_short_name . ')' : '');

                                            return ["{$d->department_code}|{$d->division_code}" => $label];
                                        })
                                        ->toArray();
                                })
                                ->visible(fn (Get $get) => (bool) $get('requires_division_tracking'))
                                ->required(fn (Get $get) => (bool) $get('requires_division_tracking'))
                                ->helperText('Select specific divisions that must comply separately.')
                                ->dehydrated(false)
                                ->loadStateFromRelationshipsUsing(function ($component, $record) {
                                    if (!$record) {
                                        return;
                                    }

                                    $component->state(
                                        $record->requiredDocumentDivisions()
                                            ->get()
                                            ->map(fn ($d) => "{$d->department_code}|{$d->division_code}")
                                            ->toArray()
                                    );
                                })
                                ->saveRelationshipsUsing(function ($component, $record, $state) {
                                    $state = collect($state ?? [])->unique()->values();

                                    $existingDivisions = $record->requiredDocumentDivisions()->get();
                                    $existingKeys = $existingDivisions
                                        ->map(fn ($d) => "{$d->department_code}|{$d->division_code}")
                                        ->toBase();

                                    $lockedKeys = $record->complyingOffices()
                                        ->whereNotNull('division_code')
                                        ->whereIn('status', [0, 1])
                                        ->get()
                                        ->map(fn ($co) => "{$co->department_code}|{$co->division_code}")
                                        ->toBase();

                                    // Guard: can't turn OFF division tracking while any division is locked
                                    if (!$record->requires_division_tracking && $lockedKeys->isNotEmpty()) {
                                        $names = $record->complyingOffices()
                                            ->whereNotNull('division_code')
                                            ->whereIn('status', [0, 1])
                                            ->get()
                                            ->map(function ($office) {
                                                return DB::connection('mysql2')
                                                    ->table('fms.divisions')
                                                    ->where('department_code', $office->department_code)
                                                    ->where('division_code', $office->division_code)
                                                    ->value('division_name1')
                                                    ?? "{$office->department_code}|{$office->division_code}";
                                            })
                                            ->unique()
                                            ->implode(', ');

                                        Notification::make()
                                            ->title('Cannot disable division tracking')
                                            ->body('These divisions have already started complying: ' . $names)
                                            ->danger()
                                            ->send();

                                        // Force the toggle back on since compliance already exists per-division
                                        $record->requires_division_tracking = true;
                                        $record->saveQuietly();

                                        return;
                                    }

                                    $removedLocked = $lockedKeys->diff($state);

                                    if ($removedLocked->isNotEmpty()) {

                                        $names = $record->complyingOffices()
                                            ->whereNotNull('division_code')
                                            ->whereIn('status', [0, 1])
                                            ->where(function ($query) use ($removedLocked) {
                                                foreach ($removedLocked as $key) {
                                                    [$deptCode, $divCode] = explode('|', $key);

                                                    $query->orWhere(function ($q) use ($deptCode, $divCode) {
                                                        $q->where('department_code', $deptCode)
                                                            ->where('division_code', $divCode);
                                                    });
                                                }
                                            })
                                            ->get()
                                            ->map(function ($office) {
                                                return DB::connection('mysql2')
                                                    ->table('fms.divisions')
                                                    ->where('department_code', $office->department_code)
                                                    ->where('division_code', $office->division_code)
                                                    ->value('division_name1')
                                                    ?? "{$office->department_code}|{$office->division_code}";
                                            })
                                            ->implode(', ');

                                        Notification::make()
                                            ->title('Cannot remove some divisions')
                                            ->body('Already complied: ' . $names)
                                            ->danger()
                                            ->send();

                                        $state = $state->merge($lockedKeys)->unique()->values();
                                    }

                                    if (!$record->requires_division_tracking) {
                                        $record->requiredDocumentDivisions()->delete();
                                        $record->complyingOffices()->whereNotNull('division_code')->delete();
                                        return;
                                    }

                                    $toAdd = $state->diff($existingKeys);
                                    $toRemove = $existingKeys->diff($state);

                                    foreach ($toRemove as $entry) {
                                        [$deptCode, $divCode] = explode('|', $entry);

                                        RequiredDocumentDivision::where('required_document_id', $record->id)
                                            ->where('department_code', $deptCode)
                                            ->where('division_code', $divCode)
                                            ->delete();

                                        ComplyingOffice::where('required_document_id', $record->id)
                                            ->where('department_code', $deptCode)
                                            ->where('division_code', $divCode)
                                            ->delete();
                                    }

                                    foreach ($toAdd as $entry) {
                                        [$deptCode, $divCode] = explode('|', $entry);

                                        RequiredDocumentDivision::create([
                                            'required_document_id' => $record->id,
                                            'department_code'      => $deptCode,
                                            'division_code'        => $divCode,
                                        ]);

                                        ComplyingOffice::firstOrCreate(
                                            [
                                                'required_document_id' => $record->id,
                                                'department_code'      => $deptCode,
                                                'division_code'        => $divCode,
                                            ],
                                            ['status' => -1]
                                        );
                                    }
                                })
                                ->columnSpanFull(),
                        ])
                        ->visible(function ($record) {
                            if (!$record) {
                                return true;
                            }

                            $user = auth()->user();
                            if ($user->hasRoleSafe('super_admin')) {
                                return true;
                            }

                            $agencyDepartmentCode = Office::where('office', $record->agency_name)
                                ->value('department_code');

                            return $user->department_code === $agencyDepartmentCode;
                        }),
                            ])
                ]);
    }
}