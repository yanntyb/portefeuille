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
    limit?: number;
}>();

defineEmits<{ toggle: [assetId: number] }>();

/** Sorting here makes the weight bars monotonic whatever order the caller passes. */
const sortedHoldings = computed<HoldingLine[]>(() =>
    [...props.holdings].sort((left, right) => (right.marketValue ?? 0) - (left.marketValue ?? 0)),
);

/** Cropping only what is rendered leaves every aggregate below computed on the whole portfolio. */
const visibleHoldings = computed<HoldingLine[]>(() =>
    props.limit ? sortedHoldings.value.slice(0, props.limit) : sortedHoldings.value,
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

/** A single monotonic fade over the rendered rows, so the faintest bar stays readable in both themes. */
const opacityAt = (index: number): number =>
    1 - (index / Math.max(1, visibleHoldings.value.length - 1)) * (1 - FAINTEST_BAR_OPACITY);

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
            v-for="(line, index) in visibleHoldings"
            :key="line.assetId"
            data-holding-row
            class="flex flex-col gap-1.5 border-b border-separator py-3 last:border-b-0"
            :class="isHidden(line.assetId) ? 'opacity-40' : ''"
        >
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="shrink-0 text-subtle-foreground transition-colors hover:text-foreground"
                    :aria-label="isHidden(line.assetId) ? 'Afficher' : 'Masquer'"
                    @click="$emit('toggle', line.assetId)"
                >
                    <EyeOff v-if="isHidden(line.assetId)" class="size-4" />
                    <Eye v-else class="size-4" />
                </button>

                <Link
                    :href="`/instruments/${line.assetId}`"
                    prefetch
                    data-holding-name
                    class="block min-w-0 flex-1 truncate font-semibold hover:underline"
                >
                    {{ line.assetName }}
                    <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                </Link>

                <span data-holding-value class="w-24 shrink-0 text-right font-bold tabular-nums">
                    {{ value(line.marketValue) }}
                </span>
                <span
                    data-holding-gain-pct
                    class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                    :class="gainClass(line.gain)"
                >
                    {{ pct(line.gainPct) }}
                </span>
            </div>

            <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
            <div class="flex items-center gap-3 pl-7 text-xs">
                <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
                    <span
                        data-holding-bar
                        class="block h-full rounded-full bg-sector-bar"
                        :style="{ width: barWidth(line), opacity: opacityAt(index) }"
                    ></span>
                </span>
                <span data-holding-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                    {{ share(shareOf(line)) }}
                </span>

                <span class="w-16 shrink-0">
                    <Sparkline v-if="valuesFor(line.assetId).length > 1" :values="valuesFor(line.assetId)" />
                    <span v-else-if="!series" class="block h-5 w-full animate-pulse rounded bg-muted"></span>
                </span>

                <span
                    data-holding-gain
                    class="ml-auto shrink-0 text-right tabular-nums"
                    :class="gainClass(line.gain)"
                >
                    {{ signedEur(line.gain, 0) }}
                </span>
            </div>
        </li>
    </ul>
</template>
