<script setup lang="ts">
import { computed } from 'vue';
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import type { PropertyValueSeries } from '@/lib/realEstate';

const props = defineProps<{ series?: PropertyValueSeries }>();

const labels = computed<string[]>(() => props.series?.labels ?? []);
const values = computed<number[]>(() => props.series?.values ?? []);
const remaining = computed<number[]>(() => props.series?.remaining ?? []);
</script>

<template>
    <section data-section="value" class="flex flex-col gap-6">
        <div class="flex flex-col gap-1.5 px-6">
            <h2 class="text-[17px] leading-none font-bold">Valeur du bien</h2>
            <p class="text-sm text-muted-foreground">Estimation comparée au capital restant dû.</p>
        </div>

        <ValueVsInvestedChart
            defer-key="valueSeries"
            :labels="labels"
            :value="values"
            :invested="remaining"
            comparison-label="Restant dû"
            delta-label="Net"
            description="Valeur estimée du bien comparée au capital restant dû, l'écart étant le patrimoine net."
        />
    </section>
</template>
