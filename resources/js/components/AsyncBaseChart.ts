import { defineAsyncComponent, type Component } from 'vue';
import ChartSkeleton from '@/components/ChartSkeleton.vue';

/**
 * Echarts pèse à lui seul les deux tiers du JS de l'application : il n'est demandé qu'au moment
 * où un graphe est réellement monté, donc jamais avant le premier rendu.
 *
 * Le composant est construit ici, au chargement du module, et non dans le `setup` de chaque
 * section. Un enveloppeur créé par montage ne retient pas sa résolution : il repasse par une
 * promesse — donc par le squelette — à chaque navigation, même le chunk déjà en cache. Partagé,
 * il retient le composant résolu et peint le graphe dès le premier rendu des visites suivantes.
 */
export const AsyncBaseChart: Component = defineAsyncComponent({
    loader: () => import('@/components/BaseChart.vue'),
    loadingComponent: ChartSkeleton,
    delay: 0,
});
