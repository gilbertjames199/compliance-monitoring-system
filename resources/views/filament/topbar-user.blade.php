<div class="flex items-center gap-2 px-4 text-sm">
    <div class="flex flex-col items-end">
        <span class="font-semibold text-gray-950 dark:text-white">
            {{ auth()->user()->name }}
        </span>
        <span class="text-xs text-gray-600 dark:text-gray-400">
            {{ ucfirst(auth()->user()->role ?? 'user') }}
        </span>
    </div>
</div>