<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import { buildEvolutionChart } from '@/lib/chart';
import { eur as formatEur } from '@/lib/format';
import type { EvolutionSeries } from '@/lib/portfolio';

const props = defineProps<{
    hasHoldings: boolean;
    hiddenAssetIds: Set<number>;
    series?: EvolutionSeries;
    initialRange?: string;
}>();

type RangeKey = '1M' | '6M' | '1Y' | 'max';
type GranularityKey = 'day' | 'week' | 'month';

const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

const isRangeKey = (value: string | undefined): value is RangeKey =>
    rangeOptions.some((option) => option.key === value);

const selectedRange = ref<RangeKey>(isRangeKey(props.initialRange) ? props.initialRange : 'max');
const granularity: GranularityKey = 'day';
const reloading = ref<boolean>(false);

const reloadSeries = (): void => {
    router.reload({
        only: ['evolutionSeries'],
        data: { range: selectedRange.value, granularity },
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};

const selectRange = (key: RangeKey): void => {
    selectedRange.value = key;
    reloadSeries();
};

const eur = (value: number | null): string => formatEur(value, 0);

const hasEvolution = computed<boolean>(() => (props.series?.labels.length ?? 0) > 0);

const evolutionChart = computed(() =>
    buildEvolutionChart({
        labels: props.series?.labels ?? [],
        perAsset: props.series?.perAsset ?? [],
        hiddenIds: props.hiddenAssetIds,
        valueFormatter: eur,
    }),
);

const evolutionKey = computed<string>(() => {
    const labels = props.series?.labels ?? [];
    const hidden = Array.from(props.hiddenAssetIds)
        .sort((a, b) => a - b)
        .join('.');

    return `${selectedRange.value}-${granularity}-${labels.length}-${labels[0] ?? ''}-${labels[labels.length - 1] ?? ''}-${hidden}`;
});
</script>

<template>
    <section data-section="evolution" :class="['flex flex-col gap-6 px-6 transition-opacity', reloading ? 'opacity-50' : '']">
        <h2 class="leading-none font-semibold">Valeur de marché par titre</h2>
        <div>
            <ChartRangeToggle
                v-if="hasHoldings"
                :options="rangeOptions"
                :model-value="selectedRange"
                @update:model-value="selectRange"
            />
            <Deferred data="evolutionSeries">
                <template #fallback>
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </template>

                <VueApexCharts
                    v-if="hasEvolution"
                    :key="evolutionKey"
                    type="area"
                    height="300"
                    :options="evolutionChart.options"
                    :series="evolutionChart.series"
                />
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas encore d'historique de valorisation.
                </p>
            </Deferred>
        </div>
    </section>
</template>
