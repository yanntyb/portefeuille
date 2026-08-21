<script setup lang="ts">
import { computed } from 'vue';
import HeroFigures from '@/components/HeroFigures.vue';
import { frLongDate, pct } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import { capitalGainOf, capitalGainPctOf, propertyHeroMeta, type PropertyDetail } from '@/lib/realEstate';

const props = defineProps<{ property: PropertyDetail }>();

/** Le grand chiffre est le patrimoine net, comme la valeur de marché l'est pour une position. */
const gain = computed<number>(() => capitalGainOf(props.property));

const gainPct = computed<number | null>(() => capitalGainPctOf(props.property));

const metaEntries = computed<HeroMetaEntry[]>(() => propertyHeroMeta(props.property));

const gainLabel = computed<string | null>(() => (gainPct.value === null ? null : pct(gainPct.value)));
</script>

<template>
    <header data-section="hero" class="flex flex-col gap-4 px-6">
        <div class="flex flex-col gap-0.5">
            <h1 class="text-xl font-bold">{{ props.property.name }}</h1>
            <p class="text-[13px] font-medium text-subtle-foreground">
                <template v-if="props.property.address">{{ props.property.address }} · </template>
                acquis le {{ frLongDate(props.property.acquisitionDate) }}
            </p>
        </div>

        <HeroFigures
            :value="props.property.netWorth"
            :gain="gain"
            :gain-label="gainLabel"
            :entries="metaEntries"
        />
    </header>
</template>
