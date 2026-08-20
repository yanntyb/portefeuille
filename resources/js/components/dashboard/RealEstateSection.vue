<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Link } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { PropertyOverview, RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate?: RealEstateOverview }>();

const properties = computed<PropertyOverview[]>(() => props.realEstate?.properties ?? []);

const hasProperties = computed<boolean>(() => properties.value.length > 0);
</script>

<template>
    <section data-section="real-estate" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Immobilier</h2>

        <Deferred data="realEstate">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 2" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <template v-if="hasProperties">
                <p class="text-sm text-muted-foreground">
                    <span data-real-estate-net class="font-semibold text-foreground">
                        {{ eur(props.realEstate?.totalNetWorth ?? 0) }}
                    </span>
                    de patrimoine net ·
                    {{ eur(props.realEstate?.totalValue ?? 0) }} estimés,
                    {{ eur(props.realEstate?.totalRemaining ?? 0) }} restant dus
                </p>

                <ul class="flex flex-col gap-2">
                    <li v-for="property in properties" :key="property.id" data-property-row>
                        <Link
                            :href="`/properties/${property.id}`"
                            prefetch
                            class="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                        >
                            <span class="truncate font-medium">{{ property.name }}</span>
                            <span class="flex shrink-0 items-center gap-3 tabular-nums">
                                <span class="text-muted-foreground">
                                    {{ eur(property.monthlyCashFlow) }}/mois
                                </span>
                                <span class="font-semibold">{{ eur(property.netWorth) }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun bien immobilier pour l'instant.
            </p>
        </Deferred>
    </section>
</template>
