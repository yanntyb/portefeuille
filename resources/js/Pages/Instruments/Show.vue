<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
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
}

const props = defineProps<{ instrument: Instrument; priceHistory?: PriceHistory; valuation?: ValuationSeries }>();

const flatCard = 'border-0 bg-transparent shadow-none rounded-none';

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });

const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

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
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
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

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const valuationChartSeries = computed(() => [
    { name: 'Valeur', data: props.valuation?.valuations ?? [] },
    { name: 'Investi', data: props.valuation?.invested ?? [] },
]);

const valuationChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'top' },
}));
</script>

<template>
    <Head :title="props.instrument.name" />

    <main class="min-h-screen bg-background p-6 text-foreground">
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

            <Card :class="flatCard">
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

            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Valeur vs Investi</CardTitle>
                    <CardDescription>Évolution de ma position sur ce titre</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="valuation">
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

            <section class="grid gap-4 lg:grid-cols-3">
                <Card :class="[flatCard, 'lg:col-span-2']">
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
