<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { PropertyOverview } from '@/lib/realEstate';

const props = defineProps<{ properties: PropertyOverview[] }>();

const hasProperties = computed<boolean>(() => props.properties.length > 0);
</script>

<template>
    <section data-section="real-estate" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Biens</h2>

        <ul v-if="hasProperties" class="flex flex-col gap-2">
            <li v-for="property in props.properties" :key="property.id" data-property-row>
                <Link
                    :href="`/properties/${property.id}`"
                    prefetch
                    class="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                >
                    <span class="truncate font-medium">{{ property.name }}</span>
                    <span class="flex shrink-0 items-center gap-3 tabular-nums">
                        <span class="text-muted-foreground">{{ eur(property.monthlyCashFlow) }}/mois</span>
                        <span class="font-semibold">{{ eur(property.netWorth) }}</span>
                    </span>
                </Link>
            </li>
        </ul>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Aucun bien immobilier pour l'instant.
        </p>
    </section>
</template>
