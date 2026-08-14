<script setup lang="ts">
import HoldingsList from '@/components/HoldingsList.vue';
import type { EvolutionSeries, HoldingLine } from '@/lib/portfolio';

defineProps<{
    holdings: HoldingLine[];
    hiddenAssetIds: Set<number>;
    series?: EvolutionSeries;
}>();

defineEmits<{ toggle: [assetId: number] }>();
</script>

<template>
    <section data-section="holdings" class="flex flex-col gap-6 px-6">
        <h2 class="leading-none font-semibold">Positions</h2>
        <div class="min-w-0">
            <HoldingsList
                v-if="holdings.length"
                :holdings="holdings"
                :hidden-asset-ids="hiddenAssetIds"
                :series="series"
                @toggle="$emit('toggle', $event)"
            />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune position pour le moment.
            </p>
        </div>
    </section>
</template>
