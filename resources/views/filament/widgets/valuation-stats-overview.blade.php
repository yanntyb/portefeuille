@php
    $data = $this->getValuationData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section :contained="false">
        <span class="text-sm font-medium text-gray-950 dark:text-white">
            Valorisation
        </span>

        <p class="mt-1 text-2xl font-semibold {{ $data['color'] === 'success' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
            {{ $data['valuation'] }}
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
