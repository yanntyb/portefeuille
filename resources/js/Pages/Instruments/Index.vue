<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import IncomeSection from '@/components/instruments/IncomeSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { CatalogTrend } from '@/lib/catalog';
import type { AnnualIncome, IncomeSummary } from '@/lib/income';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => snapshot.instrumentsList?.trends);
const performances = aheadOfNetwork(() => props.performances, () => snapshot.instrumentsList?.performances);
const evolutionSeries = aheadOfNetwork(
    () => props.evolutionSeries,
    () => snapshot.instrumentsList?.evolutionSeries,
);
const sectorBreakdown = aheadOfNetwork(
    () => props.sectorBreakdown,
    () => snapshot.instrumentsList?.sectorBreakdown,
);
const income = aheadOfNetwork(() => props.income, () => snapshot.instrumentsList?.income);
const annualIncome = aheadOfNetwork(() => props.annualIncome, () => snapshot.instrumentsList?.annualIncome);
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

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Actions' }]" />
</template>
