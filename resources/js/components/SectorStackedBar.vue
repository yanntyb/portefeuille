<script setup lang="ts">
import { computed } from 'vue';
import { eur as formatEur } from '@/lib/format';
import type { SectorBreakdownRow } from '@/lib/sector';

const FAINTEST_SEGMENT_OPACITY = 0.35;

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

/** Sorting keeps the segment order and the fade meaningful whatever order the caller passes. */
const sortedRows = computed<SectorBreakdownRow[]>(() =>
    [...props.rows].sort((left, right) => right.share - left.share),
);

/** Segments split one whole bar, so their widths are shares of the total, not of the largest. */
const total = computed<number>(() =>
    sortedRows.value.reduce((sum, row) => sum + row.share, 0),
);

const segmentWidth = (share: number): string =>
    total.value > 0 ? `${(share / total.value) * 100}%` : '0%';

/** A single monotonic fade over the whole bar, so the faintest segment stays readable in both themes. */
const opacityAt = (index: number): number =>
    1 - (index / Math.max(1, sortedRows.value.length - 1)) * (1 - FAINTEST_SEGMENT_OPACITY);

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const amount = (value: number): string => formatEur(value, 0);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex h-3 w-full overflow-hidden rounded-full bg-muted">
            <span
                v-for="(row, index) in sortedRows"
                :key="row.label"
                data-sector-bar
                class="block h-full bg-foreground"
                :style="{ width: segmentWidth(row.share), opacity: opacityAt(index) }"
            ></span>
        </div>

        <ul class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
            <li v-for="(row, index) in sortedRows" :key="row.label" class="flex items-center gap-1.5">
                <span
                    class="size-2 shrink-0 rounded-full bg-foreground"
                    :style="{ opacity: opacityAt(index) }"
                ></span>
                <span data-sector-label class="font-medium">{{ row.label }}</span>
                <span class="tabular-nums text-muted-foreground">
                    <template v-if="row.amount !== null">
                        <span data-sector-amount>{{ amount(row.amount) }}</span> ·
                    </template>
                    <span data-sector-share>{{ share(row.share) }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
