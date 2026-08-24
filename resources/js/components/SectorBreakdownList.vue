<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { eur as formatEur, sharePct as share } from '@/lib/format';
import { collapsedSectors, type SectorBreakdownRow, type SectorView } from '@/lib/sector';

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

const isExpanded = ref<boolean>(false);

const view = computed<SectorView>(() => collapsedSectors(props.rows, isExpanded.value));

const amount = (value: number): string => formatEur(value, 0);
</script>

<template>
    <!-- `grow` et non `flex-1` : sans hauteur libre à distribuer la liste garde exactement sa taille naturelle. -->
    <div class="flex min-h-0 flex-col">
        <ul class="flex min-h-0 grow flex-col gap-4 overflow-y-auto overscroll-y-contain md:overflow-visible">
            <li v-for="entry in view.rows" :key="entry.row.label" class="flex max-h-20 grow flex-col justify-center gap-1.5 md:max-h-none md:grow-0">
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
            <Button
                variant="outline"
                size="sm"
                data-sector-toggle
                class="text-[13.5px] text-muted-foreground"
                @click="isExpanded = !isExpanded"
            >
                {{ isExpanded ? 'Réduire' : 'Voir plus' }}
            </Button>
        </div>
    </div>
</template>
