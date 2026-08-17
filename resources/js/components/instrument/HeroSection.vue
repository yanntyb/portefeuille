<script setup lang="ts">
import { computed } from 'vue';
import GainPill from '@/components/GainPill.vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import { heroMeta, heroValueOf, type HeroMetaEntry, type Instrument } from '@/lib/instrument';

const props = defineProps<{
    instrument: Instrument;
}>();

const position = computed(() => props.instrument.position);

const heroValue = computed<number | null>(() => heroValueOf(props.instrument));

const metaEntries = computed<HeroMetaEntry[]>(() => heroMeta(props.instrument));
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

            <!-- Grille plutôt que flux : les libellés s'alignent en colonne, les valeurs sur leur bord droit. -->
            <p data-hero-meta class="grid grid-cols-2 gap-x-8 gap-y-1.5 pt-1.5 text-[13.5px] text-muted-foreground">
                <span
                    v-for="entry in metaEntries"
                    :key="entry.label || entry.value"
                    class="flex items-baseline justify-between gap-3 whitespace-nowrap"
                >
                    <template v-if="entry.label">
                        {{ entry.label }}
                        <strong class="font-semibold text-foreground tabular-nums">{{ entry.value }}</strong>
                    </template>
                    <template v-else>{{ entry.value }}</template>
                </span>
            </p>
        </div>
    </header>
</template>
