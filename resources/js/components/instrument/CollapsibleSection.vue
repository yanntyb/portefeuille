<script setup lang="ts">
import { ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps<{ section: string; title: string }>();

/**
 * Repliée à l'ouverture de la fiche : le graphe de valorisation tient le haut de l'écran, et les
 * sections qui le suivent l'en chasseraient toutes déroulées. Le pli ne se mémorise pas d'une
 * visite à l'autre — un état en mémoire suffit, comme pour les années des listes.
 */
const isOpen = ref<boolean>(false);

const toggle = (): void => {
    isOpen.value = !isOpen.value;
};
</script>

<template>
    <section :data-section="props.section" class="flex flex-col gap-6 px-6">
        <!--
            Le chevron ferme la ligne au lieu de l'ouvrir : posé avant le titre, il décalerait tous
            les titres de section de sa propre largeur, hors de la marge que suit le reste de la page.
        -->
        <button
            type="button"
            data-section-toggle
            class="flex w-full items-center gap-1.5 text-left"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            <h2 class="text-[17px] leading-none font-bold">{{ props.title }}</h2>
            <ChevronRight
                class="ml-auto size-4 shrink-0 text-muted-foreground transition-transform"
                :class="isOpen ? 'rotate-90' : ''"
            />
        </button>

        <slot v-if="isOpen" />
    </section>
</template>
