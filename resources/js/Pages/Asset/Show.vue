<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AnalysisSection from '@/components/instrument/AnalysisSection.vue';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import FiguresSection from '@/components/instrument/FiguresSection.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import InstrumentChart from '@/components/instrument/InstrumentChart.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import SectorsSection from '@/components/instrument/SectorsSection.vue';
import TransactionsSection from '@/components/transactions/TransactionsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { AssetDividendHistory } from '@/lib/income';
import type { Instrument, InstrumentAnalysis, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
    /** Absente des expositions qui ne distribuent rien : le serveur ne l'envoie pas. */
    dividends?: AssetDividendHistory;
    analysis?: InstrumentAnalysis;
}>();

const snapshot = useSnapshotStore();

/** Une exposition sans distribution n'a pas de détachements à annoter : la liste vide le dit. */
const receipts = computed(() => props.dividends?.receipts ?? []);

/** `instrument` et `performances` sont synchrones côté serveur : rien à combler. */
const priceHistory = aheadOfNetwork(
    () => props.priceHistory,
    () => snapshot.assetPage(String(props.instrument.id))?.priceHistory,
);
const valuation = aheadOfNetwork(
    () => props.valuation,
    () => snapshot.assetPage(String(props.instrument.id))?.valuation,
);
const analysis = aheadOfNetwork(
    () => props.analysis,
    () => snapshot.assetPage(String(props.instrument.id))?.analysis ?? undefined,
);
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <InstrumentChart
            v-if="props.instrument.position"
            :valuation="valuation"
            :price-history="priceHistory"
            :dividends="receipts"
        />

        <PriceHistorySection v-else :price-history="priceHistory" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; les repères viennent ensuite. -->
        <FiguresSection :instrument="props.instrument" />

        <AnalysisSection
            v-if="props.instrument.position"
            :analysis="analysis"
            :performances="props.performances"
        />

        <TransactionsSection
            :transactions="props.instrument.transactions"
            variant="bare"
            empty-label="Aucune transaction sur cet actif."
            :asset="{ id: props.instrument.id, name: props.instrument.name }"
        />

        <DividendsSection v-if="props.dividends && receipts.length" :dividends="props.dividends" />

        <!-- Un secteur unique se lit en étiquette dans l'en-tête : sa section n'aurait qu'une ligne à 100 %. -->
        <SectorsSection
            v-if="props.instrument.sectors.length > 1"
            :sectors="props.instrument.sectors"
            :market-value="props.instrument.position?.marketValue ?? null"
        />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.instrument.assetClassLabel, href: props.instrument.assetClassHref },
            { label: props.instrument.name },
        ]"
    />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
