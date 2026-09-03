<script setup lang="ts">
import { computed } from 'vue';
import { AsyncBaseChart } from '@/components/AsyncBaseChart';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import { buildWealthStackOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { WealthSeries } from '@/lib/wealth';

const props = defineProps<{ series?: WealthSeries | null }>();

/** Non réactive, comme sur la fiche instrument : la rendre réactive repeindrait à chaque pixel. */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const labels = computed<string[]>(() => props.series?.labels ?? []);

const hasHistory = computed<boolean>(() => labels.value.length > 0);

const option = computed<ChartOption>(() => buildWealthStackOption({
    labels: labels.value,
    classes: props.series?.classes ?? [],
    invested: props.series?.invested ?? [],
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: lastZoom,
    description: 'Patrimoine total, ses classes d\'actif empilées, comparé au montant investi.',
}));
</script>

<template>
    <section data-section="wealth-evolution" class="flex shrink-0 flex-col gap-4">
        <template v-if="props.series !== null">
            <div v-if="hasHistory" class="px-6">
                <AsyncBaseChart :option="option" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </template>

        <DeferredBlock v-else data="series">
            <template #fallback>
                <div class="px-6">
                    <ChartSkeleton />
                </div>
            </template>
        </DeferredBlock>
    </section>
</template>
