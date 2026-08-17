<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppCarousel from '@/components/AppCarousel.vue';
import AppCarouselPage from '@/components/AppCarouselPage.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/dashboard/EvolutionSection.vue';
import HoldingsSection from '@/components/dashboard/HoldingsSection.vue';
import PerformancesSection from '@/components/dashboard/PerformancesSection.vue';
import SectorsSection from '@/components/dashboard/SectorsSection.vue';
import ValuationSection from '@/components/dashboard/ValuationSection.vue';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <!--
        Sur mobile la page et son fil d'Ariane partagent une boîte d'exactement un écran : un fil
        `sticky` occupe sa place dans le flux, donc laissé au-dessous d'un `main` de 100dvh il
        rendrait le document plus haut que l'écran et laisserait un résidu de défilement.
        `dvh` et non `vh` : sous iOS `100vh` passe derrière la barre d'adresse.
    -->
    <div class="flex h-dvh flex-col overflow-hidden md:block md:h-auto md:overflow-visible">
        <AppPage fill>
            <AppCarousel>
                <AppCarouselPage label="Valeur">
                    <ValuationSection v-if="overview.holdings.length" :overview="overview" />

                    <EvolutionSection :series="evolutionSeries" />
                </AppCarouselPage>

                <AppCarouselPage label="Positions">
                    <HoldingsSection :holdings="overview.holdings" :series="evolutionSeries" />
                </AppCarouselPage>

                <AppCarouselPage v-if="overview.holdings.length" label="Performances">
                    <PerformancesSection :performances="performances" />
                </AppCarouselPage>

                <AppCarouselPage v-if="overview.holdings.length" label="Secteurs">
                    <SectorsSection :slices="sectorBreakdown" />
                </AppCarouselPage>
            </AppCarousel>
        </AppPage>

        <AppBreadcrumb :items="[{ label: 'Tableau de bord' }]" />
    </div>
</template>
