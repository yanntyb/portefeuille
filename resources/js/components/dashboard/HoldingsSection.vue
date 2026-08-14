<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import HoldingsList from '@/components/HoldingsList.vue';
import type { EvolutionSeries, HoldingLine } from '@/lib/portfolio';

const VISIBLE_HOLDINGS = 10;

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
                :limit="VISIBLE_HOLDINGS"
                @toggle="$emit('toggle', $event)"
            />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune position pour le moment.
            </p>
        </div>

        <Link
            href="/instruments"
            data-holdings-all
            class="self-center rounded-md border border-border px-3 py-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
            Voir tous les instruments
        </Link>
    </section>
</template>
