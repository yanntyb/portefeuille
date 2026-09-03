<script setup lang="ts">
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { transactionLabelOf } from '@/lib/instrument';
import type { WealthTransactionLine } from '@/lib/wealth';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

const props = defineProps<{ transactions?: WealthTransactionLine[] | null }>();

const dialog = useTransactionDialogStore();
</script>

<template>
    <!--
        Repliée à l'arrivée, et la prop différée ne part qu'au dépli : le `Deferred` vit sous le pli
        de `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert. Le tableau de bord ne
        paie donc l'historique entier que pour qui le demande.
    -->
    <CollapsibleSection section="wealth-transactions" title="Transactions">
        <!--
            Dans le slot `aside`, dont la bascule de dépli est une couche sœur : le clic sur « + »
            n'ouvre donc pas la section, et le bouton reste atteignable pli fermé.
        -->
        <template #aside>
            <AddTransactionButton />
        </template>

        <TransactionYearList
            v-if="props.transactions !== null && props.transactions !== undefined"
            :lines="props.transactions"
            variant="named"
            empty-label="Aucune transaction pour l'instant."
            editable
            @edit="dialog.openEdit($event as WealthTransactionLine)"
            @delete="dialog.askDeleteLine($event, transactionLabelOf($event as WealthTransactionLine))"
        />

        <DeferredBlock v-else data="transactions" />
    </CollapsibleSection>
</template>
