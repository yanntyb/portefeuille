<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyCashFlowSection from '@/components/property/PropertyCashFlowSection.vue';
import PropertyExpenseYears from '@/components/property/PropertyExpenseYears.vue';
import PropertyHeroSection from '@/components/property/PropertyHeroSection.vue';
import PropertyLoanSection from '@/components/property/PropertyLoanSection.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
import PropertyRentHistory from '@/components/property/PropertyRentHistory.vue';
import type { AmortizationLine, PropertyDetail } from '@/lib/realEstate';

const props = defineProps<{
    property: PropertyDetail;
    amortization?: AmortizationLine[];
}>();
</script>

<template>
    <Head :title="props.property.name" />

    <AppPage>
        <PropertyHeroSection :property="props.property" />

        <PropertyLoanSection v-if="props.property.loan" :loan="props.property.loan" :lines="amortization" />

        <PropertyMetricsGrid :metrics="props.property.metrics" :loan="props.property.loan" />

        <PropertyCashFlowSection :flows="props.property.monthlyCashFlows" />

        <PropertyRentHistory :months="props.property.rentHistory" />

        <PropertyExpenseYears :years="props.property.expenseYears" />
    </AppPage>

    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.property.name },
        ]"
    />
</template>
