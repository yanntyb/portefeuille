<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/dashboard/EvolutionSection.vue';
import InstrumentsSection from '@/components/dashboard/InstrumentsSection.vue';
import PerformancesSection from '@/components/dashboard/PerformancesSection.vue';
import SectorsSection from '@/components/dashboard/SectorsSection.vue';
import ValuationSection from '@/components/dashboard/ValuationSection.vue';
import type { CatalogTrend } from '@/lib/catalog';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <!-- Pas de fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <SectorsSection v-if="overview.holdings.length" :slices="sectorBreakdown" />
    </AppPage>
</template>
