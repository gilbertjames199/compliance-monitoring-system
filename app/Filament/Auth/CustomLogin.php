<?php

namespace App\Filament\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Javarex\DdoLogin\Pages\Login as DdoLogin;

class CustomLogin extends DdoLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUserNameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUserNameFormComponent(): TextInput
    {
        return TextInput::make('UserName')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return TextInput::make('UserPassword')
            ->label('Password')
            ->required()
            ->password()
            ->revealable();
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'UserName'     => $data['UserName'],
            'UserPassword' => $data['UserPassword'],
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(100);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }

        try {
            $data = $this->form->getState();

            // 1. Fetch user from fms database systemusers table
            $user = User::where('UserName', $data['UserName'])->first();

            // 2. Validate existence and MD5 password
            if (! $user || md5($data['UserPassword']) !== $user->UserPassword) {
                throw ValidationException::withMessages([
                    'data.UserName' => __('filament-panels::auth/pages/login.messages.failed'),
                ]);
            }

            // 3. Check if account is active
            if (! $user->is_active) {
                Notification::make()
                    ->title('Account Inactive')
                    ->danger()
                    ->body('Please contact your administrator.')
                    ->send();

                return null;
            }

            // 4. Log the user in
            // Filament::auth()->login($user, $data['remember'] ?? false);
            Auth::guard('web')->login($user, $data['remember'] ?? false);

            // 5. Regenerate session
            session()->regenerate();
          
            return app(LoginResponse::class);

        } catch (ValidationException $e) {
            throw $e;
        }
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.UserName' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}