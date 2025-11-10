<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use App\Models\Office;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_code')
                    ->label('Office')
                    ->options(Office::all()->pluck('office', 'department_code'))
                    ->required(),
                Select::make('status')
                    ->label('Compliance Status')
                    ->options([
                        -1 => 'Not Complied',
                        0  => 'Partially Complied',
                        1  => 'Complied',
                    ])
                    ->default(-1)
                    ->required(),
                // TextInput::make('department_code')
                //     ->required(),
                Select::make('require')
                    ->label('Compliance Status')
                    ->options([
                        -1 => 'Not Complied',
                        0  => 'Partially Complied',
                        1  => 'Complied',
                    ])
                    ->default(-1)
                    ->required(),
                TextInput::make('requirement_id')
                    ->required(),
                // TextInput::make('status')
                //     ->required(),
            ]);
    }
}
