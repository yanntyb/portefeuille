<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import type { CatalogTrend } from '@/lib/catalog';
import { areTrendsPending, holdingRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = defineProps<{
    holdings: HoldingLine[];
    trends?: CatalogTrend[] | null;
}>();

const page = usePage();

/** Seules les étincelles attendent les tendances différées : les positions sont déjà servies. */
const loading = computed<boolean>(() => areTrendsPending(props.trends, page.rescuedProps));

const rows = computed<InstrumentRow[]>(() => holdingRows(props.holdings, props.trends));
</script>

<template>
    <CollapsibleSection section="instruments" title="Instruments" aria-label="Instruments">
        <!-- La section porte `px-6`, les lignes leur propre `px-3` : le retrait les ramène à la marge des listes. -->
        <div class="-mx-3">
            <InstrumentList
                :rows="rows"
                :loading="loading"
                empty-label="Aucune position pour le moment."
            />
        </div>
    </CollapsibleSection>
</template>
