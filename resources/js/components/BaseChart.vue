<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { chartHeight } from '@/lib/layout';
import { CHART_LOCALE, echarts, type ChartOption } from '@/lib/echarts';

const props = defineProps<{ option: ChartOption }>();

/** La fenêtre de zoom est rendue au parent plutôt que lue sur l'instance : elle reste privée. */
const emit = defineEmits<{ zoom: [window: { start: number; end: number }] }>();

const container = useTemplateRef<HTMLElement>('container');

/**
 * Seul endroit du projet où une instance de graphe est manipulée. Les sections ne
 * produisent qu'un objet d'options ; tout le DOM en découle, sans état intermédiaire.
 */
let chart: echarts.ECharts | null = null;

const resizeObserver = new ResizeObserver((): void => chart?.resize());

/**
 * Fenêtre visible, en pourcentage de l'historique. Publiée en attribut : c'est le seul état
 * du graphe qu'un lecteur — ou un test — peut avoir besoin de lire depuis l'extérieur.
 */
const zoomWindow = ref<string | null>(null);

/** Les charges utiles de `datazoom` diffèrent selon la poignée : l'option courante fait foi. */
const publishZoom = (): void => {
    const zooms = chart?.getOption()?.dataZoom as { start?: number; end?: number }[] | undefined;
    const zoom = zooms?.[0];

    if (typeof zoom?.start !== 'number' || typeof zoom.end !== 'number') {
        return;
    }

    zoomWindow.value = `${Math.round(zoom.start)}-${Math.round(zoom.end)}`;
    emit('zoom', { start: zoom.start, end: zoom.end });
};

onMounted((): void => {
    if (container.value === null) {
        return;
    }

    chart = echarts.init(container.value, null, { renderer: 'svg', locale: CHART_LOCALE });
    chart.setOption(props.option);
    chart.on('datazoom', publishZoom);
    publishZoom();
    resizeObserver.observe(container.value);
});

/** `notMerge` évite qu'une série retirée survive dans l'instance après un changement de filtre. */
watch(
    (): ChartOption => props.option,
    (option: ChartOption): void => {
        chart?.setOption(option, { notMerge: true });
    },
    { deep: true },
);

onBeforeUnmount((): void => {
    resizeObserver.disconnect();
    chart?.dispose();
    chart = null;
});
</script>

<template>
    <div ref="container" data-chart :data-zoom-window="zoomWindow" class="w-full" :style="{ height: `${chartHeight}px` }"></div>
</template>
