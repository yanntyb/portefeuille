<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import AnalysisSection from '@/components/instruments/AnalysisSection.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import TransactionsSection from '@/components/instruments/TransactionsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { ClassAnalysis } from '@/lib/classAnalysis';
import type { CatalogTrend } from '@/lib/catalog';
import type { NamedTransactionLine } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string; hasSectors: boolean };
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    evolutionSeries?: EvolutionSeries;
    performances?: Performance[];
    classAnalysis?: ClassAnalysis;
    transactions?: NamedTransactionLine[];
    /** Absente des expositions sans secteur : le serveur ne l'envoie pas. */
    sectorBreakdown?: SectorSlice[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classList(props.assetClass.key);

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => cached()?.trends);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => cached()?.evolutionSeries);
const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const classAnalysis = aheadOfNetwork(() => props.classAnalysis, () => cached()?.classAnalysis);
const transactions = aheadOfNetwork(() => props.transactions, () => cached()?.transactions);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
</script>

<template>
    <Head :title="props.assetClass.label" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <!-- Les secteurs vivent dans l'analyse : c'est une lecture de la poche, pas une section
             à part. -->
        <AnalysisSection
            :analysis="classAnalysis"
            :performances="performances"
            :has-sectors="props.assetClass.hasSectors"
            :slices="sectorBreakdown"
        />

        <TransactionsSection :transactions="transactions" />
    </AppPage>

    <AppBottomBar
        :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.assetClass.label }]"
    />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
