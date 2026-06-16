<?php

namespace App\Console\Commands;

use App\Mail\ComplianceReminderMail;
use App\Models\ComplyingOffice;
use App\Models\Office;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendComplianceReminders extends Command
{
    protected $signature = 'compliance:send-reminders';
    protected $description = 'Send daily email reminders to offices that have not yet complied';

    public function handle(): void
    {
        $this->info('Checking pending compliance offices...');

        $pending = ComplyingOffice::query()
            ->with('requiredDocument')
            ->where(function ($q) {
                $q->where('status', -1)->orWhereNull('status');
            })
            ->whereHas('requiredDocument')
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending offices found. No reminders sent.');
            return;
        }

        $sentCount    = 0;
        $skippedCount = 0;

        foreach ($pending as $complyingOffice) {
            $recipients = User::where('department_code', $complyingOffice->department_code)
                ->whereNotNull('email')
                ->get();

            if ($recipients->isEmpty()) {
                $this->warn("Skipped — no email found for department_code: {$complyingOffice->department_code}");
                $skippedCount++;
                continue;
            }

            $officeName = Office::on('mysql2')
                ->where('department_code', $complyingOffice->department_code)
                ->value('office') ?? "Department Code {$complyingOffice->department_code}";

            foreach ($recipients as $recipient) {
                Mail::to($recipient->email)
                    ->send(new ComplianceReminderMail(
                        document: $complyingOffice->requiredDocument,
                        officeName: $officeName,
                    ));
            }

            $complyingOffice->update(['last_notified_at' => now()]);
            $sentCount++;

            $this->info("Reminder sent to: {$officeName}");
        }

        $this->info("Done. Sent: {$sentCount} | Skipped: {$skippedCount}");
    }
}