<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import type { PortfolioOverview } from '@/lib/portfolio';

defineProps<{ overview: PortfolioOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);

const signedRoundedEur = (value: number | null): string => signedEur(value, 0);
</script>

<template>
    <section data-section="valuation" class="flex flex-col gap-1.5 px-6">
        <p class="text-[13px] font-medium text-subtle-foreground">Valeur du portefeuille</p>

        <div class="flex flex-wrap items-baseline gap-3">
            <p data-portfolio-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(overview.totalValue) }}
            </p>
            <GainPill :value="overview.totalGain" :label="pct(overview.totalGainPct)" />
        </div>

        <p data-portfolio-meta class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
            <span class="whitespace-nowrap">
                Investi <strong class="font-semibold text-foreground tabular-nums">{{ eur(overview.totalCost) }}</strong>
            </span>
            <span class="whitespace-nowrap">
                Gain
                <strong class="font-semibold tabular-nums" :class="gainClass(overview.totalGain)">
                    {{ signedRoundedEur(overview.totalGain) }}
                </strong>
            </span>
        </p>
    </section>
</template>
