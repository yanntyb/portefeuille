<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import InvestedGainMeta from '@/components/InvestedGainMeta.vue';
import { eur, pct } from '@/lib/format';

/**
 * Le grand chiffre d'une page et ce qui l'explique : valeur, pastille de gain, investi et gain
 * détaillés. Le tableau de bord et les pages d'exposition en avaient chacun une copie à 95 %.
 * La pastille se cache sans pourcentage : un gain sans mise à laquelle le rapporter n'a pas de
 * pourcentage, et « 0 % » mentirait.
 */
const props = defineProps<{
    /** Préfixe des attributs `data-*` que les tests navigateur lisent : `portfolio` sur une exposition, `wealth` au tableau de bord. */
    prefix: 'portfolio' | 'wealth';
    section: string;
    totalValue: number;
    totalGain: number;
    totalGainPct: number | null;
    invested: number;
    realizedGain: number;
    /** Le cash d'origine d'une exposition ; sans objet au tableau de bord. */
    originCash?: number;
}>();
</script>

<template>
    <section :data-section="props.section" class="flex shrink-0 flex-col gap-1.5 px-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <p v-bind="{ [`data-${props.prefix}-value`]: '' }" class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(props.totalValue, 0) }}
            </p>
            <GainPill
                v-if="props.totalGainPct !== null"
                v-bind="{ [`data-${props.prefix}-gain-pct`]: '' }"
                :value="props.totalGain"
                :label="pct(props.totalGainPct)"
            />
        </div>

        <InvestedGainMeta
            v-bind="{ [`data-${props.prefix}-meta`]: '' }"
            :invested="props.invested"
            :gain="props.totalGain"
            :realized-gain="props.realizedGain"
            :origin-cash="props.originCash"
            :digits="0"
        />
    </section>
</template>
