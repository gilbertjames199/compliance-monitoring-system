<x-filament-panels::page>
    <div class="mx-auto w-full max-w-2xl">
        <x-filament::section icon="heroicon-o-building-office-2">
            <x-slot name="heading">
                Select Your Department & Division
            </x-slot>
            <x-slot name="description">
                Choose the division(s) you belong to. This
                determines the records you'll see and manage in the system.
            </x-slot>

            <form wire:submit="submit" class="space-y-6">
                {{ $this->form }}

                <div class="flex justify-end border-t border-gray-100 pt-4 dark:border-white/10">
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-arrow-right"
                        icon-position="after"
                        wire:loading.attr="disabled"
                        wire:target="submit"
                        style="margin-top: 2rem;"
                    >
                        <span wire:loading.remove wire:target="submit">Continue</span>
                        <span wire:loading wire:target="submit">Saving...</span>
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>