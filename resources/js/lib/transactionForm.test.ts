import { afterEach, describe, expect, it, vi } from 'vitest';
import type { NamedTransactionLine } from '@/lib/instrument';
import {
    draftFromLine,
    emptyDraft,
    parseDecimalInput,
    payloadOf,
    transactionTotal,
    type TransactionDraft,
} from '@/lib/transactionForm';

const draft = (overrides: Partial<TransactionDraft> = {}): TransactionDraft =>
    emptyDraft({ walletId: '3', assetId: '7', quantity: '2', unitPrice: '300', fees: '1.5', ...overrides });

describe('parseDecimalInput', () => {
    it('lit la virgule décimale du clavier français', () => {
        expect(parseDecimalInput('1234,56')).toBe(1234.56);
    });

    it('avale un montant collé depuis un affichage formaté', () => {
        /** `eur()` rend « 1 234,56 € » avec une espace insécable : le coller doit marcher. */
        expect(parseDecimalInput('1 234,56')).toBe(1234.56);
        expect(parseDecimalInput('1 234,56')).toBe(1234.56);
    });

    it('distingue « pas encore saisi » de « zéro »', () => {
        expect(parseDecimalInput('')).toBeNull();
        expect(parseDecimalInput('   ')).toBeNull();
        expect(parseDecimalInput('0')).toBe(0);
    });

    it('refuse ce qui n\'est pas un nombre', () => {
        expect(parseDecimalInput('abc')).toBeNull();
        expect(parseDecimalInput('12,34,56')).toBeNull();
        expect(parseDecimalInput('12€')).toBeNull();
    });

    it('laisse passer un négatif, que le serveur refusera', () => {
        /** Le client ne juge pas : un refus muet ferait disparaître la saisie sans message. */
        expect(parseDecimalInput('-4')).toBe(-4);
    });
});

describe('transactionTotal', () => {
    it('alourdit un achat de ses frais', () => {
        expect(transactionTotal(draft({ type: 'buy' }))).toBe(601.5);
    });

    it('grève une vente de ses frais', () => {
        /** Miroir de `Portfolio\\Services\\TransactionFlow`, dans les deux sens. */
        expect(transactionTotal(draft({ type: 'sell' }))).toBe(598.5);
    });

    it('compte des frais absents pour zéro', () => {
        expect(transactionTotal(draft({ fees: '' }))).toBe(600);
    });

    it('ne rend rien tant que la quantité ou le prix n\'est pas un nombre', () => {
        expect(transactionTotal(draft({ quantity: '' }))).toBeNull();
        expect(transactionTotal(draft({ unitPrice: 'abc' }))).toBeNull();
    });
});

describe('emptyDraft', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('part de la date du jour et d\'un achat, frais à zéro', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 3, 15, 10, 0, 0));

        expect(emptyDraft()).toEqual({
            walletId: '',
            assetId: '',
            date: '2026-04-15',
            type: 'buy',
            quantity: '',
            unitPrice: '',
            fees: '0',
        });
    });

    it('accepte un instrument imposé par la page', () => {
        expect(emptyDraft({ assetId: '12' }).assetId).toBe('12');
    });
});

describe('draftFromLine', () => {
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

    it('reprend l\'enveloppe, l\'actif, le sens et les montants de la ligne', () => {
        expect(draftFromLine(line)).toEqual({
            walletId: '3',
            assetId: '7',
            date: '2026-03-04',
            type: 'sell',
            quantity: '2',
            unitPrice: '300',
            fees: '1.5',
        });
    });

    it('laisse l\'actif vide sur une ligne qui ne le nomme pas', () => {
        const { assetId: _, assetName: __, ...bare } = line;

        /** La fiche d'un actif impose le sien : elle le passe en surcharge. */
        expect(draftFromLine(bare).assetId).toBe('');
        expect(draftFromLine(bare, { assetId: '9' }).assetId).toBe('9');
    });
});

describe('payloadOf', () => {
    it('normalise les virgules en points', () => {
        expect(payloadOf(draft({ quantity: '2,5', unitPrice: '1 234,56', fees: '0,99' })))
            .toMatchObject({ quantity: '2.5', unitPrice: '1234.56', fees: '0.99' });
    });

    it('envoie tel quel un montant illisible, pour que l\'erreur porte sur la saisie', () => {
        expect(payloadOf(draft({ quantity: 'deux' })).quantity).toBe('deux');
    });

    it('compte un champ de frais vidé pour zéro', () => {
        expect(payloadOf(draft({ fees: '' })).fees).toBe('0');
    });

    it('ne touche ni à la date, ni au sens, ni aux identifiants', () => {
        expect(payloadOf(draft({ date: '2026-01-02', type: 'sell' })))
            .toMatchObject({ date: '2026-01-02', type: 'sell', walletId: '3', assetId: '7' });
    });
});
