<script setup lang="ts">
import { eur } from '@/lib/format';
import type { RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview }>();
</script>

<template>
    <section data-section="real-estate-summary" class="flex shrink-0 flex-col gap-1.5 px-6">
        <p data-real-estate-net class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
            {{ eur(props.realEstate.totalNetWorth, 0) }}
        </p>

        <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
            <span class="whitespace-nowrap">
                Estimé
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalValue, 0) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Restant dû
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalRemaining, 0) }}
                </strong>
            </span>
        </p>
    </section>
</template>
