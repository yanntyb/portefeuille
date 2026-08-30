import { eur, pct, sharePct } from '@/lib/format';
import type { InstrumentAnalysis } from '@/lib/instrument';

export type IndicatorId =
    | 'pru'
    | 'pruGap'
    | 'ma200'
    | 'ma200Gap'
    | 'rsi14'
    | 'high52w'
    | 'high52wGap'
    | 'atrPct'
    | 'maxDrawdown'
    | 'portfolioWeight';

export interface AnalysisRow {
    indicator: IndicatorId;
    label: string;
    value: string;
    /** Posé seulement là où le signe dit un gain ou une perte : ailleurs, il n'est qu'une direction. */
    gain?: number | null;
}

export interface AnalysisGroup {
    title: string | null;
    rows: AnalysisRow[];
}

/** Le RSI se lit en entier : sa décimale n'ajoute rien à une échelle de 0 à 100. */
const index = (value: number | null): string => (value === null ? '—' : String(Math.round(value)));

/** Le serveur rend une profondeur positive ; la chute se lit avec son signe. */
const drawdown = (value: number | null): string => (value === null ? '—' : pct(-value));

/**
 * Les repères d'analyse en groupes de lignes prêtes à rendre. Un repère absent garde sa ligne et
 * rend un tiret : la ligne dit ce que la fiche sait mesurer, son absence ne doit pas se lire comme
 * un oubli. Un groupe entièrement vide, lui, disparaît — il n'apprendrait rien.
 */
export const analysisGroups = (analysis: InstrumentAnalysis): AnalysisGroup[] => {
    const groups: AnalysisGroup[] = [
        {
            title: null,
            rows: [
                { indicator: 'pru', label: 'PRU', value: eur(analysis.pru) },
                {
                    indicator: 'pruGap',
                    label: 'Écart au PRU',
                    value: pct(analysis.pruGapPct),
                    gain: analysis.pruGapPct,
                },
            ],
        },
        {
            title: 'Tendance',
            rows: [
                { indicator: 'ma200', label: 'MM200', value: eur(analysis.ma200) },
                { indicator: 'ma200Gap', label: 'Cours vs MM200', value: pct(analysis.ma200GapPct) },
                { indicator: 'rsi14', label: 'RSI 14', value: index(analysis.rsi14) },
                { indicator: 'high52w', label: 'Plus-haut 52 s.', value: eur(analysis.high52w) },
                {
                    indicator: 'high52wGap',
                    label: 'Sous le plus-haut',
                    value: pct(analysis.high52wGapPct),
                },
            ],
        },
        {
            title: 'Risque',
            rows: [
                { indicator: 'atrPct', label: 'ATR 14', value: sharePct(analysis.atrPct) },
                { indicator: 'maxDrawdown', label: 'Max drawdown', value: drawdown(analysis.maxDrawdown) },
                {
                    indicator: 'portfolioWeight',
                    label: 'Poids du portefeuille',
                    value: sharePct(analysis.portfolioWeightPct),
                },
            ],
        },
    ];

    return groups.filter((group) => group.rows.some((row) => row.value !== '—'));
};
