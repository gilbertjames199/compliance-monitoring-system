<?php

namespace App\Filament\Resources\RequiredDocuments\Tables;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Office;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Mail;
use App\Mail\RequirementDeadlineMail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Blade;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\Layout\View as LayoutView;

class RequiredDocumentsTable 
{
    protected static ?string $model = RequiredDocument::class;

    public static function configure(Table $table): Table
    {
        // dd(auth()->user());


            // ->query(function ($query) {
            //     $user = auth()->user();
            //     $departmentCode = $user->department_code;
            //     // dd($user);
            //     // If department_code = 25, show all records
            //     if ($departmentCode == 25) {
            //         return $query;
            //     }

            //     // Otherwise, filter records based on related complyingOffices
            //     return $query->whereHas('complyingOffices', function ($q) use ($departmentCode) {
            //         $q->where('department_code', $departmentCode);
            //     });
            // })
            
            
        return $table
             ->modifyQueryUsing(function (Builder $query) {

                $user = auth()->user();
                if (! $user) {
                    return;
                }

                // Superadmin sees everything
                if ($user->hasRole('super_admin')) {
                    return;
                }

                if ($user->department_code == 25 && $user->hasRole('super_admin')) {
                    return;
                }

                // Only show records where the requiring agency matches the user's office name
                $query->where('agency_name', $user->office->office);

                // Optionally hide confidential requirements from AO & Admin
                if ($user->hasAnyRole(['AO', 'admin'])) {
                    $query->where('is_confidential', false);
                }
            })
            
            ->columns([
                TextColumn::make('requirement')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('agency_name')
                    ->label('Requiring Agency')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('year')
                    ->searchable(),
                TextColumn::make('category.category')
                    ->label('Category')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('date_from')
                    ->label('Date From')
                    ->date()
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])// show actions column
            ->recordActions([

                    // Action::make('Notify Office')
                    //     ->action(function ($record, $data) {

                    //         // Get complying offices that are NOT yet complied
                    //         $complyingOffices = ComplyingOffice::where('requirement_id', $record->id)
                    //                                 ->where('status', '!=', '1')
                    //                                 ->get();

                    //         foreach ($complyingOffices as $office) {

                    //             // Users in this department
                    //             $users = User::where('department_code', $office->department_code)->get();

                    //             foreach ($users as $user) {

                    //                 // Skip AO/Admin for confidential requirements
                    //                 if ($record->is_confidential && $user->hasAnyRole(['AO','admin'])) {
                    //                     continue;
                    //                 }

                    //                 Mail::to($user->email)
                    //                     ->send(new RequirementDeadlineMail($record));
                    //             }
                    //         }

                    //     })
                    //     ->color('primary')
                    //     ->icon('heroicon-o-envelope'),

                Action::make('manage_compliance')
                    ->label('View/Update Complying Offices')
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->modalHeading(fn($record) => "Complying Offices for '{$record->requirement}'")
                    ->modalSubmitActionLabel('Save Changes')
                    ->modalWidth('6xl')
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
                            $fields[] = Grid::make(12)->schema([
                                // 🔹 Office Name (read-only)
                                TextInput::make("office_{$office->id}_name")
                                    ->label('Office Name')
                                    ->default($office->office_name)
                                    ->disabled()
                                    ->columnSpan(2),

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
                                    ->columnSpan(2)
                                    ->native(false)
                                    ->reactive(),

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
                                                "<a href='".Storage::disk('public')->url($file)."' target='_blank'>"
                                                .basename($file)."</a>"
                                            )
                                            ->implode('<br>');
                                    })
                                    ->html()
                                    ->columnSpan(2),

                                // 🔹 Validation Status (editable)
                                ToggleButtons::make("office_{$office->id}_validation_status")
                                    ->label('Validation Status')
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
                                    ->default($office->validation_status ?? 'pending_review')
                                    ->required()
                                    ->reactive()
                                    ->dehydrated()
                                    ->disabled(function ($get, $record) use ($office) {
                                        // Check if status is not "Complied" (1)
                                        $isComplied = $get("office_{$office->id}_status") == 1;
                                        
                                        if (!$isComplied) {
                                            return true;
                                        }

                                        // Check if user is from the requiring agency
                                        $user = auth()->user();
                                        $userOfficeName = optional($user->office)->office;
                                        
                                        // Get the agency_name from the record (RequiredDocument)
                                        $isRequiringAgency = $userOfficeName === $record?->agency_name;

                                        // Enable only if user is from requiring agency AND status is complied
                                        return !$isRequiringAgency;
                                    })
                                    ->helperText(function ($get, $record) use ($office) {
                                        $user = auth()->user();
                                        $userOfficeName = optional($user->office)->office;
                                        $isRequiringAgency = $userOfficeName === $record?->agency_name;
                                        $isComplied = $get("office_{$office->id}_status") == 1;

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
                                        $set("office_{$office->id}_validated_at", now());
                                    } else {
                                        $set("office_{$office->id}_validated_at", null);
                                    }
                                    })
                                    ->columnSpan(4),

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
                                    ->columnSpan(4)
                                    ->visible(fn ($get) =>
                                        in_array(
                                            $get("office_{$office->id}_validation_status"),
                                            ['validated', 'returned']
                                        )
                                    )->columnSpan(2),

                            ]);
                        }

                        return $fields;
                    })
                    ->action(function ($record, array $data) {
                        foreach ($record->complyingOffices as $office) {
                            $key = "office_{$office->id}_validation_status";
                            $datetimeKey = "office_{$office->id}_validated_at";

                            if (isset($data[$key])) {
                                $office->update([
                                    'validation_status' => $data[$key],
                                    'validated_at'     => $data[$datetimeKey] ?? null,
                                ]);
                            }
                        }

                    Notification::make()
                        ->title('Validation statuses updated successfully!')
                        ->success()
                        ->send();
                }),
                    
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
