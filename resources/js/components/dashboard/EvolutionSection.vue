<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import BaseChart from '@/components/BaseChart.vue';
import { buildEvolutionOption, INITIAL_ZOOM_WINDOW, type ZoomWindow } from '@/lib/chart';
import { eur as formatEur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { EvolutionSeries } from '@/lib/portfolio';

const props = defineProps<{
    series?: EvolutionSeries;
}>();

const CHART_HEIGHT = 300;

/**
 * Volontairement non réactive : le zoom est déjà appliqué dans l'instance quand l'événement
 * arrive. La stocker sert seulement à survivre à une reconstruction des options — la rendre
 * réactive ferait recalculer et repeindre le graphe à chaque pixel de glissement.
 */
let lastZoom: ZoomWindow = INITIAL_ZOOM_WINDOW;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const labels = computed<string[]>(() => props.series?.labels ?? []);
const hasEvolution = computed<boolean>(() => labels.value.length > 0);

const option = computed<ChartOption>(() => buildEvolutionOption({
    labels: labels.value,
    perAsset: props.series?.perAsset ?? [],
    valueFormatter: (value: number): string => formatEur(value, 0),
    window: lastZoom,
}));
</script>

<template>
    <section data-section="evolution" class="flex flex-col gap-6">
        <Deferred data="evolutionSeries">
            <template #fallback>
                <div class="px-0 sm:px-6">
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <div v-if="hasEvolution" class="px-0 sm:px-6">
                <BaseChart :option="option" :height="CHART_HEIGHT" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
