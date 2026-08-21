<script setup lang="ts">
import { computed } from 'vue';
import { eur, fractionPct } from '@/lib/format';
import type { PropertyMetrics } from '@/lib/realEstate';

const props = defineProps<{ metrics: PropertyMetrics }>();

interface MetricTile {
    key: string;
    label: string;
    value: string;
}

/** Un ratio nul n'a pas de tuile : mieux vaut quatre tuiles pleines qu'une cinquième en tiret. */
const ratioTile = (key: string, label: string, ratio: number | null): MetricTile | null =>
    ratio === null ? null : { key, label, value: fractionPct(ratio) };

const tiles = computed<MetricTile[]>(() =>
    [
        ratioTile('grossYield', 'Rendement brut', props.metrics.grossYield),
        ratioTile('netYield', 'Rendement net', props.metrics.netYield),
        { key: 'annualCashFlow', label: 'Cash-flow annuel', value: eur(props.metrics.annualCashFlow) },
        ratioTile('cashOnCash', 'Cash-on-cash', props.metrics.cashOnCash),
        ratioTile('ltv', 'LTV', props.metrics.ltv),
    ].filter((tile): tile is MetricTile => tile !== null),
);
</script>

<template>
    <!-- Fond plutôt que bordure : la page n'a pas un trait, une tuile s'y pose par sa teinte. -->
    <section data-section="metrics" class="grid grid-cols-2 gap-2 px-6 md:grid-cols-5">
        <div
            v-for="tile in tiles"
            :key="tile.key"
            :data-metric="tile.key"
            class="flex flex-col gap-0.5 rounded-xl bg-muted px-3 py-2.5"
        >
            <span data-metric-value class="font-semibold tabular-nums">{{ tile.value }}</span>
            <span data-metric-label class="text-xs text-muted-foreground">{{ tile.label }}</span>
        </div>
    </section>
</template>
