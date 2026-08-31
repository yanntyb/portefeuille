<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur, gainClass, pct, sharePct as share, signedEur } from '@/lib/format';
import type { InstrumentRow } from '@/lib/instrumentList';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

const props = defineProps<{
    rows: InstrumentRow[];
    loading: boolean;
    emptyLabel: string;
}>();
</script>

<template>
    <!--
        La liste vit désormais dans une section repliée : elle ne s'étire plus pour occuper le bas de
        la page, ses lignes prennent la hauteur de leur contenu.
    -->
    <ul v-if="rows.length" class="flex flex-col">
        <li
            v-for="row in rows"
            :key="row.rowKey"
            data-instrument-row
            class="flex flex-col justify-center border-b border-separator last:border-b-0"
        >
            <!--
                La ligne entière mène à la fiche, teintée au clic comme une classe du tableau de
                bord : la valeur et le poids racontent l'actif autant que son nom.
            -->
            <Link
                :href="`/asset/${row.id}`"
                prefetch
                class="flex flex-col gap-1.5 rounded-md px-3 py-3 hover:bg-muted"
            >
                <span class="flex items-center gap-3">
                    <span data-instrument-name class="block min-w-0 flex-1 truncate font-semibold">
                        {{ row.name }}
                        <span v-if="row.ticker" class="text-muted-foreground">({{ row.ticker }})</span>
                    </span>

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
                </span>

                <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
                <span class="flex items-center gap-3 text-xs">
                    <span
                        v-if="row.walletName"
                        data-instrument-wallet
                        :title="`${row.walletName} — ${row.accountTypeLabel}`"
                        class="max-w-[3.5rem] shrink-0 truncate rounded-full bg-muted px-2 py-0.5 font-semibold text-muted-foreground md:max-w-[9rem]"
                    >
                        {{ row.walletName }}
                    </span>

                    <span class="h-1.5 w-6 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
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
                </span>
            </Link>
        </li>
    </ul>

    <p v-else data-instrument-empty class="py-8 text-center text-sm text-muted-foreground">
        {{ emptyLabel }}
    </p>
</template>
