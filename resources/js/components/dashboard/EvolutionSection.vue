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

/**
 * Distance au bord droit de la dernière position lue. Le graphe s'ouvre à droite (null), puis
 * garde ce point d'ancrage à chaque élargissement : prolonger l'historique ne le fait pas sauter.
 */
const anchorFromRight = ref<number | null>(null);

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

const onScroll = (): void => {
    const element = scroller.value;
    if (element === null) {
        return;
    }

    anchorFromRight.value = element.scrollWidth - element.scrollLeft;

    if (element.scrollLeft <= LOAD_THRESHOLD_PX) {
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

    element.scrollLeft = anchorFromRight.value === null
        ? element.scrollWidth
        : element.scrollWidth - anchorFromRight.value;
};

const viewportObserver = new ResizeObserver((entries): void => {
    const width = entries[0]?.contentRect.width ?? 0;
    requestAnimationFrame((): void => {
        viewportWidth.value = width;
    });
});

/** Repositionner depuis le callback ferait boucler l'observateur : on attend la frame suivante. */
const contentObserver = new ResizeObserver((): void => {
    requestAnimationFrame(anchorScroll);
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

                <div ref="scroller" data-evolution-scroller class="min-w-0 flex-1 overflow-x-auto" @scroll="onScroll">
                    <div ref="content" class="w-fit">
                        <VueApexCharts
                            v-if="isMeasured"
                            :key="chartKey"
                            type="area"
                            :height="CHART_HEIGHT"
                            :width="chartWidth"
                            :options="evolutionChart.options"
                            :series="evolutionChart.series"
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
