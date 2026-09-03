<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import WealthClassesSection from '@/components/dashboard/WealthClassesSection.vue';
import WealthEvolutionSection from '@/components/dashboard/WealthEvolutionSection.vue';
import WealthIncomeSection from '@/components/dashboard/WealthIncomeSection.vue';
import WealthAccountsSection from '@/components/dashboard/WealthAccountsSection.vue';
import WealthSectorsSection from '@/components/dashboard/WealthSectorsSection.vue';
import WealthSummarySection from '@/components/dashboard/WealthSummarySection.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import TransactionsSection from '@/components/transactions/TransactionsSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { TransactionLine } from '@/lib/instrument';
import type { SyncState } from '@/lib/sync';
import type { WealthAccount, WealthIncome, WealthOverview, WealthSector, WealthSeries } from '@/lib/wealth';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: WealthOverview;
    sync: SyncState;
    series?: WealthSeries;
    income?: WealthIncome;
    sectors?: WealthSector[];
    transactions?: TransactionLine[];
    accounts?: WealthAccount[];
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const series = aheadOfNetwork(() => props.series, () => snapshot.dashboard?.series);
const income = aheadOfNetwork(() => props.income, () => snapshot.dashboard?.income);
const sectors = aheadOfNetwork(() => props.sectors, () => snapshot.dashboard?.sectors);
const transactions = aheadOfNetwork(() => props.transactions, () => snapshot.dashboard?.transactions);
const accounts = aheadOfNetwork(() => props.accounts, () => snapshot.dashboard?.accounts);
</script>

<template>
    <Head title="Tableau de bord" />

    <AppPage>
        <WealthSummarySection :overview="props.overview" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; la répartition vient ensuite. -->
        <WealthEvolutionSection :series="series" />

        <WealthClassesSection :overview="props.overview" />

        <!-- Repliée sur son total : le chiffre se lit dans le titre, la ventilation se demande. -->
        <WealthIncomeSection :income="income" />

        <!-- Ce que le patrimoine rapporte, puis ce qui l'a fait bouger. -->
        <TransactionsSection :transactions="transactions" section="wealth-transactions" />

        <!-- Sous quel régime tout cela est tenu : la lecture par enveloppe, toutes classes
             confondues — un PEA tient des actions, un compte-titres tient le reste. -->
        <WealthAccountsSection :accounts="accounts" />

        <!-- La lecture la plus fine ferme la page : les secteurs qui traversent les classes. -->
        <WealthSectorsSection :sectors="sectors" />
    </AppPage>

    <!-- Barre sans fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppBottomBar :sync="props.sync" />

    <!--
        Frère d'`AppPage` et non enfant : le conteneur de la page est un `flex flex-col gap-6`, où
        un enfant sans rendu visible ajouterait un écart fantôme en bas de page.
    -->
    <TransactionDialog />
</template>
