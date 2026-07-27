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

type AssetSeries = { assetId: number; name: string; value: number[]; invested: number[] };

type EvolutionInput = {
    labels: string[];
    perAsset: AssetSeries[];
    hiddenIds: Set<number>;
    valueFormatter: (value: number) => string;
};

export function buildEvolutionChart({
    labels,
    perAsset,
    hiddenIds,
    valueFormatter,
}: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions } {
    const visible = perAsset.filter((asset) => !hiddenIds.has(asset.assetId));
    const point = (i: number, y: number): { x: string; y: number } => ({ x: labels[i], y });

    const cumulative: number[][] = visible.map((_, k) =>
        labels.map((_label, i) => visible.slice(0, k + 1).reduce((sum, asset) => sum + (asset.value[i] ?? 0), 0)),
    );

    const series: ApexAxisChartSeries = [];
    const colors: string[] = [];
    for (let k = visible.length - 1; k >= 0; k -= 1) {
        series.push({
            name: visible[k].name,
            type: 'area',
            data: labels.map((_label, i) => point(i, cumulative[k][i])),
        });
        colors.push(GREY_SCALE[k % GREY_SCALE.length]);
    }

    const options: ApexOptions = {
        chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
        colors,
        stroke: { curve: 'smooth', width: 0 },
        fill: { type: 'solid', opacity: 0.9 },
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
        legend: { show: false },
        responsive: LEGEND_BELOW_ON_MOBILE,
        tooltip: {
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex }): string => {
                const i = dataPointIndex;
                const totalValue = visible.reduce((sum, asset) => sum + (asset.value[i] ?? 0), 0);
                const totalInvested = visible.reduce((sum, asset) => sum + (asset.invested[i] ?? 0), 0);
                const gain = totalValue - totalInvested;
                const gainColor = gain >= 0 ? GAIN_COLOR : LOSS_COLOR;
                const gainSign = gain >= 0 ? '+' : '−';

                const row = (color: string, label: string, text: string): string =>
                    `<div class="apexcharts-tooltip-series-group apexcharts-active" style="display: flex;">`
                    + `<span class="apexcharts-tooltip-marker" style="background-color: ${color};"></span>`
                    + `<div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">`
                    + `<div class="apexcharts-tooltip-y-group">`
                    + `<span class="apexcharts-tooltip-text-y-label">${label}: </span>`
                    + `<span class="apexcharts-tooltip-text-y-value">${text}</span>`
                    + `</div></div></div>`;

                const header = `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${formatTooltipDate(labels[i])}</div>`;
                const valueRow = row(VALUE_LINE_COLOR, 'Valeur', valueFormatter(totalValue));
                const gainRow = row(gainColor, gain >= 0 ? 'Gain' : 'Perte', `${gainSign} ${valueFormatter(Math.abs(gain))}`);
                const assetRows = visible
                    .map((asset, k) => row(GREY_SCALE[k % GREY_SCALE.length], asset.name, valueFormatter(asset.value[i] ?? 0)))
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
