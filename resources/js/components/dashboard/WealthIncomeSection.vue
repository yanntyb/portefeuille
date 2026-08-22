<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { eur, gainClass, signedEur } from '@/lib/format';
import type { WealthIncome } from '@/lib/wealth';

const props = defineProps<{ income?: WealthIncome }>();

const hasIncome = computed<boolean>(() => (props.income?.monthlyTotal ?? 0) !== 0);
</script>

<template>
    <!--
        Un seul chiffre net : les dividendes des douze derniers mois mensualisés, plus le locatif
        déjà net de charges et d'échéances. Le détail par origine se lit sur /instruments et
        /properties.
    -->
    <section data-section="wealth-income" class="flex shrink-0 flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Revenus</h2>

        <Deferred data="income">
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

            <template v-if="hasIncome">
                <p class="flex flex-wrap items-baseline gap-2">
                    <span
                        data-income-monthly
                        class="text-2xl font-bold tabular-nums"
                        :class="gainClass(props.income?.monthlyTotal ?? 0)"
                    >
                        {{ signedEur(props.income?.monthlyTotal ?? 0) }}
                    </span>
                    <span class="text-[13.5px] text-muted-foreground">par mois</span>
                </p>

                <ul class="flex flex-col gap-2 text-sm">
                    <li data-income-origin class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Dividendes</span>
                        <span class="font-semibold tabular-nums">
                            {{ eur(props.income?.monthlyDividends ?? 0) }}
                        </span>
                    </li>
                    <li data-income-origin class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Locatif net</span>
                        <span
                            class="font-semibold tabular-nums"
                            :class="gainClass(props.income?.monthlyRentalNet ?? 0)"
                        >
                            {{ signedEur(props.income?.monthlyRentalNet ?? 0) }}
                        </span>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu pour l'instant.
            </p>
        </Deferred>
    </section>
</template>
