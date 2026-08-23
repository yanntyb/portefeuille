<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import ProfitabilitySection from '@/components/properties/ProfitabilitySection.vue';
import PropertyList from '@/components/properties/PropertyList.vue';
import RealEstateEvolutionSection from '@/components/properties/RealEstateEvolutionSection.vue';
import RealEstateSummarySection from '@/components/properties/RealEstateSummarySection.vue';
import RentalIncomeSection from '@/components/properties/RentalIncomeSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type {
    PropertyProfitability,
    RealEstateIncome,
    RealEstateOverview,
    RealEstateSeries,
} from '@/lib/realEstate';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    realEstate: RealEstateOverview;
    series?: RealEstateSeries;
    profitability?: PropertyProfitability[];
    income?: RealEstateIncome;
}>();

const snapshot = useSnapshotStore();

/** `realEstate` est synchrone côté serveur : elle est toujours là, rien à combler. */
const series = aheadOfNetwork(() => props.series, () => snapshot.propertiesList?.series);
const profitability = aheadOfNetwork(() => props.profitability, () => snapshot.propertiesList?.profitability);
const income = aheadOfNetwork(() => props.income, () => snapshot.propertiesList?.income);
</script>

<template>
    <Head title="Immobilier" />

    <AppPage>
        <RealEstateSummarySection :real-estate="props.realEstate" />

        <RealEstateEvolutionSection :series="series" />

        <PropertyList :properties="props.realEstate.properties" />

        <ProfitabilitySection v-if="props.realEstate.properties.length" :profitability="profitability" />

        <RentalIncomeSection v-if="props.realEstate.properties.length" :income="income" />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Immobilier' }]" />
</template>
