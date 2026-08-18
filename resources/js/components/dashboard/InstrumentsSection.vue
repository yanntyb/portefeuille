<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import InstrumentSearch from '@/components/InstrumentSearch.vue';
import { isRangeKey, rangeOptions, type CatalogLine, type CatalogTrend, type RangeKey } from '@/lib/catalog';
import { instrumentSections, isCatalogLoading, mergeInstrumentRows, type InstrumentSection } from '@/lib/instrumentList';
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
const page = usePage();

/** Le catalogue et ses tendances arrivent différés ; seul le squelette des tendances est visible. */
const loading = computed<boolean>(() => isCatalogLoading(
    props.trends,
    page.rescuedProps,
    reloading.value,
));

const sections = computed<InstrumentSection[]>(() =>
    instrumentSections(
        mergeInstrumentRows(props.holdings, props.catalog?.lines, props.trends),
        query.value,
        props.catalog === undefined,
    ),
);

/** La liste montre tout le catalogue : son vide parle d'instruments, pas seulement de positions. */
const emptyLabel = computed<string>(() =>
    query.value.trim() === ''
        ? 'Aucun instrument pour le moment.'
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
        </div>

        <InstrumentList :sections="sections" :loading="loading" :empty-label="emptyLabel" />
    </section>
</template>
