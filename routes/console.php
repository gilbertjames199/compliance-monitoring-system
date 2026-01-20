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

Schedule::job(new SendRequirementNotification())->everyTenSeconds();


// Check and create recurring documents every 5 seconds
// Schedule::call(function () {
//     $records = RequiredDocument::where('is_recurring', true)
//         ->whereNotNull('recurrence_type')
//         ->get();
    
//     if ($records->isEmpty()) {
//         Log::info('No recurring documents found');
//         return;
//     }
    
//     foreach ($records as $record) {
//         // Get complying offices for this requirement
//         $selectedOffices = ComplyingOffice::where('requirement_id', $record->id)
//             ->pluck('department_code')
//             ->toArray();
        
//         // Skip if no complying offices (these documents are incomplete)
//         if (empty($selectedOffices)) {
//             Log::info("No complying offices found for requirement ID: {$record->id}");
//             continue;
//         }
        
//         // Dispatch job to create next occurrence if it's time
//         CreateRecurringDocuments::dispatch(
//             $record,
//             $record->recurrence_type,
//             $record->recurrence_interval
//         );
//     }
// })->everyFiveSeconds()->name('create-recurring-documents');

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
