<script setup lang="ts">
import { computed } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import type { RealEstateSeries } from '@/lib/realEstate';

const props = defineProps<{ series?: RealEstateSeries }>();

const labels = computed<string[]>(() => props.series?.labels ?? []);
const netWorth = computed<number[]>(() => props.series?.netWorth ?? []);
const invested = computed<number[]>(() => props.series?.invested ?? []);
</script>

<template>
    <section data-section="real-estate-evolution" class="flex shrink-0 flex-col gap-4">
        <h2 class="px-6 text-[17px] leading-none font-bold">Évolution</h2>

        <ValueVsInvestedChart
            defer-key="series"
            :labels="labels"
            :value="netWorth"
            :invested="invested"
            description="Patrimoine net du parc comparé au cash sorti de la poche."
        />
    </section>
</template>
