<?php

namespace Javarex\DdoLogin\Commands;

use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    // protected $signature = 'ddo-login:install';
    protected $signature = 'ddo-login:install {
        --theme : Update the Filament theme.css file to include plugin styles and sources
    }';
    protected $description = 'Install the DDO Login plugin with optional publish steps';

    public function handle(): int
    {
        $this->info('🚀 Installing DDO Login Plugin...');

         // Optional theme.css modification
        if ($this->option('theme')) {
            $this->updateThemeCss();
        } else {
            $this->warn('⚠️ Skipping theme.css modification. Use --theme-css to add it.');
            $migrationPath = database_path('migrations');
            $migrationPattern = 'add_username_to_users_table.php';
    
            // Check if any migration file contains that name
            $existingMigration = collect(File::files($migrationPath))
                ->first(fn($file) => str_contains($file->getFilename(), $migrationPattern));
                
            if ($existingMigration) {
                $this->info('✅ Migration already exists: ' . $existingMigration->getFilename());
            } else {
                $this->info('📦 Publishing migration...');
                 $this->call('vendor:publish', [
                    '--tag' => 'migration',
                    '--force' => true,
                ]);
                $this->info('✅ Migration published successfully!');
            }
           
            
            ##Add image
            if (! File::isDirectory(public_path('images'))) {
                File::makeDirectory(public_path('images'));
            } 
            
            File::copy(
                __DIR__ . '/../../resources/images/login-logo.png',
                public_path('images/login-logo.png')
            );
    
            if ($this->confirm('Do you want to publish the config file?', true)) {
                $this->call('vendor:publish', [
                    '--tag' => 'ddo-login-config',
                    '--force' => true,
                ]);
            }
    
            if ($this->confirm('Do you want to publish the Edit Profile page?', false)) {
                $this->call('vendor:publish', [
                    '--tag' => 'ddo-login-edit',
                    '--force' => true,
                ]);
            }
    
            if ($this->confirm('Do you want to publish the Login page?', false)) {
                $this->call('vendor:publish', [
                    '--tag' => 'ddo-login',
                    '--force' => true,
                ]);
            }
    
            if ($this->confirm('Do you want to publish the login view?', false)) {
                $this->call('vendor:publish', [
                    '--tag' => 'ddo-login-views',
                    '--force' => true,
                ]);
            }
    
    
            $providerPath = app_path('Providers/Filament/AdminPanelProvider.php');
    
            if (File::exists($providerPath)) {
                $contents = File::get($providerPath);
    
                if (! str_contains($contents, "LoginDdoPlugin::make()")) {
                    $updated = str_replace(
                        "->plugins([",
                        "->plugins([\n                \\Javarex\\DdoLogin\\LoginDdoPlugin::make(),",
                        $contents
                    );
    
                    File::put($providerPath, $updated);
                    $this->info('✅ LoginDdoPlugin added to AdminPanelProvider.');
                } else {
                    $this->warn('⚠️ LoginDdoPlugin already exists in AdminPanelProvider.');
                }
            } else {
                $this->error('❌ AdminPanelProvider not found.');
            }
    
            $this->info('✅ DDO Login installation complete!');
        }


        return self::SUCCESS;
    }

    public function updateThemeCss(): void
    {
        $themeCss = resource_path('css/filament/admin/theme.css');

        if (!File::exists($themeCss)) {
            $this->error("❌ theme.css not found at: {$themeCss}");
        }

        $content = File::get($themeCss);

        // Lines to add
        $importLine = "@import '../../../../vendor/javarex/ddo-login/resources/css/plugin.css';";
        $sourceLine = "@source '../../../../vendor/javarex/ddo-login/resources/views/**/*';";

        $updated = $content;

        // 1️⃣ Add @import if missing
        if (!str_contains($content, $importLine)) {
            if (preg_match_all('/^@import.*$/m', $content, $matches)) {
                // After last @import
                $lastImport = end($matches[0]);
                $updated = str_replace(
                    $lastImport,
                    $lastImport . PHP_EOL . $importLine,
                    $updated
                );
            } else {
                // No @import section — add to top
                $updated = $importLine . PHP_EOL . $updated;
            }

            $this->info('✅ Added plugin.css import to theme.css');
        } else {
            $this->info('ℹ️ plugin.css import already exists');
        }

        // 2️⃣ Add @source if missing
        if (!str_contains($updated, $sourceLine)) {
            if (preg_match_all('/^@source.*$/m', $updated, $matches)) {
                // After last @source
                $lastSource = end($matches[0]);
                $updated = str_replace(
                    $lastSource,
                    $lastSource . PHP_EOL . $sourceLine,
                    $updated
                );
            } else {
                // No @source section — append at the bottom
                $updated .= PHP_EOL . $sourceLine;
            }

            $this->info('✅ Added @source line to theme.css');
        } else {
            $this->info('ℹ️ @source line already exists');
        }

        File::put($themeCss, $updated);
    }
}
