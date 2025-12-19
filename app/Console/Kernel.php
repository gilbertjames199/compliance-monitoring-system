<?php

namespace App\Console;

use App\Models\RequiredDocument;
use App\Notifications\RequirementDue;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
        $tomorrow = now()->addDays(2)->toDateString();

        $requirements = RequiredDocument::whereDate('due_date', $tomorrow)->get();

        foreach ($requirements as $requirement) {
            $offices = $requirement->complyingOffices;
            foreach ($offices as $office) {
                $office->users->each(function ($user) use ($requirement) {
                    $user->notify(new RequirementDue($requirement));
                });
            }
        }
    })->dailyAt('10:20'); // run daily at 8 AM

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
