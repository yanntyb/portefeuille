<script setup lang="ts">
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

const valueChartOptions: ApexOptions = {
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: true } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    colors: ['#4f46e5'],
    fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0 } },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        categories: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: { labels: { formatter: (v: number): string => `${Math.round(v)} €` } },
    tooltip: { y: { formatter: (v: number): string => `${v.toLocaleString('fr-FR')} €` } },
};

const valueSeries = [
    { name: 'Valeur', data: [10000, 10800, 10400, 11500, 12300, 13100] },
];

const allocationOptions: ApexOptions = {
    chart: { fontFamily: 'inherit' },
    labels: ['Actions', 'Obligations', 'Crypto', 'Liquidités'],
    colors: ['#4f46e5', '#0ea5e9', '#f59e0b', '#10b981'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val: number): string => `${Math.round(Number(val))}%` },
    stroke: { width: 0 },
};

const allocationSeries = [45, 25, 18, 12];
</script>

<template>
    <Head title="Tableau de bord" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Tableau de bord</h1>
                    <p class="text-sm text-muted-foreground">Suivi de vos investissements</p>
                </div>
                <span class="rounded-full bg-muted px-3 py-1 text-xs text-muted-foreground">
                    Données de démonstration
                </span>
            </header>

            <section class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardDescription>Valeur totale</CardDescription>
                        <CardTitle class="text-2xl">13 100 €</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Gains / pertes</CardDescription>
                        <CardTitle class="text-2xl text-emerald-600 dark:text-emerald-400">+3 100 €</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Rendement</CardDescription>
                        <CardTitle class="text-2xl text-emerald-600 dark:text-emerald-400">+31 %</CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Valeur du portefeuille</CardTitle>
                        <CardDescription>6 derniers mois</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts
                            type="area"
                            height="300"
                            :options="valueChartOptions"
                            :series="valueSeries"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Répartition</CardTitle>
                        <CardDescription>Par classe d'actif</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts
                            type="donut"
                            height="300"
                            :options="allocationOptions"
                            :series="allocationSeries"
                        />
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
</template>
