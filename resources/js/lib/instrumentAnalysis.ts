import { eur, pct, sharePct } from '@/lib/format';
import type { InstrumentAnalysis } from '@/lib/instrument';

export type IndicatorId =
    | 'price'
    | 'pru'
    | 'pruGap'
    | 'high52w'
    | 'high52wGap'
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

/**
 * Le serveur rend une profondeur positive ; la chute se lit avec son signe. Une chute nulle est un
 * cas à part : `pct(-0)` colle un `+` (car `-0 >= 0`) devant un `-0,0` que rend `toLocaleString`,
 * soit `+-0,0 %`. `sharePct`, qui ne pose jamais de signe, évite l'écueil.
 */
const drawdown = (value: number | null): string =>
    value === null ? '—' : value === 0 ? sharePct(0) : pct(-value);

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
                { indicator: 'price', label: 'Prix', value: eur(analysis.price) },
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
