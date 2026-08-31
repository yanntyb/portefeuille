import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import type { LineSeriesOption } from 'echarts/charts';
import type { ChartOption } from '@/lib/echarts';
import { eur } from '@/lib/format';
import type { DividendMark } from '@/lib/income';

const { useThemeStore } = await import('@/stores/theme');
const { axisGutter, buildPriceHistoryOption, buildValueVsInvestedOption, buildWealthStackOption, sumPerAsset } = await import('@/lib/chart');

/**
 * Le store de thème lit `usePreferredDark()`, qui rendrait la palette dépendante de
 * l'environnement. Fixer le mode explicitement la fige sur le thème clair.
 */
beforeEach((): void => {
    setActivePinia(createPinia());
    useThemeStore().mode = 'light';
});

/** Étiquettes ISO au premier de chaque mois, à partir de janvier 2023. */
const monthlyLabels = (months: number): string[] =>
    Array.from({ length: months }, (_unused: unknown, index: number): string =>
        new Date(Date.UTC(2023, index, 1)).toISOString().slice(0, 10),
    );

/** Instant d'une étiquette ISO, dans le fuseau où la fenêtre de zoom est calculée. */
const timeOf = (label: string): number => Date.parse(`${label}T00:00:00`);

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

type AreaGradient = { type: string; colorStops: { offset: number; color: string }[] };

const topStop = (gradient: AreaGradient): string => gradient.colorStops[0].color;

const bottomStop = (gradient: AreaGradient): string => gradient.colorStops[1].color;

type AxisExtent = { min: number; max: number };

const yAxisOf = (option: ChartOption) =>
    option.yAxis as {
        scale: boolean;
        min: (extent: AxisExtent) => number;
        max: (extent: AxisExtent) => number;
        axisLabel: { formatter: (value: number) => string };
        splitLine: { show: boolean };
    };

/**
 * Étiquette d'une graduation dans l'ordre où ECharts la demande : il borne l'axe sur les extrêmes
 * de la fenêtre visible, puis étiquette chaque graduation.
 */
const yAxisLabel = (option: ChartOption, value: number, extent: AxisExtent): string => {
    const yAxis = yAxisOf(option);

    yAxis.min(extent);
    yAxis.max(extent);

    return yAxis.axisLabel.formatter(value);
};

const xAxisLabelOf = (option: ChartOption) =>
    (option.xAxis as { axisLabel: { formatter: (value: number) => string } }).axisLabel;

const seriesOf = (option: ChartOption): LineSeriesOption[] => option.series as LineSeriesOption[];

