<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use App\Mail\DueDateReminderMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\RequirementDeadlineMail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendRequirementNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $requirementId;

    /**
     * Create a new job instance.
     */
    public function __construct($requirementId = null)
    {
        $this->requirementId = $requirementId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->requirementId === null) {
            $this->multiSend();
            return;
        }

        $record = RequiredDocument::find($this->requirementId);

        if (!$record) return;

        $complyingOffices = ComplyingOffice::where('required_document_id', $record->id)->get();

        foreach ($complyingOffices as $office) {

            $usersQuery = User::where('department_code', $office->department_code);

            // If confidential, only send to super_admin and department_head
            if ($record->is_confidential) {
                $usersQuery->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['super_admin', 'department_head']);
                });
            }

            $users = $usersQuery->get();

            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->queue(new RequirementDeadlineMail($record));
                    Log::info("Email queued for user {$user->id} - Requirement: {$record->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to queue email for user {$user->id}", ['error' => $e->getMessage()]);
                }
            }
        }
    }

    protected function multiSend(): void
    {
        // Get documents due 2 days from now that haven't had reminder sent yet
        $documents = RequiredDocument::whereDate('due_date', now()->addDays(2))
            ->whereNull('reminder_sent_at')
            ->with('complyingOffices')
            ->get();

        if ($documents->isEmpty()) {
            Log::info('No documents with due date in 2 days pending reminders');
            return;
        }

        foreach ($documents as $document) {
            $departmentCodes = $document->complyingOffices->pluck('department_code')->unique();
            
            // Get users in departments and avoid duplicates
            $usersQuery = User::whereIn('department_code', $departmentCodes)->distinct();
            
            // If confidential, only send to super_admin and department_head
            if ($document->is_confidential) {
                $usersQuery->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['super_admin', 'department_head']);
                });
            }
            
            $users = $usersQuery->get();
            
            $emailCount = 0;
            foreach ($users as $user) {
                try {
                    Mail::to($user->email)->queue(new DueDateReminderMail($document));
                    Log::info("Reminder email queued for user {$user->id} - Document: {$document->id}");
                    $emailCount++;
                } catch (\Exception $e) {
                    Log::error("Failed to queue reminder email for user {$user->id}", ['error' => $e->getMessage()]);
                }
            }
            
            // Mark reminder as sent to prevent duplicate emails
            if ($emailCount > 0) {
                $document->update(['reminder_sent_at' => now()]);
            }
        }
    }
}
