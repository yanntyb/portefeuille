<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { eur, gainClass, signedEur } from '@/lib/format';
import { rentalIncomeBars, type RealEstateIncome, type RentalIncomeBar } from '@/lib/realEstate';

const props = defineProps<{ income?: RealEstateIncome | null }>();

const bars = computed<RentalIncomeBar[]>(() => rentalIncomeBars(props.income?.years ?? []));

const rounded = (value: number): string => eur(value, 0);
</script>

<template>
    <!-- Même titre que la section des titres : c'est la même question posée à l'autre moitié
         du patrimoine, et le net est déjà net de charges et d'échéances. -->
    <section data-section="rental-income" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Revenus</h2>

        <template v-if="props.income !== null">
            <template v-if="bars.length">
                <p data-rental-income-summary class="text-sm text-muted-foreground">
                    <span class="font-semibold tabular-nums" :class="gainClass(props.income?.net12m ?? 0)">
                        {{ rounded(props.income?.net12m ?? 0) }}
                    </span>
                    nets sur douze mois, après
                    <span class="tabular-nums">{{ rounded(props.income?.expenses12m ?? 0) }}</span>
                    de charges et
                    <span class="tabular-nums">{{ rounded(props.income?.loanPayments12m ?? 0) }}</span>
                    d'échéances
                </p>

                <ul class="flex min-h-0 grow flex-col gap-3">
                    <li
                        v-for="bar in bars"
                        :key="bar.year.year"
                        data-rental-income-year
                        :title="bar.title"
                        class="flex max-h-12 grow items-center gap-3 text-sm md:max-h-none md:grow-0"
                    >
                        <span data-rental-income-label class="w-12 shrink-0 font-semibold tabular-nums">
                            {{ bar.year.year }}
                        </span>

                        <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-separator">
                            <span
                                data-rental-income-bar
                                class="block h-full rounded-full"
                                :class="bar.barColor"
                                :style="{ width: bar.barWidth }"
                            ></span>
                        </span>

                        <span
                            data-rental-income-net
                            class="w-24 shrink-0 text-right font-semibold tabular-nums"
                            :class="gainClass(bar.year.net)"
                        >
                            {{ signedEur(bar.year.net, 0) }}
                        </span>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu locatif pour l'instant.
            </p>
        </template>

        <Deferred v-else data="income">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </section>
</template>
