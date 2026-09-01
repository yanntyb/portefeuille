import { largestOf, relativeBarWidth } from '@/lib/bars';
import { frDayMonth, signedEur } from '@/lib/format';

/**
 * `quantity` et `amountPerShare` sont nulles quand elles ne sont pas connues : un dividende
 * confirmé dont le détachement dérivé a disparu n'a personne pour les dire, et zéro se lirait
 * comme un fait mesuré. `amount` est toujours connu — c'est ce qui a été encaissé.
 */
export interface DividendReceipt {
    assetId: number;
    exDate: string;
    quantity: number | null;
    amountPerShare: number | null;
    amount: number;
}

export interface AssetDividendHistory {
    receipts: DividendReceipt[];
    totalReceived: number;
    last12Months: number;
    /** Attendu sur les douze prochains mois, extrapolé des détachements récents. */
    estimatedAnnual: number;
    /** Perçu sur douze mois rapporté au coût de la position, en pourcentage. */
    yieldOnCost: number | null;
}

/** Montants indexés par origine de revenu : `dividend` aujourd'hui, un loyer demain. */
export type IncomeBySource = Record<string, number>;

export interface IncomeSummary {
    totalReceived: number;
    last12Months: number;
    /** Attendu sur les douze prochains mois, toutes origines confondues. */
    estimatedAnnual: number;
    bySource: IncomeBySource;
}

export interface AnnualIncome {
    year: number;
    total: number;
    bySource: IncomeBySource;
}

export interface AnnualIncomeBar {
    year: number;
    total: number;
    barWidth: string;
}

/** Une barre par année, mesurée contre la meilleure année et non contre leur somme. */
export const annualIncomeBars = (rows: AnnualIncome[]): AnnualIncomeBar[] => {
    const largest = largestOf(rows.map((row) => row.total));

    return rows.map((row) => ({
        year: row.year,
        total: row.total,
        barWidth: relativeBarWidth(row.total, largest),
    }));
};

export interface DividendYear {
    year: string;
    /** Perçu sur l'année, détachements cumulés. */
    total: number;
    receipts: DividendReceipt[];
}

/** Regroupe les détachements par année, la plus récente en tête, l'ordre reçu conservé dans chaque groupe. */
export const dividendYears = (receipts: DividendReceipt[]): DividendYear[] => {
    const groups = new Map<string, DividendReceipt[]>();

    for (const receipt of receipts) {
        const year = receipt.exDate.slice(0, 4);
        groups.set(year, [...(groups.get(year) ?? []), receipt]);
    }

    return [...groups.entries()]
        .sort(([left], [right]) => right.localeCompare(left))
        .map(([year, yearReceipts]) => ({
            year,
            total: yearReceipts.reduce((total, receipt) => total + receipt.amount, 0),
            receipts: yearReceipts,
        }));
};

export interface DividendMark {
    /** Point de la série de valorisation sur lequel la pastille se pose. */
    index: number;
    /** Date réelle du détachement, et non celle du point : la pastille n'est qu'une approximation. */
    dateLabel: string;
    amountLabel: string;
}

/** Dernier point qui précède la date, ou `-1` quand la série commence après elle. */
const pointIndexFor = (labels: string[], exDate: string): number =>
    labels.reduce(
        (found: number, label: string, index: number): number => (label <= exDate ? index : found),
        -1,
    );

/**
 * Pastilles de détachement à poser sur la courbe de valorisation. La série est hebdomadaire : un
 * détachement tombe presque toujours entre deux points, et se cale donc sur le dernier qui le
 * précède — le caler en avant le montrerait avant qu'il ait eu lieu. Un détachement antérieur au
 * premier point n'a aucun point d'accroche : la position n'y était pas encore valorisée, il est
 * écarté. Deux détachements calés sur le même point gardent chacun leur repère, l'infobulle les
 * énonçant l'un sous l'autre plutôt qu'en un cumul qui perdrait leurs dates.
 */
export const dividendMarks = (labels: string[], receipts: DividendReceipt[]): DividendMark[] =>
    receipts
        .map((receipt: DividendReceipt) => ({ receipt, index: pointIndexFor(labels, receipt.exDate) }))
        .filter(({ index }: { index: number }): boolean => index >= 0)
        .sort((left, right) => left.index - right.index)
        .map(({ receipt, index }): DividendMark => ({
            index,
            dateLabel: frDayMonth(receipt.exDate),
            amountLabel: signedEur(receipt.amount),
        }));
