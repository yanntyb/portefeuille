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

interface Position {
    quantity: number;
    avgCost: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
}

interface TransactionLine {
    date: string;
    isSell: boolean;
    typeLabel: string;
    quantity: number;
    unitPrice: number;
    fees: number;
    total: number;
}

interface SectorWeight {
    label: string;
    weight: number;
}

interface Instrument {
    id: number;
    name: string;
    ticker: string | null;
    isin: string | null;
    type: string;
    typeLabel: string;
    lastPrice: number | null;
    lastPriceDate: string | null;
    position: Position | null;
    transactions: TransactionLine[];
    sectors: SectorWeight[];
}

interface PriceHistory {
    labels: string[];
    close: number[];
}

interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
    prices: number[];
}

const props = defineProps<{
    instrument: Instrument;
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
    valuationRange?: string;
    valuationGranularity?: string;
}>();

const flatCard = 'border-0 bg-transparent shadow-none rounded-none';

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });

const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

const signedPct = (value: number): string => {
    const delta = value - 100;
    return `${delta >= 0 ? '+' : ''}${delta.toFixed(1)} %`;
};

const base100 = (serie: number[]): number[] =>
    serie.length === 0 || serie[0] === 0
        ? serie.map((): number => 100)
        : serie.map((value: number): number => (value / serie[0]) * 100);

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

const reloadValuation = (): void => {
    router.reload({
        only: ['valuation'],
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
    reloadValuation();
};

const selectGranularity = (key: GranularityKey): void => {
    if (selectedGranularity.value === key) {
        return;
    }
    selectedGranularity.value = key;
    reloadValuation();
};

const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

const hasPriceHistory = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const priceChartSeries = computed(() => [
    { name: 'Cours', data: props.priceHistory?.close ?? [] },
]);

const priceChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.priceHistory?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
}));

const hasPosition = computed<boolean>(() => props.instrument.position !== null);

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const valuationKey = computed<string>(() => {
    const labels = props.valuation?.labels ?? [];
    return `${labels.length}:${labels[0] ?? ''}:${labels[labels.length - 1] ?? ''}`;
});

const coursChartSeries = computed(() => [
    { name: 'Cours', data: base100(props.valuation?.prices ?? []) },
]);

const coursChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#10b981'],
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => signedPct(value) } },
    tooltip: { y: { formatter: (value: number): string => signedPct(value) } },
    legend: { position: 'top' },
}));

const positionChartSeries = computed(() => [
    { name: 'Valeur', data: base100(props.valuation?.valuations ?? []) },
    { name: 'Investi', data: base100(props.valuation?.invested ?? []) },
]);

const positionChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => signedPct(value) } },
    tooltip: { y: { formatter: (value: number): string => signedPct(value) } },
    legend: { position: 'top' },
}));
</script>

<template>
    <Head :title="props.instrument.name" />

    <main class="min-h-screen overflow-x-hidden bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header class="flex flex-col gap-1">
                <Link href="/instruments" class="text-sm text-muted-foreground hover:underline">← Instruments</Link>
                <h1 class="text-2xl font-semibold">
                    {{ props.instrument.name }}
                    <span v-if="props.instrument.ticker" class="text-muted-foreground">({{ props.instrument.ticker }})</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.instrument.typeLabel }}
                    <span v-if="props.instrument.isin"> · ISIN {{ props.instrument.isin }}</span>
                    <span v-if="props.instrument.lastPrice !== null">
                        · {{ eur(props.instrument.lastPrice) }}
                        <span v-if="props.instrument.lastPriceDate" class="text-xs">au {{ props.instrument.lastPriceDate }}</span>
                    </span>
                </p>
            </header>

            <section v-if="props.instrument.position" class="grid gap-4 sm:grid-cols-4">
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Quantité</CardDescription>
                        <CardTitle class="text-2xl">{{ props.instrument.position.quantity }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>PRU</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(props.instrument.position.avgCost) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Valeur</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(props.instrument.position.marketValue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Gain / perte</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(props.instrument.position.gain)">
                            {{ eur(props.instrument.position.gain) }}
                            <span class="text-sm">({{ pct(props.instrument.position.gainPct) }})</span>
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <section v-if="hasPosition" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
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

                <Deferred data="valuation">
                    <template #fallback>
                        <div class="-mx-6 grid gap-4 sm:mx-0 lg:grid-cols-2">
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </div>
                    </template>

                    <div
                        v-if="hasValuation"
                        class="-mx-6 grid gap-4 transition-opacity sm:mx-0 lg:grid-cols-2"
                        :class="reloading ? 'opacity-50' : ''"
                    >
                        <Card :class="flatCard">
                            <CardHeader>
                                <CardTitle>Cours</CardTitle>
                                <CardDescription>Performance base 100 sur la période</CardDescription>
                            </CardHeader>
                            <CardContent class="px-0 sm:px-6">
                                <VueApexCharts :key="valuationKey" type="line" height="300" :options="coursChartOptions" :series="coursChartSeries" />
                            </CardContent>
                        </Card>

                        <Card :class="flatCard">
                            <CardHeader>
                                <CardTitle>Valeur vs Investi</CardTitle>
                                <CardDescription>Performance base 100 sur la période</CardDescription>
                            </CardHeader>
                            <CardContent class="px-0 sm:px-6">
                                <VueApexCharts :key="valuationKey" type="line" height="300" :options="positionChartOptions" :series="positionChartSeries" />
                            </CardContent>
                        </Card>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-muted-foreground">
                        Pas encore d'historique de valorisation.
                    </p>
                </Deferred>
            </section>

            <Card v-else :class="flatCard">
                <CardHeader>
                    <CardTitle>Cours</CardTitle>
                    <CardDescription>Historique sur 12 mois</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="priceHistory">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasPriceHistory"
                            type="area"
                            height="300"
                            :options="priceChartOptions"
                            :series="priceChartSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas d'historique de prix disponible.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card :class="[flatCard, 'min-w-0 lg:col-span-2']">
                    <CardHeader>
                        <CardTitle>Transactions</CardTitle>
                        <CardDescription>Mes mouvements sur cet actif</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="props.instrument.transactions.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Sens</TableHead>
                                    <TableHead class="text-right">Quantité</TableHead>
                                    <TableHead class="text-right">Prix unit.</TableHead>
                                    <TableHead class="text-right">Frais</TableHead>
                                    <TableHead class="text-right">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(line, index) in props.instrument.transactions" :key="index">
                                    <TableCell>{{ line.date }}</TableCell>
                                    <TableCell :class="line.isSell ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ line.typeLabel }}
                                    </TableCell>
                                    <TableCell class="text-right">{{ line.quantity }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.unitPrice) }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.fees) }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.total) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Aucune transaction sur cet actif.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="props.instrument.sectors.length" :class="flatCard">
                    <CardHeader>
                        <CardTitle>Secteurs</CardTitle>
                        <CardDescription>Répartition sectorielle</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="flex flex-col gap-2">
                            <li v-for="(sector, index) in props.instrument.sectors" :key="index" class="flex justify-between text-sm">
                                <span>{{ sector.label }}</span>
                                <span class="text-muted-foreground">{{ (sector.weight * 100).toFixed(1) }} %</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
</template>
