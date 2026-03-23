<?php

namespace App\Filament\Resources\RequiredDocuments\Tables;

use App\Mail\RequirementDeadlineMail;
use App\Models\ComplyingOffice;
use App\Models\DocumentCategory;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\View as LayoutView;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class RequiredDocumentsTable 
{
    protected static ?string $model = RequiredDocument::class;

    public static function configure(Table $table): Table
    {
        // dd(auth()->user());
            
        return $table
             ->modifyQueryUsing(function (Builder $query) {

                $user = auth()->user();
                if (! $user) {
                    return;
                }

                // Superadmin sees everything
                if ($user->hasRoleSafe('super_admin')) {
                    return;
                }

                if ($user->department_code == 25 && $user->hasRoleSafe('department_head')) {
                    return;
                }

                // Only show records where the requiring agency matches the user's office name
                // $query->where('agency_name', $user->office->office);

                $officeName = Office::on('mysql2') // if cross-database
                    ->where('department_code', $user->department_code)
                    ->value('office'); // just get the office name as string

                $query->where('agency_name', $officeName);

                // Optionally hide confidential requirements from AO & Admin
                if ($user->hasRoleSafe('AO', 'admin')) {
                    $query->where('is_confidential', false);
                }
            })
            ->defaultGroup('agency_name')
            ->columns([
                TextColumn::make('requirement')
                    ->searchable()
                    ->wrap()
                    ->limit(100)
                    ->searchable()
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column contents exceeds the length limit.
                        return $state;
                    }),
                TextColumn::make('agency_name')
                    ->label('Requiring Agency')
                    ->searchable()
                    ->wrap(),
                IconColumn::make('is_confidential')
                    ->label('Confidential')
                    ->boolean()
                    ->sortable()
                    ->searchable()
                    ->trueColor('warning')   // yellow
                    ->falseColor('gray')     // dark / black-ish
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),
                TextColumn::make('year')
                    ->searchable(),
                TextColumn::make('category.category')
                    ->label('Category')
                    ->searchable()
                    ->wrap()
                    ->limit(100),
                TextColumn::make('date_from')
                    ->label('Start Date')
                    ->date()
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Deadline')
                    ->date()
                    ->searchable(),
                TextColumn::make('compliance_count')
                    ->label('Complied Count')
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        $total = $record->complyingOffices()->count();
                        $complied = $record->complyingOffices()
                            ->where('status', 1)
                            ->count();

                        return "{$complied} / {$total}";
                    })
                    ->badge()
                    ->color(function (string $state) {
                        [$done, $total] = array_map('intval', explode(' / ', $state));

                        return ($total > 0 && $done === $total)
                            ? 'success'   // all completed
                            : 'danger';   // not completed
                    }),

                TextColumn::make('validation_count')
                    ->label('Validated Count')
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        $total = $record->complyingOffices()->count();
                        $validated = $record->complyingOffices()
                            ->where('validation_status', 'validated')
                            ->count();

                        return "{$validated} / {$total}";
                    })
                    ->badge()
                    ->color(function (string $state) {
                        [$validated, $total] = array_map('intval', explode(' / ', $state));

                        return ($total > 0 && $validated === $total)
                            ? 'success'
                            : 'warning';
                    }),


            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('is_confidential')
                    ->label('Confidential')
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['is_confidential'])) {
                            $query->where('is_confidential', $data['is_confidential']);
                        }
                    })
                    ->form([
                        Select::make('is_confidential')
                            ->label('Confidential')
                            ->options([
                                1 => 'Yes',
                                0 => 'No',
                            ])
                            ->placeholder('Select...'),
                    ]),

                Filter::make('year')
                    ->label('Year')
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['year'])) {
                            $query->where('year', $data['year']);
                        }
                    })
                    ->form([
                        Select::make('year')
                            ->label('Year')
                            ->options(
                                RequiredDocument::query()
                                    ->select('year')
                                    ->distinct()
                                    ->orderBy('year', 'desc')
                                    ->pluck('year', 'year')
                                    ->toArray()
                            )
                            ->placeholder('Select Year'),
                    ]),

                Filter::make('category')
                    ->label('Category')
                    ->query(function (Builder $query, array $data) {
                        if (!empty($data['category'])) {
                            // Use whereHas to filter by related category
                            $query->whereHas('category', function (Builder $q) use ($data) {
                                $q->where('id', $data['category']);
                            });
                        }
                    })
                    ->form([
                        Select::make('category')
                            ->label('Category')
                            ->options(
                                DocumentCategory::query()
                                    ->orderBy('category')
                                    ->pluck('category', 'id')
                                    ->toArray()
                            )
                            ->placeholder('Select Category'),
                    ]),

            ])

            ->recordActions([
                Action::make('Print')
                    ->label('Print Summary Details')
                    ->icon('heroicon-o-printer')
                    ->color('primary')
                    ->modalContent(fn($record) => view('filament.print', [
                        'requirement_id' => $record->id,
                    ]))
                    ->slideOver(),
                EditAction::make(),

                
                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();

                        if ($user->hasRoleSafe('super_admin')) {
                            return true;
                        }

                        if ($user->hasRoleSafe('department_head')) {
                            $officeName = Office::on('mysql2')
                                ->where('department_code', $user->department_code)
                                ->value('office');

                            $hasCompliance = $record->complyingOffices()
                                ->whereIn('status', [0, 1])
                                ->exists();

                            return $record->agency_name === $officeName && !$hasCompliance;
                        }

                        return false;
                    }),

                Action::make('manage_compliance')
                    ->label('Validate Submissions')
                    ->icon('heroicon-o-document-check')
                    ->color('danger')
                    ->modalHeading(fn($record) => "Complying Offices for '{$record->requirement}'")
                    ->modalSubmitActionLabel('Save Changes')
                    ->modalWidth('7xl')
                    ->form(function ($record) {
                        $offices = $record->complyingOffices()->get()->map(function ($complying) {
                            $office = Office::on('mysql2')
                                ->where('department_code', $complying->department_code)
                                ->first();

                            $complying->office_name = $office?->office ?? 'N/A';
                            return $complying;
                        });

                        if ($offices->isEmpty()) {
                            return [
                                Placeholder::make('no_compliance')
                                    ->content('No complying offices found.')
                            ];
                        }

                        $fields = [];

                        foreach ($offices as $office) {
                            $borderClass = match ((int) $office->status) {
                                -1 => 'border-l-4 border-danger-500',
                                0  => 'border-l-4 border-warning-500',
                                1  => 'border-l-4 border-success-500',
                                default => 'border-l-4 border-gray-300',
                            };

                            $fields[] = Section::make($office->office_name)
                                ->description('Submitted documents and validation status')
                                ->collapsible()
                                // ✅ Auto-collapse if already validated
                                ->collapsed($office->validation_status === 'validated')
                                ->extraAttributes(['class' => "pl-4 {$borderClass}" ])
                                ->schema([
                                    Grid::make(14)->schema([
                                    // 🔹 Office Name (read-only)
                                    TextInput::make("office_{$office->id}_name")
                                        ->label('Office Name')
                                        ->default($office->office_name)
                                        ->disabled()
                                        ->columnSpan(3)
                                        ->hidden(),

                                    // 🔹 Status (read-only)
                                    Select::make("office_{$office->id}_status")
                                        ->label('Compliance Status')
                                        ->options([
                                            -1 => 'Not Complied',
                                            0  => 'Partially Complied',
                                            1  => 'Complied',
                                        ])
                                        ->default($office->status)
                                        ->disabled()
                                        ->native(false)
                                        ->reactive()
                                        ->columnSpan(2),

                                    // 🔹 File Upload / Attachments (read-only)
                                    Placeholder::make("office_{$office->id}_attachments")
                                        ->label('Submitted Attachments')
                                        ->content(function () use ($office) {
                                            if (!$office->attachments) {
                                                return 'No files submitted.';
                                            }

                                            $attachments = is_array($office->attachments)
                                                ? $office->attachments
                                                : json_decode($office->attachments, true);

                                            return collect($attachments)
                                                ->map(fn ($file) =>
                                                    "<a href='".Storage::disk('public')->url($file)."' 
                                                        target='_blank' 
                                                        style='color: #2563eb; text-decoration: underline;'
                                                        onmouseover='this.style.color=\"#1d4ed8\"' 
                                                        onmouseout='this.style.color=\"#2563eb\"'>"
                                                    .basename($file)."</a>"
                                                )
                                                ->implode('<br>');
                                        })
                                        ->html()
                                        ->columnSpan(3),



                                    // 🔹 Validation Status (editable)
                                    Select::make("office_{$office->id}_validation_status")
                                        ->label('Validation Status')
                                        // ->inline()
                                        ->options([
                                            'pending_review' => 'Pending Review',
                                            'returned'       => 'Returned',
                                            'validated'      => 'Validated',
                                        ])
                                        // ->colors([
                                        //     'pending_review' => 'warning',
                                        //     'returned'       => 'danger',
                                        //     'validated'      => 'success',
                                        // ])
                                        ->default($office->validation_status ?? 'pending_review')
                                        ->required()
                                        ->reactive()
                                        ->dehydrated()
                                        ->disabled(function ($get, $record) use ($office) {
                                                $user = auth()->user();

                                                // Superadmin can always edit (but still requires complied status)
                                                $isComplied = $get("office_{$office->id}_status") == 1;

                                                if ($user->hasRoleSafe('super_admin')) {
                                                    return !$isComplied;
                                                }

                                                if (!$isComplied) {
                                                    return true;
                                                }

                                                // Match user's department_code with the agency's department_code
                                                $agencyDepartmentCode = \App\Models\Office::where('office', $record?->agency_name)
                                                    ->value('department_code');

                                                $isRequiringAgency = $user->department_code === $agencyDepartmentCode;

                                                return !$isRequiringAgency;
                                            })
                                            ->helperText(function ($get, $record) use ($office) {
                                                $user = auth()->user();
                                                $isComplied = $get("office_{$office->id}_status") == 1;

                                                if ($user->hasRoleSafe('super_admin')) {
                                                    return $isComplied ? 'Review and validate the submitted documents.' : 'Validation is only available when the compliance status is "Complied".';
                                                }

                                                $agencyDepartmentCode = \App\Models\Office::where('office', $record?->agency_name)
                                                    ->value('department_code');

                                                $isRequiringAgency = $user->department_code === $agencyDepartmentCode;

                                                if (!$isRequiringAgency) {
                                                    return 'Only the requiring agency (' . ($record?->agency_name ?? 'N/A') . ') can validate submissions.';
                                                }

                                                if (!$isComplied) {
                                                    return 'Validation is only available when the compliance status is "Complied".';
                                                }

                                                return 'Review and validate the submitted documents.';
                                            })
                                        ->afterStateUpdated(function ($state, $set) use ($office) {
                                        if (in_array($state, ['validated', 'returned'])) {
                                            $set("office_{$office->id}_validated_by", auth()->user()->name);
                                            $set("office_{$office->id}_validated_at", now());
                                        } else {
                                            $set("office_{$office->id}_validated_by", null);
                                            $set("office_{$office->id}_validated_at", null);
                                        }
                                        })
                                        ->columnSpan(2),
                                    
                                    // 🔹 Admin Remarks (YOUR LOGIC – intact)
                                    Textarea::make("office_{$office->id}_admin_remarks")
                                        ->label('Remarks')
                                        ->rows(2)
                                        ->default($office->admin_remarks) // ✅ LOAD EXISTING
                                        ->reactive()
                                        ->nullable()
                                        ->columnSpan(3)
                                        // ✅ REQUIRED when Returned or Validated
                                        ->required(fn ($get) =>
                                            in_array(
                                                $get("office_{$office->id}_validation_status"),
                                                ['returned', 'validated']
                                            )
                                        )
                                        ->dehydrated(true)
                                        ->disabled(function ($record) use ($office) {
                                            if (!$record) {
                                                return true;
                                            }

                                            $user = auth()->user();
                                            $isComplied = (int) $office->status === 1;

                                            // Superadmin can always edit (but still requires complied status)
                                            if ($user->hasRoleSafe('super_admin')) {
                                                return !$isComplied;
                                            }

                                            $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                                ->value('department_code');

                                            $isRequiringAgency = $user->department_code === $agencyDepartmentCode;

                                            return !($isRequiringAgency && $isComplied);
                                        })
                                        ->helperText(function ($record) use ($office) {
                                            if (!$record) {
                                                return '';
                                            }

                                            $user = auth()->user();
                                            $isComplied = (int) $office->status === 1;

                                            if ($user->hasRoleSafe('super_admin')) {
                                                return $isComplied ? 'Add validation remarks for this submission.' : 'Remarks can only be added when the status is "Complied".';
                                            }

                                            $agencyDepartmentCode = \App\Models\Office::where('office', $record->agency_name)
                                                ->value('department_code');

                                            $isRequiringAgency = $user->department_code === $agencyDepartmentCode;

                                            if (!$isRequiringAgency) {
                                                return 'Only the requiring agency can add remarks.';
                                            }

                                            if (!$isComplied) {
                                                return 'Remarks can only be added when the status is "Complied".';
                                            }

                                            return 'Add validation remarks for this submission.';
                                        }),


                                    TextInput::make("office_{$office->id}_validated_by")
                                        ->label(fn ($get) => match ($get("office_{$office->id}_validation_status")) {
                                            'validated' => 'Validated By',
                                            'returned'  => 'Returned By',
                                            default     => '',
                                        })
                                        ->default($office->validated_by)
                                        ->disabled()
                                        ->dehydrated()
                                        ->columnSpan(2),


                                    DateTimePicker::make("office_{$office->id}_validated_at")
                                        ->label(fn ($get) => match ($get("office_{$office->id}_validation_status")) {
                                            'validated' => 'Validated At',
                                            'returned'  => 'Returned At',
                                            default     => '',
                                        })
                                        ->default($office->validated_at)
                                        ->disabled()
                                        ->dehydrated()
                                        ->displayFormat('m/d/Y h:i A')
                                        ->seconds(false)
                                        ->columnSpan(2),
                                ]),
                            ]);
                        }

                        return $fields;
                    })
                    ->action(function ($record, array $data) {
                        foreach ($record->complyingOffices as $office) {
                            $statusKey  = "office_{$office->id}_validation_status";
                            $remarksKey = "office_{$office->id}_admin_remarks";
                            $byKey      = "office_{$office->id}_validated_by";
                            $atKey      = "office_{$office->id}_validated_at";

                            // ✅ Update validation fields IF PRESENT
                            if (array_key_exists($statusKey, $data)) {
                                $office->validation_status = $data[$statusKey];
                                $office->validated_by      = $data[$byKey] ?? null;
                                $office->validated_at      = $data[$atKey] ?? null;

                                // ✅ Update compliance status based on validation status
                                if ($data[$statusKey] === 'returned') {
                                    $office->status = 0; // Partially Complied
                                } elseif ($data[$statusKey] === 'validated') {
                                    $office->status = 1; // Fully Complied
                                }
                            }

                            // ✅ ALWAYS allow remarks to update if present
                            if (array_key_exists($remarksKey, $data)) {
                                $office->admin_remarks = $data[$remarksKey];
                            }

                            // ✅ Save once
                            $office->save();

                        }

                    Notification::make()
                        ->title('Validation updated successfully')
                        ->success()
                        ->send();
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(function () {
                            $user = auth()->user();
                            return $user->hasRoleSafe('super_admin') || $user->hasRoleSafe('department_head');
                        })
                        ->before(function (DeleteBulkAction $action, $records) {
                            $user = auth()->user();

                            // Super admin can always delete
                            if ($user->hasRoleSafe('super_admin')) {
                                return;
                            }

                            if ($user->hasRoleSafe('department_head')) {
                                $officeName = Office::on('mysql2')
                                    ->where('department_code', $user->department_code)
                                    ->value('office');

                                $hasRestricted = $records->contains(function ($record) use ($officeName) {
                                    // Block if not their agency
                                    if ($record->agency_name !== $officeName) {
                                        return true;
                                    }

                                    // Block if any complying office has already submitted
                                    return $record->complyingOffices()
                                        ->whereIn('status', [0, 1])
                                        ->exists();
                                });

                                if ($hasRestricted) {
                                    Notification::make()
                                        ->title('Deletion Not Allowed')
                                        ->body('One or more selected requirements cannot be deleted because offices have already submitted compliance.')
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    $action->cancel();
                                }
                            }
                        })
                        // ->authorize(function ($records) {
                        //     $user = auth()->user();

                        //     if ($user->hasRoleSafe('super_admin')) {
                        //         return true;
                        //     }

                        //     if ($user->hasRoleSafe('department_head')) {
                        //         $officeName = Office::on('mysql2')
                        //             ->where('department_code', $user->department_code)
                        //             ->value('office');

                        //         return $records->every(function ($record) use ($officeName) {
                        //             $hasCompliance = $record->complyingOffices()
                        //                 ->whereIn('status', [0, 1])
                        //                 ->exists();

                        //             return $record->agency_name === $officeName && !$hasCompliance;
                        //         });
                        //     }

                        //     return false;
                        // }),
                ]),
            ]);
    }
    
    public static function getTableQuery(): Builder
    {
        // $query = parent::getTableQuery();

        $user = auth()->user();
        $departmentCode = $user->department_code ?? null;
        // dd($user);
        if ($departmentCode == 25) {
            return $query; // show all
        }

        return $query->whereHas('complyingOffices', function ($q) use ($departmentCode) {
            $q->where('department_code', $departmentCode);
        });
    }
}
