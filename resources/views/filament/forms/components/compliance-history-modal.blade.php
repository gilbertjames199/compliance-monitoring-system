@php
    $statusLabel = fn ($v) => match ((string) $v) {
        '-1' => 'Not Complied',
        '0'  => 'Partially Complied',
        '1'  => 'Complied',
        default => null,
    };

    $validationLabel = fn ($v) => match ($v) {
        'pending_review' => 'Pending Review',
        'returned'       => 'Returned',
        'validated'      => 'Validated',
        default          => null,
    };

    $dotColor = fn ($event) => match ($event) {
        'submitted'        => 'bg-info-500',
        'validated'        => 'bg-success-500',
        'returned'         => 'bg-danger-500',
        'reminder_sent'    => 'bg-warning-500',
        'reminder_skipped', 'reminder_failed' => 'bg-gray-400',
        default            => 'bg-gray-400',
    };

    $eventLabel = fn ($event) => match ($event) {
        'submitted'        => 'Submitted',
        'validated'        => 'Validated',
        'returned'         => 'Returned',
        'reminder_sent'    => 'Reminder Sent',
        'reminder_skipped' => 'Reminder Skipped',
        'reminder_failed'  => 'Reminder Failed',
        default            => ucfirst(str_replace('_', ' ', $event ?? '-')),
    };
@endphp

<div class="max-h-[65vh] overflow-y-auto pr-2">
    @forelse ($logs as $log)
        @php
            $user = $users->get($log->user_id);
            $userName = $user->FullName ?? $user->UserName ?? ($log->user_id ? "User #{$log->user_id}" : 'System');
            $oldStatus = $statusLabel($log->old_status);
            $newStatus = $statusLabel($log->new_status);
            $oldValidation = $validationLabel($log->old_validation_status);
            $newValidation = $validationLabel($log->new_validation_status);
        @endphp

        <div class="relative pb-6 pl-6 last:pb-0">
            @if (!$loop->last)
                <span class="absolute left-[5px] top-3 bottom-0 w-px bg-gray-200 dark:bg-gray-700"></span>
            @endif
            <span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full {{ $dotColor($log->event) }}"></span>

            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3 space-y-2">
                {{-- Header: event + timestamp --}}
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium text-sm text-gray-900 dark:text-gray-100">
                        {{ $eventLabel($log->event) }}
                    </span>
                    <span class="text-xs text-gray-500">
                        {{ \Carbon\Carbon::parse($log->action_at)->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                    </span>
                </div>

                {{-- Action by --}}
                <div class="text-xs text-gray-500">
                    Action by: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $userName }}</span>
                    @if ($log->division_name)
                        &middot; {{ $log->division_name }}
                    @endif
                </div>

                {{-- Status changes --}}
                @if ($newStatus)
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        Compliance Status:
                        @if ($oldStatus && $oldStatus !== $newStatus)
                            <span class="text-gray-400">{{ $oldStatus }}</span> →
                        @endif
                        <span class="font-medium">{{ $newStatus }}</span>
                    </div>
                @endif

                @if ($newValidation)
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        Validation Status:
                        @if ($oldValidation && $oldValidation !== $newValidation)
                            <span class="text-gray-400">{{ $oldValidation }}</span> →
                        @endif
                        <span class="font-medium">{{ $newValidation }}</span>
                    </div>
                @endif

                {{-- Remarks --}}
                @if ($log->remarks)
                    <div class="text-xs text-gray-700 dark:text-gray-300 italic border-l-2 border-gray-200 dark:border-gray-700 pl-2">
                        {{ $log->remarks }}
                    </div>
                @endif

                {{-- Filename placeholder — wired up once attachments column exists --}}
                @if (!empty($log->attachments))
                    <div class="text-xs text-gray-600 dark:text-gray-300">
                        <span class="text-gray-400">Files:</span>
                        {{ collect($log->attachments)->map(fn ($f) => basename($f))->join(', ') }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 text-center py-6">No history recorded yet for this submission.</p>
    @endforelse
</div>