<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { AmortizationLine, LoanSummary } from '@/lib/realEstate';

const props = defineProps<{ loan: LoanSummary; lines?: AmortizationLine[] }>();

/** `T00:00:00` évite le décalage d'un jour qu'un parsing UTC infligerait à une date sans heure. */
const frMonth = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
};

const pct = (ratio: number): string => `${(ratio * 100).toFixed(2).replace('.', ',')} %`;
</script>

<template>
    <section data-section="amortization" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Crédit</h2>

        <p class="text-sm text-muted-foreground">
            {{ eur(props.loan.principal) }} à {{ pct(props.loan.annualRate) }} sur
            {{ props.loan.termMonths }} mois ·
            {{ eur(props.loan.monthlyPayment) }}/mois ·
            {{ eur(props.loan.remainingPrincipal) }} restant dus ·
            coût total {{ eur(props.loan.totalCost) }}
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
                            <td class="py-1 pr-4">{{ frMonth(line.month) }}</td>
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
