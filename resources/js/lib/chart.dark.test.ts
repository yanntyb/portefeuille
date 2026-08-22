import { describe, expect, it, vi } from 'vitest';
import type { ChartOption } from '@/lib/echarts';
import { eur } from '@/lib/format';

/** Fichier séparé : `vi.mock` porte sur tout le module, un seul thème par fichier. */
vi.mock('@/lib/theme', () => ({ isDark: { value: true } }));

const { buildValueVsInvestedOption, buildWealthStackOption } = await import('@/lib/chart');

const option = (): ChartOption =>
    buildValueVsInvestedOption({
        labels: ['2025-01-01', '2025-06-01', '2026-01-01'],
        value: [1000, 1100, 1200],
        invested: [900, 900, 900],
        valueFormatter: (value: number): string => eur(value, 0),
        window: null,
        description: 'Thème sombre.',
    });

describe('palette sombre', () => {
    it('teinte les libellés d\'axe pour qu\'ils restent lisibles sur un fond sombre', () => {
        const axisLabel = (option().xAxis as { axisLabel: { color: string } }).axisLabel;

        expect(axisLabel.color).toBe('#7f858f');
    });

    it('donne une couleur différente du thème clair : un mutant rendant la même palette passerait inaperçu ici, mais pas dans chart.test.ts', () => {
        const axisLabel = (option().xAxis as { axisLabel: { color: string } }).axisLabel;

        expect(axisLabel.color).not.toBe('#9aa0ac');
    });

    it('garde la courbe et son nom, le thème ne changeant que les teintes', () => {
        expect((option().series as { name?: string }[]).map((serie) => serie.name)).toEqual(['Valeur']);
    });

    it('donne aux bandes du patrimoine des couleurs distinctes en thème sombre', () => {
        const option = buildWealthStackOption({
            labels: ['2026-01-05', '2026-01-12'],
            classes: [
                { label: 'Actions', values: [1000, 1100] },
                { label: 'Immobilier', values: [500, 520] },
                { label: 'Crypto', values: [200, 240] },
            ],
            invested: [1400, 1400],
            valueFormatter: (amount: number): string => `${amount} €`,
            window: null,
            description: 'Patrimoine total.',
        });

        const series = option.series as {
            areaStyle: { color: { colorStops: { color: string }[] } };
        }[];
        const tops = series.map((serie) => serie.areaStyle.color.colorStops[0].color);

        expect(new Set(tops).size).toBe(3);
        expect(tops[1]).toBe('rgba(224,167,95,0.22)');
        expect(tops[2]).toBe('rgba(232,121,249,0.22)');
    });
});
