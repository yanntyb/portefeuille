<script setup lang="ts">
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { frDate } from '@/lib/format';
import type { TransactionLine } from '@/lib/instrument';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

const props = defineProps<{ transactions: TransactionLine[]; assetId: number; assetName: string }>();

const dialog = useTransactionDialogStore();

/** L'actif vient de la page : les lignes nues ne le nomment pas. */
const asset = (): { id: number; name: string } => ({ id: props.assetId, name: props.assetName });
</script>

<template>
    <!--
        Repliée à l'ouverture de la fiche : le graphe de valorisation tient le haut de l'écran, et
        l'historique déroulé y repousserait performances et secteurs hors de l'écran.
    -->
    <CollapsibleSection section="transactions" title="Transactions">
        <!-- L'instrument de la fiche est imposé à la modale : on saisit ce qu'on regarde. -->
        <template #aside>
            <AddTransactionButton :asset-id="props.assetId" :asset-name="props.assetName" />
        </template>

        <TransactionYearList
            :lines="props.transactions"
            variant="bare"
            empty-label="Aucune transaction sur cet actif."
            editable
            @edit="dialog.openEdit($event, asset())"
        />
    </CollapsibleSection>
</template>
