<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Head } from '@inertiajs/vue3';
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

interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
}

const props = defineProps<{ overview: PortfolioOverview; valuationSeries?: ValuationSeries }>();

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
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        categories: props.valuationSeries?.labels ?? [],
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
    <Head title="Tableau de bord" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header>
                <h1 class="text-2xl font-semibold">Tableau de bord</h1>
                <p class="text-sm text-muted-foreground">Suivi de vos investissements</p>
            </header>

            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Évolution</CardTitle>
                    <CardDescription>Valeur du portefeuille vs investi</CardDescription>
                </CardHeader>
                <CardContent>
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

            <section class="grid gap-4 sm:grid-cols-3">
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Valeur totale</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(overview.totalValue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Gains / pertes</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ eur(overview.totalGain) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Rendement</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ pct(overview.totalGainPct) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card :class="[flatCard, 'lg:col-span-2']">
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
                                        {{ line.assetName }}
                                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
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
