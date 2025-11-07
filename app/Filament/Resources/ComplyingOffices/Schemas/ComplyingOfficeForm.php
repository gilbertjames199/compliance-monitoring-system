<?php

namespace App\Filament\Resources\ComplyingOffices\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ComplyingOfficeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('department_code')
                    ->required(),
                TextInput::make('requirement_id')
                    ->required(),
                TextInput::make('status')
                    ->required(),
            ]);
    }
}
