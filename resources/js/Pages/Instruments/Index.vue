<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import IncomeSection from '@/components/instruments/IncomeSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import type { CatalogTrend } from '@/lib/catalog';
import type { AnnualIncome, IncomeSummary } from '@/lib/income';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();
</script>

<template>
    <Head title="Actions" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <IncomeSection v-if="overview.holdings.length" :income="income" :annual-income="annualIncome" />

        <SectorsSection v-if="overview.holdings.length" :slices="sectorBreakdown" />
    </AppPage>

    <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Actions' }]" />
</template>
