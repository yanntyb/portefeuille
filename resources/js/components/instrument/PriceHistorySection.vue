<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import { buildPriceHistoryOption } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { PriceHistory } from '@/lib/instrument';

const props = defineProps<{ priceHistory?: PriceHistory }>();

/** Même raison que sur le tableau de bord : echarts n'est téléchargé qu'au montage du graphe. */
const BaseChart = defineAsyncComponent({
    loader: () => import('@/components/BaseChart.vue'),
    loadingComponent: ChartSkeleton,
    delay: 0,
});

const hasPriceHistory = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const priceChartOption = computed<ChartOption>(() => buildPriceHistoryOption({
    labels: props.priceHistory?.labels ?? [],
    close: props.priceHistory?.close ?? [],
    valueFormatter: eur,
}));
</script>

<template>
    <section data-section="price-history" class="flex flex-col gap-6">
        <div class="flex flex-col gap-1.5 px-6">
            <h2 class="text-[17px] leading-none font-bold">Cours</h2>
            <p class="text-sm text-muted-foreground">Historique sur 12 mois</p>
        </div>

        <div class="px-0 sm:px-6">
            <Deferred data="priceHistory">
                <template #fallback>
                    <ChartSkeleton />
                </template>

                <BaseChart v-if="hasPriceHistory" :option="priceChartOption" />
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas d'historique de prix disponible.
                </p>
            </Deferred>
        </div>
    </section>
</template>
