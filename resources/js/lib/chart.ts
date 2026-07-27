import type { ApexOptions } from 'apexcharts';

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
        legend: { position: legendPosition, labels: { colors: '#fff' } },
        responsive: LEGEND_BELOW_ON_MOBILE,
    };
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
