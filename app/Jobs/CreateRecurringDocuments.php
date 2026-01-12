<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\ComplyingOffice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateRecurringDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $record;
    protected $selectedOffices;
    protected $recurrenceType;
    protected $recurrenceInterval;

    /**
     * Create a new job instance.
     */
    public function __construct($record, $selectedOffices, $recurrenceType, $recurrenceInterval)
    {
        $this->record = $record;
        $this->selectedOffices = $selectedOffices;
        $this->recurrenceType = $recurrenceType;
        $this->recurrenceInterval = $recurrenceInterval;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $baseDateFrom = Carbon::parse($this->record->date_from);
        $baseDueDate = Carbon::parse($this->record->due_date);

        // Create only 1 duplicate
        $newDateFrom = $this->calculateNextDate($baseDateFrom, $this->recurrenceType, $this->recurrenceInterval, 1);
        $newDueDate = $this->calculateNextDate($baseDueDate, $this->recurrenceType, $this->recurrenceInterval, 1);

        // Create duplicate record
        $duplicateRecord = $this->record->replicate();
        $duplicateRecord->date_from = $newDateFrom;
        $duplicateRecord->due_date = $newDueDate;
        $duplicateRecord->save();

        // Create complying offices for duplicate
        foreach ($this->selectedOffices as $deptCode) {
            ComplyingOffice::create([
                'department_code' => $deptCode,
                'requirement_id'  => $duplicateRecord->id,
                'status'          => -1,
                'due_date'        => $newDueDate,
            ]);
        }
    }

     private function calculateNextDate(Carbon $baseDate, string $recurrenceType, ?int $interval, int $occurrence): Carbon
    {
        $date = $baseDate->copy();

        switch ($recurrenceType) {
            case 'yearly':
                return $date->addYears($occurrence);
            case 'quarterly':
                return $date->addMonths(3 * $occurrence);
            case 'semester':
                return $date->addMonths(6 * $occurrence);
            case 'custom':
                return $date->addDays($interval * $occurrence);
            default:
                return $date;
        }
    }
}
