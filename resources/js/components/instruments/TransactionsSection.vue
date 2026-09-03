<script setup lang="ts">
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { transactionLabelOf } from '@/lib/instrument';
import type { NamedTransactionLine } from '@/lib/instrument';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

const props = withDefaults(
    defineProps<{
        transactions?: NamedTransactionLine[] | null;
        /**
         * Ce que nomme `data-section` : l'état du pli est un `ref` local, rien n'est partagé entre
         * pages — c'est la lecture du DOM et les tests qui veulent savoir de quelle page il s'agit.
         */
        section?: string;
        emptyLabel?: string;
        /**
         * L'enveloppe de la page, quand il y en a une : la saisie ouverte depuis ici la reprend
         * telle quelle. La même section sert une page de classe, qui n'en a pas — les deux props
         * y restent indéfinis et la modale repart d'un sélecteur.
         */
        walletId?: number;
        walletName?: string;
    }>(),
    { transactions: null, section: 'class-transactions', emptyLabel: 'Aucune transaction sur cette classe.' },
);

const dialog = useTransactionDialogStore();
</script>

<template>
    <!--
        Repliée à l'arrivée, et la prop différée ne part qu'au dépli : le `Deferred` vit sous le pli
        de `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert. La page ne paie donc
        l'historique de la poche que pour qui le demande.
    -->
    <CollapsibleSection :section="props.section" title="Transactions">
        <template #aside>
            <AddTransactionButton :wallet-id="props.walletId" :wallet-name="props.walletName" />
        </template>

        <TransactionYearList
            v-if="props.transactions !== null && props.transactions !== undefined"
            :lines="props.transactions"
            variant="named"
            :empty-label="props.emptyLabel"
            editable
            @edit="dialog.openEdit($event as NamedTransactionLine)"
            @delete="dialog.askDeleteLine($event, transactionLabelOf($event as NamedTransactionLine))"
        />

        <DeferredBlock v-else data="transactions" />
    </CollapsibleSection>
</template>
