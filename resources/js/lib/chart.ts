import type { ApexAxisChartSeries, ApexOptions } from 'apexcharts';

const LEGEND_BELOW_ON_MOBILE: ApexOptions['responsive'] = [
    { breakpoint: 640, options: { legend: { position: 'bottom' } } },
];

const OVERLAP_PX = 6;

function formatTooltipDate(label: string | number): string {
    const date = new Date(`${label}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return String(label);
    }
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
}

type TimeSeriesOptionsInput = {
    categories: (string | number)[];
    valueFormatter: (value: number) => string;
    legendPosition?: 'left' | 'top' | 'bottom' | 'right';
};

export function buildTimeSeriesOptions({
    categories,
    valueFormatter,
    legendPosition = 'left',
}: TimeSeriesOptionsInput): ApexOptions {
    return {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
        xaxis: {
            type: 'datetime',
            categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { hideOverlappingLabels: true, style: { colors: 'oklch(0.708 0 0)' } },
        },
        yaxis: { labels: { formatter: (value: number): string => valueFormatter(value), style: { colors: 'oklch(0.708 0 0)' } } },
        tooltip: {
            shared: false,
            intersect: false,
            custom: ({ seriesIndex, dataPointIndex, w }): string => {
                const values: number[][] = w.globals.series;
                const hovered = values[seriesIndex]?.[dataPointIndex];
                if (hovered == null) {
                    return '';
                }

                const range = (w.globals.maxY - w.globals.minY) || 1;
                const pxPerUnit = (w.globals.gridHeight || 1) / range;
                const threshold = OVERLAP_PX / pxPerUnit;

                const title = formatTooltipDate(categories[dataPointIndex]);
                const rows = values
                    .map((serie, index) => ({ index, value: serie?.[dataPointIndex] }))
                    .filter(({ index, value }) => value != null && (index === seriesIndex || Math.abs(value - hovered) <= threshold))
                    .map(({ index, value }) => {
                        const color = w.globals.colors[index];
                        const name = w.globals.seriesNames[index];

                        return `<div class="apexcharts-tooltip-series-group apexcharts-active" style="display: flex;">`
                            + `<span class="apexcharts-tooltip-marker" style="background-color: ${color};"></span>`
                            + `<div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">`
                            + `<div class="apexcharts-tooltip-y-group">`
                            + `<span class="apexcharts-tooltip-text-y-label">${name}: </span>`
                            + `<span class="apexcharts-tooltip-text-y-value">${valueFormatter(value)}</span>`
                            + `</div></div></div>`;
                    })
                    .join('');

                return `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${title}</div>${rows}`;
            },
        },
        legend: { position: legendPosition, horizontalAlign: 'left', labels: { colors: '#fff' } },
        responsive: LEGEND_BELOW_ON_MOBILE,
    };
}

const GREY_SCALE = ['#e2e8f0', '#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155'];
const VALUE_LINE_COLOR = '#4f46e5';
const GAIN_COLOR = '#10b981';
const LOSS_COLOR = '#ef4444';
const BAND_NAMES = ['__gain__', '__loss__'];

type EvolutionInput = {
    labels: string[];
    value: number[];
    totalInvested: number[];
    perAsset: { name: string; invested: number[] }[];
    valueFormatter: (value: number) => string;
};

