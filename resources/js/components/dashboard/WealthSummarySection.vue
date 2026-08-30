<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import type { WealthOverview } from '@/lib/wealth';

const props = defineProps<{ overview: WealthOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);
</script>

<template>
    <section data-section="wealth-summary" class="flex shrink-0 flex-col gap-1.5 px-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <p data-wealth-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(props.overview.totalValue) }}
            </p>
            <GainPill
                v-if="props.overview.totalGainPct !== null"
                data-wealth-gain-pct
                :value="props.overview.totalGain"
                :label="pct(props.overview.totalGainPct)"
            />
        </div>

        <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
            <span class="whitespace-nowrap">
                Investi
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.overview.totalInvested) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Gain
                <strong class="font-semibold tabular-nums" :class="gainClass(props.overview.totalGain)">
                    {{ signedEur(props.overview.totalGain, 0) }}
                </strong>
            </span>
        </p>
    </section>
</template>
