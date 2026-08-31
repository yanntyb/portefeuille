<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import TransactionForm from '@/components/transactions/TransactionForm.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { refreshableKeys } from '@/lib/inertiaRefresh';
import { useSnapshotStore } from '@/stores/snapshot';
import { useTransactionDialogStore } from '@/stores/transactionDialog';
import { usePage } from '@inertiajs/vue3';

/**
 * La modale de saisie, montée une fois par page en frère de `AppPage` — pas dedans : son conteneur
 * est un `flex flex-col gap-6`, où un enfant sans rendu visible ajouterait un écart fantôme en bas
 * de page.
 *
 * Un seul `Dialog` qui change de volet, et non un second dialogue empilé pour la confirmation :
 * deux dialogues reka-ui superposés donnent deux verrous de défilement, deux pièges de focus et un
 * `Échap` ambigu. Le volet de confirmation reste un vrai dialogue modal — titre, description,
 * actions — ce que la règle « jamais `confirm()` » demande.
 */
const dialog = useTransactionDialogStore();
const snapshot = useSnapshotStore();
const page = usePage();

const isOpen: ComputedRef<boolean> = computed((): boolean => dialog.mode !== 'closed');

const isConfirming: ComputedRef<boolean> = computed((): boolean => dialog.mode === 'confirm-delete');

const title: ComputedRef<string> = computed((): string => {
    if (isConfirming.value) {
        return 'Supprimer cette transaction ?';
    }

    return dialog.editingId === null ? 'Nouvelle transaction' : 'Modifier la transaction';
});

const deleting: Ref<boolean> = ref(false);

const confirmDelete = (): void => {
    if (dialog.deleting === null || deleting.value) {
        return;
    }

    deleting.value = true;

    router.delete(`/transactions/${dialog.deleting.id}`, {
        only: refreshableKeys(page.props),
        preserveState: true,
        preserveScroll: true,
        onSuccess: (): void => {
            dialog.close();
            void snapshot.sync();
        },
        onFinish: (): void => {
            deleting.value = false;
        },
    });
};

/** Fermer par la croix, par `Échap` ou par l'extérieur remet l'état à zéro comme « Annuler ». */
const onOpenChange = (open: boolean): void => {
    if (!open) {
        dialog.close();
    }
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="onOpenChange">
        <!-- Sans plafond de hauteur, les boutons sortent de l'écran clavier virtuel ouvert. -->
        <DialogContent class="max-h-[calc(100dvh-2rem)] overflow-y-auto" data-transaction-dialog>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="isConfirming && dialog.deleting !== null">
                    {{ dialog.deleting.label }} — cette suppression est définitive et la position
                    sera reprojetée.
                </DialogDescription>
            </DialogHeader>

            <template v-if="isConfirming">
                <DialogFooter>
                    <!-- Le focus part sur l'annulation : la sortie sûre, pas l'action destructrice. -->
                    <Button type="button" variant="outline" :disabled="deleting" @click="dialog.backToForm()">
                        Annuler
                    </Button>

                    <Button
                        type="button"
                        variant="destructive"
                        data-transaction-confirm-delete
                        :disabled="deleting"
                        @click="confirmDelete()"
                    >
                        {{ deleting ? 'Suppression…' : 'Supprimer' }}
                    </Button>
                </DialogFooter>
            </template>

            <!--
                La clé remonte le formulaire à chaque ouverture : `useForm` fige ses valeurs
                initiales, et sans elle une correction hériterait des valeurs et des erreurs de la
                précédente.
            -->
            <TransactionForm v-else :key="dialog.formKey" />
        </DialogContent>
    </Dialog>
</template>
