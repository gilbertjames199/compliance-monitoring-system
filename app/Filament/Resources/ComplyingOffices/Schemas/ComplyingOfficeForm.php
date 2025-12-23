<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use App\Models\Office;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ToggleButtons;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_code')
                    ->label('Complying Office')
                    ->options(Office::all()->pluck('office', 'department_code'))
                    ->required(),
                TextInput::make('department_code')
                    ->readOnly(),

                
                Select::make('requirement_id')
                    ->label('Requirement')
                    ->options(\App\Models\RequiredDocument::orderBy('requirement')->pluck('requirement', 'id'))
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Set the TextInput to show the ID
                        $set('requirement_id_text', $state);

                        $requirement = \App\Models\RequiredDocument::find($state);
                        if ($requirement) {
                            $set('agency_name', $requirement->agency_name);
                            // $set('date_from', $requirement->date_from); 
                            // $set('due_date', $requirement->due_date);   
                        } else {
                            $set('agency_name', null);
                            // $set('date_from', null);
                            // $set('due_date', null);
                        }
                    }),

                TextInput::make('requirement_id_text')
                    ->label('Requirement ID')
                    ->readOnly()
                    ->afterStateHydrated(function ($set, $record) {
                        if ($record && $record->requirement_id) {
                            $set('requirement_id_text', $record->requirement_id);
                        }
                    }),


                TextInput::make('agency_name')
                    ->label('Requiring Agency')
                    ->readOnly()
                    ->afterStateHydrated(function ($set, $record) {
                        if ($record && $record->requirement_id) {
                            $requirement = \App\Models\RequiredDocument::find($record->requirement_id);
                            if ($requirement) {
                                $set('agency_name', $requirement->agency_name);
                            }
                        }
                    }),

                    // DatePicker::make('date_from')
                    //     ->label('Date From')
                    //     ->afterStateHydrated(function ($set, $record) {
                    //         if ($record && $record->requirement_id) {
                    //             $requirement = \App\Models\RequiredDocument::find($record->requirement_id);
                    //             if ($requirement) {
                    //                 $set('date_from', $requirement->date_from);
                    //             }
                    //         }
                    //     }),

                    // DatePicker::make('due_date')
                    //     ->label('Due Date')
                    //     ->afterStateHydrated(function ($set, $record) {
                    //         if ($record && $record->requirement_id) {
                    //             $requirement = \App\Models\RequiredDocument::find($record->requirement_id);
                    //             if ($requirement) {
                    //                 $set('due_date', $requirement->due_date);
                    //             }
                    //         }
                    //     }),

                    
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
                    ->required(),

                FileUpload::make('attachment')
                    ->multiple()
                    ->downloadable()
                    ->openable()
                    ->previewable()
                    ->directory('compliance-attachments')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! empty($state)) {
                            $set('status', 'submitted');
                        }
                    })
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
