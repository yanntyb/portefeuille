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
    <section data-section="sectors" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Secteurs</h2>
        <Deferred data="sectorBreakdown">
            <template #fallback>
                <div class="min-h-0 w-full flex-1 animate-pulse rounded-md bg-muted md:h-[280px] md:flex-none"></div>
            </template>

            <SectorBreakdownList v-if="hasSectors" :rows="rows" class="min-h-0 flex-1 md:flex-none" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore de données sectorielles.
            </p>
        </Deferred>
    </section>
</template>
