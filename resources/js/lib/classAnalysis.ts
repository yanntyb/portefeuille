import { pct, sharePct } from '@/lib/format';
import type { AnalysisRow } from '@/lib/instrumentAnalysis';

export interface AnalysisInstrument {
    assetId: number;
    label: string;
}

/**
 * L'analyse d'une exposition entière. `maxDrawdown` est un pourcentage positif — le signe se pose
 * à l'affichage — quand `high52wGapPct` arrive déjà négatif ou nul.
 */
export interface ClassAnalysis {
    maxDrawdown: number | null;
    high52wGapPct: number | null;
    instruments: AnalysisInstrument[];
    /** Carrée et symétrique, dans l'ordre d'`instruments` ; `null` faute d'histoire commune. */
    correlations: (number | null)[][];
}

export interface CorrelationCell {
    value: string;
    /** La case d'un instrument avec lui-même : toujours parfaite, donc muette. */
    isSelf: boolean;
    tone: string;
}

export interface CorrelationRow {
    label: string;
    cells: CorrelationCell[];
}

/**
 * La chute se lit avec son signe. Une chute nulle est un cas à part : `pct(-0)` colle un `+` (car
 * `-0 >= 0`) devant un `-0,0` que rend `toLocaleString`. `sharePct`, qui ne pose jamais de signe,
 * évite l'écueil. Même raisonnement que dans `instrumentAnalysis`.
 */
const drawdown = (value: number | null): string =>
    value === null ? '—' : value === 0 ? sharePct(0) : pct(-value);

/**
 * Les deux repères de la poche entière, le plus-haut d'abord : c'est la distance du jour, quand la
 * chute maximale raconte des années. Un repère absent garde sa ligne et rend un tiret — la ligne
 * dit ce que la page sait mesurer, son absence ne doit pas se lire comme un oubli.
 */
export const basketRows = (analysis: ClassAnalysis): AnalysisRow[] => [
    {
        indicator: 'classHigh52wGap',
        label: 'Sous le plus-haut',
        value: pct(analysis.high52wGapPct),
    },
    {
        indicator: 'classMaxDrawdown',
        label: 'Max drawdown',
        value: drawdown(analysis.maxDrawdown),
    },
];

/**
 * La teinte d'une case : plus deux lignes bougent ensemble, plus elle chauffe. C'est le doublon
 * qu'on cherche à voir — deux fonds fortement corrélés ne diversifient rien — et la corrélation
 * négative, qui amortit, se lit au contraire en froid.
 */
const toneOf = (value: number | null): string => {
    if (value === null) {
        return 'text-muted-foreground';
    }

    if (value >= 0.8) {
        return 'bg-rose-500/25 text-foreground';
    }

    if (value >= 0.6) {
        return 'bg-rose-500/12 text-foreground';
    }

    if (value >= 0.3) {
        return 'bg-amber-500/15 text-foreground';
    }

    if (value >= 0) {
        return 'bg-emerald-500/10 text-foreground';
    }

    return 'bg-emerald-500/22 text-foreground';
};

/** Deux décimales, comme partout où une corrélation se cite : au centième près, elle se compare. */
const correlationLabel = (value: number | null): string =>
    value === null ? '—' : value.toFixed(2).replace('.', ',');

/** La matrice en lignes prêtes à rendre, chacune libellée par son instrument. */
export const correlationGrid = (analysis: ClassAnalysis): CorrelationRow[] =>
    analysis.instruments.map((instrument, line) => ({
        label: instrument.label,
        cells: analysis.instruments.map((_, column) => {
            const isSelf = line === column;
            const value = analysis.correlations[line]?.[column] ?? null;

            return {
                value: isSelf ? '' : correlationLabel(value),
                isSelf,
                tone: isSelf ? 'bg-muted' : toneOf(value),
            };
        }),
    }));
