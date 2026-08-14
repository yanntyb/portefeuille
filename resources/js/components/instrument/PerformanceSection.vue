<script setup lang="ts">
import PerformanceTable from '@/components/PerformanceTable.vue';
import type { Performance } from '@/lib/performance';

defineProps<{ performances: Performance[] }>();
</script>

<template>
    <section data-section="performance" class="flex flex-col gap-6 px-6">
        <h2 class="leading-none font-semibold">Performance</h2>
        <PerformanceTable :performances="performances" />
    </section>
</template>
