<?php

namespace App\Filament\Resources\RequiredDocuments\RelationManagers;

use Dom\Text;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Office;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\ComplyingOffice;
use Illuminate\Validation\Rule;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequirementDeadlineMail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\RelationManagers\RelationManager;

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
                    ->helperText('Each office can only be added once per requirement.'),


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



                DateTimePicker::make('submitted_at')
                    ->label('Submitted At')
                    ->disabled(),

                Textarea::make('submission_notes')
                    ->label('Submission Notes')
                    ->rows(2)
                    ->disabled()
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
                        $userOfficeName = optional($user->office)->office;
                        $isRequiringAgency = $userOfficeName === $requiredDocument->agency_name;
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
                            return true; // disable on create
                        }

                        $user = auth()->user();

                        // Get the parent RequiredDocument record to access agency_name
                        $requiredDocument = $this->getOwnerRecord();

                        // Check if user's office name matches the requiring agency
                        $userOfficeName = optional($user->office)->office;
                        $isRequiringAgency = $userOfficeName === $requiredDocument->agency_name;

                        // Alternative: Also check if user's department_code matches
                        // (uncomment if you want to enable for users in the same department)
                        // $userDeptCode = $user->department_code;
                        // $isRequiringAgency = $isRequiringAgency || $userDeptCode === $requiredDocument->department_code;

                        // Enable only if:
                        // 1. User is from the requiring agency, AND
                        // 2. The compliance status is 1 (Complied)
                        $isComplied = (int)$record->status === 1;

                        return !($isRequiringAgency && $isComplied);
                    })

                    ->afterStateUpdated(function ($state, $set, $record) {
                        // Set validated_at when validation_status becomes "validated"
                        if (in_array($state, ['validated', 'returned'])) {
                            $set('validated_at', now());
                        } else {
                            $set('validated_at', null);
                        }
                    })
                    ->dehydrated(),

                DateTimePicker::make('validated_at')
                    ->label(fn ($get) => $get('validation_status') === 'validated' ? 'Validated At' : ($get('validation_status') === 'returned' ? 'Returned At' : ''))
                    ->disabled()
                    ->dehydrated() // <-- important
                    ->displayFormat('m/d/Y h:i A')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state) : null)
                    ->seconds(false)
                    ->visible(fn ($get) => in_array($get('validation_status'), ['validated', 'returned'])),

                Textarea::make('admin_remarks')
                    ->label('Remarks')
                    ->nullable()
                    ->rows(2)
                    ->required()
                    ->columnSpanFull()
                    ->disabled(function ($record) {
                            if (!$record) {
                                return true; // disable on create
                            }

                            $user = auth()->user();

                            // Get the parent RequiredDocument record to access agency_name
                            $requiredDocument = $this->getOwnerRecord();

                            // Check if user's office name matches the requiring agency
                            $userOfficeName = optional($user->office)->office;
                            $isRequiringAgency = $userOfficeName === $requiredDocument->agency_name;

                            // Enable only if:
                            // 1. User is from the requiring agency, AND
                            // 2. The compliance status is 1 (Complied)
                            $isComplied = (int)$record->status === 1;

                            return !($isRequiringAgency && $isComplied);
                        })
                        ->helperText(function ($record) {
                            if (!$record) {
                                return '';
                            }

                            $user = auth()->user();
                            $requiredDocument = $this->getOwnerRecord();
                            $userOfficeName = optional($user->office)->office;
                            $isRequiringAgency = $userOfficeName === $requiredDocument->agency_name;
                            $isComplied = (int)$record->status === 1;

                            if (!$isRequiringAgency) {
                                return 'Only the requiring agency can add remarks.';
                            }

                            if (!$isComplied) {
                                return 'Remarks can only be added when the status is "Complied".';
                            }

                            return 'Add validation remarks for this submission.';
                        }),     
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
                    ->options([
                        '-1' => 'Not Complied',
                        '0'  => 'Partially Complied',
                        '1'  => 'Complied',
                    ]),

                SelectFilter::make('validation_status')
                    ->label('Validation Status')
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

                        $requirement = $this->getOwnerRecord();

                        // Get complying offices that are NOT yet complied
                        $complyingOffices = ComplyingOffice::where('required_document_id', $requirement->id)
                            ->where('status', '!=', 1)
                            ->get();

                        foreach ($complyingOffices as $office) {

                            // Users in this department
                            $users = User::where('department_code', $office->department_code)->get();

                            foreach ($users as $user) {

                                // Skip AO/Admin for confidential requirements
                                if ($requirement->is_confidential && $user->hasAnyRole(['AO', 'admin'])) {
                                    continue;
                                }

                                Mail::to($user->email)
                                    ->send(new RequirementDeadlineMail($requirement));
                            }
                        }
                    })
                    ->color('warning')
                    ->icon('heroicon-o-envelope'),

                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make()
                    ->visible(function () {
                        $user = auth()->user();
                        $requiredDocument = $this->getOwnerRecord();
                        $userOfficeName = optional($user->office)->office;
                        
                        // Only show create button if user is from the requiring agency
                        return $userOfficeName === $requiredDocument->agency_name;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make()
                     ->visible(function () {
                            $user = auth()->user();
                            $requiredDocument = $this->getOwnerRecord();
                            $userOfficeName = optional($user->office)->office;
                            
                            // Only show create button if user is from the requiring agency
                            return $userOfficeName === $requiredDocument->agency_name;
                        }),
                ]),
            ]);
    }
}
