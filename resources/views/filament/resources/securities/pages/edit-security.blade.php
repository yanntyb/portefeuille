<x-filament-panels::page>
    <div
        @if ($this->isUpdating)
            wire:poll.5s="dehydrate"
        @endif
    >
        @if ($this->isUpdating)
            <div class="mb-4 flex items-center gap-3 rounded-xl bg-white px-6 py-4 shadow-lg dark:bg-gray-800">
                <x-filament::loading-indicator class="h-6 w-6 text-primary-500" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Mise à jour en cours…
                </span>
            </div>
        @endif

        {{ $this->content }}
    </div>
</x-filament-panels::page>
