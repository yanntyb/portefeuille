import type { IndicatorId } from '@/lib/instrumentAnalysis';

export interface IndicatorHelp {
    title: string;
    subtitle: string;
    body: string[];
    /** Encart en chasse fixe, comme dans l'aide des performances. */
    formula?: string;
    /** La limite de lecture, posée en dernier et en retrait : ce que le chiffre ne dit pas. */
    caveat?: string;
}

/**
 * Le texte des aides de la section Analyse, en données plutôt qu'en gabarit.
 *
 * Une seule entrée aujourd'hui : l'ATR est le seul repère dont le nom ne dit pas ce qu'il mesure.
 * Les autres — prix de revient, plus-haut, poids — se lisent dans leur libellé, et un bouton
 * d'aide par ligne encombrait la section sans rien apprendre. La table reste partielle par
 * construction : `AnalysisRow` n'affiche le bouton que là où une aide existe, si bien qu'en
 * rajouter une suffit à la faire apparaître.
 */
export const indicatorHelp: Partial<Record<IndicatorId, IndicatorHelp>> = {
    atrPct: {
        title: 'Comment lire l\'ATR',
        subtitle: 'L\'amplitude quotidienne moyenne, en pourcentage du cours',
        body: [
            'De combien bouge une journée ordinaire. « 1,9 % » veut dire que l\'écart entre le haut et le bas d\'une séance vaut en moyenne 1,9 % du cours.',
            'Exprimé en pourcentage, il se compare d\'un instrument à l\'autre : c\'est le chiffre qui dit lequel de deux titres secoue le plus.',
            'Il sert à dimensionner : à risque égal, plus l\'ATR est haut, plus la ligne doit rester petite.',
        ],
        formula: 'moyenne des amplitudes vraies sur 14 séances ÷ dernier cours',
    },
};
