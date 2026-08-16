<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur, gainClass, pct } from '@/lib/format';
import type { CatalogRow } from '@/lib/catalog';

type SortKey = 'name' | 'type' | 'change' | 'price' | 'value';

const props = defineProps<{
    rows: CatalogRow[];
    loading: boolean;
    emptyLabel: string;
}>();

const columns: { key: SortKey; label: string; numeric: boolean }[] = [
    { key: 'name', label: 'Nom', numeric: false },
    { key: 'type', label: 'Type', numeric: false },
    { key: 'change', label: 'Variation', numeric: true },
    { key: 'price', label: 'Dernier prix', numeric: true },
    { key: 'value', label: 'Valeur', numeric: true },
];

const sortKey = ref<SortKey>('name');
const descending = ref<boolean>(false);

/** Numbers read best from the biggest down, names from A to Z. */
const toggleSort = (key: SortKey): void => {
    if (sortKey.value === key) {
        descending.value = !descending.value;

        return;
    }

    sortKey.value = key;
    descending.value = columns.find((column) => column.key === key)?.numeric ?? false;
};

const sortValue = (row: CatalogRow): string | number | null => {
    switch (sortKey.value) {
        case 'name':
            return row.name;
        case 'type':
            return row.typeLabel;
        case 'change':
            return row.changePct;
        case 'price':
            return row.lastPrice;
        case 'value':
            return row.marketValue;
    }
};

const compare = (left: CatalogRow, right: CatalogRow): number => {
    const leftValue = sortValue(left);
    const rightValue = sortValue(right);

    return typeof leftValue === 'string' && typeof rightValue === 'string'
        ? leftValue.localeCompare(rightValue, 'fr')
        : Number(leftValue) - Number(rightValue);
};

/** Rows without a value for the sorted column stay at the bottom, whatever the direction. */
const sortedRows = computed<CatalogRow[]>(() => {
    const sortable = props.rows.filter((row) => sortValue(row) !== null);
    const unsortable = props.rows.filter((row) => sortValue(row) === null);

    sortable.sort((left, right) => (descending.value ? -1 : 1) * compare(left, right));

    return [...sortable, ...unsortable];
});

/** Bars are scaled against the strongest move so the calmest instrument stays visible. */
const largestMove = computed<number>(() =>
    Math.max(0, ...props.rows.map((row) => Math.abs(row.changePct ?? 0))),
);

const moveWidth = (row: CatalogRow): string =>
    largestMove.value > 0 ? `${(Math.abs(row.changePct ?? 0) / largestMove.value) * 100}%` : '0%';

const moveColour = (row: CatalogRow): string =>
    (row.changePct ?? 0) >= 0 ? 'bg-emerald-500/70' : 'bg-red-500/70';

const sortMark = (key: SortKey): string =>
    sortKey.value !== key ? '' : descending.value ? '↓' : '↑';
</script>

<template>
    <section data-section="catalog" class="flex min-w-0 flex-col px-6">
        <Table v-if="rows.length">
            <TableHeader>
                <TableRow>
                    <TableHead
                        v-for="column in columns"
                        :key="column.key"
                        :class="column.numeric ? 'text-right' : ''"
                    >
                        <button
                            type="button"
                            :data-sort="column.key"
                            class="inline-flex items-center gap-1 transition-colors hover:text-foreground"
                            :class="sortKey === column.key ? 'text-foreground' : ''"
                            @click="toggleSort(column.key)"
                        >
                            {{ column.label }}
                            <span class="w-2 text-xs">{{ sortMark(column.key) }}</span>
                        </button>
                    </TableHead>
                    <TableHead class="w-20 text-right">Tendance</TableHead>
                </TableRow>
            </TableHeader>

            <TableBody>
                <TableRow
                    v-for="row in sortedRows"
                    :key="row.id"
                    data-catalog-row
                    :data-held="row.held ? 'true' : 'false'"
                    class="group cursor-pointer hover:bg-muted/50"
                >
                    <TableCell class="font-medium">
                        <Link :href="`/instruments/${row.id}`" prefetch class="flex items-center gap-2">
                            <span
                                class="size-1.5 shrink-0 rounded-full"
                                :class="row.held ? 'bg-foreground' : 'bg-transparent ring-1 ring-border'"
                                :title="row.held ? 'Détenu' : 'Suivi'"
                            ></span>
                            <span data-catalog-name class="min-w-0 truncate group-hover:underline">
                                {{ row.name }}
                                <span v-if="row.ticker" class="text-muted-foreground">({{ row.ticker }})</span>
                            </span>
                        </Link>
                    </TableCell>

                    <TableCell>
                        <span
                            data-catalog-type
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ row.typeLabel }}
                        </span>
                    </TableCell>

                    <TableCell class="text-right">
                        <span v-if="loading" class="ml-auto block h-4 w-16 animate-pulse rounded bg-muted"></span>
                        <span v-else class="flex flex-col items-end gap-1">
                            <span
                                data-catalog-change
                                class="text-sm font-medium tabular-nums"
                                :class="gainClass(row.changePct)"
                            >
                                {{ pct(row.changePct) }}
                            </span>
                            <span class="hidden h-1 w-16 overflow-hidden rounded-full bg-muted sm:block">
                                <span
                                    data-catalog-move
                                    class="ml-auto block h-full rounded-full"
                                    :class="moveColour(row)"
                                    :style="{ width: moveWidth(row) }"
                                ></span>
                            </span>
                        </span>
                    </TableCell>

                    <TableCell data-catalog-price class="text-right tabular-nums">
                        {{ eur(row.lastPrice) }}
                    </TableCell>

                    <TableCell data-catalog-value class="text-right font-medium tabular-nums">
                        {{ eur(row.marketValue, 0) }}
                    </TableCell>

                    <TableCell class="text-right">
                        <span v-if="loading" class="ml-auto block h-5 w-16 animate-pulse rounded bg-muted"></span>
                        <Sparkline v-else-if="row.points.length > 1" class="ml-auto" :values="row.points" />
                        <span v-else class="text-muted-foreground">—</span>
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>

        <p v-else data-catalog-empty class="py-8 text-center text-sm text-muted-foreground">{{ emptyLabel }}</p>
    </section>
</template>