describe('buildValueVsInvestedOption — axes', () => {
    it('cadre l\'axe des valeurs sur les valeurs visibles au lieu de l\'ancrer à zéro', () => {
        expect(yAxisOf(valueVsInvested(36)).scale).toBe(true);
    });

    it('gradue l\'axe des valeurs en euros', () => {
        const label = yAxisLabel(valueVsInvested(36), 1000, { min: 1000, max: 1350 });

        expect(label.replace(/[\xa0\u202f]/g, ' ')).toBe('1 000 €');
    });

    it('se passe de lignes de fond horizontales, les deux extrêmes chiffrés donnant l\'échelle', () => {
        expect(yAxisOf(valueVsInvested(36)).splitLine.show).toBe(false);
    });

    it('borne l\'axe des valeurs sur les extrêmes de la fenêtre visible', () => {
        const yAxis = yAxisOf(valueVsInvested(36));

        expect(yAxis.min({ min: 900, max: 1350 })).toBe(900);
        expect(yAxis.max({ min: 900, max: 1350 })).toBe(1350);
    });

    it('n\'étiquette que le minimum et le maximum, pas les graduations intermédiaires', () => {
        const option = valueVsInvested(36);
        const extent = { min: 900, max: 1350 };

        expect(yAxisLabel(option, 900, extent)).not.toBe('');
        expect(yAxisLabel(option, 1350, extent)).not.toBe('');
        expect(yAxisLabel(option, 1100, extent)).toBe('');
        expect(yAxisLabel(option, 1200, extent)).toBe('');
    });

    it('étiquette la graduation qu\'ECharts arrondit à un cheveu de l\'extrême', () => {
        // ECharts pose sa graduation extrême sur une valeur arrondie : au pixel près de la borne,
        // mais pas égale à elle. Une comparaison stricte laisserait l'axe sans aucune étiquette.
        const label = yAxisLabel(valueVsInvested(36), 900.2, { min: 900, max: 1350 });

        expect(label).not.toBe('');
    });

    it('pose un axe temporel, pour que la graduation suive l\'amplitude visible', () => {
        expect((valueVsInvested(36).xAxis as { type: string }).type).toBe('time');
    });

    it('nomme le mois de chaque graduation, là où le gabarit d\'ECharts effacerait janvier', () => {
        const formatter = xAxisLabelOf(valueVsInvested(36)).formatter;

        expect(formatter(new Date(2026, 2, 1).getTime())).toBe('mars');
        expect(formatter(new Date(2025, 11, 1).getTime())).toBe('déc.');
    });

    it('porte l\'année seule sous janvier, là où le repère change', () => {
        const formatter = xAxisLabelOf(valueVsInvested(36)).formatter;

        expect(formatter(new Date(2026, 0, 1).getTime())).toBe('2026');
    });

    it('date les graduations intra-mois au jour, plutôt que de répéter le mois', () => {
        const formatter = xAxisLabelOf(valueVsInvested(36)).formatter;

        expect(formatter(new Date(2026, 5, 11).getTime())).toBe('11 juin');
        expect(formatter(new Date(2026, 0, 21).getTime())).toBe('21 janv.');
    });

    it('descend à l\'heure quand la fenêtre est trop courte pour départager les jours', () => {
        const formatter = xAxisLabelOf(valueVsInvested(36)).formatter;

        expect(formatter(new Date(2026, 5, 11, 14, 30).getTime())).toBe('14:30');
    });

    it('réserve sous la grille la place de la graduation temporelle, en plus du zoom', () => {
        const grid = valueVsInvested(36).grid as { bottom: number };

        expect(grid.bottom).toBe(68);
    });

    it('élargit la gouttière des montants avec leur nombre de chiffres, sans les laisser couper', () => {
        const gutterOf = (scale: number): number => {
            const labels = monthlyLabels(36);

            return (buildValueVsInvestedOption({
                labels,
                value: labels.map((): number => 1000 * scale),
                invested: labels.map((): number => 900 * scale),
                valueFormatter: (value: number): string => eur(value, 0),
                window: null,
                description: 'Évolution.',
            }).grid as { left: number }).left;
        };

        expect(gutterOf(1000)).toBeGreaterThan(gutterOf(1));
    });

    it('préfère la gouttière imposée à celle que ses propres valeurs réclameraient', () => {
        const labels = monthlyLabels(36);
        const option = buildValueVsInvestedOption({
            labels,
            value: labels.map((): number => 1000),
            invested: labels.map((): number => 900),
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Évolution.',
            gutter: 120,
        });

        expect((option.grid as { left: number }).left).toBe(120);
    });

    it('garde la gouttière stable quand le zoom réduit la fenêtre visible', () => {
        const leftOf = (option: ChartOption): number => (option.grid as { left: number }).left;

        const zoomed = valueVsInvested(36, { start: timeOf('2025-01-01'), end: timeOf('2025-12-01') });

        expect(leftOf(zoomed)).toBe(leftOf(valueVsInvested(36)));
    });
});

