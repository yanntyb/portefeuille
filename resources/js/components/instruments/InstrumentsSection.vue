<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import { buttonVariants } from '@/components/ui/button';
import type { CatalogTrend } from '@/lib/catalog';
import { areTrendsPending, holdingRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = defineProps<{
    holdings: HoldingLine[];
    trends?: CatalogTrend[] | null;
    /** Le catalogue de la poche : la section ne montre que les positions, la loupe mène au reste. */
    catalogHref: string;
}>();

const page = usePage();

/** Seules les étincelles attendent les tendances différées : les positions sont déjà servies. */
const loading = computed<boolean>(() => areTrendsPending(props.trends, page.rescuedProps));

const rows = computed<InstrumentRow[]>(() => holdingRows(props.holdings, props.trends));
</script>

<template>
    <CollapsibleSection section="instruments" title="Instruments" aria-label="Instruments">
        <template #aside>
            <Link
                :href="props.catalogHref"
                prefetch
                data-catalog-link
                aria-label="Rechercher un instrument"
                :class="buttonVariants({ variant: 'ghost', size: 'icon-sm' })"
            >
                <Search class="size-4" />
            </Link>
        </template>

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
