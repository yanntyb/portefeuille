<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { eur, frDate } from '@/lib/format';
import type { WealthTransactionLine } from '@/lib/wealth';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

const props = defineProps<{ transactions?: WealthTransactionLine[] | null }>();

const dialog = useTransactionDialogStore();

/**
 * Ce que le volet de confirmation récapitule : de quelle opération il s'agit, en une phrase.
 *
 * Un versement ou un retrait n'a pas d'actif : `assetName` y est `null`, et une quantité de 0 n'y
 * dit rien. La phrase se recompose alors sur le type et le montant, sans nom ni quantité fantôme.
 */
const labelOf = (line: WealthTransactionLine): string => {
    if (line.assetName === null) {
        return `${line.typeLabel} ${eur(line.total)} du ${frDate(line.date)}`;
    }

    return `${line.typeLabel} de ${line.quantity} ${line.assetName} du ${frDate(line.date)}`;
};
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
            @delete="dialog.askDeleteLine($event, labelOf($event as WealthTransactionLine))"
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
