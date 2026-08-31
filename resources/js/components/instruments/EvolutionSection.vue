<script setup lang="ts">
import { computed, ref } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import SegmentedControl, { type Segment } from '@/components/ui/SegmentedControl.vue';
import { sumPerAsset, type AssetSeries } from '@/lib/chart';
import type { EvolutionSeries } from '@/lib/portfolio';

/** Les deux lectures d'une poche : ce qu'elle vaut en bloc, ou ce que vaut chacun de ses titres. */
type Mode = 'total' | 'detail';

const props = defineProps<{
    series?: EvolutionSeries | null;
}>();

const mode = ref<Mode>('total');

const labels = computed<string[]>(() => props.series?.labels ?? []);
const perAsset = computed<AssetSeries[]>(() => props.series?.perAsset ?? []);

const value = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.value, labels.value.length),
);
const invested = computed<number[]>(
    () => sumPerAsset(perAsset.value, (asset: AssetSeries): number[] => asset.invested, labels.value.length),
);

/** Sous deux titres, le détail redirait le total : la commande disparaît plutôt que de rester inerte. */
const hasDetail = computed<boolean>(() => perAsset.value.length > 1);

const segments: Segment[] = [
    { value: 'total', label: 'Total' },
    { value: 'detail', label: 'Détail' },
];

const detailed = computed<AssetSeries[]>(() => (mode.value === 'detail' ? perAsset.value : []));

const description = computed<string>(() => (mode.value === 'detail'
    ? 'Valeur de chaque instrument de la poche, sous la valeur totale du portefeuille.'
    : 'Valeur du portefeuille comparée au montant investi.'));
</script>

<template>
    <section data-section="evolution" class="flex shrink-0 flex-col gap-4">
        <div v-if="hasDetail" class="px-6">
            <SegmentedControl
                v-model="mode"
                :segments="segments"
                label="Détail du graphe"
            />
        </div>

        <ValueVsInvestedChart
            defer-key="evolutionSeries"
            :loaded="props.series !== null"
            :labels="labels"
            :value="value"
            :invested="invested"
            :per-asset="detailed"
            :description="description"
        />
    </section>
</template>
