<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import type { InstrumentSection } from '@/lib/instrumentList';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

/** Trois lignes fantômes : le bloc du catalogue occupe sa place avant que le catalogue arrive. */
const PENDING_ROWS = [0, 1, 2];

defineProps<{
    sections: InstrumentSection[];
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
    <ul v-if="sections.length" class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-y-contain md:flex-none md:overflow-visible">
        <template v-for="section in sections" :key="section.label ?? 'resultats'">
            <!--
                L'en-tête colle au haut de la liste, qui est le conteneur de défilement sur mobile :
                on sait toujours si les lignes lues sont des positions ou le reste du catalogue.
                Au-delà du mobile la page entière défile, un en-tête collé s'épinglerait au viewport.
            -->
            <li
                v-if="section.label !== null"
                data-instrument-group
                class="sticky top-0 z-10 shrink-0 bg-background py-1.5 text-xs font-semibold uppercase tracking-wide text-subtle-foreground md:static"
            >
                {{ section.label }}
            </li>

            <li
                v-for="row in section.rows"
                :key="row.id"
                data-instrument-row
                :data-held="row.held ? 'true' : 'false'"
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

                    <!-- Ce que vaut la ligne : la position pour un instrument détenu, son cours sinon. -->
                    <span data-instrument-value class="w-24 shrink-0 text-right font-bold tabular-nums">
                        {{ row.held ? eur(row.marketValue, 0) : eur(row.lastPrice) }}
                    </span>

                    <!-- Même colonne, deux mesures : le gain latent d'une position, la variation de la période sinon. -->
                    <span
                        data-instrument-change
                        class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                        :class="gainClass(row.held ? row.gainPct : row.changePct)"
                    >
                        {{ pct(row.held ? row.gainPct : row.changePct) }}
                    </span>
                </div>

                <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
                <div class="flex items-center gap-3 text-xs">
                    <template v-if="row.held">
                        <!--
                            Le poids dans le portefeuille ne veut rien dire au milieu de résultats de
                            recherche : la barre laisse alors sa place au marqueur de détention, seule
                            chose que le titre de bloc ne dit plus.
                        -->
                        <span
                            v-if="section.label !== null"
                            class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28"
                        >
                            <span
                                data-instrument-bar
                                class="block h-full rounded-full bg-sector-bar"
                                :style="{ width: row.barWidth ?? '0%', opacity: row.opacity ?? 1 }"
                            ></span>
                        </span>
                        <span
                            v-else
                            data-instrument-held-badge
                            class="shrink-0 rounded-full bg-muted px-2 py-0.5 font-medium text-subtle-foreground"
                        >
                            Détenu
                        </span>

                        <span data-instrument-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                            {{ share(row.share ?? 0) }}
                        </span>
                    </template>

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
                        v-if="row.held"
                        data-instrument-gain
                        class="w-20 shrink-0 text-right tabular-nums"
                        :class="gainClass(row.gain)"
                    >
                        {{ signedEur(row.gain, 0) }}
                    </span>
                    <span v-else class="w-20 shrink-0"></span>
                </div>
            </li>

            <li
                v-for="ghost in section.pending ? PENDING_ROWS : []"
                :key="`fantome-${ghost}`"
                data-instrument-pending
                class="flex max-h-24 grow flex-col justify-center gap-1.5 border-b border-separator py-3 last:border-b-0 md:max-h-none md:grow-0"
            >
                <span class="h-4 w-2/5 animate-pulse rounded bg-muted"></span>
                <span class="h-3 w-1/5 animate-pulse rounded bg-muted"></span>
            </li>
        </template>
    </ul>

    <p v-else data-instrument-empty class="min-h-0 flex-1 py-8 text-center text-sm text-muted-foreground md:flex-none">
        {{ emptyLabel }}
    </p>
</template>
