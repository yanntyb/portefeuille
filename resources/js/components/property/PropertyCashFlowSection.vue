<script setup lang="ts">
import { eur } from '@/lib/format';
import type { MonthlyCashFlow } from '@/lib/realEstate';

const props = defineProps<{ flows: MonthlyCashFlow[] }>();

/** `T00:00:00` évite le décalage d'un jour qu'un parsing UTC infligerait à une date sans heure. */
const frMonth = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
};
</script>

<template>
    <section data-section="cash-flow" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Cash-flow mensuel</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted-foreground">
                        <th class="py-1 pr-4 font-normal">Mois</th>
                        <th class="py-1 pr-4 text-right font-normal">Loyers</th>
                        <th class="py-1 pr-4 text-right font-normal">Charges</th>
                        <th class="py-1 pr-4 text-right font-normal">Crédit</th>
                        <th class="py-1 text-right font-normal">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="flow in props.flows" :key="flow.month" class="border-t border-separator">
                        <td class="py-1.5 pr-4">{{ frMonth(flow.month) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.rents) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.expenses) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.loanPayment) }}</td>
                        <td
                            class="py-1.5 text-right font-semibold tabular-nums"
                            :class="flow.net < 0 ? 'text-loss' : ''"
                        >
                            {{ eur(flow.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
