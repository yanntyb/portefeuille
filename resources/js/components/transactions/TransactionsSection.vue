<script setup lang="ts">
import DeferredBlock from '@/components/DeferredBlock.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { transactionLabelOf, type TransactionLine } from '@/lib/instrument';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

/**
 * Le journal d'opérations d'une page : tableau de bord, exposition, enveloppe ou fiche, c'est la
 * même section. Repliée à l'arrivée, et la prop différée ne part qu'au dépli : le `DeferredBlock`
 * vit sous le pli de `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert — la page ne
 * paie l'historique que pour qui le demande. Une fiche sert ses lignes en synchrone et les passe
 * directement.
 *
 * `null` ou `undefined` : pas encore arrivé, squelette. `[]` : rien à montrer, `emptyLabel`.
 */
const props = withDefaults(
    defineProps<{
        transactions?: TransactionLine[] | null;
        /** Ce que nomme `data-section` : la lecture du DOM et les tests veulent savoir de quelle page il s'agit. */
        section?: string;
        emptyLabel?: string;
        /** `bare` sur une fiche, dont chaque ligne porte le même actif ; `named` partout ailleurs. */
        variant?: 'named' | 'bare';
        deferKey?: string;
        /** L'actif d'une fiche : imposé à la modale, on saisit ce qu'on regarde. */
        asset?: { id: number; name: string };
        /** L'enveloppe d'une page d'enveloppe : la saisie ouverte d'ici la reprend telle quelle. */
        wallet?: { id: number; name: string };
    }>(),
    {
        transactions: null,
        section: 'transactions',
        emptyLabel: 'Aucune transaction pour l\'instant.',
        variant: 'named',
        deferKey: 'transactions',
    },
);

const dialog = useTransactionDialogStore();
</script>

<template>
    <CollapsibleSection :section="props.section" title="Transactions">
        <!--
            Dans le slot `aside`, dont la bascule de dépli est une couche sœur : le clic sur « + »
            n'ouvre pas la section, et le bouton reste atteignable pli fermé.
        -->
        <template #aside>
            <AddTransactionButton
                :asset-id="props.asset?.id"
                :asset-name="props.asset?.name"
                :wallet-id="props.wallet?.id"
                :wallet-name="props.wallet?.name"
            />
        </template>

        <TransactionYearList
            v-if="props.transactions !== null && props.transactions !== undefined"
            :lines="props.transactions"
            :variant="props.variant"
            :empty-label="props.emptyLabel"
            editable
            @edit="dialog.openEdit($event, props.asset)"
            @delete="dialog.askDeleteLine($event, transactionLabelOf($event))"
        />

        <DeferredBlock v-else :data="props.deferKey" />
    </CollapsibleSection>
</template>
