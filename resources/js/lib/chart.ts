import type { LineSeriesOption } from 'echarts/charts';
import type { TooltipComponentOption } from 'echarts/components';
import type { ChartOption } from './echarts';
import { isDark } from './theme';

export const VALUE_LINE_COLOR = '#4f46e5';
export const INVESTED_LINE_COLOR = '#94a3b8';
export const GAIN_COLOR = '#10b981';
export const LOSS_COLOR = '#ef4444';

/** Les libellés d'axes sont peints en SVG : leur teinte suit le thème plutôt qu'un jeton CSS. */
function axisLabelColor(): string {
    return isDark.value ? 'oklch(0.708 0 0)' : 'oklch(0.556 0 0)';
}

type TooltipTheme = { backgroundColor: string; borderColor: string; textColor: string };

function tooltipTheme(): TooltipTheme {
    return isDark.value
        ? { backgroundColor: 'oklch(0.205 0 0)', borderColor: 'oklch(1 0 0 / 10%)', textColor: 'oklch(0.985 0 0)' }
        : { backgroundColor: 'oklch(1 0 0)', borderColor: 'oklch(0.922 0 0)', textColor: 'oklch(0.145 0 0)' };
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
    const theme = tooltipTheme();

    return {
        animation: false,
        aria: { enabled: true, label: { description } },
        grid: { left: 64, right: 12, top: 12, bottom, containLabel: false },
        /** Axe temporel plutôt que catégoriel : la graduation suit l'amplitude réellement visible. */
        xAxis: {
            type: 'time',
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: axisLabelColor(), hideOverlap: true },
        },
        yAxis: {
            type: 'value',
            /** Sans `scale`, ECharts englobe zéro : la variation s'écrase alors dans le haut du cadre. */
            scale: true,
            axisLine: { show: false },
            axisTick: { show: false },
            axisLabel: { color: axisLabelColor(), formatter: (value: number): string => valueFormatter(value) },
            splitLine: { lineStyle: { color: 'rgba(128,128,128,0.15)', type: 'dashed' } },
        },
        tooltip: {
            trigger: 'axis',
            backgroundColor: theme.backgroundColor,
            borderColor: theme.borderColor,
            textStyle: { color: theme.textColor, fontFamily: 'inherit' },
            axisPointer: { type: 'line', lineStyle: { color: 'rgba(128,128,128,0.4)' } },
        },
    };
}

export type AssetSeries = { assetId: number; name: string; value: number[]; invested: number[] };

/** Un axe temporel attend des couples date/valeur, pas une suite de valeurs indexées. */
function datedPoints(labels: string[], values: number[]): [string, number][] {
    return labels.map((label: string, index: number): [string, number] => [label, values[index] ?? 0]);
}

/**
 * Le couple de courbes « valeur contre investi », identique sur le tableau de bord et la fiche
 * instrument. L'investi ne bouge qu'à un achat ou une vente : l'escalier lit plus juste. Il n'est
 * tracé que sur demande — la valeur seule est la lecture courante, l'investi la comparaison.
 */
function valueVsInvestedSeries(
    labels: string[],
    value: number[],
    invested: number[],
    showInvested: boolean,
): LineSeriesOption[] {
    const series: LineSeriesOption[] = [
        {
            name: 'Valeur',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 2 },
            data: datedPoints(labels, value),
        },
    ];

    if (!showInvested) {
        return series;
    }

    return [
        ...series,
        {
            name: 'Investi',
            type: 'line',
            step: 'end',
            symbol: 'none',
            lineStyle: { width: 2, type: 'dashed' },
            data: datedPoints(labels, invested),
        },
    ];
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
    const theme = tooltipTheme();

    return {
        trigger: 'axis',
        backgroundColor: theme.backgroundColor,
        borderColor: theme.borderColor,
        textStyle: { color: theme.textColor, fontFamily: 'inherit' },
        axisPointer: { type: 'line', lineStyle: { color: 'rgba(128,128,128,0.4)' } },
        formatter: (params: unknown): string => {
            const index = pointIndex(params);
            if (index === null) {
                return '';
            }

            const totalValue = value[index] ?? 0;
            const totalInvested = invested[index] ?? 0;
            const gain = totalValue - totalInvested;

            return tooltipTitle(labels[index] ?? '')
                + tooltipRow(VALUE_LINE_COLOR, 'Valeur', valueFormatter(totalValue))
                + tooltipRow(INVESTED_LINE_COLOR, 'Investi', valueFormatter(totalInvested))
                + tooltipRow(
                    gain >= 0 ? GAIN_COLOR : LOSS_COLOR,
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
    showInvested: boolean;
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
    { labels, value, invested, valueFormatter, window, showInvested, description }: ValueVsInvestedInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);

    return {
        ...chartFrame(valueFormatter, ZOOM_SLIDER_HEIGHT + 32, description),
        color: [VALUE_LINE_COLOR, INVESTED_LINE_COLOR],
        series: valueVsInvestedSeries(labels, value, invested, showInvested),
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
                fillerColor: 'rgba(128,128,128,0.15)',
                handleStyle: { color: axisLabelColor() },
                moveHandleStyle: { color: 'rgba(128,128,128,0.3)' },
                textStyle: { color: axisLabelColor() },
                dataBackground: { lineStyle: { opacity: 0 }, areaStyle: { color: 'rgba(128,128,128,0.2)' } },
                selectedDataBackground: { lineStyle: { opacity: 0 }, areaStyle: { color: 'rgba(128,128,128,0.4)' } },
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
    return {
        ...chartFrame(valueFormatter, 32, "Historique du cours de l'instrument."),
        color: [VALUE_LINE_COLOR],
        series: [{
            name: 'Cours',
            type: 'line',
            smooth: true,
            symbol: 'none',
            sampling: 'lttb',
            lineStyle: { width: 2 },
            areaStyle: { opacity: 0.15 },
            data: datedPoints(labels, close),
        }],
    };
}
