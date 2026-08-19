<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import type { InstrumentRow } from '@/lib/instrumentList';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

defineProps<{
    rows: InstrumentRow[];
    loading: boolean;
    emptyLabel: string;
}>();

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
</script>

<template>
    <!--
        La liste prend la hauteur restante et ses lignes s'y répartissent : sans cela un petit
        portefeuille laisse un bloc vide sous lui, à un écran du bas de la page. Le `max-h` borne
        l'étirement — trois positions ne doivent pas devenir trois bandeaux.
    -->
    <ul v-if="rows.length" class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-y-contain md:flex-none md:overflow-visible">
        <li
            v-for="row in rows"
            :key="row.id"
            data-instrument-row
            class="flex max-h-24 grow flex-col justify-center gap-1.5 border-b border-separator py-3 last:border-b-0 md:max-h-none md:grow-0"
        >
            <div class="flex items-center gap-3">
                <Link
                    :href="`/instruments/${row.id}`"
                    prefetch
                    data-instrument-name
                    class="block min-w-0 flex-1 truncate font-semibold hover:underline"
                >
                    {{ row.name }}
                    <span v-if="row.ticker" class="text-muted-foreground">({{ row.ticker }})</span>
                </Link>

                <span data-instrument-value class="w-24 shrink-0 text-right font-bold tabular-nums">
                    {{ eur(row.marketValue, 0) }}
                </span>

                <span
                    data-instrument-change
                    class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                    :class="gainClass(row.gainPct)"
                >
                    {{ pct(row.gainPct) }}
                </span>
            </div>

            <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
            <div class="flex items-center gap-3 text-xs">
                <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
                    <span
                        data-instrument-bar
                        class="block h-full rounded-full bg-sector-bar"
                        :style="{ width: row.barWidth ?? '0%' }"
                    ></span>
                </span>

                <span data-instrument-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                    {{ share(row.share ?? 0) }}
                </span>

                <!-- Largeurs de queue identiques à la première ligne : tendance sous la valeur, gain sous le pourcentage. -->
                <span data-instrument-trend class="ml-auto w-24 shrink-0">
                    <Sparkline
                        v-if="row.points.length > 1"
                        :values="row.points"
                        :width="SPARKLINE_WIDTH"
                    />
                    <span v-else-if="loading" class="block h-5 w-full animate-pulse rounded bg-muted"></span>
                </span>

                <span
                    data-instrument-gain
                    class="w-20 shrink-0 text-right tabular-nums"
                    :class="gainClass(row.gain)"
                >
                    {{ signedEur(row.gain, 0) }}
                </span>
            </div>
        </li>
    </ul>

    <p v-else data-instrument-empty class="min-h-0 flex-1 py-8 text-center text-sm text-muted-foreground md:flex-none">
        {{ emptyLabel }}
    </p>
</template>
