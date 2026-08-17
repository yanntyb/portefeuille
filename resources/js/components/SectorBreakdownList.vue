<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { eur as formatEur } from '@/lib/format';
import { collapsedSectors, type SectorBreakdownRow, type SectorView } from '@/lib/sector';

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

const isExpanded = ref<boolean>(false);

const view = computed<SectorView>(() => collapsedSectors(props.rows, isExpanded.value));

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const amount = (value: number): string => formatEur(value, 0);
</script>

<template>
    <div>
        <ul class="flex flex-col gap-4">
            <li v-for="entry in view.rows" :key="entry.row.label" class="flex flex-col gap-1.5">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                    <span data-sector-label class="min-w-0 break-words font-semibold">{{ entry.row.label }}</span>
                    <span class="text-[13.5px] tabular-nums text-subtle-foreground">
                        <template v-if="entry.row.amount !== null">
                            <span data-sector-amount>{{ amount(entry.row.amount) }}</span> ·
                        </template>
                        <span data-sector-share>{{ share(entry.row.share) }}</span>
                    </span>
                </div>
                <div class="h-[7px] w-full overflow-hidden rounded-full bg-separator">
                    <div
                        data-sector-bar
                        class="h-full rounded-full bg-sector-bar"
                        :style="{ width: entry.barWidth }"
                    ></div>
                </div>
            </li>
        </ul>

        <div v-if="view.hiddenCount > 0" class="flex justify-center pt-5">
            <Button variant="outline" size="sm" class="text-[13.5px] text-muted-foreground" @click="isExpanded = !isExpanded">
                {{ isExpanded ? 'Réduire' : `Voir les ${view.hiddenCount} autres` }}
            </Button>
        </div>
    </div>
</template>
