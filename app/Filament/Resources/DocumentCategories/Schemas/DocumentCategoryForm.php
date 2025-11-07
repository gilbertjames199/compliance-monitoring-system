<?php

namespace App\Filament\Resources\DocumentCategories\Schemas;

use App\Models\Office;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;

class DocumentCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //     TextInput::make('category')
                //         ->required()
                View::make('forms.components.sticky-category')
                ->schema([
                        TextInput::make('category')
                        ->required()
                ]),
                // ->columnSpanFull(),
                // Section::make()
                //     ->schema([
                //         TextInput::make('category')
                //             ->label('Category')
                //             ->columnSpanFull()
                //             ->required(),
                //     ])
                //     ->extraAttributes([
                //         'class' => 'sticky top-0 bg-white z-50 p-4 shadow-sm',
                //         // 🧠 "sticky" keeps it fixed,
                //         // "top-0" pins it to the top,
                //         // "bg-white" avoids transparency overlap
                //         // "z-50" ensures it's above other elements
                //         // "p-4" adds spacing, "shadow-sm" gives a subtle border look
                //     ]),
                // 🔁 Repeater for Required Documents

                Repeater::make('requiredDocuments')
                    ->label('Required Documents')
                    ->relationship('requiredDocuments')
                    ->schema([
                        Section::make('Details')
                        ->columns(2)
                        ->schema([
                            TextInput::make('requirement')->required(),
                            TextInput::make('requiring_agency_internal'),
                            TextInput::make('agency_name')->label('Requiring Agency'),
                            DatePicker::make('date_from'),
                            DatePicker::make('due_date'),
                            TextInput::make('year'),
                            Toggle::make('is_confidential')->label('Confidential'),
                            Toggle::make('is_external')->label('External'),
                            Toggle::make('is_recurring')->label('Recurring'),
                        ]),

                        Section::make('Complying Offices')
                            ->schema([
                                Select::make('complying_offices')
                                ->label('Complying Offices')
                                ->multiple()
                                ->options(Office::all()->pluck('office', 'department_code'))
                                ->preload()
                                ->searchable()
                                ->helperText('Select one or more offices that must comply with this requirement.')
                                ->suffixAction(
                                    Action::make('addAll')
                                        ->label('Add All Offices')
                                        ->icon('heroicon-o-plus')
                                        ->action(function ($state, callable $set) {
                                            $set('complying_offices', Office::pluck('department_code')->toArray());
                                        })
                                    ),
                                Select::make('status')
                                    ->options([
                                        -1 => 'Not Complied',
                                        0 => 'Partially Complied',
                                        1 => 'Complied',
                                    ])
                                    ->default(-1),
                            ])
                    ])
            ->columnSpanFull()
            ]);
    }
// ->visible(fn ($get) => blank($get('id')))
    //     Repeater::make('complyingOffices')
            //         ->relationship()
            //         ->label('Complying Offices')
            //         ->schema([
            //             // Select::make('department_code')
            //             //     ->label('Office')
            //             //     ->options(fn () => Office::pluck('office', 'department_code'))
            //             //     ->required(),
            //             Select::make('selected_offices')
            //         ->label('Complying Offices')
            //         ->multiple()
            //         ->searchable()
            //         ->options(fn () => Office::pluck('office', 'department_code'))
            //         ->helperText('Select the offices required to comply with this document.'),

            //             Select::make('status')
            //                 ->label('Status')
            //                 ->options([
            //                     -1 => 'Not Complied',
            //                     0 => 'Partially Complied',
            //                     1 => 'Complied',
            //                 ])
            //                 ->default(-1)
            //                 ->required(),
            //             Actions::make([
            //                 Action::make('add_all_offices')
            //                     ->label('Add All Offices')
            //                     ->icon('heroicon-o-building-office-2')
            //                     ->action(function (callable $set) {
            //                         $allOffices = Office::pluck('department_code')->toArray();
            //                         $set('selected_offices', $allOffices);
            //                     }),
            //                 ]),
            //         ])
            //         ->orderable(false)
            //         ->collapsible()
            //         ->createItemButtonLabel('Add Office')
            //         ->afterStateHydrated(function ($state, Set $set) {
            //             // Ensure each has default status if not set
            //             $set('status', $state['status'] ?? -1);
            //         }),

            //     // // ✅ Multi-select for offices (not repeater)
            //     // Select::make('selected_offices')
            //     //     ->label('Complying Offices')
            //     //     ->multiple()
            //     //     ->searchable()
            //     //     ->options(fn () => Office::pluck('office', 'department_code'))
            //     //     ->helperText('Select the offices required to comply with this document.'),

            //     // // ✅ Shared status for all selected offices
            //     // Select::make('status')
            //     //     ->label('Status (applies to all selected offices)')
            //     //     ->options([
            //     //         'Pending' => 'Pending',
            //     //         'Complied' => 'Complied',
            //     //         'Not Applicable' => 'Not Applicable',
            //     //     ])
            //     //     ->default('Pending'),

            //     // // ⚡ "Add All Offices" button
            //     // Actions::make([
            //     //     Action::make('add_all_offices')
            //     //         ->label('Add All Offices')
            //     //         ->icon('heroicon-o-building-office-2')
            //     //         ->action(function (callable $set) {
            //     //             $allOffices = Office::pluck('department_code')->toArray();
            //     //             $set('selected_offices', $allOffices);
            //     //         }),
            //     //     ]),
}
