<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import InvestedGainMeta from '@/components/InvestedGainMeta.vue';
import { eur as formatEur, pct } from '@/lib/format';
import type { PortfolioOverview } from '@/lib/portfolio';

defineProps<{ overview: PortfolioOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);
</script>

<template>
    <section data-section="valuation" class="flex shrink-0 flex-col gap-1.5 px-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <p data-portfolio-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(overview.totalValue) }}
            </p>
            <GainPill data-portfolio-gain-pct :value="overview.totalGain" :label="pct(overview.totalGainPct)" />
        </div>

        <InvestedGainMeta
            data-portfolio-meta
            :invested="overview.totalCost"
            :gain="overview.totalGain"
            :realized-gain="overview.totalRealizedGain"
            :digits="0"
        />
    </section>
</template>
