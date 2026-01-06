<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use App\Models\Office;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\Facades\Storage;
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
                        Select::make('department_code')
                        ->columnSpanFull()
                            ->label('Complying Office')
                            ->disabled()
                            ->options(Office::all()->pluck('office', 'department_code')),
                        
                        Select::make('requirement_id')
                            ->label('Requirement')
                            ->options(\App\Models\RequiredDocument::orderBy('requirement')->pluck('requirement', 'id'))
                            ->reactive()
                            ->disabled()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $requirement = \App\Models\RequiredDocument::find($state);
                                if ($requirement) {
                                    $set('agency_name', $requirement->agency_name);
                                } else {
                                    $set('agency_name', null);
                                }
                            }),

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

                        // ToggleButtons::make('status')
                        //     ->label('Compliance Status')
                        //     ->inline()
                        //     ->options([
                        //         -1 => 'Not Complied',
                        //         0  => 'Partially Complied',
                        //         1  => 'Complied',
                        //     ])
                        //     ->colors([
                        //     '-1' => 'danger',
                        //     '0' => 'warning',
                        //     '1' => 'success',
                        //                 ])
                        //     ->default(-1)
                        //     ->disabled(fn () => !auth()->user()->hasAnyRole(['super_admin', 'admin']))
                        //     ->dehydrated(),

                       
                    


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
                            ->disabled(fn () => !auth()->user()->hasAnyRole(['super_admin']))
                            ->dehydrated()
                            ->columnSpanFull(),


                        Textarea::make('admin_remarks')
                            ->label('Admin Remarks / Comments')
                            ->placeholder(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                                
                                if ($isAdmin && $isOwnOffice) {
                                    return 'You cannot add remarks to your own office submission';
                                } elseif ($isAdmin) {
                                    return 'Enter your review comments, reasons for approval/disapproval, or requested changes';
                                } else {
                                    return 'No remarks yet';
                                }
                            })
                            ->rows(4)
                            ->disabled(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                                return !$isAdmin || ($isAdmin && $isOwnOffice);
                            })
                            ->required(function ($get, $record) use ($isAdmin) {
                                $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                                return $isAdmin && !$isOwnOffice && in_array($get('status'), [2, 3]);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(true)
                    ->collapsible()
                    ->collapsed(false),
                    // ->collapsed(function ($get, $record) use ($isAdmin) {
                    //     if (!$isAdmin) {
                    //         return empty($get('admin_remarks'));
                    //     }
                        
                    //     $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                    //     return ($isAdmin && $isOwnOffice) || empty($get('admin_remarks'));
                    // }),


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
                    ->previewable()
                    ->directory('compliance-attachments')
                    ->reactive()
                    ->disabled(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                        return $isAdmin && !$isOwnOffice; // Disable if admin AND not their own office
                    })
                    ->deletable(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                        
                        // Allow deletion if:
                        // 1. User is from the same office (even if admin)
                        // 2. AND the record is not submitted yet
                        if ($isAdmin && !$isOwnOffice) {
                            return false; // Admin from different office can't delete
                        }
                        
                        return $record?->status === -1 || $record === null;
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! empty($state)) {
                            $set('status', '0');
                            $set('submitted_at', now()); // auto set date
                        } else {
                            $set('status', -1);
                            $set('submitted_at', null);
                        }
                    })
                    ->columnSpanFull(),

                  


                    Textarea::make('submission_notes')
                        ->label('Submission Notes')
                        // ->placeholder('Add any notes about the submitted documents')

                       ->placeholder(function ($record) use ($isAdmin) {
                            $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                            $hasNotes = !empty($record->submission_notes); // Adjust field name if different
                            
                            if ($isAdmin && $isOwnOffice) {
                                return 'Add any notes about the submitted documents';
                            } elseif ($isAdmin && !$hasNotes) {
                                return 'No submission notes yet';
                            } else {
                                return 'Add any notes about the submitted documents';
                            }
                        })
                        ->rows(3)
                        ->dehydrated() // ← Add this too
                        ->disabled(function ($record) use ($isAdmin) {
                            $isOwnOffice = $record && auth()->user()->department_code === $record->department_code;
                            return $isAdmin && !$isOwnOffice;
                        })
                        ->columnSpanFull(),

                    DateTimePicker::make('submitted_at')
                        ->label('Submission Date & Time')
                        ->disabled()
                        ->dehydrated() // ← CRITICAL: Add this so it saves even when disabled
                        ->displayFormat('m/d/Y h:i A')
                        ->seconds(false)
                        ->visible(fn ($get) => !empty($get('submitted_at')))
                        ->columnSpan(1),

                        
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(false),

               

                    
                


                // FileUpload::make('attachment')
                //     ->label('Upload Required Documents')
                //     ->multiple()
                //     ->downloadable()
                //     ->openable()
                //     ->previewable()
                //     ->directory('compliance-attachments')
                //     ->afterStateUpdated(function ($state, callable $set) {
                //         if (! empty($state)) {
                //             $set('status', 'submitted');
                //         }
                //     })




                // Select::make('require')
                //     ->label('Compliance Status')
                //     ->options([
                //         -1 => 'Not Complied',
                //         0  => 'Partially Complied',
                //         1  => 'Complied',
                //     ])
                //     ->default(-1)
                //     ->required(),
                
                // TextInput::make('status')
                //     ->required(),
            ]);
    }
}
