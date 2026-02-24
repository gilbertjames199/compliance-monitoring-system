<?php

namespace App\Jobs;

use App\Mail\DueDateReminderMail;
use App\Mail\RequirementDeadlineMail;
use App\Models\ComplyingOffice;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                $allowedUserIds = DB::connection('mysql')
                    ->table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereIn('roles.name', ['super_admin', 'department_head'])
                    ->where('model_type', User::class)
                    ->pluck('model_id')
                    ->toArray();
                $usersQuery->whereIn('recid', $allowedUserIds);
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
            // If confidential, only send to super_admin and department_head
            if ($document->is_confidential) {
                $allowedUserIds = DB::connection('mysql')
                    ->table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereIn('roles.name', ['super_admin', 'department_head'])
                    ->where('model_type', User::class)
                    ->pluck('model_id')
                    ->toArray();
                $usersQuery->whereIn('recid', $allowedUserIds);
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
