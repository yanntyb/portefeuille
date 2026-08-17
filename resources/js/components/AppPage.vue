<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import { pageContainer, type PageWidth } from '@/lib/layout';

const props = withDefaults(defineProps<{ width?: PageWidth }>(), { width: 'narrow' });
</script>

<template>
    <main class="min-h-screen overflow-x-hidden bg-background py-6 text-foreground">
        <div :class="pageContainer(props.width)">
            <Card class="rounded-none border-0 shadow-[0_1px_3px_rgba(20,22,40,0.08)] sm:rounded-xl">
                <slot />
            </Card>
        </div>
    </main>
</template>
