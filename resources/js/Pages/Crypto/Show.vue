<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import PerformanceSection from '@/components/instrument/PerformanceSection.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import TransactionsSection from '@/components/instrument/TransactionsSection.vue';
import ValuationSection from '@/components/instrument/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { Instrument, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
}>();

const snapshot = useSnapshotStore();

/** `instrument` et `performances` sont synchrones côté serveur : rien à combler. */
const priceHistory = aheadOfNetwork(
    () => props.priceHistory,
    () => snapshot.cryptoPage(String(props.instrument.id))?.priceHistory,
);
const valuation = aheadOfNetwork(
    () => props.valuation,
    () => snapshot.cryptoPage(String(props.instrument.id))?.valuation,
);
</script>

<template>
    <Head :title="props.instrument.name" />

    <!-- Ni détachements ni secteurs : la fiche d'une crypto n'a que sa trajectoire et ses ordres. -->
    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection v-if="props.instrument.position" :valuation="valuation" :dividends="[]" />

        <PriceHistorySection v-else :price-history="priceHistory" />

        <PerformanceSection
            v-if="props.instrument.position && props.performances.length"
            :performances="props.performances"
        />

        <TransactionsSection :transactions="props.instrument.transactions" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Crypto', href: '/crypto' },
            { label: props.instrument.name },
        ]"
    />
</template>
