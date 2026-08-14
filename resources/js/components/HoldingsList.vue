<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import Sparkline from '@/components/Sparkline.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import type { EvolutionSeries, HoldingLine } from '@/lib/portfolio';

const FAINTEST_BAR_OPACITY = 0.35;

const props = defineProps<{
    holdings: HoldingLine[];
    hiddenAssetIds: Set<number>;
    series?: EvolutionSeries;
}>();

defineEmits<{ toggle: [assetId: number] }>();

/** Sorting here makes the weight bars monotonic whatever order the caller passes. */
const sortedHoldings = computed<HoldingLine[]>(() =>
    [...props.holdings].sort((left, right) => (right.marketValue ?? 0) - (left.marketValue ?? 0)),
);

/** Summing the lines rather than reusing the overview total keeps the weights at 100 %. */
const totalValue = computed<number>(() =>
    sortedHoldings.value.reduce((total, line) => total + (line.marketValue ?? 0), 0),
);

const shareOf = (line: HoldingLine): number =>
    totalValue.value > 0 ? ((line.marketValue ?? 0) / totalValue.value) * 100 : 0;

/** Bars are scaled against the largest position, not the total, so the smallest stays visible. */
const largestShare = computed<number>(() =>
    Math.max(0, ...sortedHoldings.value.map((line) => shareOf(line))),
);

const barWidth = (line: HoldingLine): string =>
    largestShare.value > 0 ? `${(shareOf(line) / largestShare.value) * 100}%` : '0%';

/** A single monotonic fade over the whole list, so the faintest bar stays readable in both themes. */
const opacityAt = (index: number): number =>
    1 - (index / Math.max(1, sortedHoldings.value.length - 1)) * (1 - FAINTEST_BAR_OPACITY);

const seriesByAsset = computed<Map<number, number[]>>(
    () => new Map((props.series?.perAsset ?? []).map((asset) => [asset.assetId, asset.value])),
);

const valuesFor = (assetId: number): number[] => seriesByAsset.value.get(assetId) ?? [];

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const value = (amount: number | null): string => formatEur(amount, 0);

const isHidden = (assetId: number): boolean => props.hiddenAssetIds.has(assetId);
</script>

<template>
    <ul class="flex flex-col">
        <li
            v-for="(line, index) in sortedHoldings"
            :key="line.assetId"
            data-holding-row
            class="flex items-start gap-3 border-b border-border py-4 last:border-b-0"
            :class="isHidden(line.assetId) ? 'opacity-40' : ''"
        >
            <button
                type="button"
                class="mt-0.5 shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                :aria-label="isHidden(line.assetId) ? 'Afficher' : 'Masquer'"
                @click="$emit('toggle', line.assetId)"
            >
                <EyeOff v-if="isHidden(line.assetId)" class="size-4" />
                <Eye v-else class="size-4" />
            </button>

            <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <Link
                        :href="`/instruments/${line.assetId}`"
                        data-holding-name
                        class="min-w-0 font-medium break-words hover:underline"
                    >
                        {{ line.assetName }}
                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                    </Link>
                    <span data-holding-value class="shrink-0 font-medium tabular-nums">
                        {{ value(line.marketValue) }}
                    </span>
                </div>

                <div class="flex items-baseline justify-end gap-x-3 text-xs">
                    <span class="flex shrink-0 items-baseline gap-2 tabular-nums" :class="gainClass(line.gain)">
                        <span data-holding-gain>{{ signedEur(line.gain, 0) }}</span>
                        <span data-holding-gain-pct>{{ pct(line.gainPct) }}</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
                        <span
                            data-holding-bar
                            class="block h-full rounded-full bg-foreground"
                            :style="{ width: barWidth(line), opacity: opacityAt(index) }"
                        ></span>
                    </span>
                    <span
                        data-holding-weight
                        class="w-14 shrink-0 text-right text-xs tabular-nums text-muted-foreground"
                    >
                        {{ share(shareOf(line)) }}
                    </span>
                </div>
            </div>

            <div class="hidden w-16 shrink-0 sm:block">
                <Sparkline v-if="valuesFor(line.assetId).length > 1" :values="valuesFor(line.assetId)" />
                <span v-else-if="!series" class="block h-5 w-full animate-pulse rounded bg-muted"></span>
            </div>
        </li>
    </ul>
</template>
