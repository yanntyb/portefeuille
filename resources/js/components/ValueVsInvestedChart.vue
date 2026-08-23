<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import { buildValueVsInvestedOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import { dividendMarks, type DividendMark, type DividendReceipt } from '@/lib/income';
import type { ChartOption } from '@/lib/echarts';

const props = defineProps<{
    /** Prop différée dont dépend le graphe : nomme la clé Inertia à surveiller tant que rien n'est arrivé. */
    deferKey: string;
    /**
     * Vrai dès que la fusion réseau / instantané de l'appelant a produit un objet, même sans
     * historique. Distinct de `hasHistory` : un objet présent peut porter une série vide.
     */
    loaded: boolean;
    labels: string[];
    value: number[];
    invested: number[];
    description: string;
    /** Absents sur le tableau de bord : seule la fiche instrument annote ses détachements. */
    dividends?: DividendReceipt[];
}>();

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

/** Les repères se calent sur les points de la série : ils attendent donc que celle-ci arrive. */
const marks = computed<DividendMark[]>(() => dividendMarks(props.labels, props.dividends ?? []));

const option = computed<ChartOption>(() => buildValueVsInvestedOption({
    labels: props.labels,
    value: props.value,
    invested: props.invested,
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: lastZoom,
    description: props.description,
    dividends: marks.value,
}));
</script>

<template>
    <div class="flex flex-col gap-6">
        <template v-if="loaded">
            <div v-if="hasHistory" class="px-6">
                <BaseChart :option="option" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </template>

        <Deferred v-else :data="deferKey">
            <template #fallback>
                <div class="px-6">
                    <ChartSkeleton />
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
</template>
