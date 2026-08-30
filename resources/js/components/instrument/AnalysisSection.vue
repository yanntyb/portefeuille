<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import AnalysisRow from '@/components/instrument/AnalysisRow.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { analysisGroups, type AnalysisGroup } from '@/lib/instrumentAnalysis';
import type { InstrumentAnalysis } from '@/lib/instrument';

const props = defineProps<{ analysis?: InstrumentAnalysis | null }>();

const groups = computed<AnalysisGroup[]>(() =>
    props.analysis ? analysisGroups(props.analysis) : [],
);
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

        <Deferred v-else data="analysis">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 8" :key="n" class="h-6 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
