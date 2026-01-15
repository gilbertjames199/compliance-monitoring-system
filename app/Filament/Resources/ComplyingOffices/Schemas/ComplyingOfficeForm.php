<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use App\Models\Office;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\DateTimePicker;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = auth()->user()->hasAnyRole(['superadmin']);

        return $schema
            ->components([
                 // SECTION 1: REQUIREMENT INFORMATION
                Section::make('Requirement Information')
                    ->schema([
                        Select::make('requirement_id')
                            ->label('Requirement')
                            ->options(\App\Models\RequiredDocument::orderBy('requirement')->pluck('requirement', 'id'))
                            ->reactive()
                            ->disabled()
                            ->columnSpanFull()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $requirement = \App\Models\RequiredDocument::find($state);
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
                                if ($record && $record->requirement_id) {
                                    $requirement = \App\Models\RequiredDocument::find($record->requirement_id);
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
                Section::make('Admin Review & Remarks')
                    ->description(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                        
                        if ($isAdmin && $isOwnOffice) {
                            return 'Admin feedback (You cannot review your own office submission)';
                        } elseif ($isAdmin) {
                            return 'Review the submission and provide feedback';
                        } else {
                            return 'Admin feedback on your submission';
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
                                $requirement = \App\Models\RequiredDocument::find($record->requirement_id);
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
                                    $set('validated_by', null); // Clear validator
                                } elseif ($state === 'validated') {
                                    $set('validated_at', now()); // Set validation timestamp
                                    $set('validated_by', auth()->user()->name); // Track who validated
                                } elseif ($state === 'pending_review') {
                                    // Clear validation data when set back to pending
                                    $set('validated_at', null);
                                    $set('validated_by', null);
                                }
                            })
                            ->dehydrated()
                            ->columnSpanFull(),
                        
                        DateTimePicker::make('validated_at')
                            ->label('Validated Date & Time')
                            ->disabled()
                            ->dehydrated()
                            ->displayFormat('m/d/Y h:i A')
                            ->seconds(false)
                            ->visible(fn ($get) => !empty($get('validated_at')))
                            ->columnSpan(1),

                        TextInput::make('validated_by')
                            ->label('Validated By')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn ($get) => !empty($get('validated_by')))
                            ->columnSpan(1),

                        Textarea::make('admin_remarks')
                            ->label('Admin Remarks (Requiring Agency)')
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
                            ->rows(4)
                            ->dehydrated()
                            ->disabled(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record
                                    && auth()->user()->department_code === $record->department_code;

                                return !$isAdmin || $isOwnOffice;
                            })
                            ->required(function ($get) {
                                // Require remarks when returning documents
                                return $get('validation_status') === 'returned';
                            })
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
                        ->downloadable()
                        ->openable()
                        ->directory('compliance-attachments')
                        ->maxSize(10240) // 10MB limit
                        ->acceptedFileTypes([
                            'application/pdf', 
                            'image/jpeg', 
                            'image/png', 
                            'application/msword', 
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->reactive()
                        ->disabled(function ($record, $get) {
                            if (!$record) {
                                return true;
                            }

                            $user = auth()->user();
                            
                            // Must belong to the same office
                            if ($user->department_code !== $record->department_code) {
                                return true;
                            }

                            // Get validation status
                            $validationStatus = $get('validation_status') ?? $record->validation_status;
                            
                            // Lock if validated - no uploads allowed
                            if ($validationStatus === 'validated') {
                                return true;
                            }
                            
                            // If status is "Complied" BUT validation is "returned", allow re-upload
                            $isComplied = (int) $record->status === 1;
                            $isReturned = $validationStatus === 'returned';
                            
                            // Disable if complied AND NOT returned
                            return $isComplied && !$isReturned;
                        })
                        ->deletable(function ($record, $get) {
                            if (!$record) {
                                return false;
                            }
                            
                            $validationStatus = $get('validation_status') ?? $record->validation_status;
                            
                            // Can delete if NOT validated
                            return $validationStatus !== 'validated';
                        })
                        ->afterStateUpdated(function ($state, callable $set, $get) {
                            $record = $get('record');
                            
                            if (!empty($state)) {
                                $user = auth()->user();

                                if ($user->hasRole('department_head')) {
                                    $set('status', 1); // Complied
                                    $set('validation_status', 'pending_review'); // Reset to pending review
                                } elseif ($user->hasRole('AO')) {
                                    $set('status', 0); // Partially complied
                                    $set('validation_status', 'pending_review'); // Reset to pending review
                                } else {
                                    $set('status', 0); // Default fallback
                                    $set('validation_status', 'pending_review');
                                }
                                
                               
                                $set('submitted_at', now()); // auto set date
                                
                                // Clear validation tracking when re-submitting
                                $set('validated_at', null);
                                $set('validated_by', null);
                            } else {
                                $set('status', -1); // Not submitted
                                $set('submitted_at', null);
                            }
                        })
                        ->visibility('public')
                        ->visible(fn() => auth()->user()->can('addAttachments', ComplyingOffice::class))
                        ->columnSpanFull(),

                    Textarea::make('submission_notes')
                        ->label('Submission Notes')
                        ->placeholder(function ($record) {
                            $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;

                            return $isOwnOffice
                                ? 'Add any notes about the submitted documents'
                                : 'Submission notes (read-only)';
                        })
                        ->rows(3)
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