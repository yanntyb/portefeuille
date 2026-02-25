<x-filament-widgets::widget>
    <div
        x-data="{
            canScroll: false,
            atEnd: false,
            init() {
                this.$nextTick(() => {
                    const el = this.$refs.scroller;
                    this.canScroll = el.scrollWidth > el.clientWidth;
                    el.addEventListener('scroll', () => {
                        this.atEnd = el.scrollLeft + el.clientWidth >= el.scrollWidth - 8;
                    });
                });
            },
        }"
        class="relative"
    >
        <div
            x-ref="scroller"
            class="flex gap-4 overflow-x-auto px-1 py-1 snap-x snap-mandatory scroll-px-1"
            style="-ms-overflow-style: none; scrollbar-width: none;"
        >
            @foreach ($this->getPerformanceData() as $stat)
                <div @class([
                    'fi-wi-stats-overview-stat min-w-[calc(33.333%-0.667rem)] shrink-0 snap-start',
                    'fi-color fi-color-success' => $stat['color'] === 'success',
                    'fi-color fi-color-danger' => $stat['color'] === 'danger',
                    'fi-color fi-color-gray' => $stat['color'] === 'gray',
                ])>
                    <div class="fi-wi-stats-overview-stat-content">
                        <div class="fi-wi-stats-overview-stat-label-ctn">
                            <span class="fi-wi-stats-overview-stat-label">
                                {{ $stat['label'] }}
                            </span>
                        </div>

                        <div @class([
                            'fi-wi-stats-overview-stat-value',
                            'text-green-600 dark:text-green-400' => $stat['color'] === 'success',
                            'text-red-600 dark:text-red-400' => $stat['color'] === 'danger',
                            'text-gray-400 dark:text-gray-500' => $stat['color'] === 'gray',
                        ])>
                            {{ $stat['value'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Fade + arrow indicator --}}
        <div
            x-show="canScroll && !atEnd"
            x-transition:leave.opacity.duration.300ms
            class="pointer-events-none absolute inset-y-0 right-0 flex w-16 items-center justify-end bg-gradient-to-l from-gray-50 to-transparent pr-2 dark:from-gray-950"
        >
            <x-filament::icon
                icon="heroicon-m-chevron-right"
                class="h-5 w-5 animate-pulse text-gray-400 dark:text-gray-500"
            />
        </div>
    </div>
</x-filament-widgets::widget>
