<?php

namespace App\Filament\Resources\Offices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // TextInput::make('department_code')
                //     ->readOnly()
                //     ->required(),
                // TextInput::make('ffunccod'),
                TextInput::make('office')
                    ->required(),
                TextInput::make('short_name'),
                // TextInput::make('empl_id'),
                // TextInput::make('designation'),
            ])
            ->columns(3);
    }
}
