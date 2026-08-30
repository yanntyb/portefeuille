<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import FiguresSection from '@/components/instrument/FiguresSection.vue';
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
    /** Absente des expositions qui ne distribuent rien : le serveur ne l'envoie pas. */
    dividends?: AssetDividendHistory;
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
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection
            v-if="props.instrument.position"
            :valuation="valuation"
            :dividends="receipts"
        />

        <PriceHistorySection v-else :price-history="priceHistory" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; les repères viennent ensuite. -->
        <FiguresSection :instrument="props.instrument" />

        <TransactionsSection :transactions="props.instrument.transactions" />

        <PerformanceSection
            v-if="props.instrument.position && props.performances.length"
            :performances="props.performances"
        />

        <DividendsSection v-if="props.dividends && receipts.length" :dividends="props.dividends" />

        <SectorsSection
            v-if="props.instrument.sectors.length"
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
</template>
