<script setup lang="ts">
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { Button } from '@/components/ui/button';
import { useNetworkStore } from '@/stores/network';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

/**
 * Le « + » de l'en-tête d'une section Transactions. Il vit dans le slot `aside` de
 * `CollapsibleSection`, dont la bascule de dépli est une couche **sœur** : le clic n'ouvre donc pas
 * la section, et le bouton reste visible section repliée.
 *
 * `assetId` n'est passé que par la fiche d'un actif : la modale y impose l'instrument.
 */
const props = defineProps<{ assetId?: number; assetName?: string }>();

const dialog = useTransactionDialogStore();
const network = useNetworkStore();

const label: ComputedRef<string> = computed((): string =>
    network.isOnline
        ? 'Ajouter une transaction'
        : 'Hors-ligne : la saisie est indisponible',
);

const open = (): void => {
    if (props.assetId !== undefined && props.assetName !== undefined) {
        dialog.openCreate({ id: props.assetId, name: props.assetName });

        return;
    }

    dialog.openCreate();
};
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        data-transaction-add
        :disabled="!network.isOnline"
        :aria-label="label"
        :title="label"
        class="text-muted-foreground hover:text-foreground"
        @click="open()"
    >
        <Plus />
    </Button>
</template>
