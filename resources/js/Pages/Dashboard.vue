<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface HoldingLine {
    assetId: number;
    assetName: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
    quantity: number;
    avgCost: number | null;
    lastPrice: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
}

interface AllocationSlice {
    label: string;
    value: number;
    pct: number;
    color: string;
}

interface PortfolioOverview {
    totalValue: number;
    totalCost: number;
    totalGain: number;
    totalGainPct: number;
    holdings: HoldingLine[];
    allocation: AllocationSlice[];
}

interface Performance {
    key: string;
    label: string;
    pct: number | null;
}

interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
}

interface AssetInvestedSeries {
    assetId: number;
    name: string;
    invested: number[];
}

interface InvestedByAssetSeries {
    labels: string[];
    series: AssetInvestedSeries[];
}

const props = defineProps<{ overview: PortfolioOverview; performances?: Performance[]; valuationSeries?: ValuationSeries; investedByAsset?: InvestedByAssetSeries; valuationRange?: string; valuationGranularity?: string }>();

type RangeKey = '1M' | '6M' | '1Y' | 'max';
type GranularityKey = 'day' | 'week' | 'month';

const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

const granularityOptions: { key: GranularityKey; label: string }[] = [
    { key: 'day', label: 'Jour' },
    { key: 'week', label: 'Sem' },
    { key: 'month', label: 'Mois' },
];

const isRangeKey = (value: string | undefined): value is RangeKey =>
    rangeOptions.some((option) => option.key === value);

const isGranularityKey = (value: string | undefined): value is GranularityKey =>
    granularityOptions.some((option) => option.key === value);

const selectedRange = ref<RangeKey>(isRangeKey(props.valuationRange) ? props.valuationRange : 'max');
const selectedGranularity = ref<GranularityKey>(
    isGranularityKey(props.valuationGranularity) ? props.valuationGranularity : 'month',
);
const reloading = ref<boolean>(false);

const reloadSeries = (): void => {
    router.reload({
        only: ['valuationSeries', 'investedByAsset'],
        data: { range: selectedRange.value, granularity: selectedGranularity.value },
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};

const selectRange = (key: RangeKey): void => {
    if (selectedRange.value === key) {
        return;
    }
    selectedRange.value = key;
    reloadSeries();
};

const selectGranularity = (key: GranularityKey): void => {
    if (selectedGranularity.value === key) {
        return;
    }
    selectedGranularity.value = key;
    reloadSeries();
};

const flatCard = 'border-0 bg-transparent shadow-none rounded-none';

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

const hasAllocation = computed<boolean>(() => props.overview.allocation.length > 0);

const allocationSeries = computed<number[]>(() => props.overview.allocation.map((slice) => slice.value));

const allocationOptions = computed<ApexOptions>(() => ({
    chart: { fontFamily: 'inherit' },
    labels: props.overview.allocation.map((slice) => slice.label),
    colors: props.overview.allocation.map((slice) => slice.color),
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val: number): string => `${Math.round(Number(val))}%` },
    stroke: { width: 0 },
    tooltip: { y: { formatter: (val: number): string => eur(val) } },
}));

const hasValuation = computed<boolean>(() => (props.valuationSeries?.labels.length ?? 0) > 0);

const valuationChartSeries = computed(() => [
    { name: 'Valeur', data: props.valuationSeries?.valuations ?? [] },
    { name: 'Investi', data: props.valuationSeries?.invested ?? [] },
]);

const valuationChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuationSeries?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'top' },
}));

const hasInvestedByAsset = computed<boolean>(() => (props.investedByAsset?.series.length ?? 0) > 0);

const investedByAssetSeries = computed(() =>
    (props.investedByAsset?.series ?? []).map((serie) => ({ name: serie.name, data: serie.invested })),
);

const investedByAssetOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
    stroke: { curve: 'stepline', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.investedByAsset?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'bottom' },
}));
</script>