describe('buildValueVsInvestedOption — séries', () => {
    it('trace la valeur et l\'investi, dont l\'écart est le gain', () => {
        expect(seriesOf(valueVsInvested(36)).map((serie) => serie.name)).toEqual(['Valeur', 'Investi']);
    });

    it('trace l\'investi en escalier pointillé et sans aire, celle de la valeur le recouvrant', () => {
        const [, invested] = seriesOf(valueVsInvested(36));

        expect(invested.step).toBe('end');
        expect(invested.lineStyle?.type).toBe('dashed');
        expect(invested.areaStyle).toBeUndefined();
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

describe('axisGutter', () => {
    it('réclame la largeur de la série la plus bavarde, pour que toutes tiennent dans le même cadre', () => {
        const position = { values: [123456], valueFormatter: (value: number): string => eur(value, 0) };
        const price = { values: [12.34], valueFormatter: (value: number): string => eur(value) };

        const shared = axisGutter([position, price]);

        expect(shared).toBe(axisGutter([position]));
        expect(shared).toBeGreaterThan(axisGutter([price]));
    });

    it('vaut celle d\'une série seule quand on ne lui en donne qu\'une', () => {
        const price = { values: [12.34], valueFormatter: (value: number): string => eur(value) };
        const option = buildPriceHistoryOption({
            labels: monthlyLabels(24),
            close: [12.34],
            valueFormatter: (value: number): string => eur(value),
            window: null,
        });

        expect(axisGutter([price])).toBe((option.grid as { left: number }).left);
    });
});

const priceHistory = (months: number, window: { start: number; end: number } | null = null): ChartOption => {
    const labels = monthlyLabels(months);

    return buildPriceHistoryOption({
        labels,
        close: labels.map((_unused: string, index: number): number => 100 + index),
        valueFormatter: (value: number): string => eur(value, 0),
        window,
    });
};

describe('buildPriceHistoryOption', () => {
    it('trace une seule courbe pour le cours', () => {
        expect(seriesOf(priceHistory(24)).map((serie) => serie.name)).toEqual(['Cours']);
    });

    it('cadre l\'axe des cours sur les cotations visibles au lieu de l\'ancrer à zéro', () => {
        expect(yAxisOf(priceHistory(24)).scale).toBe(true);
    });

    it('se passe aussi de lignes de fond horizontales', () => {
        expect(yAxisOf(priceHistory(24)).splitLine.show).toBe(false);
    });

    it('n\'étiquette que les extrêmes de l\'axe des valeurs, comme le graphe de valorisation', () => {
        const option = priceHistory(24);
        const extent = { min: 100, max: 123 };

        expect(yAxisLabel(option, 100, extent)).not.toBe('');
        expect(yAxisLabel(option, 123, extent)).not.toBe('');
        expect(yAxisLabel(option, 110, extent)).toBe('');
    });

    it('porte la même mini-timeline que la valorisation, ouverte sur les douze derniers mois', () => {
        const labels = monthlyLabels(60);
        const [slider] = dataZoomOf(priceHistory(60));

        expect(slider.type).toBe('slider');
        expect(slider.endValue).toBe(timeOf(labels[labels.length - 1]));
        expect(slider.startValue).toBe(timeOf(labels[labels.length - 1]) - ONE_YEAR_MS);
    });

    it('respecte la fenêtre héritée de la valorisation quand le lecteur bascule', () => {
        const chosen = { start: timeOf('2023-06-01'), end: timeOf('2024-06-01') };
        const [slider] = dataZoomOf(priceHistory(60, chosen));

        expect(slider).toMatchObject({ startValue: chosen.start, endValue: chosen.end });
    });

    it('gradue le temps comme le graphe de valorisation, année sous janvier comprise', () => {
        const option = priceHistory(24);

        expect(xAxisLabelOf(option).formatter(new Date(2026, 0, 1).getTime())).toBe('2026');
        expect((option.grid as { bottom: number }).bottom).toBe(68);
    });
});

type DataZoom = {
    type: string;
    startValue?: number;
    endValue?: number;
    minValueSpan: number;
    showDetail?: boolean;
    handleLabel?: { show: boolean };
};

const dataZoomOf = (option: ChartOption): DataZoom[] => option.dataZoom as DataZoom[];

/** 365 jours en millisecondes : le plancher de la fenêtre visible. */
const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

describe('buildValueVsInvestedOption — zoom', () => {
    it('borne la fenêtre en dates plutôt qu\'en pourcentages, pour qu\'elle se transpose d\'une série à l\'autre', () => {
        const labels = monthlyLabels(36);
        const [slider] = dataZoomOf(valueVsInvested(36));

        expect(slider.endValue).toBe(timeOf(labels[labels.length - 1]));
        expect(slider.startValue).toBe(timeOf(labels[labels.length - 1]) - ONE_YEAR_MS);
    });

    it('montre tout l\'historique quand il est plus court qu\'un an', () => {
        const labels = monthlyLabels(6);
        const [slider] = dataZoomOf(valueVsInvested(6));

        expect(slider.startValue).toBe(timeOf(labels[0]));
        expect(slider.endValue).toBe(timeOf(labels[labels.length - 1]));
    });

    it('montre tout l\'historique quand il n\'atteint pas un an', () => {
        // Douze étiquettes mensuelles depuis janvier 2023 s'arrêtent au 1er décembre : 334 jours.
        const labels = monthlyLabels(12);
        const [slider] = dataZoomOf(valueVsInvested(12));

        expect(slider.startValue).toBe(timeOf(labels[0]));
    });

    it('montre encore tout l\'historique quand il fait exactement un an', () => {
        // Treize étiquettes vont du 1er janvier 2023 au 1er janvier 2024 : 365 jours pile,
        // 2023 n'étant pas bissextile. Le plancher se compare avec `<=`, la fenêtre reste entière.
        const labels = monthlyLabels(13);
        const [slider] = dataZoomOf(valueVsInvested(13));

        expect(slider.startValue).toBe(timeOf(labels[0]));
    });

    it('rogne l\'historique dès qu\'il dépasse un an', () => {
        // Quatorze étiquettes vont jusqu'au 1er février 2024 : 396 jours, un an de moins tombe donc
        // après le premier point.
        const labels = monthlyLabels(14);
        const [slider] = dataZoomOf(valueVsInvested(14));

        expect(slider.startValue).toBeGreaterThan(timeOf(labels[0]));
        expect(slider.endValue).toBe(timeOf(labels[labels.length - 1]));
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
        const [slider] = dataZoomOf(option);

        expect(slider.startValue).toBeUndefined();
        expect(slider.endValue).toBeUndefined();
    });

    it('interdit au lecteur de descendre sous un an', () => {
        const zooms = dataZoomOf(valueVsInvested(36));

        expect(zooms).toHaveLength(1);
        expect(zooms[0].minValueSpan).toBe(ONE_YEAR_MS);
    });

    it('respecte la fenêtre déjà choisie par le lecteur plutôt que de la remettre à douze mois', () => {
        const chosen = { start: timeOf('2023-06-01'), end: timeOf('2024-06-01') };
        const [slider] = dataZoomOf(valueVsInvested(36, chosen));

        expect(slider).toMatchObject({ startValue: chosen.start, endValue: chosen.end });
    });

    it('laisse les poignées de zoom muettes, leurs bornes se lisant déjà sur l\'axe', () => {
        const [slider] = dataZoomOf(valueVsInvested(36));

        expect(slider.type).toBe('slider');
        expect(slider.showDetail).toBe(false);
        expect(slider.handleLabel?.show).toBe(false);
    });

    it('ne zoome que par la mini-timeline : la molette et le pincement restent à la page', () => {
        expect(dataZoomOf(valueVsInvested(36)).map((zoom) => zoom.type)).toEqual(['slider']);
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

/**
 * Le `formatter` de l'infobulle est une fonction pure de l'index survolé : ECharts lui passe un
 * tableau de points partageant le même `dataIndex`, on l'appelle donc directement, sans navigateur.
 */
const tooltipHtml = (option: ChartOption, dataIndex: number): string => {
    const formatter = (option.tooltip as { formatter: (params: unknown) => string }).formatter;

    return formatter([{ dataIndex }]);
};

describe('buildValueVsInvestedOption — infobulle', () => {
    it('énonce la valeur, l\'investi et le gain, plutôt que de laisser soustraire les deux courbes', () => {
        const html = tooltipHtml(valueVsInvested(36), 10).replace(/[\xa0\u202f]/g, ' ');

        expect(html).toContain('Valeur');
        expect(html).toContain('1 100 €');
        expect(html).toContain('Investi');
        expect(html).toContain('900 €');
        expect(html).toContain('Gain');
        expect(html).toContain('200 €');
    });

    it('associe chaque montant à son libellé : une inversion valeur/investi romprait ce couplage', () => {
        const html = tooltipHtml(valueVsInvested(36), 10).replace(/[\xa0\u202f]/g, ' ');

        const valeurIndex = html.indexOf('Valeur');
        const investiIndex = html.indexOf('Investi');
        const montantValeurIndex = html.indexOf('1 100 €');
        const montantInvestiIndex = html.indexOf('900 €');

        // « Valeur » précède « Investi », et chaque montant se trouve entre son propre libellé
        // et le suivant : un échange des deux valeurs déplacerait les montants d'un cran.
        expect(valeurIndex).toBeGreaterThanOrEqual(0);
        expect(investiIndex).toBeGreaterThan(valeurIndex);
        expect(montantValeurIndex).toBeGreaterThan(valeurIndex);
        expect(montantValeurIndex).toBeLessThan(investiIndex);
        expect(montantInvestiIndex).toBeGreaterThan(investiIndex);
    });

    it('signe le gain positivement, pour qu\'un mutant inversant le signe se voie', () => {
        const html = tooltipHtml(valueVsInvested(36), 10).replace(/[\xa0\u202f]/g, ' ');

        expect(html).toContain('+ 200 €');
        expect(html).not.toContain('\u2212');
    });

    it('nomme la ligne « Perte » et signe négativement quand la valeur passe sous l\'investi', () => {
        const labels = monthlyLabels(36);
        const option = buildValueVsInvestedOption({
            labels,
            value: labels.map((): number => 700),
            invested: labels.map((): number => 900),
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Baisse.',
        });

        const html = tooltipHtml(option, 5).replace(/[\xa0\u202f]/g, ' ');

        expect(html).toContain('Perte');
        expect(html).not.toContain('Gain');
        expect(html).toContain('\u2212 200 €');
        expect(html).not.toContain('+');
    });

    it('lit l\'index survolé tel quel : un décalage dataIndex + 1 ferait déborder le dernier point', () => {
        // Historique de 36 mois (index 0 à 35) : au dernier point, dataIndex + 1 sortirait du tableau.
        const html = tooltipHtml(valueVsInvested(36), 35).replace(/[\xa0\u202f]/g, ' ');

        // value[35] = 1000 + 35 * 10 = 1350, investi = 900, gain = 450.
        expect(html).toContain('1 350 €');
        expect(html).toContain('450 €');
    });

    it('titre l\'infobulle sur la date survolée', () => {
        expect(tooltipHtml(valueVsInvested(36), 0)).toContain('2023');
    });

    it('rend une infobulle vide quand ECharts ne fournit pas d\'index', () => {
        const formatter = (valueVsInvested(36).tooltip as { formatter: (params: unknown) => string }).formatter;

        expect(formatter([])).toBe('');
        expect(formatter([{}])).toBe('');
    });
});

describe('buildValueVsInvestedOption — palette claire', () => {
    it('teinte les libellés d\'axe pour qu\'ils restent lisibles sur un fond clair', () => {
        const axisLabel = (valueVsInvested(36).xAxis as { axisLabel: { color: string } }).axisLabel;

        expect(axisLabel.color).toBe('#9aa0ac');
    });
});

/** Repères de détachement calés sur les points d'une série mensuelle, tels que `dividendMarks` les rend. */
const marks = (entries: [number, string, string][]): DividendMark[] =>
    entries.map(([index, dateLabel, amountLabel]) => ({ index, dateLabel, amountLabel }));

const withDividends = (dividends: DividendMark[]): ChartOption => {
    const labels = monthlyLabels(36);

    return buildValueVsInvestedOption({
        labels,
        value: labels.map((_unused: string, index: number): number => 1000 + index * 10),
        invested: labels.map((): number => 900),
        valueFormatter: (value: number): string => eur(value, 0),
        window: null,
        description: 'Évolution avec détachements.',
        dividends,
    });
};

const markPointOf = (option: ChartOption) =>
    seriesOf(option)[0].markPoint as unknown as {
        data: { name: string; coord: [string, number]; itemStyle?: { color: string } }[];
    };

describe('buildValueVsInvestedOption — pastilles de détachement', () => {
    it('pose une pastille sur le point de chaque détachement, en plus de celle de la dernière valeur', () => {
        const data = markPointOf(withDividends(marks([
            [3, '15 avr.', '+12,40 €'],
            [10, '12 nov.', '+13,10 €'],
        ]))).data;

        expect(data.map((point) => point.coord[0])).toEqual([
            '2023-04-01',
            '2023-11-01',
            '2025-12-01',
        ]);
    });

    it('pose la pastille à la hauteur de la courbe, pour qu\'elle ne flotte pas hors du tracé', () => {
        const [dividend] = markPointOf(withDividends(marks([[3, '15 avr.', '+12,40 €']]))).data;

        expect(dividend.coord[1]).toBe(1030);
    });

    it('ne pose qu\'une pastille quand deux détachements se calent sur le même point', () => {
        const data = markPointOf(withDividends(marks([
            [3, '05 avr.', '+5,00 €'],
            [3, '20 avr.', '+3,00 €'],
        ]))).data;

        expect(data.filter((point) => point.coord[0] === '2023-04-01')).toHaveLength(1);
    });

    it('teinte les pastilles de détachement de la couleur du gain, la valeur gardant la sienne', () => {
        const data = markPointOf(withDividends(marks([[3, '15 avr.', '+12,40 €']]))).data;

        expect(data[0].itemStyle?.color).toBe('#00915d');
        expect(data[1].itemStyle?.color).toBe('#5257d6');
    });

    it('ne pose que la dernière valeur quand aucun détachement n\'est fourni', () => {
        expect(markPointOf(withDividends([])).data).toHaveLength(1);
    });

    it('énonce dans l\'infobulle la date réelle du détachement et son montant', () => {
        const html = tooltipHtml(withDividends(marks([[3, '15 avr.', '+12,40 €']])), 3)
            .replace(/[\xa0 ]/g, ' ');

        expect(html).toContain('Dividende');
        expect(html).toContain('15 avr.');
        expect(html).toContain('+12,40 €');
    });

    it('n\'énonce aucun dividende sur un point qui n\'en porte pas', () => {
        const html = tooltipHtml(withDividends(marks([[3, '15 avr.', '+12,40 €']])), 4);

        expect(html).not.toContain('Dividende');
    });

    it('énonce les deux détachements calés sur le même point, plutôt qu\'un cumul sans date', () => {
        const html = tooltipHtml(withDividends(marks([
            [3, '05 avr.', '+5,00 €'],
            [3, '20 avr.', '+3,00 €'],
        ])), 3).replace(/[\xa0 ]/g, ' ');

        expect(html).toContain('05 avr.');
        expect(html).toContain('20 avr.');
        expect(html).toContain('+5,00 €');
        expect(html).toContain('+3,00 €');
    });
});

describe('buildWealthStackOption', () => {
    const input = {
        labels: ['2026-01-05', '2026-01-12'],
        classes: [
            { label: 'Actions', values: [1000, 1100], color: 'value' },
            { label: 'Immobilier', values: [500, 520], color: 'realEstate' },
        ],
        invested: [1400, 1400],
        valueFormatter: (amount: number): string => `${amount} €`,
        window: null,
        description: 'Patrimoine total.',
    };

    it('empile les classes sur la même clé', () => {
        const option = buildWealthStackOption(input);
        const series = option.series as { name: string; stack?: string }[];

        expect(series).toHaveLength(2);
        expect(series[0].stack).toBe(series[1].stack);
        expect(series.map((serie) => serie.name)).toEqual(['Actions', 'Immobilier']);
    });

    it('empile autant de bandes que le registre déclare de classes', () => {
        const option = buildWealthStackOption({
            ...input,
            classes: [...input.classes, { label: 'Crypto', values: [200, 240], color: 'crypto' }],
        });
        const series = option.series as { name: string; areaStyle: { color: AreaGradient } }[];

        expect(series.map((serie) => serie.name)).toEqual(['Actions', 'Immobilier', 'Crypto']);
        expect(new Set(series.map((serie) => topStop(serie.areaStyle.color))).size).toBe(3);
    });

    it('fond chaque bande comme les aires des fiches, teintée par sa classe', () => {
        const option = buildWealthStackOption({
            ...input,
            classes: [...input.classes, { label: 'Crypto', values: [200, 240], color: 'crypto' }],
        });
        const series = option.series as { areaStyle: { color: AreaGradient } }[];

        expect(series.map((serie) => serie.areaStyle.color.type)).toEqual(['linear', 'linear', 'linear']);
        expect(series.map((serie) => topStop(serie.areaStyle.color))).toEqual([
            'rgba(82,87,214,0.18)',
            'rgba(179,112,26,0.18)',
            'rgba(162,28,175,0.18)',
        ]);
        expect(series.map((serie) => bottomStop(serie.areaStyle.color))).toEqual([
            'rgba(82,87,214,0)',
            'rgba(179,112,26,0)',
            'rgba(162,28,175,0)',
        ]);
    });

    it('nomme chaque classe dans l\'infobulle, la crypto comprise', () => {
        const option = buildWealthStackOption({
            ...input,
            classes: [...input.classes, { label: 'Crypto', values: [200, 240], color: 'crypto' }],
        });

        const html = (option.tooltip as { formatter: (params: unknown) => string })
            .formatter([{ dataIndex: 1 }]);

        expect(html).toContain('Crypto');
        expect(html).toContain('1860 €');
    });

    it('omet de l\'infobulle les classes que rien ne peuple', () => {
        const option = buildWealthStackOption({
            ...input,
            classes: [
                { label: 'Actions', values: [1000, 1100], color: 'value' },
                { label: 'Immobilier', values: [0, 0], color: 'realEstate' },
                { label: 'Crypto', values: [0, 240], color: 'crypto' },
            ],
        });
        const tooltip = option.tooltip as { formatter: (params: unknown) => string };

        const first = tooltip.formatter([{ dataIndex: 0 }]);
        expect(first).toContain('Actions');
        expect(first).not.toContain('Immobilier');
        expect(first).not.toContain('Crypto');

        const second = tooltip.formatter([{ dataIndex: 1 }]);
        expect(second).toContain('Crypto');
        expect(second).not.toContain('Immobilier');
    });

    it('donne au sommet de la pile le patrimoine total', () => {
        const option = buildWealthStackOption(input);
        const series = option.series as { data: [string, number][] }[];

        const top = series[0].data[1][1] + series[1].data[1][1];

        expect(top).toBe(1620);
    });

    it('garde la mini-timeline de zoom', () => {
        const option = buildWealthStackOption(input);

        expect(option.dataZoom).toHaveLength(1);
    });

    it('peint chaque bande du patrimoine de la couleur portée par sa classe, pas par son rang', () => {
        const option = buildWealthStackOption({
            labels: ['2026-01-01', '2026-02-01'],
            classes: [
                { label: 'Actions', values: [1, 2], color: 'value' },
                { label: 'Matières premières', values: [3, 4], color: 'commodity' },
                { label: 'Crypto', values: [5, 6], color: 'crypto' },
                { label: 'Immobilier', values: [7, 8], color: 'realEstate' },
            ],
            invested: [1, 1],
            valueFormatter: (amount: number): string => String(amount),
            window: null,
            description: '',
        });

        const strokes = (option.series as { lineStyle: { color: string } }[])
            .map((one) => one.lineStyle.color);

        expect(new Set(strokes).size).toBe(4);
    });
});

/** Poche de `count` instruments sur la grille mensuelle, chacun pesant un dixième de plus. */
const perAssetSeries = (count: number, months: number) =>
    Array.from({ length: count }, (_unused: unknown, rank: number) => ({
        assetId: rank + 1,
        name: `Titre ${rank + 1}`,
        value: monthlyLabels(months).map((_label: string, index: number): number => 100 * (rank + 1) + index),
        invested: monthlyLabels(months).map((): number => 100 * (rank + 1)),
    }));

const detailed = (count: number, months = 36): ChartOption => {
    const labels = monthlyLabels(months);

    return buildValueVsInvestedOption({
        labels,
        value: labels.map((_unused: string, index: number): number => 1000 + index * 10),
        invested: labels.map((): number => 900),
        valueFormatter: (value: number): string => eur(value, 0),
        window: null,
        description: 'Détail par instrument.',
        perAsset: perAssetSeries(count, months),
    });
};

describe('buildValueVsInvestedOption — détail par instrument', () => {
    it('ne trace que les instruments, précédés du total muet qui nourrit la mini-timeline', () => {
        expect(seriesOf(detailed(3)).map((serie) => serie.name))
            .toEqual(['Total', 'Titre 1', 'Titre 2', 'Titre 3']);
    });

    it('empile les instruments sur une même pile, le tracé du haut valant le total', () => {
        const [ghost, ...instruments] = seriesOf(detailed(3));

        expect(new Set(instruments.map((serie) => serie.stack)).size).toBe(1);
        expect(instruments[0].stack).toBeDefined();
        expect(ghost.stack).toBeUndefined();
    });

    it('laisse les courbes nues : vingt aires pleines empilées noieraient le tracé', () => {
        const [, first] = seriesOf(detailed(3));

        expect(first.areaStyle).toBeUndefined();
        expect(first.markPoint).toBeUndefined();
    });

    it('date les points de chaque instrument, l\'axe temporel attendant des couples', () => {
        const [, first] = seriesOf(detailed(1, 3));

        expect(first.data).toEqual([
            ['2023-01-01', 100],
            ['2023-02-01', 101],
            ['2023-03-01', 102],
        ]);
    });

    it('donne à chaque instrument sa propre teinte, pour qu\'aucune paire ne se confonde', () => {
        expect(new Set((detailed(6).color as string[]).slice(1)).size).toBe(6);
    });

    it('recycle la palette au-delà de ses teintes, plutôt que de laisser une bande sans couleur', () => {
        const instruments = (detailed(12).color as string[]).slice(1);

        expect(instruments).toHaveLength(12);
        expect(instruments.every((color: string): boolean => typeof color === 'string' && color !== '')).toBe(true);
        expect(instruments[10]).toBe(instruments[0]);
    });

    it('se passe de légende : vingt intitulés d\'ETF mangeraient le graphe, l\'infobulle les nomme', () => {
        expect(detailed(20).legend).toBeUndefined();
        expect(valueVsInvested(36).legend).toBeUndefined();
    });

    it('rend au tracé la bande qu\'une légende aurait prise', () => {
        const bottomOf = (option: ChartOption): number => (option.grid as { bottom: number }).bottom;

        expect(bottomOf(detailed(3))).toBe(bottomOf(valueVsInvested(36)));
    });
});

/** Infobulle du mode détail : ECharts y passe un point par bande de la pile. */
const detailTooltipHtml = (option: ChartOption, dataIndex: number): string => {
    const formatter = (option.tooltip as { formatter: (params: unknown) => string }).formatter;

    return formatter([{ dataIndex }]);
};

describe('buildValueVsInvestedOption — infobulle du détail', () => {
    it('chiffre chaque instrument survolé', () => {
        const html = detailTooltipHtml(detailed(2), 10).replace(/[\xa0\u202f]/g, ' ');

        expect(html).toContain('Titre 1');
        expect(html).toContain('110 €');
        expect(html).toContain('Titre 2');
        expect(html).toContain('210 €');
    });

    it('tait la valeur et l\'investi, que le mode détail ne trace plus', () => {
        const html = detailTooltipHtml(detailed(2), 10);

        expect(html).not.toContain('Valeur');
        expect(html).not.toContain('Investi');
        expect(html).not.toContain('Gain');
    });

    it('classe les instruments du plus lourd au plus léger, la lecture cherchant les gros porteurs', () => {
        const html = detailTooltipHtml(detailed(3), 10);

        expect(html.indexOf('Titre 3')).toBeLessThan(html.indexOf('Titre 2'));
        expect(html.indexOf('Titre 2')).toBeLessThan(html.indexOf('Titre 1'));
    });

    it('nomme chaque bande de la pile, l\'infobulle étant seule à les identifier', () => {
        const html = detailTooltipHtml(detailed(3), 10);

        expect(html).toContain('Titre 1');
        expect(html).toContain('Titre 2');
        expect(html).toContain('Titre 3');
    });
});

describe('buildValueVsInvestedOption — tenue de l\'infobulle', () => {
    it('confine l\'infobulle au cadre, un nom d\'ETF la poussant sinon hors de l\'écran', () => {
        const tooltip = detailed(6).tooltip as { confine?: boolean };

        expect(tooltip.confine).toBe(true);
    });

    it('borne la largeur de l\'infobulle sans jamais dépasser l\'écran', () => {
        const tooltip = detailed(6).tooltip as { extraCssText?: string };

        expect(tooltip.extraCssText).toContain('max-width:min(420px,calc(100vw - 2rem))');
    });

    it('coupe un nom trop long sur une seule ligne, plutôt que de l\'enrouler sur trois', () => {
        const html = detailTooltipHtml(detailed(2), 10);

        expect(html).toContain('text-overflow:ellipsis');
        expect(html).toContain('white-space:nowrap');
        expect(html).toContain('overflow:hidden');
        /** Sans quoi une boîte flex refuse de descendre sous la largeur de son contenu. */
        expect(html).toContain('min-width:0');
    });
});

describe('buildValueVsInvestedOption — aperçu de la mini-timeline', () => {
    it('porte le total en tête, seule série qu\'ECharts sait montrer dans la mini-timeline', () => {
        // La mini-timeline dessine la première série, en valeurs brutes : sans ce porteur, elle
        // afficherait l'allure du premier instrument seul, à un ordre de grandeur du portefeuille.
        const [ghost] = seriesOf(detailed(1, 3));

        expect(ghost.data).toEqual([
            ['2023-01-01', 1000],
            ['2023-02-01', 1010],
            ['2023-03-01', 1020],
        ]);
    });

    it('rend ce porteur invisible dans le cadre, où il doublerait le sommet de la pile', () => {
        const [ghost] = seriesOf(detailed(3));

        expect(ghost.lineStyle?.opacity).toBe(0);
        expect(ghost.silent).toBe(true);
        expect(ghost.areaStyle).toBeUndefined();
        expect(ghost.markPoint).toBeUndefined();
    });
});

describe('buildValueVsInvestedOption — assise du détail', () => {
    it('cadre la pile sur les extrêmes visibles, comme la courbe de valeur', () => {
        const yAxis = yAxisOf(detailed(3));

        expect(yAxis.min({ min: 300, max: 6000 })).toBe(300);
        expect(yAxis.max({ min: 300, max: 6000 })).toBe(6000);
    });

    it('chiffre ces deux extrêmes et eux seuls, le zéro n\'ayant plus à figurer', () => {
        const option = detailed(3);
        const extent = { min: 300, max: 6000 };

        expect(yAxisLabel(option, 300, extent)).not.toBe('');
        expect(yAxisLabel(option, 6000, extent)).not.toBe('');
        expect(yAxisLabel(option, 0, extent)).toBe('');
    });
});
