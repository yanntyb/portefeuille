<script setup lang="ts">
import { computed, useTemplateRef } from 'vue';
import { useElementSize } from '@vueuse/core';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import { sumPerAsset, type AssetSeries } from '@/lib/chart';
import { isWideViewport } from '@/lib/viewport';
import type { EvolutionSeries } from '@/lib/portfolio';

/** Hauteur du tracé au-delà du mobile, où la page reprend son défilement continu. */
const WIDE_CHART_HEIGHT = 300;

/** En deçà le tracé n'est plus lisible : la page du carrousel défile plutôt que de l'écraser. */
const MIN_CHART_HEIGHT = 220;

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

/**
 * Le graphe est mesuré plutôt que dimensionné en CSS : echarts peint dans une boîte de hauteur
 * chiffrée. La boîte hôte, elle, s'étire — c'est elle qui absorbe le vide en bas de la page.
 */
const host = useTemplateRef<HTMLElement>('host');

const { height } = useElementSize(host);

const chartHeight = computed<number>(() =>
    isWideViewport.value
        ? WIDE_CHART_HEIGHT
        : Math.max(MIN_CHART_HEIGHT, Math.round(height.value)),
);
</script>

<template>
    <section data-section="evolution" class="flex min-h-0 flex-1 flex-col gap-4 md:flex-none">
        <h2 class="px-6 text-[17px] leading-none font-bold">Évolution</h2>

        <!-- Le tracé s'étire, mais pas jusqu'à écraser les performances sous lui : au-delà il rend la main. -->
        <div ref="host" class="min-h-0 max-h-[420px] flex-1 md:max-h-none md:flex-none">
            <ValueVsInvestedChart
                defer-key="evolutionSeries"
                :labels="labels"
                :value="value"
                :invested="invested"
                :height="chartHeight"
                description="Valeur du portefeuille comparée au montant investi."
            />
        </div>
    </section>
</template>
