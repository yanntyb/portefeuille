import type { LineSeriesOption } from 'echarts/charts';
import type { TooltipComponentOption } from 'echarts/components';
import type { ChartOption } from './echarts';
import { isDark } from './theme';

type ChartPalette = {
    value: string;
    invested: string;
    gain: string;
    loss: string;
    axisLabel: string;
    grid: string;
    areaTop: string;
    areaBottom: string;
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
    return isDark.value
        ? {
            value: '#8f93f0',
            invested: '#6b7280',
            gain: '#34d399',
            loss: '#f87171',
            axisLabel: '#7f858f',
            grid: '#262a33',
            areaTop: 'rgba(143,147,240,0.22)',
            areaBottom: 'rgba(143,147,240,0)',
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
            axisLabel: '#9aa0ac',
            grid: '#eceef2',
            areaTop: 'rgba(82,87,214,0.18)',
            areaBottom: 'rgba(82,87,214,0)',
            filler: 'rgba(82,87,214,0.12)',
            dataBackground: '#e2e4ea',
            selectedDataBackground: 'rgba(82,87,214,0.45)',
            tooltipBackground: '#ffffff',
            tooltipBorder: '#e2e4ea',
            tooltipText: '#16181d',
            surface: '#ffffff',
        };
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

/**
 * Ossature partagée par les trois graphes : axes, grille et cadre d'infobulle suivent le thème.
 * La description accessible est rédigée à la main plutôt que laissée au gabarit anglais d'ECharts.
 */
function chartFrame(valueFormatter: ValueFormatter, bottom: number, description: string): ChartOption {
    const colors = palette();

    return {
        animation: false,
        aria: { enabled: true, label: { description } },
        grid: { left: 64, right: 12, top: 12, bottom, containLabel: false },
        /** Axe temporel plutôt que catégoriel : la graduation suit l'amplitude réellement visible. */
        xAxis: {
            type: 'time',
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: colors.axisLabel, hideOverlap: true },
        },
        yAxis: {
            type: 'value',
            /** Sans `scale`, ECharts englobe zéro : la variation s'écrase alors dans le haut du cadre. */
            scale: true,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: colors.axisLabel, formatter: (value: number): string => valueFormatter(value) },
            splitLine: { lineStyle: { color: colors.grid } },
        },
        tooltip: chartTooltip(),
    };
}

/** Cadre d'infobulle commun : seul le contenu change d'un graphe à l'autre. */
function chartTooltip(): TooltipComponentOption {
    const colors = palette();

    return {
        trigger: 'axis',
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
 * Le couple de courbes « valeur contre investi », identique sur le tableau de bord et la fiche
 * instrument. L'investi ne bouge qu'à un achat ou une vente : l'escalier lit plus juste. Les deux
 * courbes sont toujours tracées — la comparaison est la lecture, pas une option.
 */
function valueVsInvestedSeries(labels: string[], value: number[], invested: number[]): LineSeriesOption[] {
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
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: colors.areaTop },
                        { offset: 1, color: colors.areaBottom },
                    ],
                },
            },
            markPoint: lastPointMarker(points),
            data: points,
        },
        {
            name: 'Investi',
            type: 'line',
            step: 'end',
            symbol: 'none',
            lineStyle: { width: 1.5, type: 'dashed' },
            data: datedPoints(labels, invested),
        },
    ];
}

/** Pastille sur la dernière valeur : elle ancre la lecture sur « où en est-on aujourd'hui ». */
function lastPointMarker(points: [string, number][]): LineSeriesOption['markPoint'] {
    const last = points[points.length - 1];

    if (last === undefined) {
        return undefined;
    }

    const colors = palette();

    return {
        symbol: 'circle',
        symbolSize: 8,
        silent: true,
        label: { show: false },
        itemStyle: { color: colors.value, borderColor: colors.surface, borderWidth: 2 },
        data: [{ name: 'Dernière valeur', coord: last }],
    };
}

/**
 * Infobulle des deux graphes « valeur contre investi » : le gain se lit sur place plutôt que
 * de laisser le lecteur soustraire lui-même les deux lignes.
 */
