import type { LineSeriesOption } from 'echarts/charts';
import type { TooltipComponentOption } from 'echarts/components';
import type { ChartOption } from './echarts';
import type { DividendMark } from './income';
import { useThemeStore } from '@/stores/theme';

type ChartPalette = {
    value: string;
    invested: string;
    gain: string;
    loss: string;
    bond: string;
    commodity: string;
    realEstate: string;
    crypto: string;
    /** Teintes catégorielles des courbes d'instruments, recyclées au-delà de la dixième. */
    series: string[];
    axisLabel: string;
    filler: string;
    dataBackground: string;
    selectedDataBackground: string;
    tooltipBackground: string;
    tooltipBorder: string;
    tooltipText: string;
    surface: string;
};

/**
 * ECharts peint son SVG lui-même : un `var(--jeton)` n'y serait pas résolu. La palette recopie donc
 * à la main les valeurs de `resources/css/app.css` — toute retouche là-bas se répercute ici.
 */
function palette(): ChartPalette {
    return useThemeStore().isDark
        ? {
            value: '#8f93f0',
            invested: '#6b7280',
            gain: '#34d399',
            loss: '#f87171',
            bond: '#2dd4bf',
            commodity: '#d6e85e',
            realEstate: '#e0a75f',
            crypto: '#e879f9',
            series: [
                '#8f93f0', '#2dd4bf', '#e0a75f', '#e879f9', '#f87171',
                '#34d399', '#d6e85e', '#60a5fa', '#f472b6', '#a8a29e',
            ],
            axisLabel: '#7f858f',
            filler: 'rgba(143,147,240,0.18)',
            dataBackground: '#2f343e',
            selectedDataBackground: 'rgba(143,147,240,0.5)',
            tooltipBackground: '#1c1f26',
            tooltipBorder: '#2f343e',
            tooltipText: '#eceef2',
            surface: '#14161b',
        }
        : {
            value: '#5257d6',
            invested: '#b6bac4',
            gain: '#00915d',
            loss: '#c2321f',
            bond: '#0d9488',
            commodity: '#535e08',
            realEstate: '#b3701a',
            crypto: '#a21caf',
            series: [
                '#5257d6', '#0d9488', '#b3701a', '#a21caf', '#c2321f',
                '#00915d', '#535e08', '#2563eb', '#db2777', '#78716c',
            ],
            axisLabel: '#9aa0ac',
            filler: 'rgba(82,87,214,0.12)',
            dataBackground: '#e2e4ea',
            selectedDataBackground: 'rgba(82,87,214,0.45)',
            tooltipBackground: '#ffffff',
            tooltipBorder: '#e2e4ea',
            tooltipText: '#16181d',
            surface: '#ffffff',
        };
}

/**
 * L'aire fondue des tracés : la teinte au sommet, transparente en bas. Un peu plus soutenue en
 * thème sombre, où le fond mange les faibles opacités.
 */
function fadedArea(color: string): NonNullable<LineSeriesOption['areaStyle']>['color'] {
    return {
        type: 'linear',
        x: 0,
        y: 0,
        x2: 0,
        y2: 1,
        colorStops: [
            { offset: 0, color: rgba(color, useThemeStore().isDark ? 0.22 : 0.18) },
            { offset: 1, color: rgba(color, 0) },
        ],
    };
}

/** Teinte hexadécimale de la palette, ramenée en `rgba()` pour porter une opacité. */
function rgba(color: string, alpha: number): string {
    const [red, green, blue] = [1, 3, 5].map((at: number): number => parseInt(color.slice(at, at + 2), 16));

    return `rgba(${red},${green},${blue},${alpha})`;
}

