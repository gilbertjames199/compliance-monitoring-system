<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\ComplyingOffice;
use Illuminate\Console\Command;
use App\Models\RequiredDocument;

class GenerateRecurringCompliance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-recurring-compliance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate next instances for recurring compliance requirements (Testable)';

    /**
     * Execute the console command.
     */
    // public function handle()
    // {
    //      $this->info('Starting recurring compliance generation...');

    //     // Get all active recurring requirements
    //     $requirements = RequiredDocument::where('is_recurring', true)->get();

    //     foreach ($requirements as $req) {

    //         foreach ($req->complyingOffices as $office) {

    //             $currentDueDate = Carbon::parse($office->due_date);

    //             // Calculate next due date
    //             $nextDueDate = $this->calculateNextDueDate($currentDueDate, $req->recurrence_type);

    //             // Check if the next period already exists
    //             $exists = ComplyingOffice::where('requirement_id', $req->id)
    //                 ->where('department_code', $office->department_code)
    //                 ->whereDate('due_date', $nextDueDate->toDateString())
    //                 ->exists();

    //             if (!$exists) {
    //                 ComplyingOffice::create([
    //                     'requirement_id'  => $req->id,
    //                     'department_code' => $office->department_code,
    //                     'status'          => -1, // Not Complied
    //                     'due_date'        => $nextDueDate,
    //                 ]);

    //                 $this->info("Created recurring instance for '{$req->requirement}' ({$office->department_code}) due {$nextDueDate->toDateString()}");
    //             }
    //         }
    //     }

    //     $this->info('Recurring compliance generation completed.');
    // }

    
    // private function calculateNextDueDate(Carbon $currentDate, string $recurrenceType): Carbon
    // {
    //     return match($recurrenceType) {
    //         'quarterly' => $currentDate->copy()->addMonths(3),
    //         'yearly' => $currentDate->copy()->addYear(),
    //         default => $currentDate,
    //     };
    // }





      public function handle()
    {
        $this->info('Starting recurring compliance generation...');

        // Get all recurring requirements
        $requirements = RequiredDocument::where('is_recurring', true)->get();

        foreach ($requirements as $req) {

            foreach ($req->complyingOffices as $office) {

                $currentDueDate = Carbon::parse($office->due_date);

                // TEMPORARY TEST: Next instance in 3 minutes
                $nextDueDate = $this->calculateNextDueDateForTest($currentDueDate);

                // Check if the next period already exists
                $exists = ComplyingOffice::where('requirement_id', $req->id)
                    ->where('department_code', $office->department_code)
                    ->whereDate('due_date', $nextDueDate->toDateString())
                    ->exists();

                if (!$exists) {
                    ComplyingOffice::create([
                        'requirement_id'  => $req->id,
                        'department_code' => $office->department_code,
                        'status'          => -1, // Not Complied
                        'due_date'        => $nextDueDate,
                    ]);

                    $this->info("Created recurring instance for '{$req->requirement}' ({$office->department_code}) due {$nextDueDate}");
                }
            }
        }

        $this->info('Recurring compliance generation completed.');
    }

    /**
     * Calculate next due date for testing: +3 minutes
     */
    private function calculateNextDueDateForTest(Carbon $currentDate): Carbon
    {
        return $currentDate->copy()->addMinutes(3); // next instance in 3 minutes
    }

}
