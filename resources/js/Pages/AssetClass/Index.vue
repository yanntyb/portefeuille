<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import AnalysisSection from '@/components/instruments/AnalysisSection.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PortfolioSummarySection from '@/components/PortfolioSummarySection.vue';
import TransactionsSection from '@/components/transactions/TransactionsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { BasketAnalysis } from '@/lib/basketAnalysis';
import type { CatalogTrend } from '@/lib/catalog';
import type { TransactionLine } from '@/lib/instrument';
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
    basketAnalysis?: BasketAnalysis;
    transactions?: TransactionLine[];
    /** Absente des expositions sans secteur : le serveur ne l'envoie pas. */
    sectorBreakdown?: SectorSlice[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classList(props.assetClass.key);

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => cached()?.trends);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => cached()?.evolutionSeries);
const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const basketAnalysis = aheadOfNetwork(() => props.basketAnalysis, () => cached()?.basketAnalysis);
const transactions = aheadOfNetwork(() => props.transactions, () => cached()?.transactions);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
</script>

<template>
    <Head :title="props.assetClass.label" />

    <AppPage>
        <PortfolioSummarySection
            v-if="overview.holdings.length"
            prefix="portfolio"
            section="valuation"
            :total-value="overview.totalValue"
            :total-gain="overview.totalGain"
            :total-gain-pct="overview.totalGainPct"
            :invested="overview.totalCost"
            :realized-gain="overview.totalRealizedGain"
            :origin-cash="overview.cash"
        />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection
            :holdings="overview.holdings"
            :trends="trends"
            :catalog-href="`/${props.assetClass.slug}/catalogue`"
        />

        <!-- Les secteurs vivent dans l'analyse : c'est une lecture de la poche, pas une section
             à part. -->
        <AnalysisSection
            :analysis="basketAnalysis"
            :performances="performances"
            :has-sectors="props.assetClass.hasSectors"
            :slices="sectorBreakdown"
        />

        <TransactionsSection :transactions="transactions" section="class-transactions" empty-label="Aucune transaction sur cette classe." />
    </AppPage>

    <AppBottomBar
        :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.assetClass.label }]"
    />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
