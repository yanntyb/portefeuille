<script setup lang="ts">
import { pageContainer, type PageWidth } from '@/lib/layout';

const props = withDefaults(defineProps<{ width?: PageWidth }>(), { width: 'narrow' });
</script>

<template>
    <!--
        La barre du bas est un frère de <main> dans le flux : réserver 100dvh ici l'empilerait
        sous le viewport et ferait défiler la page de l'épaisseur de la barre, page vide comprise.
        D'où la soustraction de min-h-11 (2.75rem), la hauteur imposée à cette barre.
    -->
    <main class="min-h-[calc(100dvh-2.75rem)] overflow-x-hidden bg-background py-6 text-foreground">
        <div :class="[pageContainer(props.width), 'flex flex-col gap-6']">
            <slot />
        </div>
    </main>
</template>
