<script setup lang="ts">
import { computed } from 'vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import { fractionPct, gainClass, signedEur } from '@/lib/format';
import { profitabilityBars, type ProfitabilityBar, type PropertyProfitability } from '@/lib/realEstate';

const props = defineProps<{ profitability?: PropertyProfitability[] | null }>();

const bars = computed<ProfitabilityBar[]>(() => profitabilityBars(props.profitability ?? []));

/** Ce que la ligne ne montre pas, au survol : les trois autres façons de juger un bien. */
const title = (bar: ProfitabilityBar): string => [
    `Brut ${ratio(bar.line.metrics.grossYield)}`,
    `Cash-on-cash ${ratio(bar.line.metrics.cashOnCash)}`,
    `LTV ${ratio(bar.line.metrics.ltv)}`,
].join(' · ');

const ratio = (value: number | null): string => (value === null ? '—' : fractionPct(value));
</script>

<template>
    <section data-section="profitability" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Rentabilité</h2>

        <template v-if="props.profitability !== null">
            <!-- `grow` et non `flex-1` : sans hauteur libre à distribuer, les barres gardent leur taille naturelle. -->
            <ul v-if="bars.length" class="flex min-h-0 grow flex-col gap-3">
                <li
                    v-for="bar in bars"
                    :key="bar.line.id"
                    data-profitability-row
                    :title="title(bar)"
                    class="flex max-h-12 grow items-center gap-3 text-sm md:max-h-none md:grow-0"
                >
                    <span data-profitability-name class="w-24 shrink-0 truncate font-semibold md:w-40">
                        {{ bar.line.name }}
                    </span>

                    <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-separator">
                        <span
                            data-profitability-bar
                            class="block h-full rounded-full bg-sector-bar"
                            :style="{ width: bar.barWidth }"
                        ></span>
                    </span>

                    <span
                        data-profitability-cash-flow
                        class="w-24 shrink-0 text-right font-semibold tabular-nums"
                        :class="gainClass(bar.line.metrics.annualCashFlow)"
                    >
                        {{ signedEur(bar.line.metrics.annualCashFlow, 0) }}
                    </span>

                    <span data-profitability-yield class="w-20 shrink-0 text-right font-semibold tabular-nums">
                        {{ ratio(bar.line.metrics.netYield) }}
                    </span>
                </li>
            </ul>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun bien à comparer pour l'instant.
            </p>
        </template>

        <DeferredBlock v-else data="profitability" />
    </section>
</template>
