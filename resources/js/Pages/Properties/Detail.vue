<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyAmortizationTable from '@/components/property/PropertyAmortizationTable.vue';
import PropertyCashFlowSection from '@/components/property/PropertyCashFlowSection.vue';
import PropertyExpenseYears from '@/components/property/PropertyExpenseYears.vue';
import PropertyHeroSection from '@/components/property/PropertyHeroSection.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
import PropertyRentHistory from '@/components/property/PropertyRentHistory.vue';
import PropertyValueSection from '@/components/property/PropertyValueSection.vue';
import type { AmortizationLine, PropertyDetail, PropertyValueSeries } from '@/lib/realEstate';

const props = defineProps<{
    property: PropertyDetail;
    valueSeries?: PropertyValueSeries;
    amortization?: AmortizationLine[];
}>();
</script>

<template>
    <Head :title="props.property.name" />

    <AppPage>
        <PropertyHeroSection :property="props.property" />

        <PropertyValueSection :series="props.valueSeries" />

        <PropertyMetricsGrid :metrics="props.property.metrics" :loan="props.property.loan" />

        <PropertyCashFlowSection :flows="props.property.monthlyCashFlows" />

        <PropertyRentHistory :months="props.property.rentHistory" />

        <PropertyExpenseYears :years="props.property.expenseYears" />

        <PropertyAmortizationTable v-if="props.property.loan" :loan="props.property.loan" :lines="amortization" />
    </AppPage>

    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.property.name },
        ]"
    />
</template>
