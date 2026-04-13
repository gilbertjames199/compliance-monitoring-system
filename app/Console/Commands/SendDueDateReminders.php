<?php

namespace App\Console\Commands;

use App\Mail\DueDateReminderMail;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDueDateReminders extends Command
{
    protected $signature = 'reminders:due-documents';
    protected $description = 'Send email reminders for documents due in 2 days';

    public function handle()
    {
        while (true) {
            $documents = RequiredDocument::where('due_date', now()->addDays(2)->toDateString())->get();

            foreach ($documents as $document) {
                $doc = $document->complyingOffices;
                $users = User::whereIn('department_code', $doc->pluck('department_code'))->get();

                foreach ($users as $user) {
                    // Prevent duplicate emails
                    $cacheKey = "due_reminder_{$document->id}_user_{$user->id}";
                    if (Cache::has($cacheKey)) {
                        continue;
                    }

                    $officeName = Office::where('department_code', $user->department_code)
                        ->value('office') ?? $user->department_code;

                    try {
                        Mail::to($user->email)->send(
                            new DueDateReminderMail($document, $user, $officeName)
                        );

                        // Prevent resending for 24 hours
                        Cache::put($cacheKey, true, now()->addDay());

                        Log::info("Reminder sent to {$user->email} for document {$document->requirement}");
                        $this->info("Sent to: {$user->email}");

                    } catch (\Exception $e) {
                        Log::error("Failed to send reminder to {$user->email}: " . $e->getMessage());
                        $this->error("Failed: {$user->email}");
                    }
                }
            }

            $this->info('Checked at: ' . now());
            sleep(3600); // Check every hour
        }
    }
}
