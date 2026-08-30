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

/**
 * Un titre vif n'a qu'un secteur, à 100 % : une section de ventilation n'y répartirait rien. Il se
 * lit alors comme une étiquette de l'en-tête, au même rang que le type et l'ISIN. Les expositions
 * qui en traversent plusieurs gardent leur section — c'est là qu'il y a des parts à comparer.
 */
const soleSector = computed<string | null>(() =>
    props.instrument.sectors.length === 1 ? props.instrument.sectors[0].label : null,
);

/** Une seule écriture d'étiquette : type et secteur sont du même rang, ils se peignent pareil. */
const pill = 'rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground';

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
            <!-- Sous le nom, l'identifiant seul : c'est le seul texte qui désigne le titre plutôt que le classe. -->
            <p v-if="instrument.isin" data-hero-isin class="text-[13px] font-medium text-subtle-foreground">
                {{ instrument.isin }}
            </p>

            <!--
                Type et secteur sont deux rangements, pas deux détails du titre : ils se lisent en
                étiquettes, côte à côte. `flex-wrap` parce qu'un libellé de secteur long tiendrait
                mal sur la même ligne qu'un type, sur les écrans étroits.
            -->
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                <span :class="pill" data-hero-type>{{ instrument.typeLabel }}</span>
                <span v-if="soleSector" :class="pill" data-hero-sector>{{ soleSector }}</span>
            </div>
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
