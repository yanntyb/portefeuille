<script setup lang="ts">
import { pageContainer, type PageWidth } from '@/lib/layout';

const props = withDefaults(defineProps<{ width?: PageWidth }>(), { width: 'narrow' });
</script>

<template>
    <main class="min-h-screen overflow-x-hidden bg-background py-6 text-foreground">
        <div :class="[pageContainer(props.width), 'flex flex-col gap-6']">
            <slot />
        </div>
    </main>
</template>
