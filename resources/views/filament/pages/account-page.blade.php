<x-filament-panels::page>
    <div wire:loading.delay wire:target="refreshPrices" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/25 dark:bg-gray-900/50">
        <div class="flex items-center gap-3 rounded-xl bg-white px-6 py-4 shadow-lg dark:bg-gray-800">
            <x-filament::loading-indicator class="h-6 w-6 text-primary-500" />
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                Mise à jour en cours…
            </span>
        </div>
    </div>

    @if ($this->isUpdating)
        <div class="absolute top-3 right-4 flex items-center gap-2">
            <x-filament::loading-indicator class="h-5 w-5 text-primary-500" />
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Mise à jour…
            </span>
        </div>
    @endif

    <div
        x-data
        x-init="$nextTick(() => {
            const store = $store['{{ $this->tableStoreName() }}'];
            if (store) {
                $wire.restoreFromTableStore(store);
            }
        })"
    ></div>

    <div class="-mt-4">
        {{ $this->content }}
    </div>
    {{ $this->table }}

    @if ($this->hasMoreRecords())
        <div class="flex justify-center">
            {{ $this->loadMoreAction }}
        </div>
    @endif
</x-filament-panels::page>
