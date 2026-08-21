<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyHeroSection from '@/components/property/PropertyHeroSection.vue';
import PropertyIncomeSection from '@/components/property/PropertyIncomeSection.vue';
import PropertyLoanSection from '@/components/property/PropertyLoanSection.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
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

        <PropertyMetricsGrid :metrics="props.property.metrics" />

        <PropertyLoanSection v-if="props.property.loan" :loan="props.property.loan" :lines="amortization" />

        <PropertyIncomeSection
            :flows="props.property.monthlyCashFlows"
            :rents="props.property.rentHistory"
            :expense-years="props.property.expenseYears"
        />
    </AppPage>

    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.property.name },
        ]"
    />
</template>
