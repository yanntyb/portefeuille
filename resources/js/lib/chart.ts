import type { ChartOption } from './echarts';
import { isDark } from './theme';

export const VALUE_LINE_COLOR = '#4f46e5';
export const INVESTED_LINE_COLOR = '#94a3b8';
export const GAIN_COLOR = '#10b981';
export const LOSS_COLOR = '#ef4444';

const GREY_SCALE_ON_DARK = ['#e2e8f0', '#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155'];
const GREY_SCALE_ON_LIGHT = ['#334155', '#475569', '#64748b', '#94a3b8', '#cbd5e1', '#e2e8f0'];

/** Le dégradé part toujours de la teinte la plus contrastée avec le fond du thème courant. */
function greyScale(): string[] {
    return isDark.value ? GREY_SCALE_ON_DARK : GREY_SCALE_ON_LIGHT;
}

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
function chartFrame(valueFormatter: ValueFormatter, bottom: number, description: string, valueInterval?: number): ChartOption {
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
            interval: valueInterval,
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

export type ZoomWindow = { start: number; end: number };

type EvolutionInput = {
    labels: string[];
    perAsset: AssetSeries[];
    hiddenIds: Set<number>;
    valueFormatter: ValueFormatter;
    window: ZoomWindow;
};

/** Part de l'historique visible à l'ouverture : le graphe s'ouvre sur la période récente. */
export const INITIAL_ZOOM_WINDOW: ZoomWindow = { start: 70, end: 100 };

/** Hauteur réservée sous la grille à la mini-timeline du zoom, en pixels. */
const ZOOM_SLIDER_HEIGHT = 40;

/**
 * Un intervalle plus large que n'importe quelle amplitude ne laisse subsister que les deux
 * graduations extrêmes : le lecteur garde les bornes de l'échelle, sans les paliers du milieu.
 */
const EXTREME_TICKS_ONLY = Number.POSITIVE_INFINITY;

/**
 * Aires empilées du tableau de bord. La fenêtre temporelle est choisie côté client par le
 * `dataZoom` : rien ici ne dépend du réseau ni de la taille du conteneur.
 */
export function buildEvolutionOption({ labels, perAsset, hiddenIds, valueFormatter, window }: EvolutionInput): ChartOption {
    const visible = perAsset.filter((asset: AssetSeries): boolean => !hiddenIds.has(asset.assetId));
    const palette = greyScale();
    const theme = tooltipTheme();

    const description = visible.length === 0
        ? 'Évolution de la valeur du portefeuille. Aucun titre affiché.'
        : `Évolution de la valeur du portefeuille, par titre : ${visible.map((asset) => asset.name).join(', ')}.`;

    return {
        ...chartFrame(valueFormatter, ZOOM_SLIDER_HEIGHT + 44, description, EXTREME_TICKS_ONLY),
        color: visible.map((_asset: AssetSeries, index: number): string => palette[index % palette.length]),
        series: visible.map((asset: AssetSeries) => ({
            name: asset.name,
            type: 'line' as const,
            stack: 'total',
            symbol: 'none' as const,
            lineStyle: { width: 0 },
            areaStyle: { opacity: 0.9 },
            data: datedPoints(labels, asset.value),
        })),
        dataZoom: [
            { type: 'inside', start: window.start, end: window.end },
            {
                type: 'slider',
                start: window.start,
                end: window.end,
                height: ZOOM_SLIDER_HEIGHT,
                bottom: 0,
                borderColor: 'transparent',
                fillerColor: 'rgba(128,128,128,0.15)',
                handleStyle: { color: axisLabelColor() },
                moveHandleStyle: { color: 'rgba(128,128,128,0.3)' },
                textStyle: { color: axisLabelColor() },
                dataBackground: { lineStyle: { opacity: 0 }, areaStyle: { color: 'rgba(128,128,128,0.2)' } },
                selectedDataBackground: { lineStyle: { opacity: 0 }, areaStyle: { color: 'rgba(128,128,128,0.4)' } },
            },
        ],
        tooltip: {
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

                const totalValue = visible.reduce((sum, asset) => sum + (asset.value[index] ?? 0), 0);
                const totalInvested = visible.reduce((sum, asset) => sum + (asset.invested[index] ?? 0), 0);
                const gain = totalValue - totalInvested;

                return tooltipTitle(labels[index] ?? '')
                    + tooltipRow(VALUE_LINE_COLOR, 'Valeur', valueFormatter(totalValue))
                    + tooltipRow(
                        gain >= 0 ? GAIN_COLOR : LOSS_COLOR,
                        gain >= 0 ? 'Gain' : 'Perte',
                        `${gain >= 0 ? '+' : '−'} ${valueFormatter(Math.abs(gain))}`,
                    )
                    + visible
                        .map((asset, k) => tooltipRow(palette[k % palette.length], asset.name, valueFormatter(asset.value[index] ?? 0)))
                        .join('');
            },
        },
    };
}

/** ECharts passe un tableau de points survolés ; tous partagent le même index de catégorie. */
function pointIndex(params: unknown): number | null {
    const points = Array.isArray(params) ? params : [params];
    const first = points[0] as { dataIndex?: number } | undefined;

    return typeof first?.dataIndex === 'number' ? first.dataIndex : null;
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

type ValuationInput = {
    labels: string[];
    valuations: number[];
    invested: number[];
    valueFormatter: ValueFormatter;
};

/** Valeur contre investi. L'investi ne bouge qu'à un achat ou une vente : l'escalier lit plus juste. */
export function buildValuationOption({ labels, valuations, invested, valueFormatter }: ValuationInput): ChartOption {
    return {
        ...chartFrame(valueFormatter, 32, 'Valeur de la position comparée au montant investi.'),
        color: [VALUE_LINE_COLOR, INVESTED_LINE_COLOR],
        series: [
            {
                name: 'Valeur',
                type: 'line',
                smooth: true,
                symbol: 'none',
                sampling: 'lttb',
                lineStyle: { width: 2 },
                data: datedPoints(labels, valuations),
            },
            {
                name: 'Investi',
                type: 'line',
                step: 'end',
                symbol: 'none',
                lineStyle: { width: 2, type: 'dashed' },
                data: datedPoints(labels, invested),
            },
        ],
    };
}

export type ValuationRangeKey = '1M' | '6M' | '1Y' | 'max';

export const VALUATION_RANGES: { key: ValuationRangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

/** One point per day only makes sense over a short window, so the period picks the step. */
export function granularityForRange(range: ValuationRangeKey): 'day' | 'week' | 'month' {
    if (range === '1M') {
        return 'day';
    }

    return range === 'max' ? 'month' : 'week';
}
