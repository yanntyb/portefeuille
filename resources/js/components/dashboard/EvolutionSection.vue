<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Deferred, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import { buildEvolutionAxis, buildEvolutionChart } from '@/lib/chart';
import { eur as formatEur } from '@/lib/format';
import type { EvolutionSeries } from '@/lib/portfolio';

const props = defineProps<{
    hiddenAssetIds: Set<number>;
    series?: EvolutionSeries;
    initialMonths: number;
}>();

const CHART_HEIGHT = 300;
const AXIS_WIDTH = 64;
const PX_PER_POINT = 6;
const MONTHS_STEP = 6;
const LOAD_THRESHOLD_PX = 200;

const monthsLoaded = ref<number>(props.initialMonths);
const loading = ref<boolean>(false);
const viewportWidth = ref<number>(0);
const scroller = ref<HTMLElement | null>(null);
const content = ref<HTMLElement | null>(null);

/** Distance au bord droit de la dernière position lue, restaurée à chaque élargissement du graphe. */
const anchorFromRight = ref<number | null>(null);

/**
 * Tant que personne n'a fait défiler, le graphe se recolle au bord droit et ne charge rien :
 * seuls les gestes du lecteur distinguent un vrai défilement du repositionnement automatique.
 */
const scrolledByReader = ref<boolean>(false);

const eur = (value: number | null): string => formatEur(value, 0);

const labels = computed<string[]>(() => props.series?.labels ?? []);
const hasEvolution = computed<boolean>(() => labels.value.length > 0);
const hasMore = computed<boolean>(() => props.series?.hasMore === true);

/** Apex fige la largeur qu'il avait au montage : on attend la mesure du conteneur avant de le monter. */
const isMeasured = computed<boolean>(() => viewportWidth.value > 0);

const chartWidth = computed<number>(() => Math.max(viewportWidth.value, labels.value.length * PX_PER_POINT));

const chartInput = computed(() => ({
    labels: labels.value,
    perAsset: props.series?.perAsset ?? [],
    hiddenIds: props.hiddenAssetIds,
    valueFormatter: eur,
}));

const evolutionChart = computed(() => buildEvolutionChart(chartInput.value));
const evolutionAxis = computed(() => buildEvolutionAxis(chartInput.value));

const chartKey = computed<string>(() => {
    const hidden = Array.from(props.hiddenAssetIds)
        .sort((a, b) => a - b)
        .join('.');

    return `${labels.value.length}-${labels.value[0] ?? ''}-${hidden}`;
});

const loadOlderHistory = (): void => {
    if (loading.value || !hasMore.value) {
        return;
    }

    loading.value = true;

    router.reload({
        only: ['evolutionSeries'],
        data: { months: monthsLoaded.value + MONTHS_STEP },
        onSuccess: (): void => {
            monthsLoaded.value += MONTHS_STEP;
        },
        onFinish: (): void => {
            loading.value = false;
        },
    });
};

const markReaderScroll = (): void => {
    scrolledByReader.value = true;
};

/** Origine du geste en cours : `null` tant qu'aucun doigt ne tient le graphe. */
const dragStartX = ref<number | null>(null);
const dragStartScrollLeft = ref<number>(0);

/**
 * Le défilement tactile est piloté à la main plutôt que laissé au navigateur : le doigt
 * entraîne le graphe au pixel près et le relâchement l'arrête net, sans inertie qui
 * continuerait de charger l'historique après le geste.
 */
const onTouchStart = (event: TouchEvent): void => {
    const element = scroller.value;
    if (element === null || event.touches.length !== 1) {
        return;
    }

    dragStartX.value = event.touches[0].clientX;
    dragStartScrollLeft.value = element.scrollLeft;
};

const onTouchMove = (event: TouchEvent): void => {
    const element = scroller.value;
    if (element === null || dragStartX.value === null || event.touches.length !== 1) {
        return;
    }

    markReaderScroll();
    element.scrollLeft = dragStartScrollLeft.value + (dragStartX.value - event.touches[0].clientX);
};

const onTouchEnd = (): void => {
    dragStartX.value = null;
};

/** Un doigt posé n'est pas un défilement : le tactile ne compte qu'à partir du glissement. */
const onPointerDown = (event: PointerEvent): void => {
    if (event.pointerType !== 'touch') {
        markReaderScroll();
    }
};

