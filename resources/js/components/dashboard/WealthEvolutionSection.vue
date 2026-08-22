<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import { buildWealthStackOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { WealthSeries } from '@/lib/wealth';

const props = defineProps<{ series?: WealthSeries }>();

/** Echarts pèse les deux tiers du JS : il n'est demandé qu'au montage réel d'un graphe. */
const BaseChart = defineAsyncComponent({
    loader: () => import('@/components/BaseChart.vue'),
    loadingComponent: ChartSkeleton,
    delay: 0,
});

/** Non réactive, comme sur la fiche instrument : la rendre réactive repeindrait à chaque pixel. */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const labels = computed<string[]>(() => props.series?.labels ?? []);

const hasHistory = computed<boolean>(() => labels.value.length > 0);

const option = computed<ChartOption>(() => buildWealthStackOption({
    labels: labels.value,
    securities: props.series?.securities ?? [],
    realEstate: props.series?.realEstate ?? [],
    invested: props.series?.invested ?? [],
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: lastZoom,
    description: 'Patrimoine total, titres et immobilier empilés, comparé au montant investi.',
}));
</script>

<template>
    <section data-section="wealth-evolution" class="flex shrink-0 flex-col gap-4">
        <h2 class="px-6 text-[17px] leading-none font-bold">Évolution</h2>

        <Deferred data="series">
            <template #fallback>
                <div class="px-6">
                    <ChartSkeleton />
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <div v-if="hasHistory" class="px-6">
                <BaseChart :option="option" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
