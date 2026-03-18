<?php

namespace Javarex\DdoLogin\Pages;

use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Auth\Pages\Login as PagesLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\MultiFactor\Contracts\HasBeforeChallengeHook;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

class Login extends PagesLogin
{
    public static ?string $tagline = null;
    // protected string $layout = 'filament.pages.auth.login';
    // protected static string $layout = 'ddo-login::auth.login';
    protected static string $layout = 'ddo-login::login';

    public static bool $useUsername = false;

    public static string $login_type;

    public static bool $registrationEnabled = false;

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
            'registrationEnabled' => static::$registrationEnabled,
        ]);
    }

    public function getHeading(): string | Htmlable
    {
        return '';
        return new HtmlString("<div class='text-gray-600'>Login</div>");
    }


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                static::$useUsername
                    ? $this->getUserNameFormComponent()
                    : $this->getEmailFormComponent()
                        ->extraInputAttributes([
                            'class' => 'dark:text-gray-900',
                            'tabindex' => 1
                        ])
                        // ->label(fn(	$component) => new HtmlString('<span class="dark:text-gray-800">'.ucwords($component->getName()).'</span>'))
                        ,
                $this->getPasswordFormComponent()
                    ->extraInputAttributes([
                        'class' => 'dark:text-gray-900'
                    ]),
                    // ->label(fn(	$component) => new HtmlString('<span class="dark:text-gray-800">'.ucwords($component->getName()).'</span>')),
                $this->getRememberFormComponent()
                    ->extraInputAttributes([
                        'class' => 'dark:text-gray-900'
                    ])
                    // ->label(fn(	$component) => new HtmlString('<span class="dark:text-gray-800">'.ucwords($component->getName()).'</span>')),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction()
                ->extraAttributes([
                    'id' => 'login-button'
                ]),
        ];
    }

    protected function getUserNameFormComponent(): TextInput
    {
        return TextInput::make('username')
                ->label('Username')
                ->required()
                ->autocomplete(false)
                ->autofocus()
                ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = static::$login_type;

        return [
            $login_type => $data[$login_type],
            'password'  => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        $login_type = static::$login_type;
        throw ValidationException::withMessages([
            "data.$login_type" => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
    
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(100);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authProvider = $authGuard->getProvider(); /** @phpstan-ignore-line */
        $credentials = $this->getCredentialsFromFormData($data);

        $user = $authProvider->retrieveByCredentials($credentials);

        if ((! $user) || (! $authProvider->validateCredentials($user, $credentials))) {
            $this->userUndertakingMultiFactorAuthentication = null;

            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        if (
            filled($this->userUndertakingMultiFactorAuthentication) &&
            (decrypt($this->userUndertakingMultiFactorAuthentication) === $user->getAuthIdentifier())
        ) {
            $this->multiFactorChallengeForm->validate();
        } else {
            foreach (Filament::getMultiFactorAuthenticationProviders() as $multiFactorAuthenticationProvider) {
                if (! $multiFactorAuthenticationProvider->isEnabled($user)) {
                    continue;
                }

                $this->userUndertakingMultiFactorAuthentication = encrypt($user->getAuthIdentifier());

                if ($multiFactorAuthenticationProvider instanceof HasBeforeChallengeHook) {
                    $multiFactorAuthenticationProvider->beforeChallenge($user);
                }

                break;
            }

            if (filled($this->userUndertakingMultiFactorAuthentication)) {
                $this->multiFactorChallengeForm->fill();

                return null;
            }
        }

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        }, $data['remember'] ?? false)) {
            $this->fireFailedEvent($authGuard, $user, $credentials);
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

}
