<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppCarousel from '@/components/AppCarousel.vue';
import AppCarouselPage from '@/components/AppCarouselPage.vue';
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

    <!--
        Boîte d'exactement un écran, partagée avec le fil d'Ariane : un fil `sticky` occupe sa place
        dans le flux, donc laissé au-dessous d'un `main` d'un écran il rendrait le document plus haut
        que la fenêtre. `dvh` et non `vh` : sous iOS `100vh` passe derrière la barre d'adresse.
    -->
    <div class="flex h-dvh flex-col overflow-hidden md:block md:h-auto md:overflow-visible">
        <AppPage fill>
            <AppCarousel>
                <AppCarouselPage label="Aperçu">
                    <HeroSection :instrument="props.instrument" />

                    <ValuationSection v-if="props.instrument.position" :valuation="props.valuation" />

                    <PriceHistorySection v-else :price-history="props.priceHistory" />
                </AppCarouselPage>

                <AppCarouselPage
                    v-if="props.instrument.position && props.performances.length"
                    label="Performance"
                >
                    <PerformanceSection :performances="props.performances" />
                </AppCarouselPage>

                <AppCarouselPage v-if="props.instrument.sectors.length" label="Secteurs">
                    <SectorsSection
                        :sectors="props.instrument.sectors"
                        :market-value="props.instrument.position?.marketValue ?? null"
                    />
                </AppCarouselPage>

                <AppCarouselPage label="Transactions">
                    <TransactionsSection :transactions="props.instrument.transactions" />
                </AppCarouselPage>
            </AppCarousel>
        </AppPage>

        <AppBreadcrumb
            :items="[
                { label: 'Tableau de bord', href: '/' },
                { label: props.instrument.name },
            ]"
        />
    </div>
</template>
