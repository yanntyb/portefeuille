<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import ProfitabilitySection from '@/components/properties/ProfitabilitySection.vue';
import PropertyList from '@/components/properties/PropertyList.vue';
import RealEstateEvolutionSection from '@/components/properties/RealEstateEvolutionSection.vue';
import RealEstateSummarySection from '@/components/properties/RealEstateSummarySection.vue';
import RentalIncomeSection from '@/components/properties/RentalIncomeSection.vue';
import type {
    PropertyProfitability,
    RealEstateIncome,
    RealEstateOverview,
    RealEstateSeries,
} from '@/lib/realEstate';

const props = defineProps<{
    realEstate: RealEstateOverview;
    series?: RealEstateSeries;
    profitability?: PropertyProfitability[];
    income?: RealEstateIncome;
}>();
</script>

<template>
    <Head title="Immobilier" />

    <AppPage>
        <RealEstateSummarySection :real-estate="props.realEstate" />

        <RealEstateEvolutionSection :series="props.series" />

        <PropertyList :properties="props.realEstate.properties" />

        <ProfitabilitySection v-if="props.realEstate.properties.length" :profitability="props.profitability" />

        <RentalIncomeSection v-if="props.realEstate.properties.length" :income="props.income" />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Immobilier' }]" />
</template>
