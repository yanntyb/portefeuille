<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import type { Performance } from '@/lib/performance';

defineProps<{ performances?: Performance[] }>();
</script>

<template>
    <section data-section="performances" class="flex flex-col gap-6 px-6">
        <h2 class="flex items-center gap-1 leading-none font-semibold">
            Performance par période
            <PerformanceInfoDialog variant="periods" />
        </h2>
        <Deferred data="performances">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 5" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <PerformanceBars
                v-if="performances && performances.length"
                :performances="performances"
            />
        </Deferred>
    </section>
</template>
