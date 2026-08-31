import type { TransactionLine } from '@/lib/instrument';
import { isoToday } from '@/lib/format';

/**
 * Le formulaire de saisie, de sa forme à l'envoi.
 *
 * Tous les champs sont des chaînes, y compris les montants. Deux raisons : un `<input>` rend une
 * chaîne, et surtout le serveur reste seul juge de la validité — coercer côté client ferait taire
 * une saisie fautive au lieu de la faire dire.
 */
export type TransactionDraft = {
    walletId: string;
    assetId: string;
    date: string;
    type: 'buy' | 'sell';
    quantity: string;
    unitPrice: string;
    fees: string;
};

/** Ce que le serveur reçoit : les clés camelCase du fil, les montants normalisés. */
export type TransactionPayload = {
    walletId: string;
    assetId: string;
    date: string;
    type: 'buy' | 'sell';
    quantity: string;
    unitPrice: string;
    fees: string;
};

/**
 * Lit un décimal saisi à la française. Rend `null` sur tout ce qui n'est pas un nombre — y compris
 * la chaîne vide — pour que l'appelant distingue « pas encore saisi » de « zéro ».
 *
 * La virgule est la touche décimale d'un clavier français, et c'est la raison pour laquelle les
 * champs sont en `type="text"` : sous `type="number"`, la virgule rend le champ invalide et `value`
 * revient vide, si bien que la saisie disparaît sans un mot.
 *
 * Les espaces — fine, insécable, ordinaire — sont retirées : coller un montant déjà formaté par
 * `eur()` doit marcher, c'est le geste naturel depuis un relevé de courtier.
 */
export const parseDecimalInput = (raw: string): number | null => {
    const cleaned = raw
        .replace(/[\s  ]/g, '')
        .replace(',', '.')
        .trim();

    if (cleaned === '') {
        return null;
    }

    const value = Number(cleaned);

    return Number.isFinite(value) ? value : null;
};

/**
 * Le montant de la ligne en cours de saisie, miroir de `Portfolio\Services\TransactionFlow` : les
 * frais s'ajoutent à ce que coûte un achat et se retranchent de ce que rapporte une vente.
 *
 * `null` tant que la quantité ou le prix n'est pas un nombre : afficher zéro laisserait croire à un
 * total calculé.
 */
export const transactionTotal = (draft: TransactionDraft): number | null => {
    const quantity = parseDecimalInput(draft.quantity);
    const unitPrice = parseDecimalInput(draft.unitPrice);

    if (quantity === null || unitPrice === null) {
        return null;
    }

    const fees = parseDecimalInput(draft.fees) ?? 0;
    const gross = quantity * unitPrice;

    return draft.type === 'sell' ? gross - fees : gross + fees;
};

/**
 * Un formulaire vierge. La date du jour et un achat : ce que l'on saisit le plus souvent, et les
 * deux seuls champs qu'on peut pré-remplir sans rien inventer.
 *
 * Les frais partent de `0` et non du vide : la plupart des lignes n'en portent pas, et un champ
 * vide se lirait comme un oubli.
 */
export const emptyDraft = (overrides: Partial<TransactionDraft> = {}): TransactionDraft => ({
    walletId: '',
    assetId: '',
    date: isoToday(),
    type: 'buy',
    quantity: '',
    unitPrice: '',
    fees: '0',
    ...overrides,
});

/**
 * Le formulaire d'une ligne existante. `id` dit laquelle corriger, `walletId` dit où : sans lui,
 * l'enveloppe repartirait du vide et une simple correction de quantité déplacerait la ligne.
 *
 * `assetId` n'est présent que sur les lignes qui nomment leur actif ; sur la fiche d'un actif, il
 * est imposé par la page et passé en `overrides`.
 */
export const draftFromLine = (
    line: TransactionLine & { assetId?: number },
    overrides: Partial<TransactionDraft> = {},
): TransactionDraft => ({
    walletId: String(line.walletId),
    assetId: line.assetId === undefined ? '' : String(line.assetId),
    date: line.date,
    type: line.isSell ? 'sell' : 'buy',
    quantity: String(line.quantity),
    unitPrice: String(line.unitPrice),
    fees: String(line.fees),
    ...overrides,
});

/**
 * Normalise les montants juste avant l'envoi : le serveur attend un point décimal, le lecteur a
 * tapé une virgule. Un champ que `parseDecimalInput` refuse part **tel quel**, pour que le message
 * d'erreur porte sur ce qui a réellement été saisi.
 */
export const payloadOf = (draft: TransactionDraft): TransactionPayload => {
    const normalize = (raw: string): string => {
        const value = parseDecimalInput(raw);

        return value === null ? raw : String(value);
    };

    return {
        walletId: draft.walletId,
        assetId: draft.assetId,
        date: draft.date,
        type: draft.type,
        quantity: normalize(draft.quantity),
        unitPrice: normalize(draft.unitPrice),
        /** Un champ de frais vidé vaut zéro, ce que le serveur accepte comme absence. */
        fees: draft.fees.trim() === '' ? '0' : normalize(draft.fees),
    };
};
