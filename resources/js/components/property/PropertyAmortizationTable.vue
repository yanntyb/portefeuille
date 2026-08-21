<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { eur, fractionPct, frMonthYear } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import type { AmortizationLine, LoanSummary } from '@/lib/realEstate';

const props = defineProps<{ loan: LoanSummary; lines?: AmortizationLine[] }>();

/** Mêmes paires libellé/valeur que l'en-tête : le résumé du prêt se lit comme celui du bien. */
const summary = computed<HeroMetaEntry[]>(() => [
    { label: 'Emprunté', value: eur(props.loan.principal) },
    { label: 'Taux', value: fractionPct(props.loan.annualRate) },
    { label: 'Mensualité', value: eur(props.loan.monthlyPayment) },
    { label: 'Durée', value: `${props.loan.termMonths} mois` },
    { label: 'Restant dû', value: eur(props.loan.remainingPrincipal) },
    { label: 'Coût total', value: eur(props.loan.totalCost) },
]);
</script>

<template>
    <section data-section="amortization" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Crédit</h2>

        <p data-loan-summary class="grid grid-cols-2 gap-x-8 gap-y-1.5 text-[13.5px] text-muted-foreground">
            <span
                v-for="entry in summary"
                :key="entry.label"
                class="flex items-baseline justify-between gap-3 whitespace-nowrap"
            >
                {{ entry.label }}
                <strong class="font-semibold tabular-nums text-foreground">{{ entry.value }}</strong>
            </span>
        </p>

        <Deferred data="amortization">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 6" :key="n" class="h-6 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">Données indisponibles hors-ligne.</p>
            </template>

            <div class="max-h-96 overflow-x-auto overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-background">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="py-1 pr-4 font-normal">Mois</th>
                            <th class="py-1 pr-4 text-right font-normal">Mensualité</th>
                            <th class="py-1 pr-4 text-right font-normal">Intérêts</th>
                            <th class="py-1 pr-4 text-right font-normal">Capital</th>
                            <th class="py-1 text-right font-normal">Restant dû</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in props.lines ?? []" :key="line.month" class="border-t border-separator">
                            <td class="py-1 pr-4">{{ frMonthYear(line.month) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.payment) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.interest) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.principal) }}</td>
                            <td class="py-1 text-right tabular-nums">{{ eur(line.remaining) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Deferred>
    </section>
</template>
