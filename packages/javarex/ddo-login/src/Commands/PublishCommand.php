<?php

namespace Javarex\DdoLogin\Commands;

use Illuminate\Console\Command;

class PublishCommand extends Command
{
    protected $signature = 'ddo-login:publish';
    protected $description = 'Publish ddo-login file/s';

    public function handle(): int
    {
        $this->info('DDO Login publish file/s');

        $choice = $this->choice(
            'What file do you want to publish?',
            [
                'All',
                'Config',
                'Views',
                'Login Page',
                'Edit Account Page',
            ],
            0 // 👈 default is "All"
        );

        match ($choice) {
            'All' => function () {
                $this->call('vendor:publish', ['--tag' => 'ddo-login-config', '--force' => true]);
                $this->call('vendor:publish', ['--tag' => 'ddo-login-views', '--force' => true]);
                $this->call('vendor:publish', ['--tag' => 'ddo-login', '--force' => true]);
                $this->call('vendor:publish', ['--tag' => 'ddo-login-edit', '--force' => true]);
            },
            'Config' =>  $this->call('vendor:publish', ['--tag' => 'ddo-login-config', '--force' => true]),
            'Views' =>  $this->call('vendor:publish', ['--tag' => 'ddo-login-views', '--force' => true]),
            'Login Page' =>  $this->call('vendor:publish', ['--tag' => 'ddo-login', '--force' => true]),
            'Edit Account Page' => $this->call('vendor:publish', ['--tag' => 'ddo-login-edit', '--force' => true]),
            default =>  $this->warn("❌ Unknown choice: $choice"),
        };

        return self::SUCCESS;
    }
}