const onScroll = (): void => {
    const element = scroller.value;
    if (element === null) {
        return;
    }

    anchorFromRight.value = element.scrollWidth - element.scrollLeft;

    if (scrolledByReader.value && element.scrollLeft <= LOAD_THRESHOLD_PX) {
        loadOlderHistory();
    }
};

/**
 * Apex peint son SVG hors du cycle de Vue : c'est la largeur réelle du contenu, et non
 * `nextTick`, qui dit quand le graphe est assez large pour être positionné.
 */
const anchorScroll = (): void => {
    const element = scroller.value;
    if (element === null) {
        return;
    }

    element.scrollLeft = scrolledByReader.value && anchorFromRight.value !== null
        ? element.scrollWidth - anchorFromRight.value
        : element.scrollWidth;
};

const MAX_ANCHOR_FRAMES = 30;

/**
 * Apex élargit son SVG sur plusieurs frames, et une seule notification de redimensionnement
 * peut tomber avant la largeur définitive : on repositionne jusqu'à ce qu'elle se stabilise.
 */
const anchorUntilStable = (): void => {
    let lastWidth = -1;
    let frames = 0;

    const step = (): void => {
        const element = scroller.value;
        if (element === null) {
            return;
        }

        anchorScroll();

        if (element.scrollWidth !== lastWidth && frames < MAX_ANCHOR_FRAMES) {
            lastWidth = element.scrollWidth;
            frames += 1;
            requestAnimationFrame(step);
        }
    };

    requestAnimationFrame(step);
};

const viewportObserver = new ResizeObserver((entries): void => {
    const width = entries[0]?.contentRect.width ?? 0;
    requestAnimationFrame((): void => {
        viewportWidth.value = width;
    });
});

/** Repositionner depuis le callback ferait boucler l'observateur : on attend la frame suivante. */
const contentObserver = new ResizeObserver((): void => {
    anchorUntilStable();
});

watch(scroller, (element, previous): void => {
    if (previous) {
        viewportObserver.unobserve(previous);
    }
    if (element) {
        viewportObserver.observe(element);
        viewportWidth.value = element.clientWidth;
    }
}, { immediate: true });

watch(content, (element, previous): void => {
    if (previous) {
        contentObserver.unobserve(previous);
    }
    if (element) {
        contentObserver.observe(element);
    }
}, { immediate: true });

watch(chartWidth, (): void => {
    anchorUntilStable();
}, { flush: 'post' });

onBeforeUnmount((): void => {
    viewportObserver.disconnect();
    contentObserver.disconnect();
});
</script>

<template>
    <section data-section="evolution" class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6">
            <div class="flex flex-col gap-1.5">
                <h2 class="leading-none font-semibold">Valeur de marché par titre</h2>
            </div>
        </div>

        <Deferred data="evolutionSeries">
            <template #fallback>
                <div class="px-0 sm:px-6">
                    <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <div v-if="hasEvolution" class="flex px-0 transition-opacity sm:px-6" :class="loading ? 'opacity-50' : ''">
                <div data-evolution-axis class="shrink-0" :style="{ width: `${AXIS_WIDTH}px` }">
                    <VueApexCharts
                        v-if="isMeasured"
                        :key="chartKey"
                        type="area"
                        :height="CHART_HEIGHT"
                        :width="AXIS_WIDTH"
                        :options="evolutionAxis.options"
                        :series="evolutionAxis.series"
                    />
                </div>

                <div
                    ref="scroller"
                    data-evolution-scroller
                    class="min-w-0 flex-1 touch-pan-y overflow-x-auto overscroll-x-contain"
                    @scroll="onScroll"
                    @wheel="markReaderScroll"
                    @pointerdown="onPointerDown"
                    @touchstart="onTouchStart"
                    @touchmove="onTouchMove"
                    @touchend="onTouchEnd"
                    @touchcancel="onTouchEnd"
                    @keydown="markReaderScroll"
                >
                    <div ref="content" class="w-fit">
                        <VueApexCharts
                            v-if="isMeasured"
                            :key="chartKey"
                            type="area"
                            :height="CHART_HEIGHT"
                            :width="chartWidth"
                            :options="evolutionChart.options"
                            :series="evolutionChart.series"
                            @mounted="anchorUntilStable"
                            @updated="anchorUntilStable"
                        />
                        <div v-else class="h-[300px] animate-pulse rounded-md bg-muted" :style="{ width: `${AXIS_WIDTH}px` }"></div>
                    </div>
                </div>
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
