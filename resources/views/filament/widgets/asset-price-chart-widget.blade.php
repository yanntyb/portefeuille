@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $type = $this->getType();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <div
        x-data="{
            datasets: [],
            getChartInstance() {
                const el = this.$refs.chartContainer;
                if (! el || ! el._x_dataStack) return null;
                const data = el._x_dataStack[0];
                return data && typeof data.getChart === 'function' ? data.getChart() : null;
            },
            initDatasets() {
                const check = (attempts = 0) => {
                    const chart = this.getChartInstance();
                    if (chart && chart.data && chart.data.datasets.length > 0) {
                        this.datasets = chart.data.datasets.map((ds, i) => ({
                            index: i,
                            label: ds.label || ('Dataset ' + (i + 1)),
                            visible: ! chart.getDatasetMeta(i).hidden,
                            color: ds.borderColor || ds.backgroundColor || '#3b82f6',
                        }));
                        return;
                    }
                    if (attempts < 30) {
                        setTimeout(() => check(attempts + 1), 200);
                    }
                };
                check();
            },
            toggleDataset(index) {
                const chart = this.getChartInstance();
                if (! chart) return;

                const meta = chart.getDatasetMeta(index);
                meta.hidden = meta.hidden === null ? true : ! meta.hidden;
                this.datasets[index].visible = ! meta.hidden;
                chart.update();
            },
        }"
        x-init="$nextTick(() => {
            initDatasets();
            $wire.$on('updateChartData', () => setTimeout(() => initDatasets(), 400));
        })"
    >
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

            <!-- Dataset toggle dropdown -->
            <x-filament::dropdown
                placement="bottom-end"
                shift
                width="xs"
                class="fi-wi-chart-filter"
            >
                <x-slot name="trigger">
                    <x-filament::icon-button
                        icon="heroicon-m-cog-6-tooth"
                        color="gray"
                        label="Paramètres"
                    />
                </x-slot>

                <div class="p-3 space-y-2">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Courbes</p>
                    <template x-for="(ds, index) in datasets" :key="index">
                        <button
                            type="button"
                            class="flex items-center gap-2 w-full text-left text-sm rounded-lg px-2 py-1.5 hover:bg-gray-50 dark:hover:bg-white/5 transition"
                            @click="toggleDataset(ds.index)"
                        >
                            <span
                                class="size-3 rounded-full shrink-0 border-2 transition"
                                :style="ds.visible
                                    ? 'background-color: ' + (Array.isArray(ds.color) ? ds.color[0] : ds.color) + '; border-color: ' + (Array.isArray(ds.color) ? ds.color[0] : ds.color)
                                    : 'border-color: ' + (Array.isArray(ds.color) ? ds.color[0] : ds.color)"
                            ></span>
                            <span
                                class="truncate transition"
                                :class="ds.visible ? 'text-gray-950 dark:text-white' : 'text-gray-400 dark:text-gray-500 line-through'"
                                x-text="ds.label"
                            ></span>
                        </button>
                    </template>
                </div>
            </x-filament::dropdown>
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
