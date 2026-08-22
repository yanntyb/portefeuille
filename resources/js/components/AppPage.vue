<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import { pageContainer, type PageWidth } from '@/lib/layout';

const props = withDefaults(defineProps<{ width?: PageWidth }>(), { width: 'narrow' });
</script>

<template>
    <main class="min-h-screen overflow-x-hidden bg-background py-6 text-foreground">
        <div :class="[pageContainer(props.width), 'flex flex-col gap-6']">
            <!-- Dans la page et non dans le fil d'Ariane : celui-ci manque au tableau de bord, le bouton y manquerait aussi. -->
            <div class="flex justify-end px-6">
                <ThemeToggle />
            </div>

            <slot />
        </div>
    </main>
</template>
