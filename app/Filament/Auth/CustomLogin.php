<?php

namespace App\Filament\Auth;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form as ComponentsForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class CustomLogin extends Login
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete()
            ->rules(['exists:users,username'])
            ->validationAttribute('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/login.form.password.label'))
            ->hint(filament()->hasPasswordReset() ? new HtmlString(Blade::render('<x-filament::link :href="filament()->getRequestPasswordResetUrl()" tabindex="3"> {{ __(\'filament-panels::auth/pages/login.actions.request_password_reset.label\') }}</x-filament::link>')) : null)
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    // public function authenticate(): ?LoginResponse
    // {
    //     $data = $this->form->getState();

    //     if (!Auth::attempt([
    //         'username' => $data['username'],
    //         'password' => $data['password'],
    //     ], $data['remember'] ?? false)) {
    //         throw ValidationException::withMessages([
    //             'username' => __('The provided username or password is incorrect.'),
    //         ]);
    //     }

    //     // Return the normal Filament login response
    //     return app(LoginResponse::class);
    // }
    // public function authenticate(): ?LoginResponse
    // {
    //      $data = $this->form->getState();

    //      if(!Auth::attempt([
    //      ]))
    // }
}
