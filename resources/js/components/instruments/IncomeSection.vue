<script setup lang="ts">
import { computed } from 'vue';
import DeferredBlock from '@/components/DeferredBlock.vue';
import { eur } from '@/lib/format';
import { annualIncomeBars, type AnnualIncome, type AnnualIncomeBar, type IncomeSummary } from '@/lib/income';

const props = defineProps<{ income?: IncomeSummary | null; annualIncome?: AnnualIncome[] | null }>();

const bars = computed<AnnualIncomeBar[]>(() => annualIncomeBars(props.annualIncome ?? []));

const hasIncome = computed<boolean>(() => (props.income?.totalReceived ?? 0) > 0);
</script>

<template>
    <!--
        Titre « Revenus » et non « Dividendes » : c'est cette section qui accueillera les autres
        origines de revenu, et la renommer plus tard déplacerait un repère déjà acquis.
    -->
    <section data-section="income" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Revenus</h2>

        <template v-if="props.income !== null">
            <template v-if="hasIncome">
                <p class="text-sm text-muted-foreground">
                    <span data-income-total class="font-semibold text-foreground">
                        {{ eur(props.income?.totalReceived ?? 0) }}
                    </span>
                    perçus, dont
                    <span data-income-last12>{{ eur(props.income?.last12Months ?? 0) }}</span>
                    sur douze mois
                    <template v-if="props.income && props.income.estimatedAnnual > 0">
                        · <span data-income-estimate>~{{ eur(props.income.estimatedAnnual) }}</span> estimés sur les douze prochains
                    </template>
                </p>

                <ul class="flex min-h-0 grow flex-col gap-3">
                    <li
                        v-for="bar in bars"
                        :key="bar.year"
                        data-income-year
                        class="flex max-h-12 grow items-center gap-3 text-sm md:max-h-none md:grow-0"
                    >
                        <span class="w-12 shrink-0 font-semibold tabular-nums">{{ bar.year }}</span>

                        <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-separator">
                            <span
                                data-income-bar
                                class="block h-full rounded-full bg-sector-bar"
                                :style="{ width: bar.barWidth }"
                            ></span>
                        </span>

                        <span class="w-24 shrink-0 text-right font-semibold tabular-nums">
                            {{ eur(bar.total, 0) }}
                        </span>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu perçu pour l'instant.
            </p>
        </template>

        <DeferredBlock v-else data="income" />
    </section>
</template>
