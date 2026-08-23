<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import type { Performance } from '@/lib/performance';

const props = defineProps<{ performances?: Performance[] | null }>();
</script>

<template>
    <section data-section="performances" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="flex items-center gap-1 text-[17px] leading-none font-bold">
            Performances
            <PerformanceInfoDialog variant="periods" />
        </h2>
        <template v-if="props.performances !== null">
            <PerformanceBars
                v-if="props.performances && props.performances.length"
                :performances="props.performances"
            />
        </template>

        <Deferred v-else data="performances">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 5" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </section>
</template>
