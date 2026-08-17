<script setup lang="ts">
/**
 * Une page du carrousel. Le libellé est publié en attribut plutôt que remonté au parent : le
 * carrousel lit ses pages dans le DOM, comme le graphe publie sa fenêtre de zoom.
 *
 * `md:contents` retire la boîte au-delà du mobile — la section redevient enfant direct de la
 * colonne de page, et le grand écran retrouve exactement la pile d'avant le carrousel.
 */
defineProps<{ label: string }>();
</script>

<template>
    <div
        data-carousel-page
        :data-carousel-label="label"
        class="flex w-full shrink-0 snap-center snap-always flex-col gap-6 overflow-y-auto overscroll-y-contain md:contents"
    >
        <slot />
    </div>
</template>
