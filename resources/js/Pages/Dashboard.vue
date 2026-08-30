<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import WealthClassesSection from '@/components/dashboard/WealthClassesSection.vue';
import WealthEvolutionSection from '@/components/dashboard/WealthEvolutionSection.vue';
import WealthIncomeSection from '@/components/dashboard/WealthIncomeSection.vue';
import WealthSummarySection from '@/components/dashboard/WealthSummarySection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { WealthIncome, WealthOverview, WealthSeries } from '@/lib/wealth';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: WealthOverview;
    series?: WealthSeries;
    income?: WealthIncome;
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const series = aheadOfNetwork(() => props.series, () => snapshot.dashboard?.series);
const income = aheadOfNetwork(() => props.income, () => snapshot.dashboard?.income);
</script>

<template>
    <Head title="Tableau de bord" />

    <AppPage>
        <WealthSummarySection :overview="props.overview" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; la répartition vient ensuite. -->
        <WealthEvolutionSection :series="series" />

        <WealthClassesSection :overview="props.overview" />

        <WealthIncomeSection :income="income" />
    </AppPage>

    <!-- Barre sans fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppBottomBar />
</template>
