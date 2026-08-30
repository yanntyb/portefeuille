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
        <div class="relative flex w-full items-center gap-1.5">
            <!--
                La bascule est une couche sous la ligne, pas la ligne elle-même : le slot `aside`
                porte des boutons (le dialogue d'aide des performances), et deux `<button>`
                imbriquées seraient invalides. Le titre la nomme pour les technologies d'assistance.
            -->
            <button
                type="button"
                data-section-toggle
                class="absolute inset-0"
                :aria-label="props.title"
                :aria-expanded="isOpen"
                @click="toggle"
            ></button>

            <h2 class="text-[17px] leading-none font-bold">{{ props.title }}</h2>

            <!--
                Le chiffre que la section résume tient dans son titre : replié, il se lit quand
                même. Collé au titre plutôt que poussé à droite — c'est la suite de la phrase que
                le titre commence, pas une colonne de chiffres. Le chevron garde le bord droit.
            -->
            <slot name="value" />

            <!-- Ce qui s'ouvre sur autre chose que le pli : posé au-dessus de la couche de bascule. -->
            <span v-if="$slots.aside" class="relative flex items-center">
                <slot name="aside" />
            </span>

            <ChevronRight
                class="ml-auto size-4 shrink-0 text-muted-foreground transition-transform"
                :class="isOpen ? 'rotate-90' : ''"
            />
        </div>

        <slot v-if="isOpen" />
    </section>
</template>
