import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Ref } from 'vue';
import type { TransactionLine } from '@/lib/instrument';
import { consumeModalEntry, pushModalEntry } from '@/lib/modalHistory';
import { draftFromLine, emptyDraft, type TransactionDraft } from '@/lib/transactionForm';

export type TransactionDialogMode = 'closed' | 'create' | 'edit' | 'confirm-delete';

/** Ce que la modale a besoin de savoir de la ligne qu'elle s'apprête à supprimer. */
export type DeletionTarget = {
    id: number;
    label: string;
};

/**
 * L'état de la modale de saisie, partagé plutôt que local.
 *
 * Il n'y a pas de layout Inertia : chaque page est une racine indépendante, si bien qu'un
 * `provide` serait à répéter dans les trois pages et qu'un `inject` manquant échouerait en silence.
 * Et le bouton d'édition vit deux niveaux sous la page (`section` → `liste` → `ligne`) : un
 * chaînage d'émissions sur trois crans pour dire « corrige cette ligne » serait du bruit.
 *
 * C'est le motif de la maison — `snapshot`, `viewport`, `theme`, `serviceWorker` — avec l'instance
 * unique de `stores/pinia.ts`.
 */
export const useTransactionDialogStore = defineStore('transactionDialog', () => {
    const mode: Ref<TransactionDialogMode> = ref('closed');
    const draft: Ref<TransactionDraft> = ref(emptyDraft());
    const editingId: Ref<number | null> = ref(null);
    const deleting: Ref<DeletionTarget | null> = ref(null);
    /** D'où vient la demande de suppression : l'annulation n'a de formulaire où revenir que du volet. */
    const deleteCameFromForm: Ref<boolean> = ref(false);

    /** Instrument imposé par le contexte : sur une fiche d'actif, on ne saisit que celui-là. */
    const lockedAssetId: Ref<number | null> = ref(null);
    const lockedAssetName: Ref<string | null> = ref(null);

    /** Enveloppe imposée par le contexte : sur la page d'une enveloppe, on ne saisit que dedans. */
    const lockedWalletId: Ref<number | null> = ref(null);
    const lockedWalletName: Ref<string | null> = ref(null);

    /**
     * Remonte à chaque ouverture, pour servir de `key` au formulaire.
     *
     * `useForm()` fige ses valeurs initiales une fois pour toutes : passer de « créer » à
     * « corriger la ligne 42 » sans remonter le composant garderait les anciennes valeurs **et**
     * les anciennes erreurs.
     */
    const formKey: Ref<number> = ref(0);

    /**
     * Les deux contextes qu'une page peut imposer sont indépendants : une fiche d'actif nomme
     * l'instrument, la page d'une enveloppe nomme l'enveloppe. Aucune page ne les combine
     * aujourd'hui, mais rien ici ne l'interdit.
     */
    function openCreate(asset?: { id: number; name: string }, wallet?: { id: number; name: string }): void {
        lockedAssetId.value = asset?.id ?? null;
        lockedAssetName.value = asset?.name ?? null;
        lockedWalletId.value = wallet?.id ?? null;
        lockedWalletName.value = wallet?.name ?? null;
        editingId.value = null;
        deleting.value = null;
        deleteCameFromForm.value = false;
        draft.value = emptyDraft({
            ...(asset === undefined ? {} : { assetId: String(asset.id) }),
            ...(wallet === undefined ? {} : { walletId: String(wallet.id) }),
        });
        formKey.value += 1;
        mode.value = 'create';
        pushModalEntry();
    }

    function openEdit(line: TransactionLine, asset?: { id: number; name: string }): void {
        lockedAssetId.value = asset?.id ?? null;
        lockedAssetName.value = asset?.name ?? null;
        editingId.value = line.id;
        deleting.value = null;
        deleteCameFromForm.value = false;
        draft.value = draftFromLine(line, asset === undefined ? {} : { assetId: String(asset.id) });
        formKey.value += 1;
        mode.value = 'edit';
        pushModalEntry();
    }

    /** Depuis le formulaire d'édition : la confirmation remplace le volet, elle ne s'empile pas. */
    function askDelete(label: string): void {
        if (editingId.value === null) {
            return;
        }

        deleting.value = { id: editingId.value, label };
        deleteCameFromForm.value = true;
        mode.value = 'confirm-delete';
    }

    /** Depuis la liste, sans passer par le formulaire. */
    function askDeleteLine(line: TransactionLine, label: string): void {
        editingId.value = line.id;
        deleting.value = { id: line.id, label };
        deleteCameFromForm.value = false;
        mode.value = 'confirm-delete';
        /** La modale s'ouvre directement sur la confirmation : une entrée de garde lui est due. */
        pushModalEntry();
    }

    /**
     * Annulation d'une confirmation. Elle revient au formulaire quand on en venait, et ferme quand
     * la suppression a été demandée depuis la liste : il n'y aurait pas de formulaire où revenir.
     */
    function backToForm(): void {
        if (!deleteCameFromForm.value) {
            close();

            return;
        }

        deleting.value = null;
        mode.value = 'edit';
    }

    function close(): void {
        /**
         * Avant de fermer : l'entrée de garde doit disparaître de l'historique, sinon le retour
         * arrière suivant semblerait ne rien faire tout en remontant la page.
         */
        consumeModalEntry();

        mode.value = 'closed';
        editingId.value = null;
        deleting.value = null;
        deleteCameFromForm.value = false;
        lockedAssetId.value = null;
        lockedAssetName.value = null;
        lockedWalletId.value = null;
        lockedWalletName.value = null;
        draft.value = emptyDraft();
    }

    return {
        mode,
        draft,
        editingId,
        deleting,
        lockedAssetId,
        lockedAssetName,
        lockedWalletId,
        lockedWalletName,
        formKey,
        openCreate,
        openEdit,
        askDelete,
        askDeleteLine,
        backToForm,
        close,
    };
});
