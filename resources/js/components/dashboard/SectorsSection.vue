<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorBreakdownRow, SectorSlice } from '@/lib/sector';

const props = defineProps<{ slices?: SectorSlice[] }>();

const rows = computed<SectorBreakdownRow[]>(() =>
    (props.slices ?? []).map((slice) => ({ label: slice.label, share: slice.pct, amount: slice.value })),
);

const hasSectors = computed<boolean>(() => rows.value.length > 0);
</script>

<template>
    <section data-section="sectors" class="flex flex-col gap-6 px-6">
        <h2 class="text-[17px] leading-none font-bold">Secteurs</h2>
        <Deferred data="sectorBreakdown">
            <template #fallback>
                <div class="h-[280px] w-full animate-pulse rounded-md bg-muted"></div>
            </template>

            <SectorBreakdownList v-if="hasSectors" :rows="rows" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore de données sectorielles.
            </p>
        </Deferred>
    </section>
</template>
