<?php

namespace App\Console;

use App\Models\RequiredDocument;
use App\Notifications\RequirementDue;
use App\Jobs\SendRequirementNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Check every day for documents due in 2 days
        // and send emails to complying office users
        $schedule->job(new SendRequirementNotification())->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

}
