<script setup lang="ts">
import { computed } from 'vue';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import CatalogSearch from '@/components/instruments/CatalogSearch.vue';
import { catalogCount, rangeOptions, type CatalogRow, type RangeKey } from '@/lib/catalog';

const props = defineProps<{
    rows: CatalogRow[];
    range: RangeKey;
}>();

defineEmits<{ 'update:range': [value: RangeKey] }>();

const query = defineModel<string>('query', { required: true });

const countLabel = computed<string>(() => catalogCount(props.rows));
</script>

<template>
    <header data-section="catalog-header" class="flex flex-col gap-5 px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col gap-0.5">
                <h1 class="text-xl font-semibold">Instruments</h1>
                <p data-catalog-count class="text-sm text-muted-foreground">{{ countLabel }}</p>
            </div>

            <ChartRangeToggle
                :options="rangeOptions"
                :model-value="props.range"
                @update:model-value="$emit('update:range', $event)"
            />
        </div>

        <CatalogSearch v-model="query" />
    </header>
</template>
