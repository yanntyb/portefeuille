<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Link, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import { buttonVariants } from '@/components/ui/button';
import type { CatalogTrend } from '@/lib/catalog';
import { areTrendsPending, holdingRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = withDefaults(
    defineProps<{
        holdings: HoldingLine[];
        trends?: CatalogTrend[] | null;
        /**
         * Le catalogue de la poche : la section ne montre que les positions, la loupe mène au reste.
         * Absente sur la page d'une enveloppe, qui n'a pas de catalogue — la loupe disparaît alors.
         */
        catalogHref?: string;
        /**
         * Prop différée dont dépendent les positions elles-mêmes : nomme la clé Inertia à
         * surveiller tant que `loaded` reste faux. Sans objet sur la page d'exposition, qui sert
         * `overview` en synchrone.
         */
        deferKey?: string;
        /**
         * Vrai dès que les positions sont arrivées, même vides — distinct d'une liste vide. La
         * page d'exposition sert `overview` en synchrone : la valeur par défaut lui convient telle
         * quelle. La page d'une enveloppe sert `positions` en différé : elle passe `false` tant que
         * la prop n'est pas arrivée, pour ne pas affirmer qu'une enveloppe ne tient rien avant
         * d'avoir la réponse (comme `ValueVsInvestedChart` le fait déjà pour `evolution`).
         */
        loaded?: boolean;
    }>(),
    { loaded: true },
);

const page = usePage();

/** Seules les étincelles attendent les tendances différées : les positions sont déjà servies. */
const loading = computed<boolean>(() => areTrendsPending(props.trends, page.rescuedProps));

const rows = computed<InstrumentRow[]>(() => holdingRows(props.holdings, props.trends));
</script>

<template>
    <CollapsibleSection section="instruments" title="Instruments" aria-label="Instruments">
        <template #aside>
            <Link
                v-if="props.catalogHref"
                :href="props.catalogHref"
                prefetch
                data-catalog-link
                aria-label="Rechercher un instrument"
                :class="buttonVariants({ variant: 'ghost', size: 'icon-sm' })"
            >
                <Search class="size-4" />
            </Link>
        </template>

        <template v-if="props.loaded">
            <!-- La section porte `px-6`, les lignes leur propre `px-3` : le retrait les ramène à la marge des listes. -->
            <div class="-mx-3">
                <InstrumentList
                    :rows="rows"
                    :loading="loading"
                    empty-label="Aucune position pour le moment."
                />
            </div>
        </template>

        <Deferred v-else :data="props.deferKey ?? 'positions'">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
