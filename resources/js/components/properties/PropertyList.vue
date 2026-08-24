<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { eur, gainClass, pct, sharePct as share, signedEur } from '@/lib/format';
import { propertyRows, type PropertyOverview, type PropertyRow } from '@/lib/realEstate';

const props = defineProps<{ properties: PropertyOverview[] }>();

const rows = computed<PropertyRow[]>(() => propertyRows(props.properties));
</script>

<template>
    <section data-section="real-estate" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Biens</h2>

        <!-- Deux lignes par bien, comme une position du portefeuille : la valeur et son gain
             d'abord, le poids dans le parc et la quittance ensuite. -->
        <ul v-if="rows.length" class="flex flex-col">
            <li
                v-for="row in rows"
                :key="row.id"
                data-property-row
                class="flex flex-col gap-1.5 border-b border-separator py-3 last:border-b-0"
            >
                <div class="flex items-center gap-3">
                    <Link
                        :href="`/properties/${row.id}`"
                        prefetch
                        data-property-name
                        class="block min-w-0 flex-1 truncate font-semibold hover:underline"
                    >
                        {{ row.name }}
                    </Link>

                    <span data-property-net class="w-24 shrink-0 text-right font-bold tabular-nums">
                        {{ eur(row.netWorth, 0) }}
                    </span>

                    <span
                        data-property-gain-pct
                        class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                        :class="gainClass(row.gainPct)"
                    >
                        {{ pct(row.gainPct) }}
                    </span>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
                        <span
                            data-property-bar
                            class="block h-full rounded-full bg-sector-bar"
                            :style="{ width: row.barWidth }"
                        ></span>
                    </span>

                    <span data-property-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                        {{ share(row.share) }}
                    </span>

                    <!-- La quittance sur le bord droit, sous le gain : la queue des deux lignes s'aligne. -->
                    <span
                        data-property-cash-flow
                        class="ml-auto shrink-0 text-right tabular-nums"
                        :class="gainClass(row.monthlyCashFlow)"
                    >
                        {{ signedEur(row.monthlyCashFlow, 0) }}/mois
                    </span>
                </div>
            </li>
        </ul>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Aucun bien immobilier pour l'instant.
        </p>
    </section>
</template>
