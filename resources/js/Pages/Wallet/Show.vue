<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import AnalysisSection from '@/components/instruments/AnalysisSection.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import TransactionsSection from '@/components/instruments/TransactionsSection.vue';
import WalletEvolutionSection from '@/components/wallet/WalletEvolutionSection.vue';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';
import type { BasketAnalysis } from '@/lib/basketAnalysis';
import type { Performance } from '@/lib/performance';
import type { HoldingLine } from '@/lib/portfolio';
import type { SectorBreakdownRow, SectorSlice } from '@/lib/sector';
import type { ClassSeries, WalletClassSlice, WealthAccount, WealthTransactionLine } from '@/lib/wealth';

const props = defineProps<{
    account: WealthAccount;
    positions?: HoldingLine[];
    breakdown?: WalletClassSlice[];
    evolution?: ClassSeries;
    performances?: Performance[];
    basketAnalysis?: BasketAnalysis;
    sectorBreakdown?: SectorSlice[];
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

        <!--
            La même section que sur une page d'exposition : une enveloppe et une poche sont deux
            découpes du même portefeuille, et leur analyse est la même lecture. `has-sectors` est
            toujours vrai — une enveloppe mêle les classes, et le bloc a son propre état vide.
        -->
        <!--
            `?? null` sur les deux props différées, et ce n'est pas cosmétique : la section
            distingue `null` (« pas encore arrivé », squelette) de `[]` (« rien à montrer », état
            vide), et une prop Inertia non arrivée vaut `undefined`, qu'elle lirait comme arrivée.
            Les pages d'exposition passent par `aheadOfNetwork`, qui rend déjà `null`.
        -->
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
            :wallet-id="props.account.walletId"
            :wallet-name="title"
        />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
