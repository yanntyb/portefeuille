<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
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

const hiddenAssetIds = ref<Set<number>>(new Set());

const toggleAsset = (assetId: number): void => {
    const next = new Set(hiddenAssetIds.value);
    if (next.has(assetId)) {
        next.delete(assetId);
    } else {
        next.add(assetId);
    }
    hiddenAssetIds.value = next;
};
</script>

<template>
    <Head title="Tableau de bord" />
<!-- CLAUDE => Le comment du breadcrum est volontaire, corriger les test -->
    <AppBreadcrumb :items="[{ label: 'Tableau de bord' }]" />

    <main class="min-h-screen overflow-x-hidden bg-background py-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <ValuationSection v-if="overview.holdings.length" :overview="overview" />

            <EvolutionSection
                :hidden-asset-ids="hiddenAssetIds"
                :series="evolutionSeries"
            />


            <PerformancesSection v-if="overview.holdings.length" :performances="performances" />



            <HoldingsSection
                :holdings="overview.holdings"
                :hidden-asset-ids="hiddenAssetIds"
                :series="evolutionSeries"
                @toggle="toggleAsset"
            />

            <SectorsSection v-if="overview.holdings.length" :slices="sectorBreakdown" />




        </div>
    </main>
</template>
