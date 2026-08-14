<script setup lang="ts">
import PerformanceBars from '@/components/PerformanceBars.vue';
import type { Performance } from '@/lib/performance';

defineProps<{ performances: Performance[] }>();
</script>

<template>
    <section data-section="performance" class="flex flex-col gap-6 px-6">
        <h2 class="leading-none font-semibold">Performance par période</h2>
        <PerformanceBars :performances="performances" />
    </section>
</template>
