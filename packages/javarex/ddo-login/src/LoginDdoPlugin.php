<?php

namespace Javarex\DdoLogin;

use Filament\Panel;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Javarex\DdoLogin\Pages\Edit;
use Javarex\DdoLogin\Pages\Login;
use Javarex\DdoLogin\Pages\Register;

class LoginDdoPlugin implements Plugin
{

    protected bool $useUsername = false;
    protected string | null $tagline = null;
    protected bool $registration = false;
    
    public function getId(): string
    {
        return 'javrex-ddologin';
    }

    public static function make(): LoginDdoPlugin
    {
        return new LoginDdoPlugin();
    }

    public function useUsername(bool $value = true): static
    {
        $this->useUsername = $value;

        return $this;
    }

    public function tagline(string | null $value = null): static
    {
        $this->tagline = $value;

        return $this;
    }

    public function registration(bool $value = true): static
    {
        $this->registration = $value;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $useUsername = config('ddo-login.use_username_login');
        $registrationEnabled = $this->registration || config('ddo-login.registration', false);

        Login::$useUsername = $useUsername;
        Login::$login_type = $useUsername ? 'username' : 'email';
        Login::$tagline = $this->tagline ?? config('ddo-login.tagline');
        Login::$registrationEnabled = $registrationEnabled;

        $panel
            ->login(Login::class)
            ->profile(Edit::class)
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label(fn() => auth()->user()->name)->url(''),
                // 'edit-profile' => fn(Action $action) => $action->make('edit-profile')->url(fn (): string => Edit::getUrl())->label('Edit Account')->icon('heroicon-o-pencil-square'),
                'logout' => fn(Action $action) => $action->label('Log out')
            ]);

        if ($registrationEnabled) {
            Register::$useUsername = $useUsername;
            Register::$tagline = $this->tagline ?? config('ddo-login.tagline');
            $panel->registration(Register::class);
        }
    }

    public function boot(Panel $panel): void
    {
    }
}