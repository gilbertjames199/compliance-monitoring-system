<?php

namespace Javarex\DdoLogin\Commands;

use Illuminate\Console\Command;

class HelpCommand extends Command
{
    protected $signature = 'ddo-login:help';
    protected $description = 'Show help information for the DDO Login plugin';

    public function handle(): int
    {
        $this->info("🔐 DDO Login Plugin - Help");
        $this->line('');
        $this->line('Available commands:');
        $this->line('  php artisan ddo-login:install   -> Publish config, migrations, and register plugin');
        $this->line('  php artisan ddo-login:publish   -> Publish all package assets (config, views, migrations)');
        $this->line('  php artisan ddo-login:help      -> Show this help menu');
        $this->line('');


        return self::SUCCESS;
    }
}
