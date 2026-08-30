<script setup lang="ts">
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import type { TransactionLine } from '@/lib/instrument';

const props = defineProps<{ transactions: TransactionLine[] }>();
</script>

<template>
    <!--
        Repliée à l'ouverture de la fiche : le graphe de valorisation tient le haut de l'écran, et
        l'historique déroulé y repousserait performances et secteurs hors de l'écran.
    -->
    <CollapsibleSection section="transactions" title="Transactions">
        <TransactionYearList
            :lines="props.transactions"
            variant="bare"
            empty-label="Aucune transaction sur cet actif."
        />
    </CollapsibleSection>
</template>
