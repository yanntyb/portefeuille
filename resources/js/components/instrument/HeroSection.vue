<script setup lang="ts">
import { computed } from 'vue';
import HeroFigures from '@/components/HeroFigures.vue';
import InvestedGainMeta from '@/components/InvestedGainMeta.vue';
import { pct } from '@/lib/format';
import { heroValueOf, investedOf, type Instrument } from '@/lib/instrument';

const props = defineProps<{
    instrument: Instrument;
}>();

const position = computed(() => props.instrument.position);

const heroValue = computed<number | null>(() => heroValueOf(props.instrument));

/** Pas de position, pas de gain : un titre seulement suivi n'a rien à comparer. */
const gainLabel = computed<string | null>(() =>
    position.value === null || position.value.gainPct === null ? null : pct(position.value.gainPct),
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

        <!-- Les repères chiffrés sortent d'ici : le graphe s'intercale entre eux et la valeur. -->
        <HeroFigures :value="heroValue" :gain="position?.gain ?? null" :gain-label="gainLabel">
            <!-- Investi et gain collent au grand chiffre, comme sur le tableau de bord et les listings. -->
            <template v-if="position" #beneath-value>
                <InvestedGainMeta
                    data-hero-summary
                    :invested="investedOf(position)"
                    :gain="position.gain"
                    :realized-gain="position.realizedGain"
                />
            </template>
        </HeroFigures>
    </header>
</template>
