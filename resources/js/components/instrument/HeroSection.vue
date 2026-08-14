<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import { buildSparklineOptions } from '@/lib/chart';
import { eur, frDate, gainClass, pct, signedEur } from '@/lib/format';
import { investedOf, type Instrument, type PriceHistory, type ValuationSeries } from '@/lib/instrument';

const props = defineProps<{
    instrument: Instrument;
    valuation?: ValuationSeries;
    priceHistory?: PriceHistory;
}>();

const position = computed(() => props.instrument.position);

/** A held instrument is worth its market value; an unheld one is only worth its last quote. */
const heroValue = computed<number | null>(
    () => position.value?.marketValue ?? props.instrument.lastPrice,
);

const invested = computed<number | null>(() =>
    position.value === null ? null : investedOf(position.value),
);

const quantityLabel = computed<string>(() =>
    position.value === null ? '' : position.value.quantity.toLocaleString('fr-FR'),
);

const sparklineSerie = computed<number[]>(() =>
    position.value === null ? (props.priceHistory?.close ?? []) : (props.valuation?.valuations ?? []),
);

const sparklineSeries = computed(() => [{ name: 'Valeur', data: sparklineSerie.value }]);

const sparklineOptions = computed<ApexOptions>(() => buildSparklineOptions(sparklineSerie.value));
</script>

<template>
    <header data-section="hero" class="flex flex-col gap-4 px-6">
        <div class="flex flex-col gap-0.5">
            <h1 class="text-xl font-semibold">
                {{ instrument.name }}
                <span v-if="instrument.ticker" class="text-muted-foreground">({{ instrument.ticker }})</span>
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ instrument.typeLabel }}
                <span v-if="instrument.isin"> · {{ instrument.isin }}</span>
            </p>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex min-w-0 flex-col gap-1">
                <p data-hero-value class="text-4xl font-semibold tabular-nums">{{ eur(heroValue) }}</p>

                <p
                    v-if="position && position.gain !== null"
                    data-hero-gain
                    class="text-base font-medium tabular-nums"
                    :class="gainClass(position.gain)"
                >
                    {{ signedEur(position.gain) }} <span>({{ pct(position.gainPct) }})</span>
                </p>

                <p data-hero-meta class="text-sm text-muted-foreground tabular-nums">
                    <template v-if="position">
                        {{ quantityLabel }} titres · PRU {{ eur(position.avgCost) }} · investi
                        {{ eur(invested) }} · cours {{ eur(instrument.lastPrice) }}
                    </template>
                    <template v-else-if="instrument.lastPriceDate">
                        au {{ frDate(instrument.lastPriceDate) }}
                    </template>
                </p>
            </div>

            <div data-hero-spark class="h-16 w-full min-w-0 sm:w-64">
                <Deferred :data="position ? 'valuation' : 'priceHistory'">
                    <template #fallback>
                        <div class="h-full w-full animate-pulse rounded-md bg-muted"></div>
                    </template>

                    <VueApexCharts
                        v-if="sparklineSerie.length > 1"
                        type="area"
                        height="64"
                        :options="sparklineOptions"
                        :series="sparklineSeries"
                    />
                </Deferred>
            </div>
        </div>
    </header>
</template>
