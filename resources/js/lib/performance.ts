import { largestOf, relativeBarWidth } from '@/lib/bars';
import { eur, signedEur } from '@/lib/format';

export interface Performance {
    key: string;
    label: string;
    startDate: string;
    valueStart: number;
    contributions: number;
    gain: number;
    pct: number;
}

export interface PerformanceBar {
    performance: Performance;
    barWidth: string;
    barColor: string;
    /** Les colonnes que le tableau ne peut plus porter restent lisibles, à un survol près. */
    title: string;
}

/**
 * Les périodes prêtes à tracer. L'ordre vient du serveur et n'est pas retrié. Les barres se
 * mesurent sur l'amplitude du mouvement, pas sur son signe, pour qu'une baisse de 20 % pèse
 * visuellement autant qu'une hausse de 20 %.
 */
export const performanceBars = (performances: Performance[]): PerformanceBar[] => {
    const largest = largestOf(performances.map((performance: Performance): number => Math.abs(performance.pct)));

    return performances.map((performance: Performance): PerformanceBar => ({
        performance,
        barWidth: relativeBarWidth(Math.abs(performance.pct), largest),
        barColor: performance.pct < 0 ? 'bg-loss-bar' : 'bg-gain-bar',
        title: `Valeur début ${eur(performance.valueStart)} · Apports ${signedEur(performance.contributions)}`,
    }));
};
