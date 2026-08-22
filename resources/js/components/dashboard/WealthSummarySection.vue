<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import GainPill from '@/components/GainPill.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import type { AssetClass, WealthOverview } from '@/lib/wealth';

const props = defineProps<{ overview: WealthOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);

interface ClassLine {
    label: string;
    href: string;
    entry: AssetClass;
}

/** Une classe sans valeur ne montre pas sa ligne : une ligne à zéro n'apprend rien. */
const lines = computed<ClassLine[]>(() => [
    { label: 'Actions', href: '/instruments', entry: props.overview.securities },
    { label: 'Immobilier', href: '/properties', entry: props.overview.realEstate },
].filter((line: ClassLine): boolean => line.entry.value !== 0));
</script>

<template>
    <section data-section="wealth-summary" class="flex shrink-0 flex-col gap-4">
        <div class="flex flex-col gap-1.5 px-6">
            <div class="flex flex-wrap items-baseline gap-3">
                <p data-wealth-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                    {{ eur(props.overview.totalValue) }}
                </p>
                <GainPill
                    v-if="props.overview.totalGainPct !== null"
                    data-wealth-gain-pct
                    :value="props.overview.totalGain"
                    :label="pct(props.overview.totalGainPct)"
                />
            </div>

            <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
                <span class="whitespace-nowrap">
                    Investi
                    <strong class="font-semibold text-foreground tabular-nums">
                        {{ eur(props.overview.totalInvested) }}
                    </strong>
                </span>
                <span class="whitespace-nowrap">
                    Gain
                    <strong class="font-semibold tabular-nums" :class="gainClass(props.overview.totalGain)">
                        {{ signedEur(props.overview.totalGain, 0) }}
                    </strong>
                </span>
            </p>
        </div>

        <ul v-if="lines.length" class="flex flex-col gap-1 px-3">
            <li v-for="line in lines" :key="line.label" data-wealth-class>
                <Link
                    :href="line.href"
                    prefetch
                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2.5 text-sm hover:bg-muted"
                >
                    <span class="font-medium">{{ line.label }}</span>
                    <span class="flex shrink-0 items-center gap-3 tabular-nums">
                        <span class="font-semibold">{{ eur(line.entry.value) }}</span>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </span>
                </Link>
            </li>
        </ul>

        <p v-else class="px-6 py-8 text-center text-sm text-muted-foreground">
            Aucun patrimoine pour l'instant.
        </p>
    </section>
</template>
