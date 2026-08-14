<script setup lang="ts">
import { computed } from 'vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import type { Performance } from '@/lib/performance';

const props = defineProps<{ performances: Performance[] }>();

/** Bars are scaled against the strongest move so the smallest period stays visible. */
const largestMove = computed<number>(() =>
    Math.max(0, ...props.performances.map((performance) => Math.abs(performance.pct))),
);

const barWidth = (value: number): string =>
    largestMove.value > 0 ? `${(Math.abs(value) / largestMove.value) * 100}%` : '0%';

const barColor = (value: number): string =>
    value < 0 ? 'bg-red-500 dark:bg-red-400' : 'bg-emerald-500 dark:bg-emerald-400';

/** The columns the table used to spend width on stay reachable, one hover away. */
const rowTitle = (performance: Performance): string =>
    `Valeur début ${eur(performance.valueStart)} · Apports ${signedEur(performance.contributions)}`;
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li
            v-for="performance in props.performances"
            :key="performance.key"
            data-perf-row
            :title="rowTitle(performance)"
            class="flex items-center gap-3 text-sm"
        >
            <span data-perf-label class="w-14 shrink-0 font-medium">{{ performance.label }}</span>

            <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                <span
                    data-perf-bar
                    class="block h-full rounded-full"
                    :class="barColor(performance.pct)"
                    :style="{ width: barWidth(performance.pct) }"
                ></span>
            </span>

            <span
                data-perf-gain
                class="w-24 shrink-0 text-right tabular-nums"
                :class="gainClass(performance.gain)"
            >
                {{ signedEur(performance.gain, 0) }}
            </span>
            <span
                data-perf-pct
                class="w-20 shrink-0 text-right tabular-nums"
                :class="gainClass(performance.pct)"
            >
                {{ pct(performance.pct) }}
            </span>
        </li>
    </ul>
</template>
