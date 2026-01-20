<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateRecurringDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected RequiredDocument $record;
    protected string $recurrenceType;
    protected ?int $recurrenceInterval;

    public function __construct(
        RequiredDocument $record,
        string $recurrenceType,
        ?int $recurrenceInterval
    ) {
        $this->record = $record;
        $this->recurrenceType = $recurrenceType;
        $this->recurrenceInterval = $recurrenceInterval;
    }

    public function handle(): void
    {
        $baseDateFrom = Carbon::parse($this->record->date_from);
        $today = Carbon::today();

        $newDateFrom = $this->calculateNextDate(
            $baseDateFrom,
            $this->recurrenceType,
            $this->recurrenceInterval,
            1
        );

        // Only create if today matches the next occurrence date
        if (!$today->isSameDay($newDateFrom)) {
            Log::info("No recurring documents to create today for requirement ID: {$this->record->id}");
            return;
        }

        // Check if a duplicate already exists
        $exists = RequiredDocument::where('date_from', $newDateFrom)
            ->where('requirement', $this->record->requirement)
            ->where('agency_name', $this->record->agency_name)
            ->exists();

        if ($exists) {
            Log::info("Recurring document already exists for requirement ID: {$this->record->id}");
            $this->record->update(['next_occurrence_created_at' => $newDateFrom]);
            return;
        }

        // ✅ Duplicate required document
        $duplicate = $this->record->replicate();
        $duplicate->date_from = $newDateFrom;
        $duplicate->due_date  = Carbon::parse($this->record->due_date)->addDays(0); // same due date logic
        $duplicate->next_occurrence_created_at = null;
        $duplicate->reminder_sent_at = null;
        $duplicate->save();

        // Load the complying offices reliably
        $this->record->load('complyingOffices');
        $offices = $this->record->complyingOffices;

        if ($offices->isEmpty()) {
            Log::info("No complying offices to replicate for requirement ID: {$this->record->id}");
        } else {
            foreach ($offices as $office) {
                ComplyingOffice::create([
                    'department_code' => $office->department_code,
                    'requirement_id'  => $duplicate->id,
                    'status'          => -1,
                    'due_date'        => $duplicate->due_date,
                ]);
            }
        }

        $this->record->update(['next_occurrence_created_at' => $newDateFrom]);

        Log::info("Created recurring document ID: {$duplicate->id} for requirement ID: {$this->record->id} with ".count($offices)." complying offices replicated.");
    }

    private function calculateNextDate(
        Carbon $baseDate,
        string $recurrenceType,
        ?int $interval,
        int $occurrence
    ): Carbon {
        return match ($recurrenceType) {
            'yearly'    => $baseDate->copy()->addYears($occurrence),
            'quarterly' => $baseDate->copy()->addMonths(3 * $occurrence),
            'semester'  => $baseDate->copy()->addMonths(6 * $occurrence),
            'custom'    => $baseDate->copy()->addDays($interval * $occurrence),
            default     => $baseDate,
        };
    }
}
