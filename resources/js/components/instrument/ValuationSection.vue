<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, router } from '@inertiajs/vue3';
import BaseChart from '@/components/BaseChart.vue';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import {
    buildValuationOption,
    granularityForRange,
    VALUATION_RANGES,
    type ValuationRangeKey,
} from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { ValuationSeries } from '@/lib/instrument';

const props = defineProps<{
    valuation?: ValuationSeries;
    initialRange?: string;
}>();

const isRangeKey = (value: string | undefined): value is ValuationRangeKey =>
    VALUATION_RANGES.some((option) => option.key === value);

const selectedRange = ref<ValuationRangeKey>(isRangeKey(props.initialRange) ? props.initialRange : 'max');
const reloading = ref<boolean>(false);

const selectRange = (key: ValuationRangeKey): void => {
    selectedRange.value = key;

    router.reload({
        only: ['valuation'],
        data: { range: key, granularity: granularityForRange(key) },
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const positionChartOption = computed<ChartOption>(() => buildValuationOption({
    labels: props.valuation?.labels ?? [],
    valuations: props.valuation?.valuations ?? [],
    invested: props.valuation?.invested ?? [],
    valueFormatter: (value: number): string => eur(value, 0),
}));
</script>

<template>
    <section data-section="valuation" class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6">
            <div class="flex flex-col gap-1.5">
                <h2 class="leading-none font-semibold">Valeur vs investi</h2>
            </div>

            <ChartRangeToggle
                :options="VALUATION_RANGES"
                :model-value="selectedRange"
                @update:model-value="selectRange"
            />
        </div>

        <Deferred data="valuation">
            <template #fallback>
                <div class="px-0 sm:px-6">
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <div v-if="hasValuation" class="px-0 sm:px-6" :class="reloading ? 'opacity-50' : ''">
                <BaseChart :option="positionChartOption" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
