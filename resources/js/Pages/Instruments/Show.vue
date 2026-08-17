<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import PerformanceSection from '@/components/instrument/PerformanceSection.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import SectorsSection from '@/components/instrument/SectorsSection.vue';
import TransactionsSection from '@/components/instrument/TransactionsSection.vue';
import ValuationSection from '@/components/instrument/ValuationSection.vue';
import type { Instrument, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
}>();
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Instruments', href: '/instruments' },
            { label: props.instrument.name },
        ]"
    />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection v-if="props.instrument.position" :valuation="props.valuation" />

        <PriceHistorySection v-else :price-history="props.priceHistory" />

        <PerformanceSection
            v-if="props.instrument.position && props.performances.length"
            :performances="props.performances"
        />

        <SectorsSection
            v-if="props.instrument.sectors.length"
            :sectors="props.instrument.sectors"
            :market-value="props.instrument.position?.marketValue ?? null"
        />

        <TransactionsSection :transactions="props.instrument.transactions" />
    </AppPage>
</template>
