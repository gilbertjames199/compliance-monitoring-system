<?php

namespace Javarex\DdoLogin\Pages;

use Filament\Schemas\Schema;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;

class Edit extends EditProfile
{
    protected function getUserNameFormComponent(): TextInput
    {
        return TextInput::make('username')
                ->label('Username')
                ->required()
                ->autocomplete(false)
                ->autofocus()
                ->extraInputAttributes(['tabindex' => 1]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('username')
                    ->required()
                    ->maxLength(255),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}