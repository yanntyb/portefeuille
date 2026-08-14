<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { eur as formatEur } from '@/lib/format';
import type { SectorBreakdownRow } from '@/lib/sector';

const COLLAPSED_COUNT = 6;
const FAINTEST_BAR_OPACITY = 0.35;

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

const isExpanded = ref<boolean>(false);

/** Sorting here keeps the bar scale and the fade meaningful whatever order the caller passes. */
const sortedRows = computed<SectorBreakdownRow[]>(() =>
    [...props.rows].sort((left, right) => right.share - left.share),
);

const hiddenCount = computed<number>(() => Math.max(0, sortedRows.value.length - COLLAPSED_COUNT));

const visibleRows = computed<SectorBreakdownRow[]>(() =>
    isExpanded.value ? sortedRows.value : sortedRows.value.slice(0, COLLAPSED_COUNT),
);

/** Bars are scaled against the largest sector, not the total, so the smallest shares stay visible. */
const largestShare = computed<number>(() => Math.max(0, ...sortedRows.value.map((row) => row.share)));

const barWidth = (share: number): string =>
    largestShare.value > 0 ? `${(share / largestShare.value) * 100}%` : '0%';

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const amount = (value: number): string => formatEur(value, 0);

/** A single monotonic fade over the whole list, so the faintest bar stays readable in both themes. */
const opacityAt = (index: number): number =>
    1 - (index / Math.max(1, sortedRows.value.length - 1)) * (1 - FAINTEST_BAR_OPACITY);
</script>

<template>
    <div>
        <ul class="flex flex-col gap-4">
            <li v-for="(row, index) in visibleRows" :key="row.label" class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                    <span data-sector-label class="min-w-0 break-words font-medium">{{ row.label }}</span>
                    <span class="tabular-nums text-muted-foreground">
                        <template v-if="row.amount !== null">
                            <span data-sector-amount>{{ amount(row.amount) }}</span> ·
                        </template>
                        <span data-sector-share>{{ share(row.share) }}</span>
                    </span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        data-sector-bar
                        class="h-full rounded-full bg-foreground"
                        :style="{ width: barWidth(row.share), opacity: opacityAt(index) }"
                    ></div>
                </div>
            </li>
        </ul>

        <Button
            v-if="hiddenCount > 0"
            variant="ghost"
            size="sm"
            class="mt-3 w-full text-muted-foreground"
            @click="isExpanded = !isExpanded"
        >
            {{ isExpanded ? 'Réduire' : `Voir les ${hiddenCount} autres` }}
        </Button>
    </div>
</template>
