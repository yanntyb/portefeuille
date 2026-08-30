<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { CatalogTrend } from '@/lib/catalog';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    evolutionSeries?: EvolutionSeries;
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classList(props.assetClass.key);

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => cached()?.trends);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => cached()?.evolutionSeries);
</script>

<template>
    <Head :title="props.assetClass.label" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <Link
            data-analysis-link
            :href="`/${props.assetClass.slug}/analyse`"
            class="mx-6 flex items-center justify-between rounded-lg border px-4 py-3 text-sm font-medium hover:bg-muted"
        >
            Analyse de l'exposition
            <ChevronRight class="size-4 text-muted-foreground" />
        </Link>
    </AppPage>

    <AppBottomBar
        :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.assetClass.label }]"
    />
</template>
