<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import type { CatalogTrend } from '@/lib/catalog';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';

defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
}>();
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
