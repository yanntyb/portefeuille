<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import type { WealthTransactionLine } from '@/lib/wealth';

const props = defineProps<{ transactions?: WealthTransactionLine[] | null }>();
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
