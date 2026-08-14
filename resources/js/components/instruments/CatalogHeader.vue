<script setup lang="ts">
import { computed } from 'vue';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import { gainClass, pct } from '@/lib/format';
import { rangeOptions, type CatalogRow, type RangeKey } from '@/lib/catalog';

const props = defineProps<{
    rows: CatalogRow[];
    range: RangeKey;
    loading: boolean;
}>();

defineEmits<{ 'update:range': [value: RangeKey] }>();

const rated = computed<CatalogRow[]>(() => props.rows.filter((row) => row.changePct !== null));

const heldCount = computed<number>(() => props.rows.filter((row) => row.held).length);

const countLabel = computed<string>(() => {
    const instruments = `${props.rows.length} instrument${props.rows.length > 1 ? 's' : ''}`;

    return heldCount.value === 0
        ? instruments
        : `${instruments} · ${heldCount.value} détenu${heldCount.value > 1 ? 's' : ''}`;
});

const best = computed<CatalogRow | null>(() =>
    rated.value.reduce<CatalogRow | null>(
        (leader, row) => (leader === null || (row.changePct ?? 0) > (leader.changePct ?? 0) ? row : leader),
        null,
    ),
);

const worst = computed<CatalogRow | null>(() =>
    rated.value.reduce<CatalogRow | null>(
        (laggard, row) => (laggard === null || (row.changePct ?? 0) < (laggard.changePct ?? 0) ? row : laggard),
        null,
    ),
);

const risingCount = computed<number>(() => rated.value.filter((row) => (row.changePct ?? 0) > 0).length);

/** Calling the laggard a fall would be a lie on a period where everything went up. */
const worstLabel = computed<string>(() =>
    (worst.value?.changePct ?? 0) < 0 ? 'Pire baisse' : 'Plus faible hausse',
);
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

        <dl v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div v-for="slot in 3" :key="slot" class="flex flex-col gap-2">
                <span class="h-3 w-24 animate-pulse rounded bg-muted"></span>
                <span class="h-6 w-32 animate-pulse rounded bg-muted"></span>
            </div>
        </dl>

        <dl v-else-if="rated.length" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-1">
                <dt class="text-xs uppercase tracking-wide text-muted-foreground">Meilleure hausse</dt>
                <dd data-catalog-best class="truncate text-lg font-medium">
                    {{ best?.name }} <span class="tabular-nums" :class="gainClass(best?.changePct ?? null)">{{ pct(best?.changePct ?? null) }}</span>
                </dd>
            </div>

            <div class="flex flex-col gap-1">
                <dt data-catalog-worst-label class="text-xs uppercase tracking-wide text-muted-foreground">
                    {{ worstLabel }}
                </dt>
                <dd data-catalog-worst class="truncate text-lg font-medium">
                    {{ worst?.name }} <span class="tabular-nums" :class="gainClass(worst?.changePct ?? null)">{{ pct(worst?.changePct ?? null) }}</span>
                </dd>
            </div>

            <div class="flex flex-col gap-1">
                <dt class="text-xs uppercase tracking-wide text-muted-foreground">En hausse</dt>
                <dd data-catalog-up class="text-lg font-medium tabular-nums">
                    {{ risingCount }} / {{ rated.length }}
                </dd>
            </div>
        </dl>
    </header>
</template>
