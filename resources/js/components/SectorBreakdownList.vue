<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { eur as formatEur } from '@/lib/format';
import type { SectorBreakdownRow } from '@/lib/sector';

const COLLAPSED_COUNT = 6;

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

const isExpanded = ref<boolean>(false);

/** Sorting here keeps the bar scale meaningful whatever order the caller passes. */
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
</script>

<template>
    <div>
        <ul class="flex flex-col gap-4">
            <li v-for="row in visibleRows" :key="row.label" class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                    <span data-sector-label class="min-w-0 break-words font-semibold">{{ row.label }}</span>
                    <span class="text-[13.5px] tabular-nums text-subtle-foreground">
                        <template v-if="row.amount !== null">
                            <span data-sector-amount>{{ amount(row.amount) }}</span> ·
                        </template>
                        <span data-sector-share>{{ share(row.share) }}</span>
                    </span>
                </div>
                <div class="h-[7px] w-full overflow-hidden rounded-full bg-separator">
                    <div
                        data-sector-bar
                        class="h-full rounded-full bg-sector-bar"
                        :style="{ width: barWidth(row.share) }"
                    ></div>
                </div>
            </li>
        </ul>

        <div v-if="hiddenCount > 0" class="flex justify-center pt-5">
            <Button variant="outline" size="sm" class="text-[13.5px] text-muted-foreground" @click="isExpanded = !isExpanded">
                {{ isExpanded ? 'Réduire' : `Voir les ${hiddenCount} autres` }}
            </Button>
        </div>
    </div>
</template>
