<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
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

const props = defineProps<{ overview: PortfolioOverview }>();

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
</script>

<template>
    <Head title="Tableau de bord" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header>
                <h1 class="text-2xl font-semibold">Tableau de bord</h1>
                <p class="text-sm text-muted-foreground">Suivi de vos investissements</p>
            </header>

            <section class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardDescription>Valeur totale</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(overview.totalValue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Gains / pertes</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ eur(overview.totalGain) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Rendement</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ pct(overview.totalGainPct) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
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
                                        <span v-if="line.lastPrice === null" class="text-muted-foreground">prix indisponible</span>
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

                <Card>
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
