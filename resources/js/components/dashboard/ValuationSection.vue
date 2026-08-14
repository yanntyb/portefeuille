<script setup lang="ts">
import { eur as formatEur, gainClass, pct } from '@/lib/format';
import type { PortfolioOverview } from '@/lib/portfolio';

defineProps<{ overview: PortfolioOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);
</script>

<template>
    <section data-section="valuation" class="flex flex-col gap-1.5 px-6">
        <p class="text-2xl font-semibold">
            {{ eur(overview.totalValue) }}
            <span class="ml-2 text-sm" :class="gainClass(overview.totalGain)">({{ pct(overview.totalGainPct) }})</span>
        </p>
    </section>
</template>
