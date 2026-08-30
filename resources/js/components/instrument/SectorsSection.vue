<script setup lang="ts">
import { computed } from 'vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
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
    <CollapsibleSection section="sectors" title="Secteurs">
        <SectorBreakdownList :rows="rows" />
    </CollapsibleSection>
</template>
