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
 * La page peut imposer un cadre à la saisie : `assetId` n'est passé que par la fiche d'un actif, où
 * la modale impose l'instrument, et `walletId` que par la page d'une enveloppe, où elle impose
 * l'enveloppe. Chaque couple ne vaut que complet — sans nom, la modale n'aurait rien à afficher à
 * la place du sélecteur qu'elle retire.
 */
const props = defineProps<{
    assetId?: number;
    assetName?: string;
    walletId?: number;
    walletName?: string;
}>();

const dialog = useTransactionDialogStore();
const network = useNetworkStore();

const label: ComputedRef<string> = computed((): string =>
    network.isOnline
        ? 'Ajouter une transaction'
        : 'Hors-ligne : la saisie est indisponible',
);

const open = (): void => {
    const asset = props.assetId !== undefined && props.assetName !== undefined
        ? { id: props.assetId, name: props.assetName }
        : undefined;

    const wallet = props.walletId !== undefined && props.walletName !== undefined
        ? { id: props.walletId, name: props.walletName }
        : undefined;

    dialog.openCreate(asset, wallet);
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
