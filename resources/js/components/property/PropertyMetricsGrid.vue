<script setup lang="ts">
import { eur, fractionPct as pct } from '@/lib/format';
import type { LoanSummary, PropertyMetrics } from '@/lib/realEstate';

const props = defineProps<{ metrics: PropertyMetrics; loan: LoanSummary | null }>();
</script>

<template>
    <section data-section="metrics" class="grid grid-cols-2 gap-4 px-6 md:grid-cols-5">
        <div v-if="props.metrics.grossYield !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Rendement brut</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.grossYield) }}</span>
        </div>
        <div v-if="props.metrics.netYield !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Rendement net</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.netYield) }}</span>
        </div>
        <div class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Cash-flow annuel</span>
            <span class="font-semibold tabular-nums">{{ eur(props.metrics.annualCashFlow) }}</span>
        </div>
        <div v-if="props.metrics.cashOnCash !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Cash-on-cash</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.cashOnCash) }}</span>
        </div>
        <div v-if="props.metrics.ltv !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">LTV</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.ltv) }}</span>
        </div>
    </section>
</template>
