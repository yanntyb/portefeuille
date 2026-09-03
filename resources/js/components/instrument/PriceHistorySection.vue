<script setup lang="ts">
import { computed } from 'vue';
import { AsyncBaseChart } from '@/components/AsyncBaseChart';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import { buildPriceHistoryOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { PriceHistory } from '@/lib/instrument';

const props = defineProps<{ priceHistory?: PriceHistory | null }>();

/** Non réactive, comme sur le graphe de valorisation : la garder évite de repeindre à chaque pixel. */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const hasPriceHistory = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const priceChartOption = computed<ChartOption>(() => buildPriceHistoryOption({
    labels: props.priceHistory?.labels ?? [],
    close: props.priceHistory?.close ?? [],
    valueFormatter: eur,
    window: lastZoom,
}));
</script>

<template>
    <section data-section="price-history" class="flex flex-col gap-6">
        <div class="flex flex-col gap-1.5 px-6">
            <h2 class="text-[17px] leading-none font-bold">Cours</h2>
            <p class="text-sm text-muted-foreground">Historique sur 12 mois</p>
        </div>

        <div class="px-6">
            <template v-if="props.priceHistory !== null">
                <AsyncBaseChart v-if="hasPriceHistory" :option="priceChartOption" @zoom="rememberZoom" />
                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas d'historique de prix disponible.
                </p>
            </template>

            <DeferredBlock v-else data="priceHistory">
                <template #fallback>
                    <ChartSkeleton />
                </template>
            </DeferredBlock>
        </div>
    </section>
</template>
