<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyHeroSection from '@/components/property/PropertyHeroSection.vue';
import PropertyIncomeSection from '@/components/property/PropertyIncomeSection.vue';
import PropertyLoanSection from '@/components/property/PropertyLoanSection.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { AmortizationLine, PropertyDetail } from '@/lib/realEstate';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    property: PropertyDetail;
    amortization?: AmortizationLine[];
}>();

const snapshot = useSnapshotStore();

/** `property` est synchrone côté serveur : elle est toujours là, rien à combler. */
const amortization = aheadOfNetwork(
    () => props.amortization,
    () => snapshot.propertyPage(String(props.property.id))?.amortization,
);
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

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Immobilier', href: '/properties' },
            { label: props.property.name },
        ]"
    />
</template>
