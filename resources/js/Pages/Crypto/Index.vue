<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { CatalogTrend } from '@/lib/catalog';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => snapshot.cryptoList?.trends);
const performances = aheadOfNetwork(() => props.performances, () => snapshot.cryptoList?.performances);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => snapshot.cryptoList?.evolutionSeries);
</script>

<template>
    <Head title="Crypto" />

    <!--
        Mêmes sections que la page Actions, moins les revenus et les secteurs : une crypto ne verse
        pas de dividende et n'appartient à aucun secteur d'activité.
    -->
    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" base-path="/crypto" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Crypto' }]" />
</template>
