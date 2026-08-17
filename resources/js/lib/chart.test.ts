import { describe, expect, it, vi } from 'vitest';
import type { LineSeriesOption } from 'echarts/charts';
import type { ChartOption } from '@/lib/echarts';
import { eur } from '@/lib/format';

/**
 * `lib/theme` crée son `ref` via `usePreferredDark()` au chargement du module, ce qui rendrait la
 * palette dépendante de l'environnement. Un faux le fige sur le thème clair.
 */
vi.mock('@/lib/theme', () => ({ isDark: { value: false } }));

const { buildPriceHistoryOption, buildValueVsInvestedOption, sumPerAsset } = await import('@/lib/chart');

/** Étiquettes ISO au premier de chaque mois, à partir de janvier 2023. */
const monthlyLabels = (months: number): string[] =>
    Array.from({ length: months }, (_unused: unknown, index: number): string =>
        new Date(Date.UTC(2023, index, 1)).toISOString().slice(0, 10),
    );

const valueVsInvested = (months: number, window: { start: number; end: number } | null = null): ChartOption => {
    const labels = monthlyLabels(months);

    return buildValueVsInvestedOption({
        labels,
        value: labels.map((_unused: string, index: number): number => 1000 + index * 10),
        invested: labels.map((): number => 900),
        valueFormatter: (value: number): string => eur(value, 0),
        window,
        description: 'Évolution de la valeur du portefeuille face aux montants investis.',
    });
};

const yAxisOf = (option: ChartOption) =>
    option.yAxis as { scale: boolean; axisLabel: { formatter: (value: number) => string } };

const seriesOf = (option: ChartOption): LineSeriesOption[] => option.series as LineSeriesOption[];

describe('buildValueVsInvestedOption — axes', () => {
    it('cadre l\'axe des valeurs sur les valeurs visibles au lieu de l\'ancrer à zéro', () => {
        expect(yAxisOf(valueVsInvested(36)).scale).toBe(true);
    });

    it('gradue l\'axe des valeurs en euros', () => {
        const formatter = yAxisOf(valueVsInvested(36)).axisLabel.formatter;

        expect(formatter(1000).replace(/[\xa0\u202f]/g, ' ')).toBe('1 000 €');
    });

    it('pose un axe temporel, pour que la graduation suive l\'amplitude visible', () => {
        expect((valueVsInvested(36).xAxis as { type: string }).type).toBe('time');
    });
});

describe('buildValueVsInvestedOption — séries', () => {
    it('trace toujours les deux courbes, la comparaison étant la lecture et non une option', () => {
        expect(seriesOf(valueVsInvested(36)).map((serie) => serie.name)).toEqual(['Valeur', 'Investi']);
    });

    it('trace l\'investi en escalier : il ne bouge qu\'à un achat ou une vente', () => {
        const [, invested] = seriesOf(valueVsInvested(36));

        expect(invested.step).toBe('end');
        expect(invested.lineStyle?.type).toBe('dashed');
    });

    it('trace la valeur sur une aire dégradée', () => {
        const [value] = seriesOf(valueVsInvested(36));
        const area = value.areaStyle?.color as { type?: string; colorStops?: unknown[] };

        expect(area.type).toBe('linear');
        expect(area.colorStops).toHaveLength(2);
    });

    it('marque la dernière valeur d\'une pastille, qui ancre le « où en est-on »', () => {
        const [value] = seriesOf(valueVsInvested(36));
        const markPoint = value.markPoint as unknown as { data: { coord: [string, number] }[] };

        expect(markPoint.data).toHaveLength(1);
        expect(markPoint.data[0].coord[0]).toBe('2025-12-01');
    });

    it('ne marque rien sur un historique vide', () => {
        const option = buildValueVsInvestedOption({
            labels: [],
            value: [],
            invested: [],
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Vide.',
        });

        expect(seriesOf(option)[0].markPoint).toBeUndefined();
    });

    it('associe chaque valeur à sa date, l\'axe temporel attendant des couples', () => {
        const [value] = seriesOf(valueVsInvested(3));

        expect(value.data).toEqual([
            ['2023-01-01', 1000],
            ['2023-02-01', 1010],
            ['2023-03-01', 1020],
        ]);
    });
});

describe('buildValueVsInvestedOption — description accessible', () => {
    it('porte la description rédigée à la main plutôt que le gabarit anglais d\'ECharts', () => {
        const aria = valueVsInvested(36).aria as { enabled: boolean; label: { description: string } };

        expect(aria.enabled).toBe(true);
        expect(aria.label.description).toBe('Évolution de la valeur du portefeuille face aux montants investis.');
    });
});

describe('buildPriceHistoryOption', () => {
    it('trace une seule courbe pour le cours', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((_unused: string, index: number): number => 100 + index),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(seriesOf(option).map((serie) => serie.name)).toEqual(['Cours']);
    });

    it('cadre l\'axe des cours sur les cotations visibles au lieu de l\'ancrer à zéro', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((_unused: string, index: number): number => 100 + index),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(yAxisOf(option).scale).toBe(true);
    });

    it('n\'offre pas de zoom : la fiche instrument le porte sur le graphe de valorisation', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((): number => 100),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(option.dataZoom).toBeUndefined();
    });
});
