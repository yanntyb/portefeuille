export const eur = (value: number | null, digits = 2): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: digits });

/** Keeps the sign visible on gains so a positive amount never reads like a plain balance. */
export const signedEur = (value: number | null, digits = 2): string =>
    value === null ? '—' : value > 0 ? `+${eur(value, digits)}` : eur(value, digits);

const oneDecimal = (value: number): string =>
    value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

export const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${oneDecimal(value)} %`;

/** Formats a base 100 value as its signed delta, e.g. 112.4 becomes "+12,4 %". */
export const signedPct = (value: number): string => {
    const delta = value - 100;

    return `${delta >= 0 ? '+' : ''}${oneDecimal(delta)} %`;
};

export const frDate = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

export const gainClass = (value: number | null): string =>
    value === null || value === 0 ? 'text-muted-foreground' : value > 0 ? 'text-gain' : 'text-loss';