<template>
    <Head title="Tableau de bord" />

    <main class="min-h-screen overflow-x-hidden bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <section v-if="overview.holdings.length" class="flex flex-col gap-3">
                <div class="flex flex-col gap-0.5">
                    <p class="text-sm text-muted-foreground">Gain / perte</p>
                    <p class="text-2xl font-semibold" :class="gainClass(overview.totalGain)">
                        {{ eur(overview.totalGain) }}
                        <span class="text-sm">({{ pct(overview.totalGainPct) }})</span>
                    </p>
                    <p class="text-sm text-muted-foreground">Valeur totale {{ eur(overview.totalValue) }}</p>
                </div>

                <Deferred data="performances">
                    <template #fallback>
                        <div class="-mx-6 overflow-x-hidden px-6">
                            <div class="flex min-w-max gap-2">
                                <div v-for="n in 6" :key="n" class="h-[52px] w-[64px] animate-pulse rounded-md bg-muted"></div>
                            </div>
                        </div>
                    </template>

                    <div v-if="performances && performances.length" class="-mx-6 overflow-x-auto px-6">
                        <div class="flex min-w-max gap-2">
                            <div
                                v-for="perf in performances"
                                :key="perf.key"
                                class="flex min-w-[64px] flex-col gap-0.5 rounded-md border border-border px-3 py-2"
                            >
                                <span class="text-xs text-muted-foreground">{{ perf.label }}</span>
                                <span class="text-sm font-medium" :class="gainClass(perf.pct)">{{ pct(perf.pct) }}</span>
                            </div>
                        </div>
                    </div>
                </Deferred>
            </section>

            <div v-if="overview.holdings.length" class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-md border border-border p-0.5">
                    <button
                        v-for="opt in rangeOptions"
                        :key="opt.key"
                        type="button"
                        class="rounded px-3 py-1 text-sm transition-colors"
                        :class="selectedRange === opt.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="selectRange(opt.key)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <div class="inline-flex rounded-md border border-border p-0.5">
                    <button
                        v-for="opt in granularityOptions"
                        :key="opt.key"
                        type="button"
                        class="rounded px-3 py-1 text-sm transition-colors"
                        :class="selectedGranularity === opt.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                        @click="selectGranularity(opt.key)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <Card :class="[flatCard, '-mx-6 transition-opacity sm:mx-0', reloading ? 'opacity-50' : '']">
                <CardHeader>
                    <CardTitle>Évolution</CardTitle>
                    <CardDescription>Valeur du portefeuille vs investi</CardDescription>
                </CardHeader>
                <CardContent class="px-0 sm:px-6">
                    <Deferred data="valuationSeries">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasValuation"
                            type="area"
                            height="300"
                            :options="valuationChartOptions"
                            :series="valuationChartSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'historique de valorisation.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>

            <Card :class="[flatCard, '-mx-6 transition-opacity sm:mx-0', reloading ? 'opacity-50' : '']">
                <CardHeader>
                    <CardTitle>Investi par titre</CardTitle>
                    <CardDescription>Montant investi cumulé sur chaque titre</CardDescription>
                </CardHeader>
                <CardContent class="px-0 sm:px-6">
                    <Deferred data="investedByAsset">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasInvestedByAsset"
                            type="line"
                            height="300"
                            :options="investedByAssetOptions"
                            :series="investedByAssetSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'investissement.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>


            <section class="grid gap-4 lg:grid-cols-3">
                <Card :class="[flatCard, 'min-w-0 lg:col-span-2']">
                    <CardHeader>
                        <CardTitle>Positions</CardTitle>
                        <CardDescription>Détail de vos lignes</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="overview.holdings.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Actif</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead class="text-right">Quantité</TableHead>
                                    <TableHead class="text-right">Dernier prix</TableHead>
                                    <TableHead class="text-right">Valeur</TableHead>
                                    <TableHead class="text-right">+/-</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(line, index) in overview.holdings" :key="index">
                                    <TableCell class="font-medium">
                                        <Link :href="`/instruments/${line.assetId}`" class="hover:underline">
                                            {{ line.assetName }}
                                            <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                        </Link>
                                    </TableCell>
                                    <TableCell>{{ line.typeLabel }}</TableCell>
                                    <TableCell class="text-right">{{ line.quantity }}</TableCell>
                                    <TableCell class="text-right">
                                        <span v-if="line.lastPrice === null" class="text-muted-foreground" title="Prix indisponible">N/D</span>
                                        <span v-else>{{ eur(line.lastPrice) }}</span>
                                    </TableCell>
                                    <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                                    <TableCell class="text-right" :class="gainClass(line.gain)">{{ pct(line.gainPct) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Aucune position pour le moment.
                        </p>
                    </CardContent>
                </Card>

                <Card :class="flatCard">
                    <CardHeader>
                        <CardTitle>Répartition</CardTitle>
                        <CardDescription>Par type d'actif</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts
                            v-if="hasAllocation"
                            type="donut"
                            height="300"
                            :options="allocationOptions"
                            :series="allocationSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas de données de répartition.
                        </p>
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
</template>
