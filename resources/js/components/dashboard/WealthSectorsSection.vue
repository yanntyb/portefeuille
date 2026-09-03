<script setup lang="ts">
import { computed } from 'vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorBreakdownRow } from '@/lib/sector';
import type { WealthSector } from '@/lib/wealth';

const props = defineProps<{ sectors?: WealthSector[] | null }>();

const rows = computed<SectorBreakdownRow[]>(() =>
    (props.sectors ?? []).map((sector: WealthSector): SectorBreakdownRow => ({
        label: sector.label,
        share: sector.pct,
        amount: sector.value,
    })),
);

const hasSectors = computed<boolean>(() => rows.value.length > 0);
</script>

<template>
    <!-- Repliée à l'arrivée, comme les transactions : la ventilation ne se charge qu'au dépli. -->
    <CollapsibleSection section="wealth-sectors" title="Secteurs">
        <!-- Tous les secteurs d'emblée : la section est déjà repliée, un second pli n'apporte rien. -->
        <SectorBreakdownList v-if="props.sectors !== null && hasSectors" :rows="rows" :collapsible="false" />

        <p v-else-if="props.sectors !== null" class="py-8 text-center text-sm text-muted-foreground">
            Pas encore de données sectorielles.
        </p>

        <DeferredBlock v-else data="sectors" />
    </CollapsibleSection>
</template>
