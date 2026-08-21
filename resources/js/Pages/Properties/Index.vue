<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyList from '@/components/properties/PropertyList.vue';
import RealEstateSummarySection from '@/components/properties/RealEstateSummarySection.vue';
import type { RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview }>();
</script>

<template>
    <Head title="Immobilier" />

    <AppPage>
        <RealEstateSummarySection :real-estate="props.realEstate" />

        <PropertyList :properties="props.realEstate.properties" />
    </AppPage>

    <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Immobilier' }]" />
</template>
