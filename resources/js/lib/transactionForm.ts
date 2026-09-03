import type { TransactionKind, TransactionLine } from '@/lib/instrument';
import { isoToday } from '@/lib/format';

/**
 * Le formulaire de saisie, de sa forme à l'envoi.
 *
 * Tous les champs sont des chaînes, y compris les montants. Deux raisons : un `<input>` rend une
 * chaîne, et surtout le serveur reste seul juge de la validité — coercer côté client ferait taire
 * une saisie fautive au lieu de la faire dire.
 *
 * Les cinq types se partagent la même forme, jamais tous ses champs à la fois : `assetId`,
 * `quantity` et `unitPrice` ne valent que pour un ordre, `amount` que pour un mouvement d'espèces
 * — un dividende porte les deux, actif et montant. `payloadOf` fait le tri avant l'envoi, le
 * serveur refusant par `prohibited` tout champ que le type saisi n'autorise pas.
 */
export type TransactionDraft = {
    walletId: string;
    assetId: string;
    date: string;
    type: TransactionKind;
    quantity: string;
    unitPrice: string;
    fees: string;
    amount: string;
};

/**
 * Ce que le serveur reçoit : les clés camelCase du fil, les montants normalisés. `assetId`,
 * `quantity`, `unitPrice`, `fees` et `amount` sont **absents**, pas vides, quand le type saisi ne
 * les autorise pas : un `numeric` conditionné par `prohibited` s'évalue quand même sur une clé
 * présente, fût-elle une chaîne vide, et la rejetterait sans le vouloir.
 */
export type TransactionPayload = {
    walletId: string;
    date: string;
    type: TransactionKind;
    assetId?: string;
    quantity?: string;
    unitPrice?: string;
    fees?: string;
    amount?: string;
};

/** Un ordre porte quantité et prix ; un mouvement d'espèces (dividende compris) porte un montant. */
export const isTradeType = (type: TransactionKind): boolean => type === 'buy' || type === 'sell';

/** Actif requis : un ordre l'échange, un dividende l'expose — seuls versement et retrait s'en passent. */
export const isAssetType = (type: TransactionKind): boolean => type !== 'deposit' && type !== 'withdrawal';

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
 * Le mouvement d'espèces de la ligne saisie, signé, miroir de `TransactionFlow::cashDelta` :
 * positif quand l'argent entre sur le compte, négatif quand il en sort.
 *
 * Zéro — et non `null` — dès que la ligne n'est pas chiffrée : l'unique appelant en fait un terme
 * de solde, et un terme manquant ne doit pas le déplacer.
 */
export const cashDeltaOf = (draft: TransactionDraft): number => {
    const magnitude = isTradeType(draft.type)
        ? transactionTotal(draft) ?? 0
        : parseDecimalInput(draft.amount) ?? 0;

    if (magnitude === 0) {
        /** Sans ce retour, un retrait sans montant rendrait `-0`, qui n'est pas `0`. */
        return 0;
    }

    return draft.type === 'buy' || draft.type === 'withdrawal' ? -magnitude : magnitude;
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
    amount: '',
    ...overrides,
});

/**
 * Le formulaire d'une ligne existante. `id` dit laquelle corriger, `walletId` dit où : sans lui,
 * l'enveloppe repartirait du vide et une simple correction de quantité déplacerait la ligne.
 *
 * `assetId` est nul sur un mouvement d'espèces, qui n'a pas d'actif ; sur la fiche d'un actif, il
 * est imposé par la page et passé en `overrides`. `total` est toujours positif (contrat de
 * `TransactionFlow`) : sur un mouvement d'espèces, c'est directement le montant à reprendre.
 */
export const draftFromLine = (
    line: TransactionLine,
    overrides: Partial<TransactionDraft> = {},
): TransactionDraft => {
    const trade = isTradeType(line.type);

    return {
        walletId: String(line.walletId),
        assetId: line.assetId === null ? '' : String(line.assetId),
        date: line.date,
        type: line.type,
        quantity: trade ? String(line.quantity) : '',
        unitPrice: trade ? String(line.unitPrice) : '',
        fees: String(line.fees),
        amount: trade ? '' : String(line.total),
        ...overrides,
    };
};

/**
 * Normalise les montants juste avant l'envoi : le serveur attend un point décimal, le lecteur a
 * tapé une virgule. Un champ que `parseDecimalInput` refuse part **tel quel**, pour que le message
 * d'erreur porte sur ce qui a réellement été saisi.
 *
 * Chaque champ que le type saisi n'autorise pas est **omis**, pas vidé : une clé présente, fût-elle
 * une chaîne vide, reste évaluée par les règles `numeric` qui accompagnent le `prohibited` du
 * serveur, et une chaîne vide n'est pas un nombre — elle rejetterait la ligne au lieu de la laisser
 * passer.
 */
export const payloadOf = (draft: TransactionDraft): TransactionPayload => {
    const normalize = (raw: string): string => {
        const value = parseDecimalInput(raw);

        return value === null ? raw : String(value);
    };

    const trade = isTradeType(draft.type);

    return {
        walletId: draft.walletId,
        date: draft.date,
        type: draft.type,
        ...(isAssetType(draft.type) ? { assetId: draft.assetId } : {}),
        ...(trade ? { quantity: normalize(draft.quantity), unitPrice: normalize(draft.unitPrice) } : {}),
        /**
         * Les frais ne partent que pour un ordre, seul type dont le formulaire montre le champ.
         * Ils partaient jusque-là quel que soit le type, si bien qu'un versement gardait les frais
         * du dernier achat saisi et s'affichait « Versement · frais 5,00 € » sous un montant qui
         * ne les compte pas. Un champ de frais vidé vaut zéro, ce que le serveur lit comme absence.
         */
        ...(trade ? { fees: draft.fees.trim() === '' ? '0' : normalize(draft.fees) } : {}),
        ...(trade ? {} : { amount: normalize(draft.amount) }),
    };
};
