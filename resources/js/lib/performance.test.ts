import { describe, expect, it } from 'vitest';
import { performanceBars, type Performance } from '@/lib/performance';

const normalizeSpaces = (value: string): string => value.replace(/[\xa0\u202f]/g, ' ');

const performance = (key: string, pct: number, gain = 100): Performance => ({
    key,
    label: key.toUpperCase(),
    startDate: '2026-01-01',
    valueStart: 1000,
    contributions: 200,
    gain,
    pct,
});

describe('performanceBars', () => {
    it('rend une barre par période, dans l\'ordre reçu du serveur', () => {
        const bars = performanceBars([performance('ytd', 10), performance('1y', 25)]);

        expect(bars.map((bar) => bar.performance.label)).toEqual(['YTD', '1Y']);
    });

    it('remplit toute la piste pour le mouvement le plus fort', () => {
        const bars = performanceBars([performance('ytd', 10), performance('1y', 25)]);

        expect(bars[1].barWidth).toBe('100%');
        expect(bars[0].barWidth).toBe('40%');
    });

    it('mesure une baisse sur son amplitude, pas sur son signe', () => {
        const bars = performanceBars([performance('ytd', -25), performance('1y', 25)]);

        expect(bars[0].barWidth).toBe('100%');
        expect(bars[1].barWidth).toBe('100%');
    });

    it('teinte la barre selon le sens du mouvement', () => {
        const bars = performanceBars([performance('ytd', -5), performance('1y', 5)]);

        expect(bars[0].barColor).toBe('bg-loss-bar');
        expect(bars[1].barColor).toBe('bg-gain-bar');
    });

    it('garde la valeur de départ et les apports dans l\'infobulle de la ligne', () => {
        const [bar] = performanceBars([performance('ytd', 10)]);

        expect(normalizeSpaces(bar.title)).toBe('Valeur début 1 000,00 € · Apports +200,00 €');
    });

    it('rend une barre nulle quand aucune période n\'a bougé', () => {
        const bars = performanceBars([performance('ytd', 0)]);

        expect(bars[0].barWidth).toBe('0%');
    });

    it('rend un tableau vide sans période', () => {
        expect(performanceBars([])).toEqual([]);
    });
});
