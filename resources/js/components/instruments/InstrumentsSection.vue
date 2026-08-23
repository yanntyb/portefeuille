<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import InstrumentList from '@/components/InstrumentList.vue';
import type { CatalogTrend } from '@/lib/catalog';
import { areTrendsPending, holdingRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = withDefaults(defineProps<{
    holdings: HoldingLine[];
    trends?: CatalogTrend[] | null;
    /** Racine des liens de la liste : chaque classe d'actif a ses propres fiches. */
    basePath?: string;
}>(), { basePath: '/instruments' });

const page = usePage();

/** Seules les étincelles attendent les tendances différées : les positions sont déjà servies. */
const loading = computed<boolean>(() => areTrendsPending(props.trends, page.rescuedProps));

const rows = computed<InstrumentRow[]>(() => holdingRows(props.holdings, props.trends));
</script>

<template>
    <!-- La section s'étire : sur mobile c'est elle, et pas le bas de la page, qui porte l'espace libre. -->
    <section
        data-section="instruments"
        class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none"
        aria-label="Instruments"
    >
        <InstrumentList
            :rows="rows"
            :loading="loading"
            :base-path="props.basePath"
            empty-label="Aucune position pour le moment."
        />
    </section>
</template>
