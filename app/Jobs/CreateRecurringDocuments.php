<?php

namespace App\Jobs;

use Carbon\Carbon;
use App\Models\RequiredDocument;
use App\Models\ComplyingOffice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        /**
         * 🔒 STEP 1: Get the LATEST generated document
         */
        $latest = RequiredDocument::with('complyingOffices')
            ->where('requirement', $this->record->requirement)
            ->where('agency_name', $this->record->agency_name)
            ->orderBy('date_from', 'desc')
            ->first();

        if (!$latest || $latest->complyingOffices->isEmpty()) {
            Log::warning('Recurring skipped: no source or offices missing', [
                'requirement' => $this->record->requirement,
            ]);
            return;
        }

        /**
         * 📆 STEP 2: Compute next date_from
         */
        $nextDateFrom = $this->calculateNextDate(
            Carbon::parse($latest->date_from),
            $this->recurrenceType,
            $this->recurrenceInterval
        );

        // ⛔ Only run on the exact recurrence day
        if (!Carbon::today()->isSameDay($nextDateFrom)) {
            return;
        }

        /**
         * ⛔ STEP 3: Prevent duplicate generation
         */
        $exists = RequiredDocument::where('requirement', $latest->requirement)
            ->where('agency_name', $latest->agency_name)
            ->whereDate('date_from', $nextDateFrom)
            ->exists();

        if ($exists) {
            return;
        }

        /**
         * 📄 STEP 4: Duplicate document
         */
        $duplicate = $latest->replicate([
            'created_at',
            'updated_at',
            'is_recurring',
            'recurrence_type',
            'recurrence_interval',
            'reminder_sent_at',
        ]);

        /**
         * ✅ FIXED DATE LOGIC
         * Preserve ORIGINAL duration (direction-safe)
         */
        $originalDateFrom = Carbon::parse($latest->date_from);
        $originalDueDate  = Carbon::parse($latest->due_date);

        $daysDiff = $originalDateFrom->diffInDays($originalDueDate, false);

        $duplicate->date_from = $nextDateFrom->copy();
        $duplicate->due_date  = $nextDateFrom->copy()->addDays($daysDiff);

        // 🔒 Disable recurrence on generated record
        $duplicate->is_recurring = false;
        $duplicate->recurrence_type = null;
        $duplicate->recurrence_interval = null;

        $duplicate->save();

        /**
         * 🏢 STEP 5: Copy complying offices
         */
        foreach ($latest->complyingOffices as $office) {
            ComplyingOffice::create([
                'department_code' => $office->department_code,
                'required_document_id'  => $duplicate->id,
                'status'          => -1,
            ]);
        }

        Log::info('Recurring document created successfully', [
            'from_id' => $latest->id,
            'to_id'   => $duplicate->id,
        ]);
    }

    /**
     * 📆 Calculate next recurrence date
     */
    private function calculateNextDate(
        Carbon $date,
        string $type,
        ?int $interval
    ): Carbon {
        return match ($type) {
            'yearly'    => $date->copy()->addYear(),
            'quarterly' => $date->copy()->addMonths(3),
            'semester'  => $date->copy()->addMonths(6),
            'custom'    => $date->copy()->addDays($interval ?? 0),
            default     => $date->copy(),
        };
    }
}
