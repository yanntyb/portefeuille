<script setup lang="ts">
import { pct } from '@/lib/format';
import type { Concentration } from '@/lib/analysis';

const props = defineProps<{ concentration: Concentration | null }>();

/** Le HHI n'est pas un pourcentage : 1 pour une position unique, 1/n pour n positions égales. */
const hhi = (value: number | null): string => (value === null ? '—' : value.toFixed(2));
</script>

<template>
    <section data-section="concentration" class="flex shrink-0 flex-col gap-3 px-6">
        <header class="flex flex-col gap-0.5">
            <h2 class="text-sm font-semibold">Concentration des positions</h2>
            <p class="text-[13px] text-muted-foreground">
                Un titre détenu dans plusieurs enveloppes compte pour une seule position.
            </p>
        </header>

        <div v-if="props.concentration === null" class="h-16 animate-pulse rounded-lg bg-muted" />

        <dl v-else class="grid grid-cols-4 gap-3">
            <div v-for="entry in [
                    { label: 'Top 1', value: pct(props.concentration.top1) },
                    { label: 'Top 3', value: pct(props.concentration.top3) },
                    { label: 'Top 5', value: pct(props.concentration.top5) },
                    { label: 'HHI', value: hhi(props.concentration.hhi) },
                ]" :key="entry.label" class="flex flex-col gap-0.5">
                <dt class="text-[13px] text-muted-foreground">{{ entry.label }}</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ entry.value }}</dd>
            </div>
        </dl>
    </section>
</template>
