<script setup lang="ts">
import { computed } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import type { ValuationSeries } from '@/lib/instrument';

const props = defineProps<{
    valuation?: ValuationSeries;
}>();

const labels = computed<string[]>(() => props.valuation?.labels ?? []);
const value = computed<number[]>(() => props.valuation?.valuations ?? []);
const invested = computed<number[]>(() => props.valuation?.invested ?? []);
</script>

<template>
    <section data-section="valuation" class="flex flex-col gap-6">
        <ValueVsInvestedChart
            defer-key="valuation"
            :labels="labels"
            :value="value"
            :invested="invested"
            description="Valeur de la position comparée au montant investi."
        />
    </section>
</template>
