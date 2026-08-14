<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ values: number[]; width?: number; height?: number }>(),
    { width: 64, height: 20 },
);

/** Leaves room for the stroke so the extremes are not clipped by the viewBox. */
const STROKE_PADDING = 1.5;

const points = computed<string>(() => {
    if (props.values.length < 2) {
        return '';
    }

    const lowest = Math.min(...props.values);
    const highest = Math.max(...props.values);
    const span = highest - lowest;
    const usableHeight = props.height - STROKE_PADDING * 2;

    return props.values
        .map((value, index) => {
            const x = (index / (props.values.length - 1)) * props.width;
            const y =
                span === 0
                    ? props.height / 2
                    : STROKE_PADDING + (1 - (value - lowest) / span) * usableHeight;

            return `${x.toFixed(2)},${y.toFixed(2)}`;
        })
        .join(' ');
});

/** A flat line reads as neutral rather than as a gain. */
const trendClass = computed<string>(() => {
    const first = props.values[0];
    const last = props.values[props.values.length - 1];

    if (first === last) {
        return 'text-muted-foreground';
    }

    return last > first ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <svg
        v-if="points"
        data-sparkline
        :class="trendClass"
        :width="width"
        :height="height"
        :viewBox="`0 0 ${width} ${height}`"
        fill="none"
        aria-hidden="true"
    >
        <polyline
            :points="points"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
    </svg>
</template>
