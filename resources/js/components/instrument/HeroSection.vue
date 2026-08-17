<script setup lang="ts">
import { computed } from 'vue';
import GainPill from '@/components/GainPill.vue';
import { eur, frDate, gainClass, pct, signedEur } from '@/lib/format';
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

/** Paires libellé/valeur du pied de l'en-tête : le libellé s'efface, la valeur porte la lecture. */
const metaEntries = computed<{ label: string; value: string }[]>(() => {
    if (position.value === null) {
        return [];
    }

    return [
        { label: 'Titres', value: quantityLabel.value },
        { label: 'PRU', value: eur(position.value.avgCost) },
        { label: 'Investi', value: eur(invested.value) },
        { label: 'Cours', value: eur(props.instrument.lastPrice) },
    ];
});
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

        <div class="flex min-w-0 flex-col gap-1.5">
            <div class="flex flex-wrap items-baseline gap-3">
                <p data-hero-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">{{ eur(heroValue) }}</p>

                <GainPill
                    v-if="position && position.gainPct !== null"
                    data-hero-gain-pct
                    :value="position.gain"
                    :label="pct(position.gainPct)"
                />
            </div>

            <p
                v-if="position && position.gain !== null"
                data-hero-gain
                class="text-[15px] font-semibold tabular-nums"
                :class="gainClass(position.gain)"
            >
                {{ signedEur(position.gain) }}
            </p>

            <p data-hero-meta class="flex flex-wrap gap-x-8 gap-y-1 pt-1.5 text-[13.5px] text-muted-foreground">
                <span v-for="entry in metaEntries" :key="entry.label" class="whitespace-nowrap">
                    {{ entry.label }}
                    <strong class="font-semibold text-foreground tabular-nums">{{ entry.value }}</strong>
                </span>
                <span v-if="position === null && instrument.lastPriceDate">
                    au {{ frDate(instrument.lastPriceDate) }}
                </span>
            </p>
        </div>
    </header>
</template>
