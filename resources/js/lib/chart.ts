import type { ApexOptions } from 'apexcharts';

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
            labels: { hideOverlappingLabels: true },
        },
        yaxis: { labels: { formatter: (value: number): string => valueFormatter(value) } },
        tooltip: { y: { formatter: (value: number): string => valueFormatter(value) } },
        legend: { position: legendPosition },
    };
}
