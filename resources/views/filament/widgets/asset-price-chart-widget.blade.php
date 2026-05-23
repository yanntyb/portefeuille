@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $type = $this->getType();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <div>
        <!-- Period controls -->
        <div class="flex items-center justify-between gap-2 flex-wrap mb-4">
            <div class="flex gap-2">
                @foreach(['1M', '3M', '6M', '1Y'] as $period)
                    <button
                        wire:click="setPeriod('{{ $period }}')"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition
                            {{ $this->period === $period
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-white/20'
                            }}"
                    >
                        {{ $period }}
                    </button>
                @endforeach
            </div>

            <!-- Custom date range -->
            <div class="flex items-center gap-2 flex-wrap">
                <input
                    type="date"
                    wire:model.live="dateFrom"
                    wire:change="setCustomPeriod"
                    class="text-sm rounded-md border border-gray-300 dark:border-white/20 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-2 py-1.5"
                />
                <span class="text-gray-400 text-sm">→</span>
                <input
                    type="date"
                    wire:model.live="dateTo"
                    wire:change="setCustomPeriod"
                    class="text-sm rounded-md border border-gray-300 dark:border-white/20 bg-white dark:bg-white/5 text-gray-900 dark:text-white px-2 py-1.5"
                />
            </div>
        </div>

        <!-- Chart canvas -->
        <div
            @if ($pollingInterval = $this->getPollingInterval())
                wire:poll.{{ $pollingInterval }}="updateChartData"
            @endif
        >
            <div
                x-ref="chartContainer"
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                wire:ignore
                data-chart-type="{{ $type }}"
                x-data="chart({
                            cachedData: @js($this->getCachedData()),
                            maxHeight: @js($maxHeight = $this->getMaxHeight()),
                            options: @js($this->getOptions()),
                            type: @js($type),
                        })"
                {{
                    (new ComponentAttributeBag)
                        ->color(ChartWidgetComponent::class, $color)
                        ->class([
                            'fi-wi-chart-canvas-ctn',
                            'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                        ])
                }}
            >
                <canvas
                    x-ref="canvas"
                    @if ($maxHeight)
                        style="max-height: {{ $maxHeight }}"
                    @endif
                ></canvas>

                <span x-ref="backgroundColorElement" class="fi-wi-chart-bg-color"></span>
                <span x-ref="borderColorElement" class="fi-wi-chart-border-color"></span>
                <span x-ref="gridColorElement" class="fi-wi-chart-grid-color"></span>
                <span x-ref="textColorElement" class="fi-wi-chart-text-color"></span>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
