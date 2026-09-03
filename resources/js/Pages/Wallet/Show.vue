<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import AnalysisSection from '@/components/instruments/AnalysisSection.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import SectorsSection from '@/components/SectorsSection.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import TransactionsSection from '@/components/transactions/TransactionsSection.vue';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';
import type { BasketAnalysis } from '@/lib/basketAnalysis';
import type { TransactionLine } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, HoldingLine } from '@/lib/portfolio';
import { rowsFromShares, type SectorBreakdownRow, type SectorSlice } from '@/lib/sector';
import type { WalletClassSlice, WealthAccount } from '@/lib/wealth';

const props = defineProps<{
    account: WealthAccount;
    positions?: HoldingLine[];
    breakdown?: WalletClassSlice[];
    evolution?: EvolutionSeries;
    performances?: Performance[];
    basketAnalysis?: BasketAnalysis;
    sectorBreakdown?: SectorSlice[];
    transactions?: TransactionLine[];
}>();

/** Le courtier titre la page quand il est connu ; le nom du portefeuille sinon. */
const title = computed<string>(() => `${props.account.broker ?? props.account.walletName} (${props.account.accountTypeLabel})`);

const breakdownRows = computed<SectorBreakdownRow[] | null>(() =>
    props.breakdown === undefined ? null : rowsFromShares(props.breakdown),
);
</script>

<template>
    <Head :title="title" />

    <AppPage>
        <WalletHeaderSection :account="props.account" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; le reste vient ensuite. -->
        <EvolutionSection
            :series="props.evolution"
            section="wallet-evolution"
            defer-key="evolution"
            total-description="Valeur de l'enveloppe dans le temps, comparée au montant investi."
        />

        <!-- Une enveloppe ne sert pas de tendances : sans `[]`, le squelette attendrait une prop jamais servie, indéfiniment. -->
        <InstrumentsSection
            :holdings="props.positions ?? null"
            :trends="[]"
            defer-key="positions"
        />

        <SectorsSection
            section="wallet-breakdown"
            title="Répartition"
            defer-key="breakdown"
            :rows="breakdownRows"
            collapsible
            empty-label="Aucune position dans cette enveloppe."
        />

        <!--
            La même section que sur une page d'exposition : une enveloppe et une poche sont deux
            découpes du même portefeuille, et leur analyse est la même lecture. `has-sectors` est
            toujours vrai — une enveloppe mêle les classes, et le bloc a son propre état vide.
        -->
        <!-- `?? null` : une prop Inertia non arrivée vaut `undefined`, et la section lit `null` comme « pas encore ». -->
        <AnalysisSection
            :analysis="props.basketAnalysis"
            :performances="props.performances ?? null"
            :has-sectors="true"
            :slices="props.sectorBreakdown ?? null"
        />

        <!-- Le « + » de la section hérite de l'enveloppe : la saisie ouverte d'ici n'a pas à la redemander. -->
        <TransactionsSection
            :transactions="props.transactions"
            section="wallet-transactions"
            empty-label="Aucune transaction sur cette enveloppe."
            :wallet="{ id: props.account.walletId, name: title }"
        />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
