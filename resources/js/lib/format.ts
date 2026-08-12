export const eur = (value: number | null, digits = 2): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: digits });

export const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

export const frDate = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

export const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';
