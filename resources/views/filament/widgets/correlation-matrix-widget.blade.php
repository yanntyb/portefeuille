@php
    $result = $this->getCorrelationData();
    $periods = App\Enums\CorrelationPeriod::cases();
    $headerActions = $this->getHeaderActions();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        heading="Corrélation"
        :collapsible="true"
        :collapsed="true"
    >
        <x-slot name="afterHeader">
            @if (count($headerActions))
                <div class="flex items-center gap-x-1">
                    @foreach ($headerActions as $action)
                        {{ $action }}
                    @endforeach
                </div>
            @endif
        </x-slot>

        <div class="flex flex-wrap items-center gap-1 mb-4">
            @foreach ($periods as $p)
                <button
                    type="button"
                    wire:click="$set('period', '{{ $p->value }}')"
                    @class([
                        'rounded-lg px-3 py-1.5 text-xs font-medium transition',
                        'bg-primary-500 text-white' => $this->period === $p->value,
                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $this->period !== $p->value,
                    ])
                >
                    {{ $p->getLabel() }}
                </button>
            @endforeach
        </div>

        @if ($result === null)
            <div class="rounded-xl bg-gray-50 p-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
                Minimum 2 titres avec suffisamment de données historiques communes requis.
            </div>
        @else
            @php
                $avg = $result->average;
                $avgColor = match (true) {
                    $avg < 0.3 => 'text-green-600 dark:text-green-400',
                    $avg <= 0.6 => 'text-yellow-600 dark:text-yellow-400',
                    default => 'text-red-600 dark:text-red-400',
                };
            @endphp

            <div class="space-y-4">
                <div>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">
                        Corrélation moyenne
                    </span>
                    <p class="mt-1 text-2xl font-semibold {{ $avgColor }}">
                        {{ number_format($avg, 2) }}
                    </p>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-gray-50 px-3 py-2 text-left font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300"></th>
                                @foreach ($result->labels as $label)
                                    <th class="whitespace-nowrap px-3 py-2 text-center font-medium text-gray-700 dark:text-gray-300">
                                        {{ Str::limit($label, 12) }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result->matrix as $i => $row)
                                <tr>
                                    <td class="sticky left-0 z-10 whitespace-nowrap bg-gray-50 px-3 py-2 font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {{ Str::limit($result->labels[$i], 12) }}
                                    </td>
                                    @foreach ($row as $j => $value)
                                        @php
                                            $absValue = abs($value);
                                            if ($i === $j) {
                                                $cellBg = 'background-color: rgba(156, 163, 175, 0.15)';
                                                $cellText = '';
                                            } else {
                                                $r = (int) min(255, 220 * $absValue + 34 * (1 - $absValue));
                                                $g = (int) min(255, 197 * (1 - $absValue) + 34 * $absValue);
                                                $b = 34;
                                                $cellBg = "background-color: rgba({$r}, {$g}, {$b}, 0.25)";
                                                $cellText = number_format($value, 2);
                                            }
                                        @endphp
                                        <td class="px-3 py-2 text-center font-mono tabular-nums text-gray-900 dark:text-gray-100"
                                            style="{{ $cellBg }}">
                                            {{ $cellText }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
