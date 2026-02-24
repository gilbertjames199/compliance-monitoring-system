<?php

namespace App\Console\Commands;

use App\Jobs\CreateRecurringDocuments;
use App\Models\RequiredDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunRecurringDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:run-recurring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Continuously checks and creates recurring documents every 5 seconds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
         $this->info('Recurring document watcher started.');

        while (true) {
            $records = RequiredDocument::where('is_recurring', true)->get();

            Log::info('Recurring watcher tick', [
                'record_count' => $records->count(),
                'time'         => now()->toDateTimeString(),
            ]);
            foreach ($records as $record) {
                CreateRecurringDocuments::dispatchSync(
                    $record,
                    $record->recurrence_type,
                    $record->recurrence_interval
                );
            }

            sleep(5);

            if (memory_get_usage(true) > 128 * 1024 * 1024) {
                $this->warn('Memory limit reached, restarting...');
                Log::warning('Recurring watcher restarting due to memory limit.');
                break; // Supervisor will auto-restart the command cleanly
            }
        }
    }
}
