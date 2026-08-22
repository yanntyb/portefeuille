<script setup lang="ts">
import { computed } from 'vue';
import GainPill from '@/components/GainPill.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import { realEstateGainOf, realEstateGainPctOf, type RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview }>();

const eur = (value: number): string => formatEur(value, 0);

const gain = computed<number>(() => realEstateGainOf(props.realEstate));

/** Nulle quand rien n'est sorti de la poche : il n'y a alors pas de rapport à afficher. */
const gainPct = computed<number | null>(() => realEstateGainPctOf(props.realEstate));
</script>

<template>
    <section data-section="real-estate-summary" class="flex shrink-0 flex-col gap-1.5 px-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <p data-real-estate-net class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(props.realEstate.totalNetWorth) }}
            </p>

            <GainPill v-if="gainPct !== null" data-real-estate-gain-pct :value="gain" :label="pct(gainPct)" />
        </div>

        <p data-real-estate-meta class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
            <span class="whitespace-nowrap">
                Investi
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalInvested) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Gain
                <strong class="font-semibold tabular-nums" :class="gainClass(gain)">
                    {{ signedEur(gain, 0) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Estimé
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalValue) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Restant dû
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalRemaining) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Cash-flow/mois
                <strong class="font-semibold tabular-nums" :class="gainClass(props.realEstate.totalMonthlyCashFlow)">
                    {{ signedEur(props.realEstate.totalMonthlyCashFlow, 0) }}
                </strong>
            </span>
        </p>
    </section>
</template>
