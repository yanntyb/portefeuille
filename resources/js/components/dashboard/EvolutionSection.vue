<script setup lang="ts">
import { computed } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import { sumPerAsset, type AssetSeries } from '@/lib/chart';
import { isWideViewport } from '@/lib/viewport';
import type { EvolutionSeries } from '@/lib/portfolio';

/** Hauteur du tracé au-delà du mobile, où la page reprend son défilement continu. */
const WIDE_CHART_HEIGHT = 240;

/** Le graphe coiffe la page : il tient dans une bande fixe et laisse la place aux positions. */
const COMPACT_CHART_HEIGHT = 170;

const props = defineProps<{
    series?: EvolutionSeries;
}>();

const labels = computed<string[]>(() => props.series?.labels ?? []);
const perAsset = computed<AssetSeries[]>(() => props.series?.perAsset ?? []);

const value = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.value, labels.value.length),
);
const invested = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.invested, labels.value.length),
);

/** Echarts peint dans une boîte de hauteur chiffrée : la hauteur est donnée, plus mesurée. */
const chartHeight = computed<number>(() =>
    isWideViewport.value ? WIDE_CHART_HEIGHT : COMPACT_CHART_HEIGHT,
);
</script>

<template>
    <section data-section="evolution" class="flex shrink-0 flex-col gap-4">
        <h2 class="px-6 text-[17px] leading-none font-bold">Évolution</h2>

        <ValueVsInvestedChart
            defer-key="evolutionSeries"
            :labels="labels"
            :value="value"
            :invested="invested"
            :height="chartHeight"
            description="Valeur du portefeuille comparée au montant investi."
        />
    </section>
</template>
