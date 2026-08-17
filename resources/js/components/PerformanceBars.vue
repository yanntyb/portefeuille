<script setup lang="ts">
import { computed } from 'vue';
import { gainClass, pct, signedEur } from '@/lib/format';
import { performanceBars, type Performance, type PerformanceBar } from '@/lib/performance';

const props = defineProps<{ performances: Performance[] }>();

const bars = computed<PerformanceBar[]>(() => performanceBars(props.performances));
</script>

<template>
    <!-- `grow` et non `flex-1` : sans hauteur libre à distribuer les barres gardent leur taille naturelle. -->
    <ul class="flex min-h-0 grow flex-col gap-3">
        <li
            v-for="bar in bars"
            :key="bar.performance.key"
            data-perf-row
            :title="bar.title"
            class="flex max-h-12 grow items-center gap-3 text-sm md:max-h-none md:grow-0"
        >
            <span data-perf-label class="w-14 shrink-0 font-semibold">{{ bar.performance.label }}</span>

            <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-separator">
                <span
                    data-perf-bar
                    class="block h-full rounded-full"
                    :class="bar.barColor"
                    :style="{ width: bar.barWidth }"
                ></span>
            </span>

            <span
                data-perf-gain
                class="w-24 shrink-0 text-right font-semibold tabular-nums"
                :class="gainClass(bar.performance.gain)"
            >
                {{ signedEur(bar.performance.gain, 0) }}
            </span>
            <span
                data-perf-pct
                class="w-20 shrink-0 text-right font-semibold tabular-nums"
                :class="gainClass(bar.performance.pct)"
            >
                {{ pct(bar.performance.pct) }}
            </span>
        </li>
    </ul>
</template>
