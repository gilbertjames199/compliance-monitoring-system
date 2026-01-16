<?php

namespace App\Filament\Resources\RequiredDocuments\RelationManagers;

use Dom\Text;
use Carbon\Carbon;
use App\Models\Office;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\DissociateBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
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
                    ->rules(function (callable $get) {
                        // Get the current requirement ID from the parent record
                        $requirementId = $this->getOwnerRecord()->id;

                        return [
                            Rule::unique('complying_offices', 'department_code')
                                ->where(fn($query) =>
                                    $query->where('requirement_id', $requirementId)
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
                                "<a href='".Storage::disk('public')->url($file)."' target='_blank'>"
                                .basename($file)."</a>"
                            )
                            ->implode('<br>');
                    })
                    ->html(),

                Placeholder::make('submitted_at')
                    ->label('Submitted At'),

                Textarea::make('submission_notes')
                    ->label('Submission Notes')
                    ->rows(2)
                    ->disabled()
                    ->columnSpanFull(),

                



                ToggleButtons::make('validation_status')
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
                    ->default(fn ($record) => $record?->validation_status ?? 'pending_review')
                    ->required()
                    ->reactive()
                    ->disabled(fn ($record) => 
                        !$record || 
                        $record->agency_name !== auth()->user()->agency_name ||
                        $record->status !== '1'
                    )
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
                    ->columnSpanFull(),
                
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
                    ->dateTime()
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
                //
            ])
            ->headerActions([
                CreateAction::make(),
                // AssociateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // DissociateAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
