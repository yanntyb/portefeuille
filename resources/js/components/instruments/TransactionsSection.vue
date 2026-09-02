<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
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
            <AddTransactionButton />
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

        <Deferred v-else data="transactions">
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
</template>
