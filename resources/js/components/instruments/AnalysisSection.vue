<script setup lang="ts">
import { computed } from 'vue';
import AnalysisRow from '@/components/instrument/AnalysisRow.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import CorrelationInfoDialog from '@/components/CorrelationInfoDialog.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import SectorsSection from '@/components/SectorsSection.vue';
import { basketRows, correlationGrid, type BasketAnalysis, type CorrelationRow } from '@/lib/basketAnalysis';
import type { AnalysisRow as Row } from '@/lib/instrumentAnalysis';
import type { Performance } from '@/lib/performance';
import { rowsFromSlices, type SectorBreakdownRow, type SectorSlice } from '@/lib/sector';

const props = defineProps<{
    analysis?: BasketAnalysis | null;
    performances?: Performance[] | null;
    /**
     * Le bloc sectoriel se décide sur la classe, jamais sur la valeur : `aheadOfNetwork` rend
     * `null` en attendant, et un `null` ne distingue pas « pas encore » de « jamais ».
     */
    hasSectors: boolean;
    slices?: SectorSlice[] | null;
}>();

const rows = computed<Row[]>(() => (props.analysis ? basketRows(props.analysis) : []));

const grid = computed<CorrelationRow[]>(() =>
    props.analysis ? correlationGrid(props.analysis) : [],
);

/** Une seule ligne ne se corrèle à rien : la matrice n'apprendrait que sa propre diagonale. */
const hasMatrix = computed<boolean>(() => grid.value.length > 1);

const isEmpty = computed<boolean>(() => grid.value.length === 0);

const hasPerformances = computed<boolean>(() => (props.performances?.length ?? 0) > 0);

/** `undefined` comme `null` valent « pas encore arrivé » : une prop Inertia non servie vaut `undefined`. */
const sectorRows = computed<SectorBreakdownRow[] | null>(() =>
    props.slices === null || props.slices === undefined ? null : rowsFromSlices(props.slices),
);
</script>

<template>
    <CollapsibleSection section="analysis" title="Analyse">
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
                    <span data-correlation-help class="flex items-center gap-1.5">
                        <span class="text-xs font-semibold text-muted-foreground uppercase">Corrélations</span>

                        <!-- Hauteur nulle, bouton centré sur la ligne : plus haut qu'une
                             étiquette, il creuserait sinon un blanc sous elle. -->
                        <span class="flex h-0 items-center">
                            <CorrelationInfoDialog />
                        </span>
                    </span>

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

        <DeferredBlock v-else data="basketAnalysis" :lines="6" line-class="h-6" />

        <!-- Ce qui décrit la poche se lit d'abord, ce qu'elle a rapporté ensuite. -->
        <div class="flex flex-col gap-2">
            <!-- L'aide se pose contre l'étiquette qu'elle explique, pas dans le titre de la
                 section : c'est des performances qu'elle parle, pas de l'analyse. -->
            <span data-perf-help class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-muted-foreground uppercase">Performances</span>

                <!-- Hauteur nulle, bouton centré sur la ligne : plus haut qu'une étiquette, il
                     creuserait sinon un blanc entre elle et les barres. -->
                <span class="flex h-0 items-center">
                    <PerformanceInfoDialog variant="periods" />
                </span>
            </span>

            <template v-if="props.performances !== null">
                <PerformanceBars v-if="hasPerformances" :performances="props.performances ?? []" />

                <p v-else class="py-8 text-center text-sm text-muted-foreground">
                    Pas encore de performance à mesurer.
                </p>
            </template>

            <DeferredBlock v-else data="performances" :lines="5" />
        </div>

        <!-- La lecture la plus fine ferme la section : les secteurs que traverse la poche. -->
        <SectorsSection v-if="props.hasSectors" variant="block" defer-key="sectorBreakdown" :rows="sectorRows" />
    </CollapsibleSection>
</template>
