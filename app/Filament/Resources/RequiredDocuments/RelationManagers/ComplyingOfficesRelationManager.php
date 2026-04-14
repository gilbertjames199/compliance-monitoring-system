<?php

namespace App\Filament\Resources\RequiredDocuments\RelationManagers;

use App\Mail\DueDateReminderMail;
use App\Models\AuditLog;
use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ComplyingOfficesRelationManager extends RelationManager
{
    protected static string $relationship = 'complyingOffices';


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TextInput::make('department_code')
                //     ->required(),
                Select::make('department_code')
                    ->label('Office')
                    ->options(Office::all()->pluck('office', 'department_code'))
                    ->searchable()
                    ->rules(function (callable $get) {
                        // Get the current requirement ID from the parent record
                        $requirementId = $this->getOwnerRecord()->id;

                        return [
                            Rule::unique('complying_offices', 'department_code')
                                ->where(fn($query) =>
                                    $query->where('required_document_id', $requirementId)
                                )
                                ->ignore($get('id')) // ignore self when editing
                        ];
                    })
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->helperText('Each office can only be added once per requirement.')
                    ->columnSpanFull(),


                Select::make('status')
                    ->label('Compliance Status')
                    ->options([
                        -1 => 'Not Complied',
                        0  => 'Partially Complied',
                        1  => 'Complied',
                    ])
                    ->default(-1)
                    ->disabled()
                    ->dehydrated(),

                Placeholder::make('attachments_view')
                    ->label('Submitted Attachments')
                    ->content(function ($record) {
                        if (!$record || empty($record->attachments)) {
                            return 'No files submitted.';
                        }

                        $attachments = is_array($record->attachments)
                            ? $record->attachments
                            : json_decode($record->attachments, true);

                        return collect($attachments)
                            ->map(fn ($file) =>
                                "<a href='".Storage::disk('public')->url($file)."' 
                                    target='_blank' 
                                    style='color: #2563eb; text-decoration: underline;'> "
                                    .basename($file).
                                "</a>"
                            )
                            ->implode('<br>');
                    })
                    ->html(),

                TextInput::make('submitted_by')
                    ->label('Submitted By')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn ($get) => !empty($get('submitted_at'))),

                DateTimePicker::make('submitted_at')
                    ->label('Submitted At')
                    ->disabled()
                    ->visible(fn ($get) => !empty($get('submitted_at'))),

                Textarea::make('submission_notes')
                    ->label('Submission Notes')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn ($get) => !empty($get('submitted_at')))
                    ->columnSpanFull(),

                ToggleButtons::make('validation_status')
                    ->label('Validation Status')
                    ->inline()
                    ->required()
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
                    ->reactive()
                    ->helperText(function ($record) {
                        if (!$record) {
                            return '';
                        }

                        $user = auth()->user();
                        $requiredDocument = $this->getOwnerRecord();

                        $agencyDepartmentCode = \App\Models\Office::where('office', $requiredDocument->agency_name)
                            ->value('department_code');

                        $isRequiringAgency = $user->department_code === $agencyDepartmentCode;
                        $isComplied = (int)$record->status === 1;

                        if (!$isRequiringAgency) {
                            return 'Only the requiring agency (' . $requiredDocument->agency_name . ') can validate submissions.';
                        }

                        if (!$isComplied) {
                            return 'Validation is only available when the compliance status is "Complied".';
                        }

                        return 'Review and validate the submitted documents.';
                    })
                    ->disabled(function ($record) {
                        if (!$record) {
                            return true;
                        }

                        $user = auth()->user();

                        // Superadmin can always edit
                        if ($user->hasRoleSafe('super_admin')) {
                            return false;
                        }

                        $requiredDocument = $this->getOwnerRecord();

                        // Look up the office/agency by name to get its department_code
                        $agencyDepartmentCode = \App\Models\Office::where('office', $requiredDocument->agency_name)
                            ->value('department_code');

                        // Compare user's department_code with the agency's department_code
                        $isRequiringAgency = $user->department_code === $agencyDepartmentCode;

                        $isComplied = (int)$record->status === 1;

                        return !($isRequiringAgency && $isComplied);
                    })

                    ->afterStateUpdated(function ($state, $set, $record) {
                        $user = auth()->user();

                        if ($state === 'returned') {
                            $set('status', 0); // Automatically change compliance status to Partially Complied
                            $set('validated_by', $user->name);
                            $set('validated_at', now());
                        } elseif ($state === 'validated') {
                            $set('status', 1); // Optionally set to Complied if validation approved
                            $set('validated_by', $user->name);
                            $set('validated_at', now());
                        } else { // pending_review
                            $set('validated_by', null);
                            $set('validated_at', null);
                        }
                    })
                    ->dehydrated()
                    ->columnSpanFull(),

                
                Textarea::make('admin_remarks')
                    ->label('Remarks/Comments/Instructions')
                    ->nullable()
                    ->rows(2)
                    ->required()
                    ->columnSpanFull()
                    ->disabled(function ($record) {
                        if (!$record) {
                            return true;
                        }

                        $user = auth()->user();

                        // Superadmin can always edit
                        if ($user->hasRoleSafe('super_admin')) {
                            return false;
                        }

                        $requiredDocument = $this->getOwnerRecord();

                        $agencyDepartmentCode = \App\Models\Office::where('office', $requiredDocument->agency_name)
                            ->value('department_code');

                        $isRequiringAgency = $user->department_code === $agencyDepartmentCode;
                        $isComplied = (int)$record->status === 1;

                        return !($isRequiringAgency && $isComplied);
                    })
                    ->helperText(function ($record) {
                        if (!$record) {
                            return '';
                        }

                        $user = auth()->user();
                        $requiredDocument = $this->getOwnerRecord();

                        $agencyDepartmentCode = \App\Models\Office::where('office', $requiredDocument->agency_name)
                            ->value('department_code');

                        $isRequiringAgency = $user->department_code === $agencyDepartmentCode;
                        $isComplied = (int)$record->status === 1;

                        if (!$isRequiringAgency) {
                            return 'Only the requiring agency (' . $requiredDocument->agency_name . ') can add remarks.';
                        }

                        if (!$isComplied) {
                            return 'Remarks can only be added when the status is "Complied".';
                        }

                        return 'Add validation remarks for this submission.';
                    }),  
                        
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
                    ->label(fn ($get) => $get('validation_status') === 'validated' ? 'Validated At' : ($get('validation_status') === 'returned' ? 'Returned At' : ''))
                    ->disabled()
                    ->dehydrated() // <-- important
                    ->displayFormat('m/d/Y h:i A')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state) : null)
                    ->seconds(false)
                    ->visible(fn ($get) => in_array($get('validation_status'), ['validated', 'returned'])),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('department_code')
            ->columns([
                TextColumn::make('office.office')
                    ->label('Office Name')
                    ->searchable(),

                TextColumn::make('submitted_at')
                    ->label('Submission Date')
                    ->formatStateUsing(function ($state) {
                        return $state ? Carbon::parse($state)->format('M d, Y') : 'Not Submitted';
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Compliance Status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            '-1' => 'Not Complied',
                            '0'  => 'Partially Complied',
                            '1'  => 'Complied',
                            default => 'Unknown',
                        };
                    })
                    ->badge() // optional: shows as colored badge
                    ->colors([
                        'danger' => '-1',
                        'warning' => '0',
                        'success' => '1',
                    ]),

                TextColumn::make('validation_status')
                    ->label('Validation Status')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'returned' => 'Returned',
                            'pending_review'  => 'Pending Review',
                            'validated'  => 'Validated',
                            default => 'pending_review',
                        };
                    })
                    ->badge() // optional: shows as colored badge
                    ->colors([
                        'danger' => 'returned',
                        'warning' => 'pending_review',
                        'success' => 'validated',
                    ])
                    ->html()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('validated_at')
                    ->label('Validation Date & Time')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Compliance Status')
                    ->multiple()
                    ->options([
                        '-1' => 'Not Complied',
                        '0'  => 'Partially Complied',
                        '1'  => 'Complied',
                    ]),

                SelectFilter::make('validation_status')
                    ->label('Validation Status')
                    ->multiple()
                    ->options([
                        'pending_review' => 'Pending Review',
                        'returned'       => 'Returned',
                        'validated'      => 'Validated',
                    ]),

            ], 
            )
            
            ->headerActions([
                CreateAction::make()
                    ->visible(function () {
                        $user = auth()->user();
                        $requiredDocument = $this->getOwnerRecord();
                        $userOfficeName = optional($user->office)->office;
                        
                        // Only show create button if user is from the requiring agency
                        return $userOfficeName === $requiredDocument->agency_name;
                    }),
                // AssociateAction::make(),
            ])
            ->recordActions([
                   Action::make('Notify Office')
                    ->action(function () {
                        $user = auth()->user(); // Get the user who clicked the button
                        $requirement = $this->getOwnerRecord();

                        // Log that the notify button was clicked
                        Log::info("Notify Office button clicked", [
                            'triggered_by' => $user->name,
                            'triggered_by_email' => $user->email,
                            'triggered_by_department' => $user->department_code,
                            'requirement_id' => $requirement->id,
                            'requirement_name' => $requirement->requirement,
                            'due_date' => $requirement->due_date,
                            'timestamp' => now()
                        ]);
    
                        // Get complying offices that are NOT yet complied
                        $complyingOffices = ComplyingOffice::where('required_document_id', $requirement->id)
                            ->where('status', '!=', 1)
                            ->get();

                        $totalOfficesNotified = 0;
                        $totalEmailsSent = 0;
                        $skippedUsers = [];

                        // Resolve office names for each department
                        $departmentCodes = $complyingOffices->pluck('department_code')->unique();
                        $officeMap = Office::whereIn('department_code', $departmentCodes)
                            ->pluck('office', 'department_code');
                        
                        $complyingOfficeMap = $complyingOffices->keyBy('department_code');


                        foreach ($complyingOffices as $office) {

                            // Users in this department
                            $users = User::where('department_code', $office->department_code)->get();
                            $officeName = $officeMap[$office->department_code] ?? $office->department_code;

                            foreach ($users as $targetUser) {

                                // Skip users without roles
                                if ($targetUser->roles->isEmpty()) {
                                    $skippedUsers[] = [
                                        'user' => $targetUser->name,
                                        'email' => $targetUser->email,
                                        'reason' => 'No roles assigned'
                                    ];
                                    continue;
                                }

                                // Skip invalid emails
                                if (empty($targetUser->email) || !filter_var($targetUser->email, FILTER_VALIDATE_EMAIL)) {
                                    $skippedUsers[] = [
                                        'user' => $targetUser->name,
                                        'email' => $targetUser->email,
                                        'reason' => 'Invalid or empty email'
                                    ];
                                    continue;
                                }

                                // If confidential, skip AO/Admin only
                                if ($requirement->is_confidential && !$targetUser->can('ViewConfidential:RequiredDocument')) {
                                    $skippedUsers[] = [
                                        'user' => $targetUser->name,
                                        'email' => $targetUser->email,
                                        'reason' => 'No permission for confidential document'
                                    ];
                                    continue;
                                }

                               $officeName = $officeMap[$targetUser->department_code] ?? $targetUser->department_code;
                                Mail::to($targetUser->email)
                                    ->send(new DueDateReminderMail($requirement, $targetUser, $officeName));

                                // ✅ Audit log per user notified (same pattern as your job)
                                AuditLog::create([
                                    'event'                  => 'requirement notification sent 1',
                                    'user_id'                => $targetUser->recid,
                                    'acted_by'               => $user->recid ?? $user->id, // Who clicked the button
                                    'action_at'              => now(),
                                    'requirement_id'         => $requirement->id,
                                    'requirement_name'       => $requirement->requirement,
                                    'complying_office_id'    => $office->id,
                                    'office_name'            => $officeName,
                                    'requiring_agency_name'  => $requirement->agency_name,
                                    'remarks'                => "Manual notification sent to {$targetUser->email} by {$user->name}",
                                ]);

                                $totalEmailsSent++;
                                sleep(1);
                            }

                            if ($users->count() > 0) {
                                $totalOfficesNotified++;
                            }
                        }

                        // Log the notification results
                        Log::info("Notify Office notification completed", [
                            'requirement_id' => $requirement->id,
                            'requirement_name' => $requirement->requirement,
                            'offices_notified' => $totalOfficesNotified,
                            'total_offices_processed' => $complyingOffices->count(),
                            'emails_sent' => $totalEmailsSent,
                            'users_skipped' => $skippedUsers,
                            'triggered_by' => $user->name,
                            'triggered_by_email' => $user->email,
                            'timestamp' => now()
                        ]);

                    

                        // Show success notification to user
                        if ($totalEmailsSent > 0) {
                            Notification::make()
                                ->title("Notification Sent")
                                ->body("Successfully sent {$totalEmailsSent} email(s) to {$totalOfficesNotified} office(s).")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title("No Notifications Sent")
                                ->body("No eligible offices or users found to notify.")
                                ->warning()
                                ->send();
                        }
                    })
                    ->color('warning')
                    ->icon('heroicon-o-envelope'),

                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();

                        // Super Admin can delete all
                        if ($user->hasRoleSafe('super_admin')) {
                            return true;
                        }

                        // Only show delete if this office has NOT submitted (status -1 or null)
                        return !in_array((string) $record->status, ['0', '1']);
                    }),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make()
                        ->visible(function () {
                            $user = auth()->user();

                            if ($user->hasRoleSafe('super_admin')) {
                                return true;
                            }

                            $requiredDocument = $this->getOwnerRecord();

                            $agencyDepartmentCode = \App\Models\Office::where('office', $requiredDocument->agency_name)
                                ->value('department_code');

                            return $user->department_code === $agencyDepartmentCode;
                        })
                        ->action(function (DeleteBulkAction $action, $records) {
                            $user = auth()->user();

                            $blockedHasCompliance = collect();
                            $allowed              = collect();

                            foreach ($records as $record) {
                                // Block if this office has already submitted (status 0 or 1)
                                if (!$user->hasRoleSafe('super_admin') && in_array((string) $record->status, ['0', '1'])) {
                                    $blockedHasCompliance->push($record);
                                    continue;
                                }

                                $allowed->push($record);
                            }

                            // Delete only allowed records
                            $allowed->each(fn ($record) => $record->delete());

                            // Notify success
                            if ($allowed->isNotEmpty()) {
                                Notification::make()
                                    ->title("{$allowed->count()} office(s) removed successfully.")
                                    ->success()
                                    ->send();
                            }

                            // Notify blocked
                            if ($blockedHasCompliance->isNotEmpty()) {
                                $names = $blockedHasCompliance
                                    ->map(fn ($r) => $r->office?->office ?? $r->department_code)
                                    ->implode(', ');

                                Notification::make()
                                    ->title('Deletion Not Allowed — Compliance Already Submitted')
                                    ->body(
                                        $blockedHasCompliance->count() . ' office(s) were skipped because they have already submitted compliance: ' . $names
                                    )
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }

                        }),
                ]),
            ]);
    }
}
