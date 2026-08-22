<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppPage from '@/components/AppPage.vue';
import WealthEvolutionSection from '@/components/dashboard/WealthEvolutionSection.vue';
import WealthIncomeSection from '@/components/dashboard/WealthIncomeSection.vue';
import WealthSummarySection from '@/components/dashboard/WealthSummarySection.vue';
import type { WealthIncome, WealthOverview, WealthSeries } from '@/lib/wealth';

const props = defineProps<{
    overview: WealthOverview;
    series?: WealthSeries;
    income?: WealthIncome;
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <!-- Pas de fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppPage>
        <WealthSummarySection :overview="props.overview" />

        <WealthEvolutionSection :series="props.series" />

        <WealthIncomeSection :income="props.income" />
    </AppPage>
</template>
