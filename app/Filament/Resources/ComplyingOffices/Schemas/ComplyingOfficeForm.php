<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use Carbon\Carbon;
use App\Models\Office;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\DateTimePicker;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = auth()->user()->hasAnyRole(['super_admin']);

        return $schema
            ->components([
                 // SECTION 1: REQUIREMENT INFORMATION
                Section::make('Requirement Information')
                    ->schema([
                        Select::make('required_document_id')
                            ->label('Requirement')
                            ->options(RequiredDocument::orderBy('requirement')->pluck('requirement', 'id'))
                            ->reactive()
                            ->disabled()
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $requirement = RequiredDocument::find($state);
                                if ($requirement) {
                                    $set('agency_name', $requirement->agency_name);
                                } else {
                                    $set('agency_name', null);
                                }
                            }),
                        Select::make('department_code')
                            ->label('Complying Office')
                            ->disabled()
                            ->options(Office::all()->pluck('office', 'department_code')),
                        
                       

                        TextInput::make('agency_name')
                            ->label('Requiring Agency')
                            ->disabled()
                            ->afterStateHydrated(function ($set, $record) {
                                if ($record && $record->required_document_id) {
                                    $requirement = RequiredDocument::find($record->required_document_id);
                                    if ($requirement) {
                                        $set('agency_name', $requirement->agency_name);
                                    }
                                }
                            }),

                        TextInput::make('date_from')
                            ->label('Date From')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                return $record?->requiredDocument?->date_from
                                    ? \Carbon\Carbon::parse($record->requiredDocument->date_from)->format('F d, Y')
                                    : '-';
                            }),

                        TextInput::make('due_date')
                            ->label('Due Date')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                return $record?->requiredDocument?->due_date
                                    ? \Carbon\Carbon::parse($record->requiredDocument->due_date)->format('F d, Y')
                                    : '-';
                            }),

                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                    

                // SECTION 2: ADMIN REVIEW & REMARKS
                Section::make('Requiring Agency Feedback')
                    ->description(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                        
                       if ($isAdmin && $isOwnOffice) {
                            return 'Requiring agency feedback (You cannot review your own office submission)';
                        } elseif ($isAdmin) {
                            return 'Review the submission and provide feedback';
                        } else {
                            return 'Requiring agency feedback on your submission';
                        }
                    })
                    ->schema([

                        ToggleButtons::make('status')
                            ->label('Compliance Status')
                           
                            ->inline()
                            ->options([
                                -1 => 'Not Complied',
                                0  => 'Partially Complied',
                                1  => 'Complied',
                            ])
                            ->colors([
                            '-1' => 'danger',
                            '0' => 'warning',
                            '1' => 'success',
                                        ])
                            ->default(-1)
                            ->reactive() // Add reactive to monitor changes
                            ->helperText(function ($get, $record) {
                                $currentStatus = $get('status') ?? $record?->status;
                                $validationStatus = $get('validation_status') ?? $record?->validation_status;
                                
                                if ($currentStatus == 1 && $validationStatus !== 'returned') {
                                    return '⚠️ Document management is locked. Uploads and removals are disabled until validation status is returned.';
                                }
                                
                                if ($currentStatus != 1) {
                                    return '💡 Once marked as "Complied", document uploads/removals will be locked until validation is returned.';
                                }
                                
                                return null;
                            })
                            ->hint(function ($get, $record) {
                                $currentStatus = $get('status') ?? $record?->status;
                                $validationStatus = $get('validation_status') ?? $record?->validation_status;
                                
                                if ($currentStatus == 1 && $validationStatus !== 'returned') {
                                    return 'Locked';
                                }
                                
                                return null;
                            })
                            ->hintIcon('heroicon-m-lock-closed')
                            ->hintColor('danger') // or 'warning' for orange color
                            ->disabled(function ($record, $get) {
                                $user = auth()->user();
                                
                                // Get validation status
                                $validationStatus = $get('validation_status') ?? $record?->validation_status;
                                
                                // If validated, lock the status for everyone
                                if ($validationStatus === 'validated') {
                                    return true;
                                }
                                
                                // If status is already "Complied" (1) and not returned, disable for everyone
                                if ($record && $record->status == 1 && $validationStatus !== 'returned') {
                                    return true;
                                }

                                // AO cannot change status at all
                                if ($user->hasRole('AO')) {
                                    return true;
                                }
                                
                                // Department Head can change when status = 0 or partially complied
                                if ($user->hasRole('department_head')) {
                                    $allowedStatuses = [0, 1];
                                    return !in_array($record->status, $allowedStatuses);
                                }
                                
                                // Super Admin → can edit only their own office and status = 0
                                if ($user->hasRole('super_admin')) {
                                    $isOwnOffice = $record->department_code === $user->department_code;
                                    $statusIsZero = $record->status == 0;
                                    return !($isOwnOffice && $statusIsZero);
                                }
                                
                                // For any other roles, disable by default
                                return true;
                            })
                            ->dehydrated()
                            ->columnSpanFull(),


                        ToggleButtons::make('validation_status')
                            ->label('Validation Status (Requiring Agency)')
                            ->inline()
                            ->options([
                                'pending_review' => 'Pending Review',
                                'returned'       => 'Returned',
                                'validated'      => 'Validated',
                            ])
                            ->colors([
                                'pending_review' => 'warning',
                                'returned'       => 'danger',
                                'validated'      => 'success',
                            ])
                            ->default(fn ($record) => $record?->validation_status ?? 'pending_review')
                            ->required()
                            ->reactive() // Make it reactive to trigger afterStateUpdated
                            ->disabled(function ($record) {
                                if (!$record) {
                                    return true;
                                }

                                $user = auth()->user();
                                
                                // Get the requiring agency from the requirement
                                $requirement = RequiredDocument::find($record->required_document_id);
                                $requiringAgency = $requirement?->agency_name;
                                
                                // Only enable if:
                                // 1. User's agency matches the requiring agency
                                // 2. AND status is "Complied" (1)
                                $isRequiringAgency = $user->agency_name === $requiringAgency;
                                $isComplied = (int) $record->status === 1;
                                
                                return !($isRequiringAgency && $isComplied);
                            })
                            ->afterStateUpdated(function ($state, callable $set, $record) {
                                // When status is set to "Returned", reset compliance status
                                if ($state === 'returned') {
                                    $set('status', 0); // Set to Partially Complied
                                    $set('validated_at', null); // Clear validation timestamp
                                    // $set('validated_by', null); // Clear validator
                                } elseif ($state === 'validated') {
                                    $set('validated_at', now()); // Set validation timestamp
                                    // $set('validated_by', auth()->user()->name); // Track who validated
                                } elseif ($state === 'pending_review') {
                                    // Clear validation data when set back to pending
                                    $set('validated_at', null);
                                    // $set('validated_by', null);
                                }
                            })
                            ->dehydrated(),
                     
                        DateTimePicker::make('validated_at')
                            ->label(fn ($get) => $get('validation_status') === 'validated'
                                ? 'Validated At'
                                : ($get('validation_status') === 'returned' ? 'Returned At' : '')
                            )
                            ->disabled() // read-only
                            ->dehydrated()
                            ->displayFormat('m/d/Y h:i A')
                            ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state) : null)
                            ->seconds(false)
                            ->visible(fn ($get) => in_array($get('validation_status'), ['validated', 'returned']))
                            ->columnSpan(1),



                        
                        Textarea::make('admin_remarks')
                            ->label('Remarks (Requiring Agency)')
                            ->placeholder(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record
                                    && auth()->user()->department_code === $record->department_code;

                                if (!$isAdmin) {
                                    return 'Remarks from the requiring agency will appear here';
                                }

                                return $isOwnOffice
                                    ? 'You cannot add remarks to your own office'
                                    : 'Enter review comments, clarifications, or audit notes';
                            })
                            ->rows(3)
                            ->dehydrated()
                            ->disabled(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record
                                    && auth()->user()->department_code === $record->department_code;

                                return !$isAdmin || $isOwnOffice;
                            })
                            // ->required(function ($get) {
                            //     // Require remarks when returning documents
                            //     return $get('validation_status') === 'returned';
                            // })
                            ->columnSpanFull(),

                    ])
                    ->columns(2)
                    ->visible(true)
                    ->collapsible()
                    ->collapsed(false),

                // SECTION 3: DOCUMENT SUBMISSION & STATUS
                Section::make('Document Submission & Compliance Status')
                    ->description(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                        
                        if ($isAdmin && !$isOwnOffice) {
                            return 'View submission and submission notes';
                        }
                        return 'Upload documents';
                    })
                    ->schema([ 

                    FileUpload::make('attachments')
                        ->label('Upload Required Documents')
                        ->multiple()
                        ->disk('public')
                        ->directory('compliance-attachments')
                        ->visibility('public')
                        ->downloadable()
                        ->openable()
                        ->imageEditor()
                        ->imagePreviewHeight(200)
                        ->maxSize(10240) // 10MB
                        ->panelLayout('grid')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])

                        /**
                         * 🚫 DISABLE LOGIC
                         */
                        ->disabled(function ($record, $get) {
                            if (! $record) {
                                return true;
                            }

                            $user = auth()->user();

                            // Must belong to same office
                            if ($user->department_code !== $record->department_code) {
                                return true;
                            }

                            $validationStatus = $get('validation_status') ?? $record->validation_status;

                            // Hard lock once validated
                            if ($validationStatus === 'validated') {
                                return true;
                            }

                            $isComplied = (int) $record->status === 1;

                            /**
                             * 🔒 Disable if:
                             * - already complied AND
                             * - NOT returned
                             */
                            return $isComplied && $validationStatus !== 'returned';
                        })

                        /**
                         * 🗑 DELETE LOGIC
                         */
                        ->deletable(fn ($record, $get) =>
                            ($get('validation_status') ?? $record?->validation_status) !== 'validated'
                        )

                        /**
                         * ⚡ RUNS ONLY ON SAVE (FAST)
                         */
                        ->mutateDehydratedStateUsing(function ($state, callable $set) {
                            $user = auth()->user();
                            
                            // No files → reset
                            if (empty($state) || (is_array($state) && count(array_filter($state)) === 0)) {
                                $set('status', -1); // Not complied
                                $set('submitted_at', null);
                                $set('validation_status', 'returned'); // or keep as returned
                                $set('validated_at', null);
                                return $state;
                            }     

                            // Set compliance status
                            if ($user->hasRole('department_head')) {
                                $set('status', 1); // Complied
                            } else {
                                $set('status', 0); // Partially complied
                            }

                            /**
                             * 🔁 RETURNED → LOCK AFTER SAVE
                             */
                            $set('validation_status', 'pending_review');

                            $set('submitted_at', now());
                            $set('validated_at', null);
                            $set('validated_by', null);

                            return $state;
                        })

                        /**
                         * 👁 PERMISSION CHECK
                         */
                        ->visible(fn () =>
                            auth()->user()->can('addAttachments', ComplyingOffice::class)
                        )

                        ->columnSpanFull(),


                    Textarea::make('submission_notes')
                        ->label('Submission Notes')
                        ->placeholder(function ($record) {
                            $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;

                            return $isOwnOffice
                                ? 'Add any notes about the submitted documents'
                                : 'Submission notes (read-only)';
                        })
                        ->rows(2)
                        ->dehydrated()
                        ->disabled(function ($record) {
                            // Disable ONLY if not their own office (read-only)
                            return $record && auth()->user()->department_code !== $record->department_code;
                        })
                        ->columnSpanFull(),

                    DateTimePicker::make('submitted_at')
                        ->label('Submission Date & Time')
                        ->disabled()
                        ->dehydrated()
                        ->displayFormat('m/d/Y h:i A')
                        ->seconds(false)
                        ->visible(fn ($get) => !empty($get('submitted_at')))
                        ->columnSpan(1),
                        
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

           
            ]);
    }
}