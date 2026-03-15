<x-filament-panels::page>
    @php
        $valuation = $this->getValuationData();
    @endphp

    <div>
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Valorisation totale</span>
        <p @class([
            'mt-1 text-2xl font-semibold',
            'text-green-600 dark:text-green-400' => $valuation['color'] === 'success',
            'text-red-600 dark:text-red-400' => $valuation['color'] === 'danger',
        ])>
            {{ $valuation['valuation'] }}
        </p>
    </div>

    {{ $this->content }}

    <div wire:init="loadPrices"></div>
</x-filament-panels::page>
