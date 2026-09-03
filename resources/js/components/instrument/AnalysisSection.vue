<script setup lang="ts">
import { computed } from 'vue';
import AnalysisRow from '@/components/instrument/AnalysisRow.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import { analysisGroups, type AnalysisGroup } from '@/lib/instrumentAnalysis';
import type { InstrumentAnalysis } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';

const props = defineProps<{
    analysis?: InstrumentAnalysis | null;
    performances?: Performance[];
}>();

const groups = computed<AnalysisGroup[]>(() =>
    props.analysis ? analysisGroups(props.analysis) : [],
);

/** Une position sans historique n'a pas de période à mesurer : le bloc disparaît plutôt que de
 *  rendre un vide que rien ne viendra remplir. */
const hasPerformances = computed<boolean>(() => (props.performances?.length ?? 0) > 0);
</script>

<template>
    <CollapsibleSection section="analysis" title="Analyse">
        <template v-if="props.analysis !== undefined && props.analysis !== null">
            <div v-for="group in groups" :key="group.title ?? 'reference'" class="flex flex-col gap-1.5 text-[13.5px]">
                <!-- Le titre de groupe se lit comme une étiquette, pas comme un second titre de
                     section : la section n'en a qu'un. -->
                <span v-if="group.title" class="text-xs font-semibold text-muted-foreground uppercase">
                    {{ group.title }}
                </span>

                <AnalysisRow v-for="row in group.rows" :key="row.indicator" :row="row" />
            </div>
        </template>

        <DeferredBlock v-else data="analysis" :lines="8" line-class="h-6" />

        <!-- Les performances ferment la section : les repères décrivent la position, elles disent
             ce qu'elle a rapporté. -->
        <div v-if="hasPerformances" class="flex flex-col gap-2">
            <span data-perf-help class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-muted-foreground uppercase">Performances</span>

                <!-- Hauteur nulle, bouton centré sur la ligne : plus haut qu'une étiquette, il
                     creuserait sinon un blanc entre elle et les barres. -->
                <span class="flex h-0 items-center">
                    <PerformanceInfoDialog variant="periods" />
                </span>
            </span>

            <PerformanceBars :performances="props.performances ?? []" />
        </div>
    </CollapsibleSection>
</template>
