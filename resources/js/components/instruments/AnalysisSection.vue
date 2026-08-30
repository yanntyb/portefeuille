<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import AnalysisRow from '@/components/instrument/AnalysisRow.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import { basketRows, correlationGrid, type ClassAnalysis, type CorrelationRow } from '@/lib/classAnalysis';
import type { AnalysisRow as Row } from '@/lib/instrumentAnalysis';
import type { Performance } from '@/lib/performance';

const props = defineProps<{
    analysis?: ClassAnalysis | null;
    performances?: Performance[] | null;
}>();

const rows = computed<Row[]>(() => (props.analysis ? basketRows(props.analysis) : []));

const grid = computed<CorrelationRow[]>(() =>
    props.analysis ? correlationGrid(props.analysis) : [],
);

/** Une seule ligne ne se corrèle à rien : la matrice n'apprendrait que sa propre diagonale. */
const hasMatrix = computed<boolean>(() => grid.value.length > 1);

const isEmpty = computed<boolean>(() => grid.value.length === 0);

const hasPerformances = computed<boolean>(() => (props.performances?.length ?? 0) > 0);
</script>

<template>
    <CollapsibleSection section="analysis" title="Analyse">
        <!-- L'aide se lit repliée : elle explique de quoi parlent les performances, en bas de
             section, pas ce qu'elles montrent. -->
        <template #aside>
            <PerformanceInfoDialog variant="periods" />
        </template>

        <!--
            Deux props différées dans une seule section, donc deux squelettes : la matrice relit
            cinq ans de cours quand les performances rejouent les transactions, et la première
            servie ne doit pas attendre l'autre pour se peindre.
        -->
        <template v-if="props.analysis !== undefined && props.analysis !== null">
            <p v-if="isEmpty" class="py-8 text-center text-sm text-muted-foreground">
                Pas encore de quoi analyser cette exposition.
            </p>

            <template v-else>
                <!-- Les deux repères de la poche entière, lus comme sur une fiche : une verticale
                     de chiffres, le libellé à gauche. -->
                <div class="flex flex-col gap-1.5 text-[13.5px]">
                    <AnalysisRow v-for="row in rows" :key="row.indicator" :row="row" />
                </div>

                <div v-if="hasMatrix" class="flex flex-col gap-2">
                    <span class="text-xs font-semibold text-muted-foreground uppercase">Corrélations</span>

                    <!-- La matrice déborde du téléphone dès quatre lignes : elle défile dans son
                         propre cadre, jamais la page. -->
                    <div class="-mx-1 overflow-x-auto px-1">
                        <table class="border-separate border-spacing-1 text-[12px]">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-10 bg-background"></th>
                                    <th
                                        v-for="instrument in props.analysis.instruments"
                                        :key="instrument.assetId"
                                        class="px-1 font-semibold text-muted-foreground"
                                    >
                                        {{ instrument.label }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="row in grid" :key="row.label">
                                    <th
                                        class="sticky left-0 z-10 bg-background pr-2 text-left font-semibold whitespace-nowrap text-muted-foreground"
                                    >
                                        {{ row.label }}
                                    </th>

                                    <td
                                        v-for="(cell, column) in row.cells"
                                        :key="column"
                                        data-correlation-cell
                                        class="rounded-sm px-2 py-1 text-center tabular-nums"
                                        :class="cell.tone"
                                    >
                                        {{ cell.value }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Rendements journaliers sur un an : plus la case est chaude, plus les deux
                        lignes montent et baissent ensemble.
                    </p>
                </div>
            </template>
        </template>

        <Deferred v-else data="classAnalysis">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 6" :key="n" class="h-6 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>

        <!-- Les performances ferment la section : ce qui décrit la poche se lit d'abord, ce
             qu'elle a rapporté ensuite. -->
        <div class="flex flex-col gap-2">
            <span class="text-xs font-semibold text-muted-foreground uppercase">Performances</span>

            <template v-if="props.performances !== null">
                <PerformanceBars v-if="hasPerformances" :performances="props.performances ?? []" />

                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas encore de performance à mesurer.
                </p>
            </template>

            <Deferred v-else data="performances">
                <template #fallback>
                    <div class="flex flex-col gap-2">
                        <div v-for="n in 5" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                    </div>
                </template>

                <template #rescue>
                    <p class="py-8 text-center text-sm text-muted-foreground">
                        Données indisponibles hors-ligne.
                    </p>
                </template>

                <span />
            </Deferred>
        </div>
    </CollapsibleSection>
</template>