function formatTooltipDate(label: string): string {
    const date = new Date(`${label}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return label;
    }

    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

function tooltipTitle(label: string): string {
    return `<div style="font-size:12px;font-weight:600;margin-bottom:4px;">${formatTooltipDate(label)}</div>`;
}

function tooltipRow(color: string, label: string, value: string): string {
    return '<div style="display:flex;align-items:center;gap:6px;font-size:12px;line-height:1.6;">'
        + `<span style="width:8px;height:8px;border-radius:9999px;background:${color};"></span>`
        + `<span style="flex:1;">${label}</span>`
        + `<span style="font-weight:600;">${value}</span>`
        + '</div>';
}

type ValueFormatter = (value: number) => string;

const MONTH_LABEL = new Intl.DateTimeFormat('fr-FR', { month: 'short' });
const DAY_MONTH_LABEL = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' });
const HOUR_LABEL = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' });

/**
 * Graduation de l'axe temporel. Chaque cran se décrit seul, à la précision que sa date porte :
 * ECharts ne cale ses crans sur le premier du mois que sur les longues fenêtres, et sur une
 * fenêtre courte il gradue au jour ou à l'heure — un libellé réduit au mois répéterait alors
 * « juin » trois crans de suite sans rien situer. Janvier porte l'année seule : c'est là que le
 * repère change, et le mois y est déductible de ses voisins. Tenir sur une seule ligne, plutôt
 * que d'empiler le mois et l'année, rend au tracé la moitié de la bande du bas.
 */
export function timeAxisLabel(value: number): string {
    const date = new Date(value);

    if (date.getHours() !== 0 || date.getMinutes() !== 0) {
        return HOUR_LABEL.format(date);
    }

    if (date.getDate() !== 1) {
        return DAY_MONTH_LABEL.format(date);
    }

    return date.getMonth() === 0 ? String(date.getFullYear()) : MONTH_LABEL.format(date);
}

/** Police des graduations : ECharts peint son SVG sans hériter de celle de la page. */
const AXIS_LABEL_FONT = '12px sans-serif';

/** Écart entre une graduation et le cadre, tel qu'ECharts le pose par défaut. */
const AXIS_LABEL_MARGIN = 8;

/** Débord de la pastille de dernière valeur au-delà du cadre : son rayon plus sa bordure. */
const LAST_POINT_OVERFLOW = 6;

/**
 * Bande au-dessus du cadre. Couvre la demi-hauteur de la graduation la plus haute, centrée sur
 * sa ligne, et le débord de la pastille quand le dernier point est aussi le plus haut.
 */
const CHART_TOP_INSET = 8;

/** Contexte de mesure, créé une seule fois : `undefined` tant que rien n'a été mesuré. */
let labelMeasurer: CanvasRenderingContext2D | null | undefined;

/**
 * Largeur réelle d'une graduation. Mesurée sur un canevas hors écran plutôt qu'estimée au
 * caractère : chiffres, espace des milliers et « € » n'ont pas la même avance. Hors navigateur
 * — les tests unitaires — l'estimation grossière suffit, aucun pixel n'y est peint.
 */
function labelWidth(text: string): number {
    if (labelMeasurer === undefined) {
        const context = typeof document === 'undefined'
            ? null
            : document.createElement('canvas').getContext('2d');

        if (context !== null) {
            context.font = AXIS_LABEL_FONT;
        }

        labelMeasurer = context;
    }

    return labelMeasurer?.measureText(text).width ?? text.length * 7;
}

/**
 * Largeur réservée aux montants de l'axe des ordonnées. Une valeur fixe était soit trop large
 * pour un petit portefeuille, soit trop courte pour un gros — la graduation débordait alors du
 * SVG et se faisait couper. Elle est calculée sur l'amplitude entière de l'historique et non sur
 * la fenêtre visible : sinon le cadre se décalerait à chaque cran de zoom, la courbe glissant
 * sous le doigt. Le maximum est majoré d'un dixième pour couvrir la graduation ronde qu'ECharts
 * place au-dessus des données.
 */
function yAxisGutter(values: number[], valueFormatter: ValueFormatter): number {
    const finite = values.filter((value: number): boolean => Number.isFinite(value));

    if (finite.length === 0) {
        return AXIS_LABEL_MARGIN;
    }

    const bounds = [Math.min(...finite), Math.max(...finite)];
    const widest = Math.max(...[...bounds, bounds[1] * 1.1].map(
        (value: number): number => labelWidth(valueFormatter(value)),
    ));

    return Math.ceil(widest) + AXIS_LABEL_MARGIN;
}

/** Une série à mesurer : ses valeurs, et le formateur qui en fera des étiquettes. */
export type GutterSeries = { values: number[]; valueFormatter: ValueFormatter };

/**
 * Gouttière assez large pour toutes les séries qu'un même cadre tracera tour à tour. La fiche
 * instrument bascule entre la valeur d'une position et le cours d'une part : mesurée série par
 * série, la gouttière changerait de largeur à chaque bascule et décalerait le tracé, la
 * mini-timeline et la graduation temporelle sous l'œil du lecteur.
 */
export function axisGutter(series: GutterSeries[]): number {
    return Math.max(...series.map(
        ({ values, valueFormatter }: GutterSeries): number => yAxisGutter(values, valueFormatter),
    ));
}

type AxisExtent = { min: number; max: number };

/**
 * Vrai pour la graduation qui tombe sur un extrême de l'axe. La comparaison est tolérante : ECharts
 * arrondit ses graduations, une égalité stricte laisserait l'axe sans aucune étiquette.
 */
function isAxisExtreme(value: number, { min, max }: AxisExtent): boolean {
    const tolerance = Math.abs(max - min) / 1000;

    return Math.abs(value - min) <= tolerance || Math.abs(value - max) <= tolerance;
}

type ChartFrameInput = {
    valueFormatter: ValueFormatter;
    /** Toutes les valeurs tracées : elles seules disent la largeur des graduations à venir. */
    values: number[];
    bottom: number;
    description: string;
    /** Largeur imposée par un cadre partagé avec d'autres séries ; sinon celle de ces valeurs. */
    gutter?: number;
    /**
     * Assoit l'axe sur zéro au lieu du minimum visible. Réservé aux tracés empilés : l'épaisseur
     * d'une bande n'y a de sens que mesurée depuis zéro, un cadrage serré la ferait mentir.
     */
    anchoredAtZero?: boolean;
};

/**
 * Ossature partagée par les trois graphes : axes, grille et cadre d'infobulle suivent le thème.
 * La description accessible est rédigée à la main plutôt que laissée au gabarit anglais d'ECharts.
 */
function chartFrame(
    { valueFormatter, values, bottom, description, gutter, anchoredAtZero = false }: ChartFrameInput,
): ChartOption {
    const colors = palette();

    /**
     * Extrêmes de la fenêtre réellement affichée, publiés par ECharts aux bornes de l'axe avant
     * d'en étiqueter les graduations. Mémorisés ici parce que le formateur d'étiquette, lui, ne
     * reçoit que la valeur d'une graduation : sans eux il ne saurait pas laquelle est un extrême.
     */
    let extent: AxisExtent = { min: Number.NaN, max: Number.NaN };

    const rememberExtent = (bounds: AxisExtent): AxisExtent => {
        extent = anchoredAtZero ? { min: 0, max: bounds.max } : bounds;

        return extent;
    };

    return {
        animation: false,
        aria: { enabled: true, label: { description } },
        grid: {
            left: gutter ?? yAxisGutter(values, valueFormatter),
            right: LAST_POINT_OVERFLOW,
            top: CHART_TOP_INSET,
            bottom,
            containLabel: false,
        },
        /** Axe temporel plutôt que catégoriel : la graduation suit l'amplitude réellement visible. */
        xAxis: {
            type: 'time',
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: colors.axisLabel, hideOverlap: true, formatter: timeAxisLabel },
        },
        yAxis: {
            type: 'value',
            /** Sans `scale`, ECharts englobe zéro : la variation s'écrase alors dans le haut du cadre. */
            scale: true,
            /**
             * Bornes collées aux extrêmes réellement visibles : l'axe ne porte plus que le minimum
             * et le maximum du tracé. Des fonctions plutôt que des nombres, pour que la fenêtre de
             * zoom recalcule ses propres extrêmes.
             */
            min: (bounds: AxisExtent): number => rememberExtent(bounds).min,
            max: (bounds: AxisExtent): number => rememberExtent(bounds).max,
            /** Deux étiquettes valent deux graduations : le reste de l'échelle n'est pas chiffré. */
            splitNumber: 1,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: {
                color: colors.axisLabel,
                formatter: (value: number): string => (
                    isAxisExtreme(value, extent) ? valueFormatter(value) : ''
                ),
            },
            /** Deux étiquettes chiffrées suffisent à donner l'échelle : les lignes de fond en plus font cage. */
            splitLine: { show: false },
        },
        tooltip: chartTooltip(),
    };
}

/** Cadre d'infobulle commun : seul le contenu change d'un graphe à l'autre. */
function chartTooltip(): TooltipComponentOption {
    const colors = palette();

    return {
        trigger: 'axis',
        /**
         * Un nom d'ETF tient sur une ligne entière : sans confinement l'infobulle sortait de
         * l'écran par la gauche, et sans largeur bornée elle y poussait le cadre à elle seule.
         */
        confine: true,
        extraCssText: 'max-width:min(320px,calc(100vw - 2rem));white-space:normal;',
        backgroundColor: colors.tooltipBackground,
        borderColor: colors.tooltipBorder,
        borderRadius: 10,
        textStyle: { color: colors.tooltipText, fontFamily: 'inherit' },
        axisPointer: { type: 'line', lineStyle: { color: colors.invested } },
    };
}

export type AssetSeries = { assetId: number; name: string; value: number[]; invested: number[] };

/** Un axe temporel attend des couples date/valeur, pas une suite de valeurs indexées. */
function datedPoints(labels: string[], values: number[]): [string, number][] {
    return labels.map((label: string, index: number): [string, number] => [label, values[index] ?? 0]);
}

/**
 * Valeur et investi sur le même cadre, identiques sur le tableau de bord et la fiche instrument :
 * l'écart entre les deux tracés est le gain, que l'infobulle continue de chiffrer. L'investi passe
 * en escalier — les mises sautent le jour de l'ordre plutôt que de glisser d'un jour à l'autre — et
 * reste sans aire, celle de la valeur le recouvrant.
 */
function valueSeries(
    labels: string[],
    value: number[],
    invested: number[],
    dividends: DividendMark[],
): LineSeriesOption[] {
    const colors = palette();
    const points = datedPoints(labels, value);

    return [
        {
            name: 'Valeur',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 2.5 },
            areaStyle: { color: fadedArea(colors.value) },
            markPoint: markPoints(points, dividends),
            data: points,
        },
        {
            name: 'Investi',
            type: 'line',
            step: 'end',
            symbol: 'none',
            sampling: 'lttb',
            /** Pointillé : la mise n'est pas une mesure de marché, elle ne se lit pas comme la valeur. */
            lineStyle: { width: 1.5, color: colors.invested, type: 'dashed' },
            data: datedPoints(labels, invested),
        },
    ];
}

/** Teinte d'un instrument selon son rang, la palette se rembobinant au-delà de sa dernière. */
function seriesColor(rank: number): string {
    const tones = palette().series;

    return tones[rank % tones.length] as string;
}

/** Nom de la pile sur laquelle les bandes du détail s'additionnent. */
const INSTRUMENT_STACK = 'instruments';

/**
 * Porteur du total, en tête et invisible. La mini-timeline du zoom ne sait montrer qu'une série,
 * la première, et en valeurs brutes : l'empilement lui échappe. Sans ce porteur elle dessinerait
 * l'allure du premier instrument seul, à un ordre de grandeur du portefeuille — ce que le graphe
 * au-dessus dément aussitôt. Muet et transparent, il ne sert qu'à cet aperçu.
 */
function totalCarrier(labels: string[], value: number[]): LineSeriesOption {
    return {
        name: 'Total',
        type: 'line',
        silent: true,
        symbol: 'none',
        lineStyle: { opacity: 0 },
        data: datedPoints(labels, value),
    };
}

/**
 * Une courbe par instrument de la poche, empilées : leur sommet vaut la valeur totale, que ce mode
 * n'a donc plus à tracer à part. Tracés nus, sans aire — vingt remplissages superposés noieraient
 * le graphe, et l'écart entre deux courbes dit déjà le poids de celle du dessus.
 */
function instrumentSeries(labels: string[], perAsset: AssetSeries[]): LineSeriesOption[] {
    return perAsset.map((asset: AssetSeries, rank: number): LineSeriesOption => {
        const color = seriesColor(rank);

        return {
            name: asset.name,
            type: 'line',
            stack: INSTRUMENT_STACK,
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 1.5, color },
            data: datedPoints(labels, asset.value),
        };
    });
}

/** Diamètre de la pastille de dernière valeur, la plus grosse du tracé. */
const LAST_POINT_SIZE = 8;

/** Pastille de détachement, plus petite : elle annote la courbe sans disputer la dernière valeur. */
const DIVIDEND_POINT_SIZE = 6;

type MarkPointItem = {
    name: string;
    coord: [string, number];
    symbolSize: number;
    itemStyle: { color: string; borderColor: string; borderWidth: number };
};

/**
 * Pastilles posées sur la courbe : les détachements dans l'ordre de l'axe, la dernière valeur en
 * dernier. Un seul `markPoint` par série chez ECharts, d'où leur cohabitation ici — chaque point
 * porte donc sa taille et sa couleur, aucune ne pouvant être commune.
 */
function markPoints(
    points: [string, number][],
    dividends: DividendMark[],
    color?: string,
): LineSeriesOption['markPoint'] {
    const data = [...dividendPoints(points, dividends), ...lastValuePoint(points, color)];

    if (data.length === 0) {
        return undefined;
    }

    return {
        symbol: 'circle',
        silent: true,
        label: { show: false },
        data,
    };
}

/**
 * Pastille sur la dernière valeur : elle ancre la lecture sur « où en est-on aujourd'hui ». Sa
 * teinte suit celle du tracé qu'elle termine, la courbe de l'investi n'étant pas de la couleur des
 * valeurs.
 */
function lastValuePoint(points: [string, number][], color?: string): MarkPointItem[] {
    const last = points[points.length - 1];

    if (last === undefined) {
        return [];
    }

    const colors = palette();

    return [{
        name: 'Dernière valeur',
        coord: last,
        symbolSize: LAST_POINT_SIZE,
        itemStyle: { color: color ?? colors.value, borderColor: colors.surface, borderWidth: 2 },
    }];
}

/**
 * Une pastille par point porteur d'un détachement, et non par détachement : deux détachements
 * calés sur la même semaine se superposeraient au pixel près. L'infobulle, elle, les énonce tous.
 */
function dividendPoints(points: [string, number][], dividends: DividendMark[]): MarkPointItem[] {
    const colors = palette();
    const indexes = [...new Set(dividends.map((mark: DividendMark): number => mark.index))];

    return indexes
        .map((index: number): [string, number] | undefined => points[index])
        .filter((point): point is [string, number] => point !== undefined)
        .map((point: [string, number]): MarkPointItem => ({
            name: 'Détachement',
            coord: point,
            symbolSize: DIVIDEND_POINT_SIZE,
            itemStyle: { color: colors.gain, borderColor: colors.surface, borderWidth: 2 },
        }));
}

/** Détachements calés sur un point donné, dans l'ordre où `dividendMarks` les a rendus. */
function dividendsAt(dividends: DividendMark[], index: number): DividendMark[] {
    return dividends.filter((mark: DividendMark): boolean => mark.index === index);
}

/**
 * Infobulle du graphe de valeur : elle porte à elle seule la comparaison avec l'investi, et le gain
 * plutôt que de laisser le lecteur soustraire.
 */
function valueVsInvestedTooltip(
    labels: string[],
    value: number[],
    invested: number[],
    valueFormatter: ValueFormatter,
    dividends: DividendMark[],
): TooltipComponentOption {
    const colors = palette();

    return {
        ...chartTooltip(),
        formatter: (params: unknown): string => {
            const index = pointIndex(params);
            if (index === null) {
                return '';
            }

            const totalValue = value[index] ?? 0;
            const totalInvested = invested[index] ?? 0;
            const gain = totalValue - totalInvested;

            const dividendRows = dividendsAt(dividends, index)
                .map((mark: DividendMark): string => tooltipRow(
                    colors.gain,
                    `Dividende · ${mark.dateLabel}`,
                    mark.amountLabel,
                ))
                .join('');

            return tooltipTitle(labels[index] ?? '')
                + tooltipRow(colors.value, 'Valeur', valueFormatter(totalValue))
                + tooltipRow(colors.invested, 'Investi', valueFormatter(totalInvested))
                + tooltipRow(
                    gain >= 0 ? colors.gain : colors.loss,
                    gain >= 0 ? 'Gain' : 'Perte',
                    `${gain >= 0 ? '+' : '−'} ${valueFormatter(Math.abs(gain))}`,
                )
                + dividendRows;
        },
    };
}

/** ECharts passe un tableau de points survolés ; tous partagent le même index de catégorie. */
/**
 * L'infobulle du mode détail : chaque instrument survolé, du plus lourd au plus léger. Les montants
 * se relisent dans les séries d'origine plutôt que dans les points d'ECharts — empilés, ceux-ci
 * portent la somme des bandes du dessous, pas la valeur de l'instrument. Elle est seule à nommer
 * les bandes : la pile se passe de légende, vingt intitulés d'ETF mangeraient le graphe.
 */
function detailTooltip(
    labels: string[],
    perAsset: AssetSeries[],
    valueFormatter: ValueFormatter,
): TooltipComponentOption {
    return {
        ...chartTooltip(),
        formatter: (params: unknown): string => {
            const index = pointIndex(params);
            if (index === null) {
                return '';
            }

            const instrumentRows = perAsset
                .map((asset: AssetSeries, rank: number) => ({ asset, rank, amount: asset.value[index] ?? 0 }))
                .sort((left, right): number => right.amount - left.amount)
                .map((row): string => tooltipRow(seriesColor(row.rank), row.asset.name, valueFormatter(row.amount)))
                .join('');

            return tooltipTitle(labels[index] ?? '') + instrumentRows;
        },
    };
}

function pointIndex(params: unknown): number | null {
    const points = Array.isArray(params) ? params : [params];
    const first = points[0] as { dataIndex?: number } | undefined;

    return typeof first?.dataIndex === 'number' ? first.dataIndex : null;
}

/**
 * Bornes de la fenêtre visible, en millisecondes depuis l'époque. Des dates plutôt que des
 * pourcentages d'amplitude : la fiche instrument bascule entre valorisation et cours, deux séries
 * qui ne commencent pas le même jour — un même pourcentage y désignerait deux dates différentes,
 * et la fenêtre se déplacerait sous l'œil du lecteur à chaque bascule.
 */
export type ZoomWindow = { start: number; end: number };

type ValueVsInvestedInput = {
    labels: string[];
    value: number[];
    invested: number[];
    valueFormatter: ValueFormatter;
    /** `null` à la première peinture : la fenêtre d'ouverture se déduit alors de l'historique. */
    window: ZoomWindow | null;
    description: string;
    /** Absent sur le tableau de bord : seule la fiche instrument annote ses détachements. */
    dividends?: DividendMark[];
    /** Posée par la fiche instrument, dont le cadre sert aussi au cours (cf. `axisGutter`). */
    gutter?: number;
    /**
     * Détail de la poche : une courbe par instrument sous le total. Absent partout ailleurs — le
     * tableau de bord et la fiche instrument n'ont rien à décomposer.
     */
    perAsset?: AssetSeries[];
};

/** Le tableau de bord raisonne sur le portefeuille entier : les titres ne sont qu'un détail de calcul. */
export function sumPerAsset(perAsset: AssetSeries[], pick: (asset: AssetSeries) => number[], length: number): number[] {
    return Array.from(
        { length },
        (_unused: unknown, index: number): number => perAsset.reduce(
            (sum: number, asset: AssetSeries): number => sum + (pick(asset)[index] ?? 0),
            0,
        ),
    );
}

/** Amplitude minimale de la fenêtre : sous un an, la courbe raconte du bruit plutôt qu'une tendance. */
const MIN_ZOOM_SPAN_MS = 365 * 24 * 60 * 60 * 1000;

function labelTime(label: string | undefined): number {
    return Date.parse(`${label ?? ''}T00:00:00`);
}

/**
 * Fenêtre d'ouverture : les douze derniers mois, datés. Un historique plus court qu'un an
 * s'affiche en entier — le plancher le fige déjà là. `null` sur un historique vide : aucune borne
 * ne s'en déduit, et le graphe montre alors tout ce qu'il a.
 */
function lastYearWindow(labels: string[]): ZoomWindow | null {
    const first = labelTime(labels[0]);
    const last = labelTime(labels[labels.length - 1]);
    const span = last - first;

    if (!Number.isFinite(span)) {
        return null;
    }

    return span <= MIN_ZOOM_SPAN_MS
        ? { start: first, end: last }
        : { start: last - MIN_ZOOM_SPAN_MS, end: last };
}

/** Hauteur réservée sous la grille à la mini-timeline du zoom, en pixels. */
const ZOOM_SLIDER_HEIGHT = 40;

/** Bande réservée à la graduation temporelle : une ligne, l'année en suffixe sous janvier. */
const TIME_AXIS_LABEL_HEIGHT = 28;

/** La mini-timeline est la seule commande de zoom, partagée par les deux formes de graphe. */
function wealthZoomSlider(visible: ZoomWindow | null): Extract<NonNullable<ChartOption['dataZoom']>, unknown[]>[number] {
    const colors = palette();

    /**
     * La mini-timeline est la seule commande de zoom : un `dataZoom` de type `inside`
     * capturait la molette et le pincement, donc volait le défilement de la page dès que le
     * doigt ou le curseur passait sur le tracé.
     */
    return {
        type: 'slider',
        /** Bornes datées : `startValue` / `endValue` plutôt que les pourcentages de `start` / `end`. */
        startValue: visible?.start,
        endValue: visible?.end,
        minValueSpan: MIN_ZOOM_SPAN_MS,
        height: ZOOM_SLIDER_HEIGHT,
        bottom: 0,
        /** Les bornes de la fenêtre se lisent sur l'axe du graphe : les redire aux poignées encombre. */
        handleLabel: { show: false },
        showDetail: false,
        borderColor: 'transparent',
        fillerColor: colors.filler,
        handleStyle: { color: colors.surface, borderColor: colors.invested },
        moveHandleStyle: { color: colors.dataBackground },
        textStyle: { color: colors.axisLabel },
        dataBackground: { lineStyle: { opacity: 0 }, areaStyle: { color: colors.dataBackground } },
        selectedDataBackground: {
            lineStyle: { opacity: 0 },
            areaStyle: { color: colors.selectedDataBackground },
        },
    };
}

/**
 * Le graphe « valeur contre investi » : mêmes courbes, même axe, même infobulle, même zoom pour
 * `/instruments` et les fiches instrument. Le tableau de bord, lui, empile titres et immobilier
 * via `buildWealthStackOption`. La fenêtre temporelle est choisie côté client par le `dataZoom` :
 * rien ici ne dépend du réseau.
 */
export function buildValueVsInvestedOption(
    {
        labels,
        value,
        invested,
        valueFormatter,
        window,
        description,
        dividends = [],
        gutter,
        perAsset = [],
    }: ValueVsInvestedInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);
    const colors = palette();
    const detailed = perAsset.length > 0;

    return {
        ...chartFrame({
            valueFormatter,
            /** L'investi peut passer sous la valeur comme au-dessus : l'axe doit tenir les deux. */
            values: [...value, ...invested],
            bottom: ZOOM_SLIDER_HEIGHT + TIME_AXIS_LABEL_HEIGHT,
            description,
            gutter,
            anchoredAtZero: detailed,
        }),
        color: detailed
            ? ['transparent', ...perAsset.map((_asset: AssetSeries, rank: number): string => seriesColor(rank))]
            : [colors.value, colors.invested],
        series: detailed
            ? [totalCarrier(labels, value), ...instrumentSeries(labels, perAsset)]
            : valueSeries(labels, value, invested, dividends),
        tooltip: detailed
            ? detailTooltip(labels, perAsset, valueFormatter)
            : valueVsInvestedTooltip(labels, value, invested, valueFormatter, dividends),
        dataZoom: [wealthZoomSlider(visible)],
    };
}

/** Une classe d'actif à empiler : son nom de bande, et ses valeurs sur la grille commune. */
export type WealthStackClass = {
    label: string;
    values: number[];
    /** Jeton de teinte porté par la classe : la couleur ne se déduit plus de son rang. */
    color: string;
};

export type WealthStackInput = {
    labels: string[];
    classes: WealthStackClass[];
    invested: number[];
    valueFormatter: ValueFormatter;
    window: ZoomWindow | null;
    description: string;
};

/** Jetons de teinte que le serveur peut poser sur une classe d'actif : voir `ChartPalette`. */
type ClassColorToken = 'value' | 'bond' | 'commodity' | 'crypto' | 'realEstate';

/**
 * Vrai pour un jeton que la palette sait résoudre. Un simple `Record<string, string>` accepterait
 * n'importe quelle chaîne au prix d'un cast qui contournerait le typage ; cette garde, elle, borne
 * l'accès à `ChartPalette` aux seules clés qu'elle porte réellement.
 */
function isClassColorToken(token: string): token is ClassColorToken {
    return token === 'value' || token === 'bond' || token === 'commodity'
        || token === 'crypto' || token === 'realEstate';
}

/**
 * La teinte d'une classe d'actif, depuis le jeton qu'elle porte. Le rang ne décide plus : il
 * décidait autrefois, et la quatrième classe reprenait la teinte de la première. Un jeton que le
 * registre ne connaîtrait pas encore retombe sur celle des titres, plutôt que de rendre `undefined`.
 */
function classColor(token: string): string {
    const colors = palette();

    return isClassColorToken(token) ? colors[token] : colors.value;
}

/** Le total empilé à un instant : la somme des classes, sommet de la pile. */
function stackTotals(labels: string[], classes: WealthStackClass[]): number[] {
    return labels.map((_, index: number): number => classes.reduce(
        (total: number, one: WealthStackClass): number => total + (one.values[index] ?? 0),
        0,
    ));
}

/** Une bande par classe d'actif, empilées : le sommet de la pile est le patrimoine total. */
function wealthStackSeries(labels: string[], classes: WealthStackClass[]): LineSeriesOption[] {
    return classes.map((one: WealthStackClass): LineSeriesOption => {
        const color = classColor(one.color);

        return {
            name: one.label,
            type: 'line',
            stack: 'patrimoine',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 1.5, color },
            areaStyle: { color: fadedArea(color) },
            data: datedPoints(labels, one.values),
        };
    });
}

function wealthStackTooltip(
    labels: string[],
    classes: WealthStackClass[],
    invested: number[],
    valueFormatter: ValueFormatter,
): TooltipComponentOption {
    const colors = palette();

    return {
        ...chartTooltip(),
        formatter: (params: unknown): string => {
            const index = pointIndex(params);
            if (index === null) {
                return '';
            }

            const totalValue = classes.reduce(
                (total: number, one: WealthStackClass): number => total + (one.values[index] ?? 0),
                0,
            );
            const totalInvested = invested[index] ?? 0;
            const gain = totalValue - totalInvested;

            /**
             * Une classe que rien ne peuple à cet instant est taise : lire « Immobilier 0 € »
             * quand on n'en possède pas encore renseigne moins que l'absence de la ligne.
             */
            const rows = classes
                .map((one: WealthStackClass): string => ((one.values[index] ?? 0) === 0
                    ? ''
                    : tooltipRow(classColor(one.color), one.label, valueFormatter(one.values[index] ?? 0))))
                .join('');

            return tooltipTitle(labels[index] ?? '')
                + tooltipRow(colors.value, 'Patrimoine', valueFormatter(totalValue))
                + rows
                + tooltipRow(colors.invested, 'Investi', valueFormatter(totalInvested))
                + tooltipRow(
                    gain >= 0 ? colors.gain : colors.loss,
                    gain >= 0 ? 'Gain' : 'Perte',
                    `${gain >= 0 ? '+' : '−'} ${valueFormatter(Math.abs(gain))}`,
                );
        },
    };
}

/**
 * Le graphe du tableau de bord : une aire empilée par classe d'actif. Forme distincte de
 * `buildValueVsInvestedOption`, qui ne trace qu'une courbe — le patrimoine se lit par sa
 * composition, un instrument par sa trajectoire.
 */
export function buildWealthStackOption(
    { labels, classes, invested, valueFormatter, window, description }: WealthStackInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);

    return {
        ...chartFrame({
            valueFormatter,
            values: stackTotals(labels, classes),
            bottom: ZOOM_SLIDER_HEIGHT + TIME_AXIS_LABEL_HEIGHT,
            description,
        }),
        series: wealthStackSeries(labels, classes),
        tooltip: wealthStackTooltip(labels, classes, invested, valueFormatter),
        dataZoom: [wealthZoomSlider(visible)],
    };
}

type PriceHistoryInput = {
    labels: string[];
    close: number[];
    valueFormatter: ValueFormatter;
    /** `null` à la première peinture ; sinon la fenêtre héritée de l'autre série de la fiche. */
    window: ZoomWindow | null;
    /** Partagée avec la valorisation : le cadre ne doit pas bouger à la bascule. */
    gutter?: number;
};

/**
 * Cours d'un instrument : une courbe unique, aire dégradée sous la ligne. Même mini-timeline que
 * la valorisation — la fiche bascule de l'une à l'autre sans changer de fenêtre.
 */
export function buildPriceHistoryOption(
    { labels, close, valueFormatter, window, gutter }: PriceHistoryInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);
    const colors = palette();
    const points = datedPoints(labels, close);

    return {
        ...chartFrame({
            valueFormatter,
            values: close,
            bottom: ZOOM_SLIDER_HEIGHT + TIME_AXIS_LABEL_HEIGHT,
            description: "Historique du cours de l'instrument.",
            gutter,
        }),
        dataZoom: [wealthZoomSlider(visible)],
        color: [colors.value],
        series: [{
            name: 'Cours',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 2.5 },
            areaStyle: { color: fadedArea(colors.value) },
            markPoint: markPoints(points, []),
            data: points,
        }],
    };
}
