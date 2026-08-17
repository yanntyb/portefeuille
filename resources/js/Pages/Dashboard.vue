<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/dashboard/EvolutionSection.vue';
import HoldingsSection from '@/components/dashboard/HoldingsSection.vue';
import PerformancesSection from '@/components/dashboard/PerformancesSection.vue';
import SectorsSection from '@/components/dashboard/SectorsSection.vue';
import ValuationSection from '@/components/dashboard/ValuationSection.vue';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <HoldingsSection :holdings="overview.holdings" :series="evolutionSeries" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <SectorsSection v-if="overview.holdings.length" :slices="sectorBreakdown" />
    </AppPage>

<!-- CLAUDE => Le comment du breadcrum est volontaire, corriger les test -->
    <AppBreadcrumb :items="[{ label: 'Tableau de bord' }]" />
</template>
