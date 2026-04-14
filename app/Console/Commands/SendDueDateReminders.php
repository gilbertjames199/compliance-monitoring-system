<?php

namespace App\Console\Commands;

use App\Mail\DueDateReminderMail;
use App\Models\Office;
use App\Models\RequiredDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDueDateReminders extends Command
{
    protected $signature = 'reminders:due-documents';
    protected $description = 'Send email reminders for documents due in 2 days';

    public function handle()
    {
        $documents = RequiredDocument::where('due_date', now()->addDays(2)->toDateString())->get();

        if ($documents->isEmpty()) {
            $this->info('No documents due in 2 days.');
            return;
        }

        $usersWithRoles = DB::connection('mysql')
            ->table('model_has_roles')
            ->where('model_type', User::class)
            ->pluck('model_id')
            ->toArray();

        foreach ($documents as $document) {
            $departmentCodes = $document->complyingOffices->pluck('department_code');

            $users = User::whereIn('department_code', $departmentCodes)
                ->whereIn('recid', $usersWithRoles)
                ->whereNotNull('email')
                ->get();

            foreach ($users as $user) {
                $cacheKey = "due_reminder_{$document->id}_user_{$user->recid}";
                if (Cache::has($cacheKey)) {
                    $this->info("Skipped (already notified): {$user->email}");
                    continue;
                }

                $officeName = Office::where('department_code', $user->department_code)
                    ->value('office') ?? $user->department_code;

                try {
                    Mail::to($user->email)->send(
                        new DueDateReminderMail($document, $user, $officeName)
                    );

                    Cache::put($cacheKey, true, now()->addDay());

                    Log::info("Reminder sent to {$user->email} for document {$document->requirement}");
                    $this->info("Sent to: {$user->email}");

                } catch (\Exception $e) {
                    Log::error("Failed to send reminder to {$user->email}: " . $e->getMessage());
                    $this->error("Failed: {$user->email}");
                }
            }
        }

        $this->info('Done at: ' . now());
    }
}
