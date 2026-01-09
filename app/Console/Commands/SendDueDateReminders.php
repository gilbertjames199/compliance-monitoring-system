<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Models\RequiredDocument;
use App\Mail\DueDateReminderMail;
use Illuminate\Support\Facades\Mail;

class SendDueDateReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:send-due-date-reminders';
    protected $signature = 'reminders:due-documents';


    /**
     * The console command description.
     *
     * @var string
     */
    // protected $description = 'Command description';
    protected $description = 'Send email reminders for documents due in 2 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dueDate = now()->addDays(2)->toDateString();

        $documents = RequiredDocument::where('due_date', now()->addDays(2)->toDateString())->get();

        foreach ($documents as $document) {

            $doc = $document->complyingOffices; // Automatically get users in department
            
            // dd($document->requirement, $doc->pluck('department_code'));
            $users = User::whereIn('department_code', $doc->pluck('department_code'))->get();
            
            foreach ($users as $user) {
                // dd($document);
                Mail::to($user->email)->send(new DueDateReminderMail($document));
            }
        }

        $this->info('Due date reminders sent successfully.');
    }
}
