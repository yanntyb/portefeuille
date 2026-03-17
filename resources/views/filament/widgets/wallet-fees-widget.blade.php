@php
    $data = $this->getFeesData();
@endphp

<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-filament::section :contained="false">
            <span class="text-sm font-medium text-gray-950 dark:text-white">
                Frais de transaction
            </span>
            <p class="mt-3 text-2xl font-semibold text-danger-600 dark:text-danger-400">
                {{ $data['transactionFees'] }}
            </p>
            <span class="text-sm text-danger-600 dark:text-danger-400">
                {{ $data['transactionFeesPercentage'] }}
            </span>
        </x-filament::section>

        <x-filament::section :contained="false">
            <span class="text-sm font-medium text-gray-950 dark:text-white">
                Frais annuels estimés
            </span>
            <p class="mt-3 text-2xl font-semibold text-danger-600 dark:text-danger-400">
                {{ $data['annualFees'] }}
            </p>
            @if (count($data['walletFees']) > 0)
                <div class="mt-2 space-y-1">
                    @foreach ($data['walletFees'] as $fee)
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span>{{ $fee['name'] }}</span>
                            <span class="font-medium">{{ $fee['formatted'] }} → {{ $fee['annual'] }}/an</span>
                        </div>
                    @endforeach
                </div>
            @else
                <span class="text-sm text-gray-400 dark:text-gray-500">Aucun frais structurel configuré</span>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
