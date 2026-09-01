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
            amount: '',
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
        type: 'sell',
        quantity: 2,
        unitPrice: 300,
        fees: 1.5,
        total: 598.5,
        auto: false,
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
            amount: '',
        });
    });

    it('laisse l\'actif vide sur une ligne qui ne le nomme pas', () => {
        const { assetId: _, assetName: __, ...bare } = line;

        /** La fiche d'un actif impose le sien : elle le passe en surcharge. */
        expect(draftFromLine(bare).assetId).toBe('');
        expect(draftFromLine(bare, { assetId: '9' }).assetId).toBe('9');
    });

    it('reprend le montant d\'un mouvement d\'espèces, quantité et prix laissés vides', () => {
        const deposit: NamedTransactionLine = {
            ...line,
            assetId: null,
            assetName: null,
            isSell: false,
            typeLabel: 'Versement',
            type: 'deposit',
            quantity: 0,
            unitPrice: 0,
            total: 1000,
        };

        expect(draftFromLine(deposit)).toEqual({
            walletId: '3',
            assetId: '',
            date: '2026-03-04',
            type: 'deposit',
            quantity: '',
            unitPrice: '',
            fees: '1.5',
            amount: '1000',
        });
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

    it('omet quantité, prix et actif sur un versement, même s\'ils traînent encore', () => {
        /**
         * Un champ en trop est rejeté par une règle `prohibited` côté serveur — mais seulement s'il
         * est réellement absent : une clé présente, même vide, resterait évaluée par le `numeric`
         * qui l'accompagne et rejetterait la ligne. Basculer de type ne doit donc laisser passer
         * aucune saisie antérieure, même si le formulaire ne l'a pas effacée lui-même.
         */
        const payload = payloadOf(draft({ type: 'deposit', amount: '1 000,50' }));

        expect(payload).toMatchObject({ type: 'deposit', amount: '1000.5' });
        expect(payload).not.toHaveProperty('assetId');
        expect(payload).not.toHaveProperty('quantity');
        expect(payload).not.toHaveProperty('unitPrice');
    });

    /**
     * Les frais partaient quel que soit le type, alors que le champ n'est montré que pour un
     * ordre : un versement gardait donc ceux du dernier achat saisi, et s'affichait
     * « Versement · frais 5,00 € » sous un montant qui ne les compte pas.
     */
    it('omet les frais sur les types qui n\'en portent pas', () => {
        expect(payloadOf(draft({ type: 'deposit', amount: '1000', fees: '5' }))).not.toHaveProperty('fees');
        expect(payloadOf(draft({ type: 'withdrawal', amount: '1000', fees: '5' }))).not.toHaveProperty('fees');
        expect(payloadOf(draft({ type: 'dividend', amount: '1000', fees: '5' }))).not.toHaveProperty('fees');
        expect(payloadOf(draft({ type: 'buy', fees: '5' })).fees).toBe('5');
        expect(payloadOf(draft({ type: 'sell', fees: '5' })).fees).toBe('5');
    });

    it('omet quantité et prix sur un dividende, mais garde l\'actif et le montant', () => {
        const payload = payloadOf(draft({ type: 'dividend', assetId: '7', amount: '42,5' }));

        expect(payload).toMatchObject({ type: 'dividend', assetId: '7', amount: '42.5' });
        expect(payload).not.toHaveProperty('quantity');
        expect(payload).not.toHaveProperty('unitPrice');
    });

    it('omet le montant sur un ordre', () => {
        expect(payloadOf(draft({ type: 'buy', amount: '99' }))).not.toHaveProperty('amount');
    });
});
