<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import WealthClassesSection from '@/components/dashboard/WealthClassesSection.vue';
import WealthEvolutionSection from '@/components/dashboard/WealthEvolutionSection.vue';
import WealthIncomeSection from '@/components/dashboard/WealthIncomeSection.vue';
import WealthSectorsSection from '@/components/dashboard/WealthSectorsSection.vue';
import WealthSummarySection from '@/components/dashboard/WealthSummarySection.vue';
import WealthTransactionsSection from '@/components/dashboard/WealthTransactionsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { WealthIncome, WealthOverview, WealthSector, WealthSeries, WealthTransactionLine } from '@/lib/wealth';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: WealthOverview;
    series?: WealthSeries;
    income?: WealthIncome;
    sectors?: WealthSector[];
    transactions?: WealthTransactionLine[];
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const series = aheadOfNetwork(() => props.series, () => snapshot.dashboard?.series);
const income = aheadOfNetwork(() => props.income, () => snapshot.dashboard?.income);
const sectors = aheadOfNetwork(() => props.sectors, () => snapshot.dashboard?.sectors);
const transactions = aheadOfNetwork(() => props.transactions, () => snapshot.dashboard?.transactions);
</script>

<template>
    <Head title="Tableau de bord" />

    <AppPage>
        <WealthSummarySection :overview="props.overview" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; la répartition vient ensuite. -->
        <WealthEvolutionSection :series="series" />

        <WealthClassesSection :overview="props.overview" />

        <!-- Du plus gros grain au plus fin : les classes, puis les secteurs qui les traversent. -->
        <WealthSectorsSection :sectors="sectors" />

        <!-- Repliée sur son total : le chiffre se lit dans le titre, la ventilation se demande. -->
        <WealthIncomeSection :income="income" />

        <!-- L'historique ferme la page. -->
        <WealthTransactionsSection :transactions="transactions" />
    </AppPage>

    <!-- Barre sans fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppBottomBar />
</template>
