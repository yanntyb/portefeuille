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
    assetClass: { key: string; label: string; hasSectors: boolean; hasIncome: boolean };
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    /** Absentes des expositions sans secteur ni revenu : le serveur ne les envoie pas. */
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classList(props.assetClass.key);

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => cached()?.trends);
const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => cached()?.evolutionSeries);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
const income = aheadOfNetwork(() => props.income, () => cached()?.income);
const annualIncome = aheadOfNetwork(() => props.annualIncome, () => cached()?.annualIncome);

/**
 * La crypto a ses propres fiches (`/crypto/{id}`), les trois autres expositions partagent celles
 * des titres (`/instruments/{id}`) : `InstrumentDetailController` et `CryptoDetailController`
 * renvoient chacun 404 sur l'actif de l'autre, donc un lien faux ne mènerait nulle part.
 */
const basePath = props.assetClass.key === 'crypto' ? '/crypto' : '/instruments';
</script>

<template>
    <Head :title="props.assetClass.label" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" :base-path="basePath" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <!--
            Les sections se décident sur la classe, jamais sur la valeur : `aheadOfNetwork` rend
            `null` en attendant, et un `null` ne distingue pas « pas encore » de « jamais ».
        -->
        <IncomeSection
            v-if="overview.holdings.length && props.assetClass.hasIncome"
            :income="income"
            :annual-income="annualIncome"
        />

        <SectorsSection
            v-if="overview.holdings.length && props.assetClass.hasSectors"
            :slices="sectorBreakdown"
        />
    </AppPage>

    <AppBottomBar
        :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.assetClass.label }]"
    />
</template>
