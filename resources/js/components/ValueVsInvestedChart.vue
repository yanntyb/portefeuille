<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import BaseChart from '@/components/BaseChart.vue';
import { buildValueVsInvestedOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';

const props = defineProps<{
    /** Prop différée dont dépend le graphe : le squelette tient tant qu'elle n'est pas arrivée. */
    deferKey: string;
    labels: string[];
    value: number[];
    invested: number[];
    description: string;
}>();

const CHART_HEIGHT = 300;

/**
 * Volontairement non réactive : le zoom est déjà appliqué dans l'instance quand l'événement
 * arrive. La stocker sert seulement à survivre à une reconstruction des options — la rendre
 * réactive ferait recalculer et repeindre le graphe à chaque pixel de glissement.
 * `null` tant que le lecteur n'a rien déplacé : le graphe choisit alors sa fenêtre d'ouverture.
 */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const hasHistory = computed<boolean>(() => props.labels.length > 0);

const option = computed<ChartOption>(() => buildValueVsInvestedOption({
    labels: props.labels,
    value: props.value,
    invested: props.invested,
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: lastZoom,
    description: props.description,
}));
</script>

<template>
    <div class="flex flex-col gap-6">
        <Deferred :data="deferKey">
            <template #fallback>
                <div class="px-0 sm:px-6">
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <div v-if="hasHistory" class="px-0 sm:px-6">
                <BaseChart :option="option" :height="CHART_HEIGHT" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </div>
</template>