export function buildEvolutionChart({
    labels,
    value,
    totalInvested,
    perAsset,
    valueFormatter,
}: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions } {
    const point = (i: number, y: number | number[] | null): { x: string; y: number | number[] | null } => ({ x: labels[i], y });

    const hideBandLegendItems = (chartContext: { el?: HTMLElement | null }): void => {
        const root = chartContext?.el;
        if (!root) {
            return;
        }
        root.querySelectorAll('.apexcharts-legend-series').forEach((node) => {
            const text = node.querySelector('.apexcharts-legend-text');
            if (text == null || text.textContent?.trim() === '') {
                (node as HTMLElement).style.display = 'none';
            }
        });
    };

    const cumulative: number[][] = perAsset.map((_, k) =>
        labels.map((_label, i) => perAsset.slice(0, k + 1).reduce((sum, asset) => sum + (asset.invested[i] ?? 0), 0)),
    );

    const areaSeries: ApexAxisChartSeries = [];
    const areaColors: string[] = [];
    for (let k = perAsset.length - 1; k >= 0; k -= 1) {
        areaSeries.push({
            name: perAsset[k].name,
            type: 'area',
            data: labels.map((_label, i) => point(i, cumulative[k][i])),
        });
        areaColors.push(GREY_SCALE[k % GREY_SCALE.length]);
    }

    const gainBand = {
        name: BAND_NAMES[0],
        type: 'rangeArea',
        data: labels.map((_label, i) =>
            value[i] >= totalInvested[i] ? point(i, [totalInvested[i], value[i]]) : point(i, [null, null] as unknown as number[])),
    };
    const lossBand = {
        name: BAND_NAMES[1],
        type: 'rangeArea',
        data: labels.map((_label, i) =>
            value[i] < totalInvested[i] ? point(i, [value[i], totalInvested[i]]) : point(i, [null, null] as unknown as number[])),
    };
    const valueLine = {
        name: 'Valeur',
        type: 'line',
        data: labels.map((_label, i) => point(i, value[i])),
    };

    const series: ApexAxisChartSeries = [...areaSeries, gainBand, lossBand, valueLine];
    const colors = [...areaColors, GAIN_COLOR, LOSS_COLOR, VALUE_LINE_COLOR];
    const strokeWidth = [...areaSeries.map(() => 0), 0, 0, 2];
    const fillOpacity = [...areaSeries.map(() => 0.9), 0.35, 0.35, 1];

    const options: ApexOptions = {
        chart: {
            type: 'line',
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'inherit',
            animations: { enabled: false },
            events: {
                mounted: (chartContext: unknown): void => hideBandLegendItems(chartContext as { el?: HTMLElement | null }),
                updated: (chartContext: unknown): void => hideBandLegendItems(chartContext as { el?: HTMLElement | null }),
            },
        },
        colors,
        stroke: { curve: 'smooth', width: strokeWidth },
        fill: { type: 'solid', opacity: fillOpacity },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
        xaxis: {
            type: 'datetime',
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { hideOverlappingLabels: true, style: { colors: 'oklch(0.708 0 0)' } },
        },
        yaxis: {
            labels: {
                formatter: (v: number): string => (v == null || !Number.isFinite(v) ? '' : valueFormatter(v)),
                style: { colors: 'oklch(0.708 0 0)' },
            },
        },
        legend: {
            position: 'left',
            horizontalAlign: 'left',
            labels: { colors: '#fff' },
            onItemClick: { toggleDataSeries: false },
            formatter: (name: string): string => (BAND_NAMES.includes(name) ? '' : name),
        },
        responsive: LEGEND_BELOW_ON_MOBILE,
        tooltip: {
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex }): string => {
                const i = dataPointIndex;
                const gain = value[i] - totalInvested[i];
                const gainColor = gain >= 0 ? GAIN_COLOR : LOSS_COLOR;
                const gainSign = gain >= 0 ? '+' : '−';

                const header = `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${formatTooltipDate(labels[i])}</div>`;

                const row = (color: string, label: string, text: string): string =>
                    `<div class="apexcharts-tooltip-series-group apexcharts-active" style="display: flex;">`
                    + `<span class="apexcharts-tooltip-marker" style="background-color: ${color};"></span>`
                    + `<div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">`
                    + `<div class="apexcharts-tooltip-y-group">`
                    + `<span class="apexcharts-tooltip-text-y-label">${label}: </span>`
                    + `<span class="apexcharts-tooltip-text-y-value">${text}</span>`
                    + `</div></div></div>`;

                const valueRow = row(VALUE_LINE_COLOR, 'Valeur', valueFormatter(value[i]));
                const gainRow = row(gainColor, gain >= 0 ? 'Gain' : 'Perte', `${gainSign} ${valueFormatter(Math.abs(gain))}`);
                const assetRows = perAsset
                    .map((asset, k) => row(GREY_SCALE[k % GREY_SCALE.length], asset.name, valueFormatter(asset.invested[i] ?? 0)))
                    .join('');

                return header + valueRow + gainRow + assetRows;
            },
        },
    };

    return { series, options };
}

type DonutOptionsInput = {
    labels: string[];
    colors: string[];
    valueFormatter: (value: number) => string;
    legendPosition?: 'left' | 'top' | 'bottom' | 'right';
};

export function buildDonutOptions({
    labels,
    colors,
    valueFormatter,
    legendPosition = 'left',
}: DonutOptionsInput): ApexOptions {
    return {
        chart: { fontFamily: 'inherit' },
        labels,
        colors,
        legend: { position: legendPosition, labels: { colors: '#fff' } },
        dataLabels: { enabled: true, formatter: (value: number): string => `${Math.round(Number(value))}%` },
        stroke: { width: 0 },
        tooltip: { y: { formatter: (value: number): string => valueFormatter(value) } },
        responsive: LEGEND_BELOW_ON_MOBILE,
    };
}
