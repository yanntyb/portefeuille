<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyAmortizationTable from '@/components/property/PropertyAmortizationTable.vue';
import PropertyCashFlowTable from '@/components/property/PropertyCashFlowTable.vue';
import PropertyExpenseYears from '@/components/property/PropertyExpenseYears.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
import PropertyRentHistory from '@/components/property/PropertyRentHistory.vue';
import { eur } from '@/lib/format';
import type { AmortizationLine, PropertyDetail } from '@/lib/realEstate';

const props = defineProps<{ property: PropertyDetail; amortization?: AmortizationLine[] }>();

/**
 * Date longue (« 20 août 2026 ») : ni `frDate` (jj/mm/aaaa) ni `frDayMonth` (jour + mois court) de
 * `lib/format` ne conviennent à la date d'acquisition, mise en avant en tête de page. Le
 * `T00:00:00` évite le décalage d'un jour qu'un parsing UTC infligerait à une date sans heure.
 */
const frLongDate = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
};
</script>

<template>
    <Head :title="props.property.name" />

    <AppPage>
        <header data-section="hero" class="flex flex-col gap-1 px-6">
            <h1 class="text-xl font-bold">{{ props.property.name }}</h1>
            <p v-if="props.property.address" class="text-sm text-muted-foreground">{{ props.property.address }}</p>
            <p class="text-sm text-muted-foreground">
                Acquis le {{ frLongDate(props.property.acquisitionDate) }} pour
                {{ eur(props.property.acquisitionPrice) }}
                <template v-if="props.property.acquisitionFees > 0">
                    (+ {{ eur(props.property.acquisitionFees) }} de frais)
                </template>
                · estimé {{ eur(props.property.currentValue) }} ·
                <span class="font-semibold text-foreground">{{ eur(props.property.netWorth) }}</span> net
            </p>
        </header>

        <PropertyMetricsGrid :metrics="props.property.metrics" :loan="props.property.loan" />

        <PropertyCashFlowTable :flows="props.property.monthlyCashFlows" />

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
