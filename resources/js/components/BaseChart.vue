<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue';
import { chartHeight } from '@/lib/layout';
import { CHART_LOCALE, echarts, type ChartOption } from '@/lib/echarts';
import type { ZoomWindow } from '@/lib/chart';

const props = defineProps<{ option: ChartOption }>();

/** La fenêtre de zoom est rendue au parent plutôt que lue sur l'instance : elle reste privée. */
const emit = defineEmits<{ zoom: [window: ZoomWindow] }>();

const container = useTemplateRef<HTMLElement>('container');

/**
 * Seul endroit du projet où une instance de graphe est manipulée. Les sections ne
 * produisent qu'un objet d'options ; tout le DOM en découle, sans état intermédiaire.
 */
let chart: echarts.ECharts | null = null;

/**
 * Boîte du dernier dessin, `null` avant toute notification. `observe()` livre une observation
 * d'entrée qui ne fait que redire les dimensions qu'`echarts.init` vient de lire : la retenir sans
 * redessiner épargne au montage une reconstruction complète du SVG — mesurée à 21 ms de thread
 * principal sur mobile, pour un rendu au pixel identique.
 */
let painted: string | null = null;

const resizeObserver = new ResizeObserver((entries: ResizeObserverEntry[]): void => {
    const box = entries[entries.length - 1]?.contentRect;

    if (box === undefined) {
        return;
    }

    /** Arrondi : les fractions de pixel d'un redimensionnement de fenêtre ne changent aucun tracé. */
    const size = `${Math.round(box.width)}x${Math.round(box.height)}`;
    const previous = painted;
    painted = size;

    if (previous !== null && previous !== size) {
        chart?.resize();
    }
});

/**
 * Fenêtre visible, en pourcentage de l'historique. Publiée en attribut : c'est le seul état
 * du graphe qu'un lecteur — ou un test — peut avoir besoin de lire depuis l'extérieur.
 */
const zoomWindow = ref<string | null>(null);

/**
 * Publie la fenêtre en attribut et la rend, ou `null` quand l'option n'en porte aucune. Les
 * charges utiles de `datazoom` diffèrent selon la poignée : l'option courante fait foi.
 */
const publishZoom = (): ZoomWindow | null => {
    const zooms = chart?.getOption()?.dataZoom as { start?: number; end?: number }[] | undefined;
    const zoom = zooms?.[0];

    if (typeof zoom?.start !== 'number' || typeof zoom.end !== 'number') {
        return null;
    }

    zoomWindow.value = `${Math.round(zoom.start)}-${Math.round(zoom.end)}`;

    return { start: zoom.start, end: zoom.end };
};

/**
 * Seul un déplacement du lecteur est rendu au parent. Rendre aussi la fenêtre d'ouverture la
 * figerait en pourcentage : une reconstruction sur un historique plus long — l'instantané puis le
 * réseau — la rejouerait sur une autre amplitude, et le graphe n'ouvrirait plus sur douze mois.
 */
const rememberReaderZoom = (): void => {
    const zoom = publishZoom();

    if (zoom !== null) {
        emit('zoom', zoom);
    }
};

onMounted((): void => {
    if (container.value === null) {
        return;
    }

    chart = echarts.init(container.value, null, { renderer: 'svg', locale: CHART_LOCALE });
    chart.setOption(props.option);
    chart.on('datazoom', rememberReaderZoom);
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
