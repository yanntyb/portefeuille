<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import PerformanceSection from '@/components/instrument/PerformanceSection.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import SectorsSection from '@/components/instrument/SectorsSection.vue';
import TransactionsSection from '@/components/instrument/TransactionsSection.vue';
import ValuationSection from '@/components/instrument/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { AssetDividendHistory } from '@/lib/income';
import type { Instrument, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
    dividends: AssetDividendHistory;
}>();

const snapshot = useSnapshotStore();

/** `instrument`, `performances` et `dividends` sont synchrones côté serveur : rien à combler. */
const priceHistory = aheadOfNetwork(
    () => props.priceHistory,
    () => snapshot.instrumentPage(String(props.instrument.id))?.priceHistory,
);
const valuation = aheadOfNetwork(
    () => props.valuation,
    () => snapshot.instrumentPage(String(props.instrument.id))?.valuation,
);
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection
            v-if="props.instrument.position"
            :valuation="valuation"
            :dividends="props.dividends.receipts"
        />

        <PriceHistorySection v-else :price-history="priceHistory" />

        <PerformanceSection
            v-if="props.instrument.position && props.performances.length"
            :performances="props.performances"
        />

        <DividendsSection
            v-if="props.dividends.receipts.length"
            :dividends="props.dividends"
        />

        <SectorsSection
            v-if="props.instrument.sectors.length"
            :sectors="props.instrument.sectors"
            :market-value="props.instrument.position?.marketValue ?? null"
        />

        <TransactionsSection :transactions="props.instrument.transactions" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Actions', href: '/instruments' },
            { label: props.instrument.name },
        ]"
    />
</template>
