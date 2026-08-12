<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import PerformanceTable from '@/components/PerformanceTable.vue';
import { buildEvolutionChart } from '@/lib/chart';
import { eur as formatEur, gainClass, pct } from '@/lib/format';
import type { Performance } from '@/lib/performance';
import { Eye, EyeOff } from 'lucide-vue-next';

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

interface PortfolioOverview {
    totalValue: number;
    totalCost: number;
    totalGain: number;
    totalGainPct: number;
    holdings: HoldingLine[];
}

interface EvolutionSeries {
    labels: string[];
    perAsset: { assetId: number; name: string; value: number[]; invested: number[] }[];
}

const props = defineProps<{ overview: PortfolioOverview; performances?: Performance[]; evolutionSeries?: EvolutionSeries; valuationRange?: string; valuationGranularity?: string }>();

type RangeKey = '1M' | '6M' | '1Y' | 'max';
type GranularityKey = 'day' | 'week' | 'month';

const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

const isRangeKey = (value: string | undefined): value is RangeKey =>
    rangeOptions.some((option) => option.key === value);

const selectedRange = ref<RangeKey>(isRangeKey(props.valuationRange) ? props.valuationRange : 'max');
const granularity: GranularityKey = 'day';
const reloading = ref<boolean>(false);

const hiddenAssetIds = ref<Set<number>>(new Set());

const toggleAsset = (assetId: number): void => {
    const next = new Set(hiddenAssetIds.value);
    if (next.has(assetId)) {
        next.delete(assetId);
    } else {
        next.add(assetId);
    }
    hiddenAssetIds.value = next;
};

const reloadSeries = (): void => {
    router.reload({
        only: ['evolutionSeries'],
        data: { range: selectedRange.value, granularity },
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

const flatCard = 'border-0 bg-transparent shadow-none rounded-none';

const eur = (value: number | null): string => formatEur(value, 0);

const hasEvolution = computed<boolean>(() => (props.evolutionSeries?.labels.length ?? 0) > 0);

const evolutionChart = computed(() =>
    buildEvolutionChart({
        labels: props.evolutionSeries?.labels ?? [],
        perAsset: props.evolutionSeries?.perAsset ?? [],
        hiddenIds: hiddenAssetIds.value,
        valueFormatter: eur,
    }),
);

const evolutionKey = computed<string>(() => {
    const labels = props.evolutionSeries?.labels ?? [];
    const hidden = Array.from(hiddenAssetIds.value)
        .sort((a, b) => a - b)
        .join('.');

    return `${selectedRange.value}-${granularity}-${labels.length}-${labels[0] ?? ''}-${labels[labels.length - 1] ?? ''}-${hidden}`;
});
</script>

<template>
    <Head title="Tableau de bord" />

    <AppBreadcrumb :items="[{ label: 'Tableau de bord' }]" />

    <main class="min-h-screen overflow-x-hidden bg-background text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <Card v-if="overview.holdings.length" data-section="valuation" :class="flatCard">
                <CardContent class="flex flex-col gap-0.5">
                    <p class="text-sm text-muted-foreground">Valorisation</p>
                    <p class="text-2xl font-semibold">
                        {{ eur(overview.totalValue) }}
                        <span class="ml-2 text-sm" :class="gainClass(overview.totalGain)">({{ pct(overview.totalGainPct) }})</span>
                    </p>
                </CardContent>
            </Card>

            <Card data-section="evolution" :class="[flatCard, 'transition-opacity sm:mx-0', reloading ? 'opacity-50' : '']">
                <CardHeader>
                    <CardDescription>Valeur de marché par titre</CardDescription>
                    <div v-if="overview.holdings.length" class="flex flex-wrap items-center gap-3 pt-2">
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
                    </div>
                </CardHeader>
                <CardContent class="px-0 sm:px-6">
                    <Deferred data="evolutionSeries">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasEvolution"
                            :key="evolutionKey"
                            type="area"
                            height="300"
                            :options="evolutionChart.options"
                            :series="evolutionChart.series"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'historique de valorisation.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>

            <Card v-if="overview.holdings.length" data-section="performances" :class="flatCard">
                <CardContent>
                    <Deferred data="performances">
                        <template #fallback>
                            <div class="flex flex-col gap-2">
                                <div v-for="n in 5" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                            </div>
                        </template>

                        <div v-if="performances && performances.length" class="flex flex-col gap-1">
                            <div class="flex items-center gap-1">
                                <p class="text-sm text-muted-foreground">Performance par période</p>
                                <PerformanceInfoDialog variant="periods" />
                            </div>
                            <PerformanceTable :performances="performances" :currency-digits="0" />
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <Card data-section="holdings" :class="flatCard">
                <CardContent>
                    <Table v-if="overview.holdings.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-10"></TableHead>
                                <TableHead>Actif</TableHead>
                                <TableHead class="text-right">Valeur</TableHead>
                                <TableHead class="text-right">+/-</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Quantité</TableHead>
                                <TableHead class="text-right">Dernier prix</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(line, index) in overview.holdings" :key="index">
                                <TableCell class="w-10">
                                    <button
                                        type="button"
                                        class="text-muted-foreground transition-colors hover:text-foreground"
                                        :aria-label="hiddenAssetIds.has(line.assetId) ? 'Afficher' : 'Masquer'"
                                        @click="toggleAsset(line.assetId)"
                                    >
                                        <EyeOff v-if="hiddenAssetIds.has(line.assetId)" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </TableCell>
                                <TableCell class="font-medium" :class="hiddenAssetIds.has(line.assetId) ? 'opacity-40' : ''">
                                    <Link :href="`/instruments/${line.assetId}`" class="hover:underline">
                                        {{ line.assetName }}
                                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                    </Link>
                                </TableCell>
                                <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                                <TableCell class="text-right" :class="gainClass(line.gain)">{{ pct(line.gainPct) }}</TableCell>
                                <TableCell>{{ line.typeLabel }}</TableCell>
                                <TableCell class="text-right">{{ line.quantity }}</TableCell>
                                <TableCell class="text-right">
                                    <span v-if="line.lastPrice === null" class="text-muted-foreground" title="Prix indisponible">N/D</span>
                                    <span v-else>{{ eur(line.lastPrice) }}</span>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="py-8 text-center text-sm text-muted-foreground">
                        Aucune position pour le moment.
                    </p>
                </CardContent>
            </Card>
        </div>
    </main>
</template>
