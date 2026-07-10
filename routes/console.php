<?php

use App\Models\RequiredDocument;
use Illuminate\Foundation\Inspiring;
use App\Jobs\CreateRecurringDocuments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::job(new SendRequirementNotification())
//     ->everyMinute()
//     ->withoutOverlapping();
// Handled by supervisor due-date-reminders

// Schedule::call(function () {
//     RequiredDocument::where('is_recurring', true)
//         ->whereNotNull('recurrence_type')
//         ->each(function ($record) {
//             CreateRecurringDocuments::dispatch(
//                 $record,
//                 $record->recurrence_type,
//                 $record->recurrence_interval
//             );
//         });
// })
// ->everyFiveSeconds()
// ->name('create-recurring-documents')
// ->withoutOverlapping();

Schedule::command('compliance:send-reminders')->dailyAt('08:00');