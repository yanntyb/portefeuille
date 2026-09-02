<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import TransactionsSection from '@/components/instruments/TransactionsSection.vue';
import WalletEvolutionSection from '@/components/wallet/WalletEvolutionSection.vue';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';
import type { HoldingLine } from '@/lib/portfolio';
import type { SectorBreakdownRow } from '@/lib/sector';
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

/**
 * `SectorBreakdownList` lit des `SectorBreakdownRow` : la ventilation d'une enveloppe s'y coule
 * sans composant neuf, seuls les noms de champs changent. Les parts viennent du serveur, rien
 * n'est recalculé ici.
 */
const breakdownRows = computed<SectorBreakdownRow[]>(() =>
    (props.breakdown ?? []).map((slice) => ({
        label: slice.label,
        share: slice.share,
        amount: slice.value,
    })),
);
</script>

<template>
    <Head :title="title" />

    <AppPage>
        <WalletHeaderSection :account="props.account" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; le reste vient ensuite. -->
        <WalletEvolutionSection :series="props.evolution" />

        <InstrumentsSection :holdings="props.positions ?? []" />

        <CollapsibleSection section="wallet-breakdown" title="Répartition">
            <SectorBreakdownList :rows="breakdownRows" />
        </CollapsibleSection>

        <TransactionsSection
            :transactions="props.transactions"
            section="class-transactions"
            empty-label="Aucune transaction sur cette enveloppe."
        />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
