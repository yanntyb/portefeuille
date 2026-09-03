<script setup lang="ts">
import { computed } from 'vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorBreakdownRow, SectorSlice } from '@/lib/sector';

const props = defineProps<{ slices?: SectorSlice[] | null }>();

const rows = computed<SectorBreakdownRow[]>(() =>
    (props.slices ?? []).map((slice) => ({ label: slice.label, share: slice.pct, amount: slice.value })),
);

const hasSectors = computed<boolean>(() => rows.value.length > 0);
</script>

<template>
    <!-- Un bloc étiqueté dans la section Analyse, comme les corrélations et les performances :
         la ventilation sectorielle décrit la poche, elle ne se replie plus pour elle-même. -->
    <div data-sectors-block class="flex flex-col gap-2">
        <span class="text-xs font-semibold text-muted-foreground uppercase">Secteurs</span>

        <template v-if="props.slices !== null">
            <SectorBreakdownList v-if="hasSectors" :rows="rows" :collapsible="false" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore de données sectorielles.
            </p>
        </template>

        <DeferredBlock v-else data="sectorBreakdown">
            <template #fallback>
                <div class="h-[280px] w-full animate-pulse rounded-md bg-muted"></div>
            </template>
        </DeferredBlock>
    </div>
</template>
