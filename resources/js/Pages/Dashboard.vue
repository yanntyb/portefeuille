<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
// import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppCarousel from '@/components/AppCarousel.vue';
import AppCarouselPage from '@/components/AppCarouselPage.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/dashboard/EvolutionSection.vue';
import InstrumentsSection from '@/components/dashboard/InstrumentsSection.vue';
import PerformancesSection from '@/components/dashboard/PerformancesSection.vue';
import SectorsSection from '@/components/dashboard/SectorsSection.vue';
import ValuationSection from '@/components/dashboard/ValuationSection.vue';
import type { CatalogLine, CatalogTrend } from '@/lib/catalog';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    catalog?: { lines: CatalogLine[] };
    catalogRange?: string;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <!--
        Boîte d'exactement un écran : le carrousel a besoin d'une hauteur définie pour paginer, et
        `AppPage fill` la prend de son parent. `dvh` et non `vh` : sous iOS `100vh` passe derrière
        la barre d'adresse, la dernière ligne serait coupée.
    -->
    <div class="flex h-dvh flex-col overflow-hidden md:block md:h-auto md:overflow-visible">
        <AppPage fill>
            <AppCarousel>
                <AppCarouselPage label="Valeur">
                    <ValuationSection v-if="overview.holdings.length" :overview="overview" />

                    <InstrumentsSection
                        :holdings="overview.holdings"
                        :catalog="catalog"
                        :catalog-range="catalogRange"
                        :trends="trends"
                    />

                    <EvolutionSection :series="evolutionSeries" />
                </AppCarouselPage>

                <AppCarouselPage v-if="overview.holdings.length" label="Performances">

                    <PerformancesSection :performances="performances" />
                </AppCarouselPage>

                <AppCarouselPage v-if="overview.holdings.length" label="Secteurs">
                    <SectorsSection :slices="sectorBreakdown" />
                </AppCarouselPage>
            </AppCarousel>
        </AppPage>

        <!-- Le tableau de bord est la racine : son fil d'Ariane n'aurait qu'un seul cran, et il coûte un écran de haut. -->
        <!-- <AppBreadcrumb :items="[{ label: 'Tableau de bord' }]" /> -->
    </div>
</template>
