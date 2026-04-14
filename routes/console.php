<?php

use App\Models\Requirement;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Inspiring;
use App\Jobs\CreateRecurringDocuments;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendRequirementNotification;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendRequirementNotification())
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(function () {
    RequiredDocument::where('is_recurring', true)
        ->whereNotNull('recurrence_type')
        ->each(function ($record) {
            CreateRecurringDocuments::dispatch(
                $record,
                $record->recurrence_type,
                $record->recurrence_interval
            );
        });
})
->everyFiveSeconds()
->name('create-recurring-documents')
->withoutOverlapping();
