<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyList from '@/components/properties/PropertyList.vue';
import RealEstateEvolutionSection from '@/components/properties/RealEstateEvolutionSection.vue';
import RealEstateSummarySection from '@/components/properties/RealEstateSummarySection.vue';
import type { RealEstateOverview, RealEstateSeries } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview; series?: RealEstateSeries }>();
</script>

<template>
    <Head title="Immobilier" />

    <AppPage>
        <RealEstateSummarySection :real-estate="props.realEstate" />

        <RealEstateEvolutionSection :series="props.series" />

        <PropertyList :properties="props.realEstate.properties" />
    </AppPage>

    <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Immobilier' }]" />
</template>
