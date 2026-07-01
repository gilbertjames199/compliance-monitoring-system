<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Support\FilamentAttachmentPreview;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = auth()->user()->hasRoleSafe('super_admin');

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
                    
                        TextInput::make('office_name')
                            ->label('Complying Office')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn ($record) => $record?->office?->office ?? '-'),

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
                            ->label('Start Date')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($record) {
                                return $record?->requiredDocument?->date_from
                                    ? \Carbon\Carbon::parse($record->requiredDocument->date_from)->format('F d, Y')
                                    : '-';
                            }),

                        TextInput::make('due_date')
                            ->label('Deadline')
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
                    ->description('Requiring agency feedback on your submission')
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
                                    return '💡 Once Compliance status is set to ‘Complied’, document uploads and removals are disabled unless the validation status is returned.';
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
                            // ->hintIcon('heroicon-m-lock-closed')
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
                                //if ($user->hasRoleSafe('AO')) {
                                if (! $user->can('UpdateComplianceStatus:ComplyingOffice')) {
                                    return true;
                                }
                                
                                // Department Head can change when status = 0 or partially complied
                                //if ($user->hasRoleSafe('department_head')) {
                                if ($user->can('UpdateDepartmentComplianceStatus:ComplyingOffice')) {
                                    $allowedStatuses = [0, 1];
                                    return !in_array($record->status, $allowedStatuses);
                                }
                                
                                // Super Admin → can edit only their own office and status = 0
                                //if ($user->hasRoleSafe('super_admin')) {
                                if ($user->can('UpdateOwnOfficeComplianceStatus:ComplyingOffice')) {
                                    $isOwnOffice = $user->hasAccessToDepartment($record->department_code);
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
                                    $set('validated_by', auth()->user()->name); // Track who validated
                                } elseif ($state === 'pending_review') {
                                    // Clear validation data when set back to pending
                                    $set('validated_at', null);
                                    $set('validated_by', null);
                                }
                            })
                            ->dehydrated()
                            ->columnSpanFull(),

                        TextInput::make('validated_by')
                            ->label(fn ($get) =>
                                $get('validation_status') === 'validated'
                                    ? 'Validated By'
                                    : ($get('validation_status') === 'returned' ? 'Returned By' : '')
                            )
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn ($get) =>
                                in_array($get('validation_status'), ['validated', 'returned'])
                            )
                            ->columnSpan(1),

                     
                        DateTimePicker::make('validated_at')
                            ->label(fn ($get) => $get('validation_status') === 'validated'
                                ? 'Validated At'
                                : ($get('validation_status') === 'returned' ? 'Returned At' : '')
                            )
                            ->disabled()
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
                                    && auth()->user()->hasAccessToDepartment($record->department_code);
                                $user = auth()->user(); // <-- define $user inside closure
                                if ($user->hasRoleSafe('requiring_agency')) {
                                    return 'Enter your review comments, clarifications, or audit notes for this submission.';
                                }

                                // For Complying Office or other roles
                                return 'Remarks from the requiring agency will appear here (read-only).';
                            })
                            ->rows(3)
                            ->dehydrated()
                            ->disabled(function ($record) use ($isAdmin) {
                                $isOwnOffice = $record
                                    && auth()->user()->hasAccessToDepartment($record->department_code);

                                return !$isAdmin || $isOwnOffice;
                            })
                            // ->required(function ($get) {
                            //     // Require remarks when returning documents
                            //     return $get('validation_status') === 'returned';
                            // })
                            ->columnSpanFull(),

                            // MODAL BUTTON - Updated
                            Actions::make([
                                Action::make('open_attachment_feedback')
                                    ->label('View Attachment Feedback')
                                    ->button()
                                    ->color('primary')
                                    ->modalHeading('Attachment Preview, Agency Remarks, and Replies')
                                    ->modalWidth('8xl')
                                    ->modalSubmitAction(false)
                                    ->modalCancelActionLabel('Close')
                                    ->modalContent(function ($record, $get, $set) {
                                        $attachments = $get('attachments') ?? $record?->attachments;
                                        $remarks = $get('attachment_remarks') ?? $record?->attachment_remarks ?? [];
                                        $drafts = $get('attachment_remark_drafts') ?? [];
                                        $annotations = $get('attachment_annotations') ?? $record?->attachment_annotations ?? [];
                                        $viewStates = $get('attachment_view_states') ?? $record?->attachment_view_states ?? [];

                                        $user = auth()->user();
                                        $isOwnOffice = $record && $user->hasAccessToDepartment($record->department_code);

                                        $validationStatus = $get('validation_status') ?? $record?->validation_status;
                                        $isValidated = $validationStatus === 'validated';
                                        $isComplied = (int) ($record?->status ?? $get('status') ?? -1) === 1;

                                        $editable = !$record
                                            || ($isOwnOffice && !$isValidated && (!$isComplied || $validationStatus === 'returned'));

                                        return view('filament.forms.components.attachment-preview-modal', [
                                            'preview' => FilamentAttachmentPreview::payload(
                                                $attachments,
                                                'complying_office_form_' . ($record?->id ?? 'new'),
                                                $remarks,
                                                $drafts,
                                                self::resolveAttachmentViewerType($record),
                                                $annotations,
                                                $viewStates
                                            ),
                                            'editable' => $editable,
                                            'annotationEditable' => false,
                                            'draftsStatePath' => 'data.attachment_remark_drafts',
                                            'annotationsStatePath' => 'data.attachment_annotations',
                                            'viewStatesStatePath' => 'data.attachment_view_states',
                                            'draftLabel' => 'Your reply',
                                            'draftPlaceholder' => 'Reply to the requiring agency about this file.',
                                        ]);
                                    }),
                            ])
                        ->columnSpanFull(),

                        Hidden::make('attachment_remarks')
                            ->default([])
                            ->dehydrated(),

                        Hidden::make('attachment_remark_drafts')
                            ->default([])
                            ->dehydrated(),

                        Hidden::make('attachment_annotations')
                            ->default([])
                            ->dehydrated(),

                        Hidden::make('attachment_view_states')
                            ->default([])
                            ->dehydrated(),

                    ])
                    ->columns(2)
                    ->visible(true)
                    ->collapsible()
                    ->collapsed(false),

                // SECTION 3: DOCUMENT SUBMISSION & STATUS
                Section::make('Document Submission & Compliance Status')
                    ->description(function ($record) use ($isAdmin) {
                        $isOwnOffice = $record && auth()->user()->hasAccessToDepartment($record->department_code);
                        
                        if ($isAdmin && !$isOwnOffice) {
                            return 'View submission and submission notes';
                        }
                        return 'Upload required documents and add submission notes.';
                    })
                    ->schema([ 
                        FileUpload::make('attachments')
                            ->label('Upload Required Documents')
                            ->multiple()
                            ->disk('public')
                            ->directory(fn () => 'compliance-attachments/' . now()->format('Y/F'))
                            ->visibility('public')
                            ->downloadable()
                            ->openable()
                            ->imageEditor()
                            ->imagePreviewHeight(200)
                            ->required()
                            ->reorderable()
                            // ->maxFiles(function ($record) {
                            //     $existing = $record?->attachments ?? [];
                            //     return max(3, count($existing)); // never less than what they already have
                            // })
                            ->helperText(function ($record) {
                                $existing = $record?->attachments ?? [];
                                $limit = max(3, count($existing));
                                return "Accepted: PDF, JPG, PNG, XLS, XLSX, CSV. Max 2MB each.";
                            })
                            ->maxSize(3072) //2mb
                            // ->panelLayout('grid')
                            ->reactive()
                            ->acceptedFileTypes([
                                'application/pdf',
                                'image/jpeg',
                                'image/png',
                                'text/csv',
                                'application/csv',
                                'text/comma-separated-values',
                                'application/vnd.ms-excel',                                          // .xls
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                                'application/octet-stream',        // ← add this (some browsers send xlsx as this)
                                'application/zip',                 // ← add this (xlsx is technically a zip)
                                'application/x-zip-compressed',   // ← add this
                            ])
                            ->rules([
                                    fn () => function (string $attribute, $value, $fail) {
                                        $originalName = strtolower($value->getClientOriginalName());
                                        
                                        // 1. Block the word "php" anywhere in the name (prevents .php.jpg)
                                        if (Str::contains($originalName, 'php')) {
                                            $fail("Security error: Filename contains restricted keywords.");
                                        }

                                        // 2. Double-check the actual extension
                                        $extension = $value->getClientOriginalExtension();
                                        if (in_array(strtolower($extension), ['php', 'php5', 'phtml', 'phar'])) {
                                            $fail("Direct PHP extension uploads are strictly prohibited.");
                                        }

                                        // 3. Server-side file size check (3MB = 3145728 bytes)
                                        if ($value->getSize() > 3 * 1024 * 1024) {
                                            $fail("Each file must not exceed 2MB.");
                                        }
                                    },
                                ])
                            ->afterStateUpdated(function ($state, $set, $get, $record) {
                                $user = auth()->user();
                                if (!empty($state)) {
                                    // ✅ Files exist → update status automatically
                                    // if ($user->can('UpdateDepartmentComplianceStatus:ComplyingOffice') || $user->can('super_admin')) {
                                    if ($user->can('UpdateDepartmentComplianceStatus:ComplyingOffice')) {
                                        $set('status', 1); // Complied
                                    } else {
                                        $set('status', 0); // Partially complied
                                    }
                                    // $set('submitted_by', $user->name);
                                    // $set('submitted_at', now());
                                    if (! $user->hasRoleSafe('super_admin')) {
                                        $set('submitted_by', $user->name);
                                        $set('submitted_at', now());
                                    }
                                    
                                    // ✅ Notify requiring agency
                                    if ($record && $record->requiringAgency) {
                                        $record->requiringAgency->notify(new \App\Notifications\DocumentSubmitted($record));
                                    }
        
                                } else {
                                    //$data['status'] = -1;
                                    $set('status', -1);
                                    $set('submitted_by', null);
                                    $set('submitted_at', null);
                                    $set('attachment_remarks', []);
                                    $set('attachment_remark_drafts', []);
                                    $set('attachment_view_states', []);
                                }
                            })


                            /**
                             * 🚫 DISABLE LOGIC
                             */
                            ->disabled(function ($record, $get) {
                                if (! $record) {
                                    return true;
                                }

                                $user = auth()->user();

                                // ✅ Super Admin can always edit/upload
                                if ($user->hasRoleSafe('super_admin')) {
                                    return false;
                                }

                                // Must belong to same office
                                if (! $user->hasAccessToDepartment($record->department_code)) {
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
                             * 👁 PERMISSION CHECK
                             */
                            ->visible(fn () =>
                                auth()->user()->can('addAttachments', ComplyingOffice::class)
                            )

                            ->columnSpanFull(),

                        // Hidden::make('attachment_remarks')
                        //     ->default([])
                        //     ->dehydrated(),

                        // Hidden::make('attachment_remark_drafts')
                        //     ->default([])
                        //     ->dehydrated(),

                        // Hidden::make('attachment_annotations')
                        //     ->default([])
                        //     ->dehydrated(),

                        // ViewField::make('attachments_preview')
                        //     ->label('Attachment Preview, Agency Remarks, and Your Replies')
                        //     ->view('filament.forms.components.attachment-preview')
                        //     ->viewData(function ($get, $record) {
                        //         $attachments = $get('attachments') ?? $record?->attachments;
                        //         $remarks = $get('attachment_remarks') ?? $record?->attachment_remarks ?? [];
                        //         $drafts = $get('attachment_remark_drafts') ?? [];
                        //         $annotations = $get('attachment_annotations') ?? $record?->attachment_annotations ?? [];
                        //         $user = auth()->user();
                        //         $isOwnOffice = $record && $user->department_code === $record->department_code;
                        //         $validationStatus = $get('validation_status') ?? $record?->validation_status;
                        //         $isValidated = $validationStatus === 'validated';
                        //         $isComplied = (int) ($record?->status ?? $get('status') ?? -1) === 1;
                        //         $editable = !$record
                        //             || ($isOwnOffice && !$isValidated && (!$isComplied || $validationStatus === 'returned'));

                        //         return [
                        //             'preview' => FilamentAttachmentPreview::payload(
                        //                 $attachments,
                        //                 'complying_office_form_' . ($record?->id ?? 'new'),
                        //                 $remarks,
                        //                 $drafts,
                        //                 self::resolveAttachmentViewerType($record),
                        //                 $annotations
                        //             ),
                        //             'editable' => $editable,
                        //             'annotationEditable' => false,
                        //             'draftsStatePath' => 'data.attachment_remark_drafts',
                        //             'annotationsStatePath' => 'data.attachment_annotations',
                        //             'draftLabel' => 'Your reply',
                        //             'draftPlaceholder' => 'Reply to the requiring agency about this file. Your message will be added to the conversation.',
                        //         ];
                        //     })
                        //     ->visible(fn ($get) => !empty($get('attachments')))
                        //     ->dehydrated(false)
                        //     ->columnSpanFull(),

                        Textarea::make('submission_notes')
                            ->label('Submission Notes')
                            ->placeholder(function ($record) {
                                $isOwnOffice = $record && auth()->user()->hasAccessToDepartment($record->department_code);

                                return $isOwnOffice
                                    ? 'Add any notes about the submitted documents'
                                    : 'Submission notes (read-only)';
                            })
                            ->rows(2)
                            ->dehydrated()
                            ->required()
                            ->disabled(function ($record, $get) {
                                if (! $record) {
                                    return false;
                                }

                                $user = auth()->user();

                                // Must belong to same office
                                if (! $user->hasAccessToDepartment($record->department_code)) {
                                    return true;
                                }

                                $validationStatus = $get('validation_status') ?? $record->validation_status;

                                // Hard lock once validated
                                if ($validationStatus === 'validated') {
                                    return true;
                                }

                                $isComplied = (int) $record->status === 1;

                                // Lock if complied and NOT returned
                                return $isComplied && $validationStatus !== 'returned';
                            })
                            ->columnSpanFull(),

                        // TextInput::make('submitted_by')
                        //     ->label('Submitted By')
                        //     ->disabled()
                        //     ->dehydrated()
                        //     ->reactive()
                        //     ->visible(fn ($get) => !empty($get('attachments')))
                        //     ->columnSpan(1),
                        TextInput::make('submitted_by')
                            ->label('Submitted By')
                            ->disabled(fn () => ! auth()->user()->hasRoleSafe('super_admin'))
                            ->dehydrated()
                            ->reactive()
                            ->visible(fn ($get) => !empty($get('attachments')))
                            ->columnSpan(1),


                        // DateTimePicker::make('submitted_at')
                        //     ->label('Submission Date & Time')
                        //     ->disabled()
                        //     ->dehydrated()
                        //     ->reactive()
                        //     ->displayFormat('m/d/Y h:i A')
                        //     ->seconds(false)
                        //     ->visible(fn ($get) => !empty($get('attachments')))
                        //     ->columnSpan(1),
                        DateTimePicker::make('submitted_at')
                            ->label('Submission Date & Time')
                            ->disabled(fn () => ! auth()->user()->hasRoleSafe('super_admin'))
                            ->dehydrated()
                            ->reactive()
                            ->displayFormat('m/d/Y h:i A')
                            // ->native(false)
                            // ->timezone(config('app.timezone'))
                            ->seconds(false)
                            ->visible(fn ($get) => !empty($get('attachments')))
                            ->columnSpan(1),
                            
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->collapsed(false),

           
            ]);
    }

    protected static function resolveAttachmentViewerType(?ComplyingOffice $record): ?string
    {
        $user = auth()->user();

        if ($user->hasRoleSafe('super_admin')) {
            return 'super_admin';
        }

        if ($record && $user->hasAccessToDepartment($record->department_code)) {
            return 'complying_office';
        }

        return 'user';
    }
}
