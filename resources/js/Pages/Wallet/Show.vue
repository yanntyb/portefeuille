<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Head } from '@inertiajs/vue3';
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

/**
 * Vrai dès que la prop différée est arrivée, même vide. Sans cette distinction, une ventilation
 * pas encore arrivée et une enveloppe qui ne tient rien rendent le même bloc vide — hors-ligne
 * comme pendant le chargement.
 */
const breakdownLoaded = computed<boolean>(() => props.breakdown !== undefined && props.breakdown !== null);

/**
 * Même logique que `breakdownLoaded`, mais pour les positions : c'est le cas le plus grave,
 * `InstrumentsSection` affirmant sinon qu'une enveloppe ne tient rien avant d'avoir la réponse.
 */
const positionsLoaded = computed<boolean>(() => props.positions !== undefined && props.positions !== null);
</script>

<template>
    <Head :title="title" />

    <AppPage>
        <WalletHeaderSection :account="props.account" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; le reste vient ensuite. -->
        <WalletEvolutionSection :series="props.evolution" />

        <!-- Une enveloppe ne sert pas de tendances : sans `[]`, le squelette attendrait une prop jamais servie, indéfiniment. -->
        <InstrumentsSection
            :holdings="props.positions ?? []"
            :trends="[]"
            defer-key="positions"
            :loaded="positionsLoaded"
        />

        <CollapsibleSection section="wallet-breakdown" title="Répartition">
            <template v-if="breakdownLoaded">
                <SectorBreakdownList :rows="breakdownRows" />
            </template>

            <Deferred v-else data="breakdown">
                <template #fallback>
                    <div class="flex flex-col gap-2">
                        <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                    </div>
                </template>

                <template #rescue>
                    <p class="py-8 text-center text-sm text-muted-foreground">
                        Données indisponibles hors-ligne.
                    </p>
                </template>

                <span />
            </Deferred>
        </CollapsibleSection>

        <TransactionsSection
            :transactions="props.transactions"
            section="wallet-transactions"
            empty-label="Aucune transaction sur cette enveloppe."
        />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
