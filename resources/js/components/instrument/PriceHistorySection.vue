<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import { buildTimeSeriesOptions } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { PriceHistory } from '@/lib/instrument';

const props = defineProps<{ priceHistory?: PriceHistory }>();

const hasPriceHistory = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const priceChartSeries = computed(() => [
    { name: 'Cours', data: props.priceHistory?.close ?? [] },
]);

const priceChartOptions = computed<ApexOptions>(() => ({
    ...buildTimeSeriesOptions({ categories: props.priceHistory?.labels ?? [], valueFormatter: eur }),
    colors: ['#4f46e5'],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
}));
</script>

<template>
    <section data-section="price-history" class="flex flex-col gap-6">
        <div class="flex flex-col gap-1.5 px-6">
            <h2 class="leading-none font-semibold">Cours</h2>
            <p class="text-sm text-muted-foreground">Historique sur 12 mois</p>
        </div>

        <div class="px-0 sm:px-6">
            <Deferred data="priceHistory">
                <template #fallback>
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </template>

                <VueApexCharts
                    v-if="hasPriceHistory"
                    type="area"
                    height="300"
                    :options="priceChartOptions"
                    :series="priceChartSeries"
                />
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas d'historique de prix disponible.
                </p>
            </Deferred>
        </div>
    </section>
</template>
