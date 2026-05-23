<x-filament-panels::page>
    <div class="mb-6">
        <h1 class="text-4xl font-bold text-gray-950 dark:text-white mb-3">{{ $meta->name }}</h1>
        <div class="flex items-center gap-3 flex-wrap">
            <x-filament::badge color="info">
                {{ ucfirst($meta->type) }}
            </x-filament::badge>
            @if ($meta->ticker)
                <span class="text-sm font-mono text-gray-500 dark:text-gray-400">{{ $meta->ticker }}</span>
            @endif
            @if ($meta->isin)
                <span class="text-sm text-gray-600 dark:text-gray-400">ISIN: {{ $meta->isin }}</span>
            @endif
        </div>
    </div>
</x-filament-panels::page>
