<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import HoldingsList from '@/components/HoldingsList.vue';
import type { EvolutionSeries, HoldingLine } from '@/lib/portfolio';
import PerformanceInfoDialog from "@/components/PerformanceInfoDialog.vue";

const VISIBLE_HOLDINGS = 10;

defineProps<{
    holdings: HoldingLine[];
    series?: EvolutionSeries;
}>();
</script>

<template>

    <section data-section="holdings" class="flex flex-col gap-6 px-6" aria-label="Positions">
        <div class="min-w-0">
            <HoldingsList
                v-if="holdings.length"
                :holdings="holdings"
                :series="series"
                :limit="VISIBLE_HOLDINGS"
            />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune position pour le moment.
            </p>
        </div>

        <Link
            href="/instruments"
            data-holdings-all
            class="self-center rounded-md border border-border px-4 py-2 text-[13.5px] font-medium text-muted-foreground transition-colors hover:text-foreground"
        >
            Voir plus
        </Link>
    </section>
</template>
