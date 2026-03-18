<?php

namespace Javarex\DdoLogin\Pages;

use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Auth\Pages\Register as PagesRegister;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Register extends PagesRegister
{
    public static ?string $tagline = null;

    protected static string $layout = 'ddo-login::register';

    public static bool $useUsername = false;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::FiveExtraLarge;
    }

    public function hasLogo(): bool
    {
        return false;
    }

    protected function getLayoutData(): array
    {
        return array_merge(parent::getLayoutData(), [
            'tagline' => static::$tagline ?? config('ddo-login.tagline', 'Online Service Request Portal'),
        ]);
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function form(Schema $schema): Schema
    {
        $components = [
            $this->getNameFormComponent()
                ->extraInputAttributes([
                    'class' => 'dark:text-gray-900',
                ]),
        ];

        if (static::$useUsername) {
            $components[] = $this->getUserNameFormComponent();
        }

        $components[] = $this->getEmailFormComponent()
            ->extraInputAttributes([
                'class' => 'dark:text-gray-900',
            ]);

        $components[] = $this->getPasswordFormComponent()
            ->extraInputAttributes([
                'class' => 'dark:text-gray-900',
            ]);

        $components[] = $this->getPasswordConfirmationFormComponent()
            ->extraInputAttributes([
                'class' => 'dark:text-gray-900',
            ]);

        return $schema->components($components);
    }

    protected function getUserNameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->maxLength(255)
            ->unique($this->getUserModel())
            ->extraInputAttributes([
                'class' => 'dark:text-gray-900',
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getRegisterFormAction()
                ->extraAttributes([
                    'id' => 'register-button'
                ]),
        ];
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        return $data;
    }
}
