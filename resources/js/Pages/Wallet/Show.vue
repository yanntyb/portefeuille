<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';
import type { HoldingLine } from '@/lib/portfolio';
import type { ClassSeries, WalletClassSlice, WealthAccount, WealthTransactionLine } from '@/lib/wealth';

const props = defineProps<{
    account: WealthAccount;
    positions?: HoldingLine[];
    breakdown?: WalletClassSlice[];
    evolution?: ClassSeries;
    transactions?: WealthTransactionLine[];
}>();

/** Le courtier titre la page quand il est connu ; le nom du portefeuille sinon. */
const title = computed<string>(() => `${props.account.broker ?? props.account.walletName} (${props.account.accountTypeLabel})`);
</script>

<template>
    <Head :title="title" />

    <AppPage>
        <WalletHeaderSection :account="props.account" />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
