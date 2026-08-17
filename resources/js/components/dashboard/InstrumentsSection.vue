<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import InstrumentSearch from '@/components/InstrumentSearch.vue';
import { isRangeKey, rangeOptions, type CatalogLine, type CatalogTrend, type RangeKey } from '@/lib/catalog';
import { mergeInstrumentRows, visibleInstrumentRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = defineProps<{
    holdings: HoldingLine[];
    catalog?: { lines: CatalogLine[] };
    catalogRange?: string;
    trends?: CatalogTrend[];
}>();

const query = ref<string>('');
const selectedRange = ref<RangeKey>(isRangeKey(props.catalogRange) ? props.catalogRange : 'max');
const reloading = ref<boolean>(false);

/** Le catalogue et ses tendances arrivent différés ; seul le squelette des tendances est visible. */
const loading = computed<boolean>(() => props.trends === undefined || reloading.value);

const rows = computed<InstrumentRow[]>(() =>
    visibleInstrumentRows(
        mergeInstrumentRows(props.holdings, props.catalog?.lines, props.trends),
        query.value,
    ),
);

/** Sans recherche la liste est le portefeuille : son vide parle de positions, pas d'instruments. */
const emptyLabel = computed<string>(() =>
    query.value.trim() === ''
        ? 'Aucune position pour le moment.'
        : 'Aucun instrument ne correspond à cette recherche.',
);

const selectRange = (key: RangeKey): void => {
    selectedRange.value = key;

    router.reload({
        only: ['trends'],
        data: { range: key },
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};
</script>

<template>
    <!-- La section s'étire : sur mobile c'est elle, et pas le bas de la page, qui porte l'espace libre. -->
    <section
        data-section="instruments"
        class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none"
        aria-label="Instruments"
    >
        <div class="flex shrink-0 gap-4">
            <InstrumentSearch v-model="query" />
<!--            <ChartRangeToggle
                :options="rangeOptions"
                :model-value="selectedRange"
                @update:model-value="selectRange"
            />-->
        </div>

        <InstrumentList :rows="rows" :loading="loading" :empty-label="emptyLabel" />
    </section>
</template>
