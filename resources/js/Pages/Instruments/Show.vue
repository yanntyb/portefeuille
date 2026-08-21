<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import PerformanceSection from '@/components/instrument/PerformanceSection.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import SectorsSection from '@/components/instrument/SectorsSection.vue';
import TransactionsSection from '@/components/instrument/TransactionsSection.vue';
import ValuationSection from '@/components/instrument/ValuationSection.vue';
import type { AssetDividendHistory } from '@/lib/income';
import type { Instrument, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
    dividends: AssetDividendHistory;
}>();
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection
            v-if="props.instrument.position"
            :valuation="props.valuation"
            :dividends="props.dividends.receipts"
        />

        <PriceHistorySection v-else :price-history="props.priceHistory" />

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

    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Titres', href: '/instruments' },
            { label: props.instrument.name },
        ]"
    />
</template>
