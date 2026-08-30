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

/** Part d'un tout, une décimale, jamais de signe ajouté : « 42,1 % ». */
export const sharePct = (value: number | null): string => (value === null ? '—' : `${oneDecimal(value)} %`);

/**
 * Formate une fraction (0,0655 = 6,55 %), à la différence de `pct()` qui reçoit des points de
 * pourcentage déjà à l'échelle et signés. Deux décimales, jamais de signe : les ratios qu'elle
 * sert (rendement, LTV…) se lisent en valeur absolue.
 */
export const fractionPct = (ratio: number): string => `${(ratio * 100).toFixed(2).replace('.', ',')} %`;

/**
 * Rend une date ISO dans le format demandé, ou la valeur telle quelle si elle n'en est pas une.
 * Le `T00:00:00` évite le décalage d'un jour qu'un parsing UTC infligerait à une date sans heure.
 */
const frDateFormat = (value: string, options: Intl.DateTimeFormatOptions): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('fr-FR', options);
};

export const frDate = (value: string | null): string =>
    value === null ? '—' : frDateFormat(value, { day: '2-digit', month: '2-digit', year: 'numeric' });

/** Jour et mois seuls : dans un groupe déjà titré par son année, la répéter sur chaque ligne est du bruit. */
export const frDayMonth = (value: string): string => frDateFormat(value, { day: '2-digit', month: 'short' });

/** Mois d'un historique : « juil. 2026 », assez court pour tenir en tête de ligne. */
export const frMonthYear = (value: string): string => frDateFormat(value, { month: 'short', year: 'numeric' });

/** Date mise en avant — acquisition d'un bien : ni `frDate` ni `frDayMonth` ne s'y prêtent. */
export const frLongDate = (value: string): string =>
    frDateFormat(value, { day: 'numeric', month: 'long', year: 'numeric' });

export const gainClass = (value: number | null): string =>
    value === null || value === 0 ? 'text-muted-foreground' : value > 0 ? 'text-gain' : 'text-loss';

/**
 * Heure au format français courant (« 11h », « 23h30 ») plutôt que le « 11:00 » que rendrait
 * `toLocaleTimeString`. Les minutes ne sont montrées que si elles ne sont pas nulles — sinon les
 * prix se synchronisant en dehors de l'heure juste (23h30) perdraient l'information, tout en
 * restant lisible à l'heure pile.
 *
 * L'heure vient de `getHours()`, pas d'`Intl.DateTimeFormat` : sur cet environnement, un
 * `hour: 'numeric', hourCycle: 'h23'` rend quand même une heure sur deux chiffres suivie d'un
 * « h » littéral séparé par une espace (« 09 h »), pas le nombre nu attendu — un comportement
 * dépendant de la version d'ICU, pas garanti par la spec. `getHours()` renvoie un entier 0-23
 * sans zéro de tête, déterministe quel que soit l'environnement.
 */
export const syncedAtLabel = (timestamp: number | null): string => {
    if (timestamp === null) {
        return 'Données hors-ligne';
    }

    const date = new Date(timestamp);
    const day = date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    const hour = date.getHours();
    const minutes = date.getMinutes();
    const time = minutes === 0 ? `${hour}h` : `${hour}h${String(minutes).padStart(2, '0')}`;

    return `Données du ${day} à ${time}`;
};
