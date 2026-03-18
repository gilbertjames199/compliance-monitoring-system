<?php

namespace Javarex\DdoLogin\Commands;

use Filament\Commands\MakeUserCommand as FilamentMakeUserCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[AsCommand(name: 'make:filament-user', aliases: [
    'filament:make-user',
    'filament:user',
    'ddo:make-user',
])]
class MakeUserCommand extends FilamentMakeUserCommand
{
    protected $description = 'Create a new Filament user with username support';

    protected $name = 'make:filament-user';

    /**
     * @var array<string>
     */
    protected $aliases = [
        'filament:make-user',
        'filament:user',
        'ddo:make-user',
    ];

    /**
     * Check if the users table has a username column
     */
    protected function hasUsernameColumn(): bool
    {
        $userModel = static::getUserModel();
        $table = (new $userModel)->getTable();

        return Schema::hasColumn($table, 'username');
    }

    /**
     * @return array<InputOption>
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            new InputOption(
                name: 'username',
                shortcut: null,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'A unique username for the user (required if username column exists)',
            ),
        ]);
    }

    /**
     * @return array{'name': string, 'email': string, 'password': string, 'username'?: string}
     */
    protected function getUserData(): array
    {
        $name = $this->options['name'] ?? text(
            label: 'Name',
            required: true,
        );

        $email = $this->options['email'] ?? text(
            label: 'Email address',
            required: true,
            validate: fn (string $email): ?string => match (true) {
                ! filter_var($email, FILTER_VALIDATE_EMAIL) => 'The email address must be valid.',
                static::getUserModel()::query()->where('email', $email)->exists() => 'A user with this email address already exists.',
                default => null,
            },
        );

        $data = [
            'name' => $name,
            'email' => $email,
        ];

        // Only prompt for username if the column exists in the users table
        if ($this->hasUsernameColumn()) {
            $username = $this->options['username'] ?? text(
                label: 'Username',
                required: true,
                validate: function (string $username): ?string {
                    if (empty($username)) {
                        return 'Username is required.';
                    }

                    if (static::getUserModel()::query()->where('username', $username)->exists()) {
                        return 'A user with this username already exists.';
                    }

                    return null;
                },
            );

            $data['username'] = $username;
        }

        $data['password'] = Hash::make($this->options['password'] ?? password(
            label: 'Password',
            required: true,
        ));

        return $data;
    }

    protected function sendSuccessMessage(Model & Authenticatable $user): void
    {
        $loginUrl = \Filament\Facades\Filament::getLoginUrl();
        $identifier = $user->getAttribute('username') ?? $user->getAttribute('email') ?? 'You';

        $this->components->info("Success! {$identifier} may now log in at {$loginUrl}");
    }
}
