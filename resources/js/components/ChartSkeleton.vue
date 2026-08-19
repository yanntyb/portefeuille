<script setup lang="ts">
/**
 * Empreinte du graphe avant qu'il ne soit peint : `chartHeight` est la hauteur de `BaseChart`,
 * pour que l'arrivée des données — ou du chunk d'echarts, chargé à la demande — ne décale rien.
 */
import { chartHeight } from '@/lib/layout';
</script>

<template>
    <div class="w-full animate-pulse rounded-md bg-muted" :style="{ height: `${chartHeight}px` }"></div>
</template>
