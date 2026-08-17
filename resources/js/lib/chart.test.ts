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

type DataZoom = {
    type: string;
    start: number;
    end: number;
    minValueSpan: number;
    showDetail?: boolean;
    handleLabel?: { show: boolean };
};

const dataZoomOf = (option: ChartOption): DataZoom[] => option.dataZoom as DataZoom[];

/** 365 jours en millisecondes : le plancher de la fenêtre visible. */
const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

describe('buildValueVsInvestedOption — zoom', () => {
    it('ouvre sur les douze derniers mois d\'un historique de trois ans', () => {
        const [inside] = dataZoomOf(valueVsInvested(36));

        expect(inside.end).toBe(100);
        expect(inside.end - inside.start).toBeGreaterThan(32);
        expect(inside.end - inside.start).toBeLessThan(35);
    });

    it('montre tout l\'historique quand il est plus court qu\'un an', () => {
        const [inside] = dataZoomOf(valueVsInvested(6));

        expect(inside.start).toBe(0);
        expect(inside.end).toBe(100);
    });

    it('montre tout l\'historique quand il n\'atteint pas un an', () => {
        // Douze étiquettes mensuelles depuis janvier 2023 s'arrêtent au 1er décembre : 334 jours.
        const [inside] = dataZoomOf(valueVsInvested(12));

        expect(inside.start).toBe(0);
        expect(inside.end).toBe(100);
    });

    it('montre encore tout l\'historique quand il fait exactement un an', () => {
        // Treize étiquettes vont du 1er janvier 2023 au 1er janvier 2024 : 365 jours pile,
        // 2023 n'étant pas bissextile. Le plancher se compare avec `<=`, la fenêtre reste entière.
        const [inside] = dataZoomOf(valueVsInvested(13));

        expect(inside.start).toBe(0);
        expect(inside.end).toBe(100);
    });

    it('rogne l\'historique dès qu\'il dépasse un an', () => {
        // Quatorze étiquettes vont jusqu'au 1er février 2024 : 396 jours, donc start ≈ 7,83.
        const [inside] = dataZoomOf(valueVsInvested(14));

        expect(inside.start).toBeGreaterThan(0);
        expect(inside.start).toBeLessThan(10);
        expect(inside.end).toBe(100);
    });

    it('montre tout sur un historique vide, sans produire de fenêtre absurde', () => {
        const option = buildValueVsInvestedOption({
            labels: [],
            value: [],
            invested: [],
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Vide.',
        });

        expect(dataZoomOf(option)[0]).toMatchObject({ start: 0, end: 100 });
    });

    it('interdit au lecteur de descendre sous un an, sur les deux commandes de zoom', () => {
        const zooms = dataZoomOf(valueVsInvested(36));

        expect(zooms).toHaveLength(2);
        expect(zooms[0].minValueSpan).toBe(ONE_YEAR_MS);
        expect(zooms[1].minValueSpan).toBe(ONE_YEAR_MS);
    });

    it('respecte la fenêtre déjà choisie par le lecteur plutôt que de la remettre à douze mois', () => {
        const zooms = dataZoomOf(valueVsInvested(36, { start: 10, end: 60 }));

        expect(zooms[0]).toMatchObject({ start: 10, end: 60 });
        expect(zooms[1]).toMatchObject({ start: 10, end: 60 });
    });

    it('laisse les poignées de zoom muettes, leurs bornes se lisant déjà sur l\'axe', () => {
        const [, slider] = dataZoomOf(valueVsInvested(36));

        expect(slider.type).toBe('slider');
        expect(slider.showDetail).toBe(false);
        expect(slider.handleLabel?.show).toBe(false);
    });

    it('offre le zoom à la molette autant qu\'à la mini-timeline', () => {
        expect(dataZoomOf(valueVsInvested(36)).map((zoom) => zoom.type)).toEqual(['inside', 'slider']);
    });
});

describe('sumPerAsset', () => {
    it('somme les titres point par point : le tableau de bord raisonne sur le portefeuille entier', () => {
        const perAsset = [
            { assetId: 1, name: 'ACME', value: [100, 110, 120], invested: [80, 80, 80] },
            { assetId: 2, name: 'BETA', value: [50, 55, 60], invested: [40, 40, 40] },
        ];

        expect(sumPerAsset(perAsset, (asset) => asset.value, 3)).toEqual([150, 165, 180]);
        expect(sumPerAsset(perAsset, (asset) => asset.invested, 3)).toEqual([120, 120, 120]);
    });

    it('traite un titre plus court que la série comme nul sur ses points manquants', () => {
        const perAsset = [
            { assetId: 1, name: 'ACME', value: [100, 110, 120], invested: [80, 80, 80] },
            { assetId: 2, name: 'BETA', value: [50], invested: [40] },
        ];

        expect(sumPerAsset(perAsset, (asset) => asset.value, 3)).toEqual([150, 110, 120]);
    });

    it('rend une série de zéros sans aucun titre', () => {
        expect(sumPerAsset([], (asset) => asset.value, 3)).toEqual([0, 0, 0]);
    });
});
