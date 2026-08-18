<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import { buildValueVsInvestedOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';

const props = withDefaults(defineProps<{
    /** Prop différée dont dépend le graphe : le squelette tient tant qu'elle n'est pas arrivée. */
    deferKey: string;
    labels: string[];
    value: number[];
    invested: number[];
    description: string;
    /** Hauteur du tracé : la section qui l'accueille sait seule la place dont elle dispose. */
    height?: number;
}>(), { height: 300 });

/**
 * Echarts pèse à lui seul les deux tiers du JS de l'application. Il n'est demandé qu'au
 * moment où un graphe est réellement monté, donc jamais avant le premier rendu.
 */
const BaseChart = defineAsyncComponent({
    loader: () => import('@/components/BaseChart.vue'),
    loadingComponent: ChartSkeleton,
    delay: 0,
});

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
                <div class="px-6">
                    <ChartSkeleton :height="props.height" />
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <div v-if="hasHistory" class="px-6">
                <BaseChart :option="option" :height="props.height" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </div>
</template>
