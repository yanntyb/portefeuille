<script setup lang="ts">
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import type { ClassSeries } from '@/lib/wealth';

const props = defineProps<{ series?: ClassSeries | null }>();
</script>

<template>
    <section data-section="wallet-evolution" class="flex shrink-0 flex-col gap-4">
        <!--
            `ValueVsInvestedChart` et non `EvolutionSection` : celle-ci veut un détail par
            instrument que la série d'une enveloppe ne produit pas — l'enveloppe se lit en bloc.
        -->
        <ValueVsInvestedChart
            defer-key="evolution"
            :loaded="props.series !== null && props.series !== undefined"
            :labels="props.series?.labels ?? []"
            :value="props.series?.value ?? []"
            :invested="props.series?.invested ?? []"
            description="Valeur de l'enveloppe dans le temps, comparée au montant investi."
        />
    </section>
</template>
