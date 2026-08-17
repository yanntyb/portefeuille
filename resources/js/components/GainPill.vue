<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ value: number | null; label: string }>();

const tone = computed<string>(() => {
    if (props.value === null || props.value === 0) {
        return 'bg-muted text-muted-foreground';
    }

    return props.value > 0 ? 'bg-gain-soft text-gain' : 'bg-loss-soft text-loss';
});
</script>

<template>
    <span :class="['rounded-full px-2.5 py-1 text-sm font-bold tabular-nums', tone]">{{ label }}</span>
</template>
