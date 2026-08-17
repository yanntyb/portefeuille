<script setup lang="ts">
import { pageContainer, type PageWidth } from '@/lib/layout';

/**
 * `fill` borne la page à la hauteur de son parent au lieu de la laisser grandir avec son contenu :
 * le carrousel du tableau de bord a besoin d'une hauteur définie pour paginer. Au-delà du mobile la
 * page reprend son défilement vertical habituel.
 */
const props = withDefaults(defineProps<{ width?: PageWidth; fill?: boolean }>(), {
    width: 'narrow',
    fill: false,
});
</script>

<template>
    <main
        class="overflow-x-hidden bg-background py-6 text-foreground"
        :class="props.fill ? 'flex min-h-0 flex-1 flex-col md:block md:min-h-screen' : 'min-h-screen'"
    >
        <div :class="[pageContainer(props.width), 'flex flex-col gap-6', props.fill && 'min-h-0 flex-1 md:flex-none']">
            <slot />
        </div>
    </main>
</template>