function valueVsInvestedTooltip(
    labels: string[],
    value: number[],
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

            const totalValue = value[index] ?? 0;
            const totalInvested = invested[index] ?? 0;
            const gain = totalValue - totalInvested;

            return tooltipTitle(labels[index] ?? '')
                + tooltipRow(colors.value, 'Valeur', valueFormatter(totalValue))
                + tooltipRow(colors.invested, 'Investi', valueFormatter(totalInvested))
                + tooltipRow(
                    gain >= 0 ? colors.gain : colors.loss,
                    gain >= 0 ? 'Gain' : 'Perte',
                    `${gain >= 0 ? '+' : '−'} ${valueFormatter(Math.abs(gain))}`,
                );
        },
    };
}

/** ECharts passe un tableau de points survolés ; tous partagent le même index de catégorie. */
function pointIndex(params: unknown): number | null {
    const points = Array.isArray(params) ? params : [params];
    const first = points[0] as { dataIndex?: number } | undefined;

    return typeof first?.dataIndex === 'number' ? first.dataIndex : null;
}

export type ZoomWindow = { start: number; end: number };

type ValueVsInvestedInput = {
    labels: string[];
    value: number[];
    invested: number[];
    valueFormatter: ValueFormatter;
    /** `null` à la première peinture : la fenêtre d'ouverture se déduit alors de l'historique. */
    window: ZoomWindow | null;
    description: string;
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
 * Fenêtre d'ouverture : les douze derniers mois, exprimés en pourcentage de l'amplitude totale
 * — l'axe étant temporel, le `dataZoom` répartit ses pourcentages sur la durée, pas sur les points.
 * Un historique plus court qu'un an s'affiche en entier : le plancher le fige déjà là.
 */
function lastYearWindow(labels: string[]): ZoomWindow {
    const span = labelTime(labels[labels.length - 1]) - labelTime(labels[0]);

    if (!Number.isFinite(span) || span <= MIN_ZOOM_SPAN_MS) {
        return { start: 0, end: 100 };
    }

    return { start: 100 * (1 - MIN_ZOOM_SPAN_MS / span), end: 100 };
}

/** Hauteur réservée sous la grille à la mini-timeline du zoom, en pixels. */
const ZOOM_SLIDER_HEIGHT = 40;

/**
 * Le graphe « valeur contre investi », seul et même pour le tableau de bord et la fiche
 * instrument : mêmes courbes, même axe, même infobulle, même zoom. La fenêtre temporelle est
 * choisie côté client par le `dataZoom` : rien ici ne dépend du réseau.
 */
export function buildValueVsInvestedOption(
    { labels, value, invested, valueFormatter, window, description }: ValueVsInvestedInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);
    const colors = palette();

    return {
        ...chartFrame(valueFormatter, ZOOM_SLIDER_HEIGHT + 32, description),
        color: [colors.value, colors.invested],
        series: valueVsInvestedSeries(labels, value, invested),
        tooltip: valueVsInvestedTooltip(labels, value, invested, valueFormatter),
        dataZoom: [
            { type: 'inside', start: visible.start, end: visible.end, minValueSpan: MIN_ZOOM_SPAN_MS },
            {
                type: 'slider',
                start: visible.start,
                end: visible.end,
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
            },
        ],
    };
}

type PriceHistoryInput = {
    labels: string[];
    close: number[];
    valueFormatter: ValueFormatter;
};

/** Cours d'un instrument : une courbe unique, aire dégradée sous la ligne. */
export function buildPriceHistoryOption({ labels, close, valueFormatter }: PriceHistoryInput): ChartOption {
    const colors = palette();
    const points = datedPoints(labels, close);

    return {
        ...chartFrame(valueFormatter, 32, "Historique du cours de l'instrument."),
        color: [colors.value],
        series: [{
            name: 'Cours',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 2.5 },
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [
                        { offset: 0, color: colors.areaTop },
                        { offset: 1, color: colors.areaBottom },
                    ],
                },
            },
            markPoint: lastPointMarker(points),
            data: points,
        }],
    };
}
