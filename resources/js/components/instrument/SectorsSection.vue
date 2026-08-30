<script setup lang="ts">
import { computed } from 'vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorWeight } from '@/lib/instrument';
import type { SectorBreakdownRow } from '@/lib/sector';

const props = defineProps<{ sectors: SectorWeight[]; marketValue: number | null }>();

/** Only a held instrument has a value to split across its sectors. */
const rows = computed<SectorBreakdownRow[]>(() =>
    props.sectors.map((sector) => ({
        label: sector.label,
        share: sector.weight * 100,
        amount: props.marketValue === null ? null : props.marketValue * sector.weight,
    })),
);
</script>

<template>
    <section data-section="sectors" class="flex flex-col gap-6 px-6">
        <h2 class="text-[17px] leading-none font-bold">Secteurs</h2>
        <SectorBreakdownList :rows="rows" />
    </section>
</template>
