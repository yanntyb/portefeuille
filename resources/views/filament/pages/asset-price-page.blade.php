<x-filament-panels::page>
    <div class="mb-6 flex flex-col gap-2">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-3xl font-bold text-gray-950 dark:text-white">{{ $meta->name }}</h1>
            @if ($meta->ticker)
                <span class="text-sm font-mono text-gray-500 dark:text-gray-400">{{ $meta->ticker }}</span>
            @endif
        </div>
        <div class="flex items-center gap-3 flex-wrap text-sm">
            <x-filament::badge color="info">
                {{ ucfirst($meta->type) }}
            </x-filament::badge>
            @if ($meta->isin)
                <span class="text-gray-600 dark:text-gray-400">ISIN: {{ $meta->isin }}</span>
            @endif
        </div>
    </div>

    @livewire(\App\Infrastructure\Filament\Widgets\AssetPriceChartWidget::class, ['assetId' => $assetId])
</x-filament-panels::page>
