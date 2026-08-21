<script setup lang="ts">
import { computed } from 'vue';
import { eur } from '@/lib/format';
import { equitySplitOf, type EquitySplit, type PropertyDetail } from '@/lib/realEstate';

const props = defineProps<{ property: PropertyDetail }>();

const split = computed<EquitySplit | null>(() => equitySplitOf(props.property));

/** Sans décimales : la barre situe une proportion, les centimes se lisent dans les repères. */
const amount = (value: number): string => eur(value, 0);
</script>

<template>
    <!-- Même barre que la répartition sectorielle : un total découpé se lit toujours pareil. -->
    <div v-if="split" data-equity-bar class="flex flex-col gap-1.5 pt-1">
        <div class="h-[7px] w-full overflow-hidden rounded-full bg-separator">
            <div
                data-equity-share
                class="h-full rounded-full bg-sector-bar"
                :style="{ width: `${split.equityShare}%` }"
            ></div>
        </div>

        <p data-equity-legend class="flex items-baseline justify-between gap-3 text-[13px] text-muted-foreground">
            <span>
                à moi
                <strong class="font-semibold tabular-nums text-foreground">{{ amount(split.equity) }}</strong>
            </span>
            <span>
                banque
                <strong class="font-semibold tabular-nums text-foreground">{{ amount(split.debt) }}</strong>
            </span>
        </p>
    </div>
</template>
