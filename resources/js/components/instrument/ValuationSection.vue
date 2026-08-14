<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import { base100, buildTimeSeriesOptions } from '@/lib/chart';
import { signedPct } from '@/lib/format';
import type { ValuationSeries } from '@/lib/instrument';

const props = defineProps<{
    valuation?: ValuationSeries;
    initialRange?: string;
    initialGranularity?: string;
}>();

type RangeKey = '1M' | '6M' | '1Y' | 'max';
type GranularityKey = 'day' | 'week' | 'month';

const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

const granularityOptions: { key: GranularityKey; label: string }[] = [
    { key: 'day', label: 'Jour' },
    { key: 'week', label: 'Sem' },
    { key: 'month', label: 'Mois' },
];

const isRangeKey = (value: string | undefined): value is RangeKey =>
    rangeOptions.some((option) => option.key === value);

const isGranularityKey = (value: string | undefined): value is GranularityKey =>
    granularityOptions.some((option) => option.key === value);

const selectedRange = ref<RangeKey>(isRangeKey(props.initialRange) ? props.initialRange : 'max');
const selectedGranularity = ref<GranularityKey>(
    isGranularityKey(props.initialGranularity) ? props.initialGranularity : 'month',
);
const reloading = ref<boolean>(false);

const reloadValuation = (): void => {
    router.reload({
        only: ['valuation'],
        data: { range: selectedRange.value, granularity: selectedGranularity.value },
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
    reloadValuation();
};

const selectGranularity = (key: GranularityKey): void => {
    selectedGranularity.value = key;
    reloadValuation();
};

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const valuationKey = computed<string>(() => {
    const labels = props.valuation?.labels ?? [];

    return `${labels.length}:${labels[0] ?? ''}:${labels[labels.length - 1] ?? ''}`;
});

const coursChartSeries = computed(() => [
    { name: 'Cours', data: base100(props.valuation?.prices ?? []) },
]);

const coursChartOptions = computed<ApexOptions>(() => ({
    ...buildTimeSeriesOptions({ categories: props.valuation?.labels ?? [], valueFormatter: signedPct }),
    colors: ['#10b981'],
}));

const positionChartSeries = computed(() => [
    { name: 'Valeur', data: base100(props.valuation?.valuations ?? []) },
    { name: 'Investi', data: base100(props.valuation?.invested ?? []) },
]);

const positionChartOptions = computed<ApexOptions>(() => ({
    ...buildTimeSeriesOptions({ categories: props.valuation?.labels ?? [], valueFormatter: signedPct }),
    colors: ['#4f46e5', '#64748b'],
}));
</script>

<template>
    <section data-section="valuation" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center gap-3 px-6">
            <ChartRangeToggle :options="rangeOptions" :model-value="selectedRange" @update:model-value="selectRange" />
            <ChartRangeToggle
                :options="granularityOptions"
                :model-value="selectedGranularity"
                @update:model-value="selectGranularity"
            />
        </div>

        <Deferred data="valuation">
            <template #fallback>
                <div class="grid gap-4 sm:px-6 lg:grid-cols-2">
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <div
                v-if="hasValuation"
                :class="reloading ? 'opacity-50' : ''"
            >

                <div class="flex min-w-0 flex-col gap-6">
                    <div class="flex flex-col gap-1.5 px-6">
                        <h2 class="leading-none font-semibold">Valeur vs Investi</h2>
                        <p class="text-sm text-muted-foreground">Performance base 100 sur la période</p>
                    </div>
                    <div class="px-0 sm:px-6">
                        <VueApexCharts :key="valuationKey" type="line" height="300" :options="positionChartOptions" :series="positionChartSeries" />
                    </div>
                </div>
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
