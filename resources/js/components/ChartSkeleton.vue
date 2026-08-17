<script setup lang="ts">
/**
 * Empreinte du graphe avant qu'il ne soit peint : même hauteur que `BaseChart`, pour que
 * l'arrivée des données — ou du chunk d'echarts, chargé à la demande — ne décale rien.
 */
const props = withDefaults(defineProps<{ height?: number }>(), { height: 300 });
</script>

<template>
    <div class="w-full animate-pulse rounded-md bg-muted" :style="{ height: `${props.height}px` }"></div>
</template>
