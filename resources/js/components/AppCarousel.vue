<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import { useElementSize, useScroll } from '@vueuse/core';
import { activePageIndex, dotClass, pageScrollOffset } from '@/lib/carousel';

const track = useTemplateRef<HTMLElement>('track');

const { x } = useScroll(track);
const { width } = useElementSize(track);

/**
 * Libellés lus sur les pages au montage plutôt que reçus en propriété : les sections du tableau de
 * bord sont conditionnelles, un nombre de pages codé en dur mentirait sur un portefeuille vide.
 */
const labels = ref<string[]>([]);

onMounted((): void => {
    labels.value = [...(track.value?.children ?? [])].map(
        (page: Element): string => page.getAttribute('data-carousel-label') ?? '',
    );
});

const activeIndex = computed<number>(() => activePageIndex(x.value, width.value, labels.value.length));

const goToPage = (index: number): void => {
    track.value?.scrollTo({ left: pageScrollOffset(index, width.value), behavior: 'smooth' });
};
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col md:contents">
        <!-- Le défilement pagé est natif : le navigateur reste maître du geste, aucun suivi du doigt en JS. -->
        <div
            ref="track"
            data-carousel
            class="flex min-h-0 flex-1 snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none] md:contents [&::-webkit-scrollbar]:hidden"
        >
            <slot />
        </div>

        <div data-carousel-dots class="flex shrink-0 items-center justify-center gap-2.5 pt-4 md:hidden">
            <button
                v-for="(label, index) in labels"
                :key="label"
                type="button"
                data-carousel-dot
                :aria-label="`Aller à ${label}`"
                :aria-current="index === activeIndex ? 'true' : undefined"
                :class="['size-2 rounded-full bg-foreground transition-all duration-200', dotClass(index, activeIndex)]"
                @click="goToPage(index)"
            ></button>
        </div>
    </div>
</template>
