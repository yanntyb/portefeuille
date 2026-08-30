<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import PerformanceBars from '@/components/PerformanceBars.vue';
import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';
import type { Performance } from '@/lib/performance';

const props = defineProps<{ performances?: Performance[] | null }>();

const hasPerformances = computed<boolean>(() => (props.performances?.length ?? 0) > 0);
</script>

<template>
    <!-- Repliée à l'arrivée comme les autres : les performances ne se calculent qu'au dépli. -->
    <CollapsibleSection section="performances" title="Performances">
        <!-- L'aide se lit repliée : elle explique de quoi parle la section, pas ce qu'elle montre. -->
        <template #aside>
            <PerformanceInfoDialog variant="periods" />
        </template>

        <template v-if="props.performances !== null">
            <PerformanceBars v-if="hasPerformances" :performances="props.performances ?? []" />

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore de performance à mesurer.
            </p>
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
    </CollapsibleSection>
</template>
