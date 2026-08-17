<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import CatalogHeader from '@/components/instruments/CatalogHeader.vue';
import CatalogList from '@/components/instruments/CatalogList.vue';
import { filterCatalog, isRangeKey, joinTrends, type CatalogLine, type CatalogRow, type CatalogTrend, type RangeKey } from '@/lib/catalog';

const props = defineProps<{
    catalog: { lines: CatalogLine[] };
    catalogRange?: string;
    trends?: CatalogTrend[];
}>();

const selectedRange = ref<RangeKey>(isRangeKey(props.catalogRange) ? props.catalogRange : 'max');
const reloading = ref<boolean>(false);

/** The catalogue itself ships with the page; only the trends of the selected period are deferred. */
const loading = computed<boolean>(() => props.trends === undefined || reloading.value);

const query = ref<string>('');

/** Le catalogue tient entier dans la page : la recherche filtre en mémoire, sans aller-retour serveur. */
const rows = computed<CatalogRow[]>(() =>
    filterCatalog(joinTrends(props.catalog.lines, props.trends), query.value),
);

const emptyLabel = computed<string>(() =>
    query.value.trim() === ''
        ? 'Aucun instrument connu.'
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
    <Head title="Instruments" />

    <AppPage width="wide">
        <CatalogHeader
            v-model:query="query"
            :rows="rows"
            :range="selectedRange"
            @update:range="selectRange"
        />

        <CatalogList :rows="rows" :loading="loading" :empty-label="emptyLabel" />
    </AppPage>

    <AppBreadcrumb width="wide" :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Instruments' }]" />
</template>
