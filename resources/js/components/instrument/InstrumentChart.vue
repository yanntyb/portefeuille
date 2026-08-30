<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { AsyncBaseChart } from '@/components/AsyncBaseChart';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import SegmentedControl, { type Segment } from '@/components/ui/SegmentedControl.vue';
import { axisGutter, buildPriceHistoryOption, buildValueVsInvestedOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import { chartHeight } from '@/lib/layout';
import { dividendMarks, type DividendMark, type DividendReceipt } from '@/lib/income';
import type { ChartOption } from '@/lib/echarts';
import type { PriceHistory, ValuationSeries } from '@/lib/instrument';

/** Les deux lectures d'un même titre : ce que vaut la position, et ce que cote la part. */
type Mode = 'valuation' | 'price';

const props = defineProps<{
    /** `null` tant que ni le réseau ni l'instantané n'ont livré la série. */
    valuation: ValuationSeries | null;
    priceHistory: PriceHistory | null;
    dividends: DividendReceipt[];
}>();

const mode = ref<Mode>('valuation');

/**
 * Volontairement non réactive : le zoom est déjà appliqué dans l'instance quand l'événement
 * arrive. La stocker sert à survivre à une reconstruction des options — dont celle qu'entraîne la
 * bascule d'une série à l'autre. Datée, elle garde son sens sur l'autre série, qui ne commence pas
 * le même jour. `null` tant que le lecteur n'a rien déplacé : le graphe choisit sa fenêtre.
 */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const hasPrices = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const segments = computed<Segment[]>(() => [
    { value: 'valuation', label: 'Valorisation' },
    { value: 'price', label: 'Cours', disabled: !hasPrices.value },
]);

/** La série du mode courant est-elle arrivée ? Le squelette n'attend que celle-là. */
const loaded = computed<boolean>(() =>
    (mode.value === 'valuation' ? props.valuation : props.priceHistory) !== null);

const deferKey = computed<string>(() => (mode.value === 'valuation' ? 'valuation' : 'priceHistory'));

const hasHistory = computed<boolean>(() => (mode.value === 'valuation'
    ? (props.valuation?.labels.length ?? 0) > 0
    : hasPrices.value));

/** Les repères se calent sur les points de la série : ils attendent donc que celle-ci arrive. */
const marks = computed<DividendMark[]>(
    () => dividendMarks(props.valuation?.labels ?? [], props.dividends),
);

const amountFormatter = (amount: number): string => eur(amount, 0);

/**
 * Une seule gouttière pour les deux séries : mesurée séparément, la valeur d'une position à cinq
 * chiffres et le cours d'une part à deux décimales n'ouvrent pas le cadre à la même abscisse, et
 * le tracé sauterait latéralement à chaque bascule.
 */
const gutter = computed<number>(() => axisGutter([
    { values: props.valuation?.valuations ?? [], valueFormatter: amountFormatter },
    { values: props.valuation?.invested ?? [], valueFormatter: amountFormatter },
    { values: props.priceHistory?.close ?? [], valueFormatter: eur },
]));

const valuationOption = computed<ChartOption>(() => buildValueVsInvestedOption({
    labels: props.valuation?.labels ?? [],
    value: props.valuation?.valuations ?? [],
    invested: props.valuation?.invested ?? [],
    valueFormatter: amountFormatter,
    window: lastZoom,
    description: 'Valeur de la position comparée au montant investi.',
    dividends: marks.value,
    gutter: gutter.value,
}));

const priceOption = computed<ChartOption>(() => buildPriceHistoryOption({
    labels: props.priceHistory?.labels ?? [],
    close: props.priceHistory?.close ?? [],
    valueFormatter: eur,
    window: lastZoom,
    gutter: gutter.value,
}));

const option = computed<ChartOption>(
    () => (mode.value === 'valuation' ? valuationOption.value : priceOption.value),
);

const emptyLabel = computed<string>(() => (mode.value === 'valuation'
    ? "Pas encore d'historique de valorisation."
    : "Pas d'historique de prix disponible."));
</script>

<template>
    <!--
        La section garde le nom de la valorisation, sur laquelle elle ouvre : c'est la lecture que
        la fiche d'une position raconte d'abord, le cours n'en étant qu'une autre vue.
    -->
    <section data-section="valuation" class="flex flex-col gap-4">
        <div class="px-6">
            <SegmentedControl
                v-model="mode"
                :segments="segments"
                label="Série tracée"
            />
        </div>

        <template v-if="loaded">
            <div v-if="hasHistory" class="px-6">
                <AsyncBaseChart :option="option" @zoom="rememberZoom" />
            </div>
            <!-- Le vide occupe la place du graphe : la bascule ne doit rien faire remonter. -->
            <p
                v-else
                data-chart-placeholder
                class="flex items-center justify-center px-6 text-center text-sm text-muted-foreground"
                :style="{ height: `${chartHeight}px` }"
            >
                {{ emptyLabel }}
            </p>
        </template>

        <Deferred v-else :data="deferKey">
            <template #fallback>
                <div class="px-6">
                    <ChartSkeleton />
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </section>
</template>
