<?php

namespace App\Filament\Resources\RequiredDocuments\Schemas;

use App\Models\ComplyingOffice;
use App\Models\Office;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class RequiredDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document Details')
                    ->schema([
                        TextInput::make('requirement')->required(),
                        Toggle::make('is_external')->label('Is External?'),
                        TextInput::make('requiring_agency_internal')->label('Requiring Agency (Internal)'),
                        TextInput::make('agency_name')->label('Agency Name'),
                        Toggle::make('is_confidential')->label('Confidential'),
                        DatePicker::make('date_from')->label('Date From'),
                        DatePicker::make('due_date')->label('Due Date'),
                        TextInput::make('year')->numeric()->label('Year'),
                        Toggle::make('is_recurring')->label('Recurring?'),
                        TextInput::make('document_category_id')->required()->label('Document Category ID'),
                    ]),

                Section::make('Complying Offices')
                    ->schema([
                        Select::make('selected_offices')
                            ->label('Select Offices')
                            ->multiple()
                            ->options(Office::pluck('office', 'department_code'))
                            ->reactive()
                            ->helperText('Select multiple offices or click "Select All Offices" below.'),

                        Select::make('status')
                            ->label('Compliance Status')
                            ->options([
                                -1 => 'Not Complied',
                                0  => 'Partially Complied',
                                1  => 'Complied',
                            ])
                            ->default(-1)
                            ->required(),

                        Actions::make([
                            Action::make('select_all')
                                ->label('Select All Offices')
                                ->button()
                                ->color('primary')
                                ->action(function (Get $get, Set $set) {
                                    $set('selected_offices', Office::pluck('department_code')->toArray());
                                }),
                        ]),
                    ]),
                /*TextInput::make('requirement')
                    ->required(),
                TextInput::make('is_external')
                    ->required(),
                TextInput::make('requiring_agency_internal'),
                TextInput::make('agency_name')
                    ->required(),
                TextInput::make('is_confidential')
                    ->required(),
                TextInput::make('date_from')
                    ->required(),
                TextInput::make('due_date')
                    ->required(),
                TextInput::make('year')
                    ->required(),
                TextInput::make('is_recurring')
                    ->required(),
                TextInput::make('document_category_id')
                    ->required(),*/
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['_selected_offices'] = $data['selected_offices'] ?? [];
        $data['_status'] = $data['status'] ?? -1;

        unset($data['selected_offices'], $data['status']);

        return $data;
    }

    /**
     * Hook after creating the RequiredDocument
     */
    public static function afterCreate($record, array $data): void
    {
        $selectedOffices = $data['_selected_offices'] ?? [];
        $status = $data['_status'] ?? -1;

        foreach ($selectedOffices as $deptCode) {
            ComplyingOffice::create([
                'department_code' => $deptCode,
                'requirement_id'  => $record->id,
                'status'          => $status,
            ]);
        }
    }
}
