@php
    $data = $this->getGainData();
@endphp

<x-filament-widgets::widget>
    <x-filament::section :contained="false">
        <span class="text-sm font-medium text-gray-950 dark:text-white">
            Plus-value
        </span>

        <div class="mt-3 grid grid-cols-2 gap-4">
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Latente</span>
                <p class="mt-1 text-2xl font-semibold {{ $data['plusValuePositive'] ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $data['plusValue'] }}
                </p>
                <span class="text-sm {{ $data['plusValuePositive'] ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $data['plusValuePercentage'] }}
                </span>
            </div>
            <div>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Réalisée</span>
                <p class="mt-1 text-2xl font-semibold {{ $data['realizedGainPositive'] ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                    {{ $data['realizedGain'] }}
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
