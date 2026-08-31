import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import type { NamedTransactionLine } from '@/lib/instrument';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

const line: NamedTransactionLine = {
    id: 42,
    walletId: 3,
    date: '2026-03-04',
    assetId: 7,
    assetName: 'Bitcoin',
    isSell: true,
    typeLabel: 'Vente',
    quantity: 2,
    unitPrice: 300,
    fees: 1.5,
    total: 598.5,
};

beforeEach((): void => {
    setActivePinia(createPinia());
});

describe('ouverture en création', () => {
    it('part d\'un formulaire vierge, sans ligne à corriger', () => {
        const dialog = useTransactionDialogStore();

        dialog.openCreate();

        expect(dialog.mode).toBe('create');
        expect(dialog.editingId).toBeNull();
        expect(dialog.draft.quantity).toBe('');
        expect(dialog.draft.fees).toBe('0');
        expect(dialog.lockedAssetId).toBeNull();
    });

    it('impose l\'actif quand la page en a un', () => {
        const dialog = useTransactionDialogStore();

        dialog.openCreate({ id: 7, name: 'Bitcoin' });

        /** Sur une fiche d'actif, saisir une opération sur un autre titre serait invisible ici. */
        expect(dialog.lockedAssetId).toBe(7);
        expect(dialog.lockedAssetName).toBe('Bitcoin');
        expect(dialog.draft.assetId).toBe('7');
    });
});

describe('ouverture en correction', () => {
    it('pré-remplit tout le formulaire depuis la ligne', () => {
        const dialog = useTransactionDialogStore();

        dialog.openEdit(line);

        expect(dialog.mode).toBe('edit');
        expect(dialog.editingId).toBe(42);
        expect(dialog.draft).toEqual({
            walletId: '3',
            assetId: '7',
            date: '2026-03-04',
            type: 'sell',
            quantity: '2',
            unitPrice: '300',
            fees: '1.5',
        });
    });

    it('remonte la clé du formulaire à chaque ouverture', () => {
        const dialog = useTransactionDialogStore();
        const start = dialog.formKey;

        dialog.openCreate();
        const afterCreate = dialog.formKey;
        dialog.openEdit(line);

        /**
         * `useForm()` fige ses valeurs initiales : sans une clé qui change, passer de « créer » à
         * « corriger la ligne 42 » garderait les anciennes valeurs et les anciennes erreurs.
         */
        expect(afterCreate).toBeGreaterThan(start);
        expect(dialog.formKey).toBeGreaterThan(afterCreate);
    });
});

describe('confirmation de suppression', () => {
    it('remplace le volet depuis le formulaire, et y revient à l\'annulation', () => {
        const dialog = useTransactionDialogStore();

        dialog.openEdit(line);
        dialog.askDelete('Vente de 2 le 04/03');

        expect(dialog.mode).toBe('confirm-delete');
        expect(dialog.deleting).toEqual({ id: 42, label: 'Vente de 2 le 04/03' });

        dialog.backToForm();

        expect(dialog.mode).toBe('edit');
        expect(dialog.deleting).toBeNull();
        /** Le formulaire est intact : on n'a fait qu'aller voir la confirmation. */
        expect(dialog.draft.quantity).toBe('2');
    });

    it('ferme à l\'annulation quand la demande venait de la liste', () => {
        const dialog = useTransactionDialogStore();

        dialog.askDeleteLine(line, 'Vente de 2 le 04/03');

        expect(dialog.mode).toBe('confirm-delete');
        expect(dialog.editingId).toBe(42);

        dialog.backToForm();

        /** Il n'y a pas de formulaire derrière : y « revenir » ouvrirait ce qu'on n'a pas demandé. */
        expect(dialog.mode).toBe('closed');
    });

    it('ne demande rien sans ligne à supprimer', () => {
        const dialog = useTransactionDialogStore();

        dialog.openCreate();
        dialog.askDelete('peu importe');

        expect(dialog.mode).toBe('create');
        expect(dialog.deleting).toBeNull();
    });
});

describe('fermeture', () => {
    it('remet tout à zéro', () => {
        const dialog = useTransactionDialogStore();

        dialog.openEdit(line, { id: 7, name: 'Bitcoin' });
        dialog.close();

        expect(dialog.mode).toBe('closed');
        expect(dialog.editingId).toBeNull();
        expect(dialog.deleting).toBeNull();
        expect(dialog.lockedAssetId).toBeNull();
        expect(dialog.lockedAssetName).toBeNull();
        expect(dialog.draft.quantity).toBe('');
    });
});
