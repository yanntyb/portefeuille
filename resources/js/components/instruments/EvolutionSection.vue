<script setup lang="ts">
import { computed } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import { sumPerAsset, type AssetSeries } from '@/lib/chart';
import type { EvolutionSeries } from '@/lib/portfolio';

const props = defineProps<{
    series?: EvolutionSeries | null;
}>();

const labels = computed<string[]>(() => props.series?.labels ?? []);
const perAsset = computed<AssetSeries[]>(() => props.series?.perAsset ?? []);

const value = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.value, labels.value.length),
);
const invested = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.invested, labels.value.length),
);
</script>

<template>
    <section data-section="evolution" class="flex shrink-0 flex-col gap-4">
        <ValueVsInvestedChart
            defer-key="evolutionSeries"
            :loaded="props.series !== null"
            :labels="labels"
            :value="value"
            :invested="invested"
            description="Valeur du portefeuille comparée au montant investi."
        />
    </section>
</template>
