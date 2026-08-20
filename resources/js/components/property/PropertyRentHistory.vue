<script setup lang="ts">
import { eur } from '@/lib/format';
import { rentMonthStatus, type RentMonth } from '@/lib/realEstate';

const props = defineProps<{ months: RentMonth[] }>();

/** `T00:00:00` évite le décalage d'un jour qu'un parsing UTC infligerait à une date sans heure. */
const frMonth = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
};

/**
 * « Partiel » et « impayé » partagent la même teinte : l'app n'a que deux tons sémantiques
 * (gain/loss) en plus du neutre, et le libellé affiché à côté du montant distingue déjà les deux
 * cas. Inventer une troisième teinte (ambre…) romprait avec ce vocabulaire binaire.
 */
const statusClass = (month: RentMonth): string => {
    const status = rentMonthStatus(month);
    if (status === 'impayé' || status === 'partiel') {
        return 'text-loss';
    }
    if (status === 'vacance') {
        return 'text-muted-foreground';
    }
    return '';
};
</script>

<template>
    <section data-section="rents" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Loyers</h2>

        <ul class="flex flex-col">
            <li
                v-for="month in props.months"
                :key="month.month"
                class="flex items-center justify-between border-t border-separator py-1.5 text-sm first:border-t-0"
            >
                <span>{{ frMonth(month.month) }}</span>
                <span class="flex items-center gap-2 tabular-nums" :class="statusClass(month)">
                    <span class="text-xs">{{ rentMonthStatus(month) !== 'plein' ? rentMonthStatus(month) : '' }}</span>
                    <span class="font-medium">{{ eur(month.effective) }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
