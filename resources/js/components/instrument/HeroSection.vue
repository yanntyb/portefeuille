<script setup lang="ts">
import { computed } from 'vue';
import GainPill from '@/components/GainPill.vue';
import { eur, frDate, pct, signedEur } from '@/lib/format';
import { investedOf, type Instrument } from '@/lib/instrument';

const props = defineProps<{
    instrument: Instrument;
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
</script>

<template>
    <header data-section="hero" class="flex flex-col gap-4 px-6">
        <div class="flex flex-col gap-0.5">
            <h1 class="text-xl font-bold">
                {{ instrument.name }}
                <span v-if="instrument.ticker" class="text-muted-foreground">({{ instrument.ticker }})</span>
            </h1>
            <p class="text-[13px] font-medium text-subtle-foreground">
                {{ instrument.typeLabel }}
                <span v-if="instrument.isin"> · {{ instrument.isin }}</span>
            </p>
        </div>

        <div class="flex min-w-0 flex-col gap-2">
            <div class="flex flex-wrap items-baseline gap-3">
                <p data-hero-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">{{ eur(heroValue) }}</p>

                <GainPill
                    v-if="position && position.gain !== null"
                    data-hero-gain
                    :value="position.gain"
                    :label="`${signedEur(position.gain)} (${pct(position.gainPct)})`"
                />
            </div>

            <p data-hero-meta class="text-[13.5px] text-muted-foreground tabular-nums">
                <template v-if="position">
                    {{ quantityLabel }} titres · PRU {{ eur(position.avgCost) }} · investi
                    {{ eur(invested) }} · cours {{ eur(instrument.lastPrice) }}
                </template>
                <template v-else-if="instrument.lastPriceDate">
                    au {{ frDate(instrument.lastPriceDate) }}
                </template>
            </p>
        </div>
    </header>
</template>
