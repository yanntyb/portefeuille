<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import { holdingWeights, type EvolutionSeries, type HoldingLine, type HoldingWeight } from '@/lib/portfolio';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

const props = defineProps<{
    holdings: HoldingLine[];
    series?: EvolutionSeries;
    limit?: number;
}>();

const weights = computed<HoldingWeight[]>(() => holdingWeights(props.holdings, props.limit));

const seriesByAsset = computed<Map<number, number[]>>(
    () => new Map((props.series?.perAsset ?? []).map((asset) => [asset.assetId, asset.value])),
);

const valuesFor = (assetId: number): number[] => seriesByAsset.value.get(assetId) ?? [];

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const value = (amount: number | null): string => formatEur(amount, 0);
</script>

<template>
    <ul class="flex flex-col">
        <li
            v-for="weight in weights"
            :key="weight.line.assetId"
            data-holding-row
            class="flex flex-col gap-1.5 border-b border-separator py-3 last:border-b-0"
        >
            <div class="flex items-center gap-3">
                <Link
                    :href="`/instruments/${weight.line.assetId}`"
                    prefetch
                    data-holding-name
                    class="block min-w-0 flex-1 truncate font-semibold hover:underline"
                >
                    {{ weight.line.assetName }}
                    <span v-if="weight.line.ticker" class="text-muted-foreground">({{ weight.line.ticker }})</span>
                </Link>

                <span data-holding-value class="w-24 shrink-0 text-right font-bold tabular-nums">
                    {{ value(weight.line.marketValue) }}
                </span>
                <span
                    data-holding-gain-pct
                    class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                    :class="gainClass(weight.line.gain)"
                >
                    {{ pct(weight.line.gainPct) }}
                </span>
            </div>

            <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
            <div class="flex items-center gap-3 text-xs">
                <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
                    <span
                        data-holding-bar
                        class="block h-full rounded-full bg-sector-bar"
                        :style="{ width: weight.barWidth, opacity: weight.opacity }"
                    ></span>
                </span>
                <span data-holding-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                    {{ share(weight.share) }}
                </span>

                <!-- Largeurs de queue identiques à la première ligne : tendance sous la valeur, gain sous le pourcentage. -->
                <span data-holding-trend class="ml-auto w-24 shrink-0">
                    <Sparkline
                        v-if="valuesFor(weight.line.assetId).length > 1"
                        :values="valuesFor(weight.line.assetId)"
                        :width="SPARKLINE_WIDTH"
                    />
                    <span v-else-if="!series" class="block h-5 w-full animate-pulse rounded bg-muted"></span>
                </span>

                <span data-holding-gain class="w-20 shrink-0 text-right tabular-nums" :class="gainClass(weight.line.gain)">
                    {{ signedEur(weight.line.gain, 0) }}
                </span>
            </div>
        </li>
    </ul>
</template>
