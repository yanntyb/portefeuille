<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import { eur, frMonthYear, gainClass, signedEur } from '@/lib/format';
import { cashFlowYears, type CashFlowYear, type MonthlyCashFlow } from '@/lib/realEstate';

const props = defineProps<{ flows: MonthlyCashFlow[] }>();

const years = computed<CashFlowYear[]>(() => cashFlowYears(props.flows));

/**
 * Seule l'année la plus récente s'ouvre : les précédentes relèvent de l'archive. « La plus
 * récente » et non l'année civile courante — en janvier, la fenêtre glissante n'a qu'un mois de
 * l'année en cours, et un groupe ouvert vaut mieux qu'une section entièrement repliée.
 */
const openYears = ref<string[]>(years.value.slice(0, 1).map((group) => group.year));

const isYearOpen = (year: string): boolean => openYears.value.includes(year);

const toggleYear = (year: string): void => {
    openYears.value = isYearOpen(year)
        ? openYears.value.filter((open) => open !== year)
        : [...openYears.value, year];
};

/** Une seule ligne détaillée à la fois : le détail se lit en regard de la ligne, pas en liste. */
const openMonth = ref<string | null>(null);

const toggleMonth = (month: string): void => {
    openMonth.value = openMonth.value === month ? null : month;
};
</script>

<template>
    <section data-section="cash-flow" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Cash-flow mensuel</h2>

        <div v-if="years.length" class="flex min-w-0 flex-col">
            <div
                v-for="group in years"
                :key="group.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-cash-flow-year="group.year"
                    class="flex items-center gap-1.5 py-3 text-sm"
                    :aria-expanded="isYearOpen(group.year)"
                    @click="toggleYear(group.year)"
                >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="isYearOpen(group.year) ? 'rotate-90' : ''"
                    />
                    <span class="font-semibold">{{ group.year }}</span>
                    <span class="ml-auto font-semibold tabular-nums" :class="gainClass(group.net)">
                        {{ signedEur(group.net) }}
                    </span>
                </button>

                <div v-if="isYearOpen(group.year)" class="flex flex-col pb-2">
                    <template v-for="month in group.months" :key="month.month">
                        <button
                            type="button"
                            :data-cash-flow-month="month.month"
                            class="flex items-center gap-3 py-2 pl-[22px] text-sm"
                            :aria-expanded="openMonth === month.month"
                            @click="toggleMonth(month.month)"
                        >
                            <span class="text-muted-foreground">{{ frMonthYear(month.month) }}</span>
                            <span class="ml-auto font-medium tabular-nums" :class="gainClass(month.net)">
                                {{ signedEur(month.net) }}
                            </span>
                        </button>

                        <p
                            v-if="openMonth === month.month"
                            data-cash-flow-detail
                            class="pb-2 pl-[22px] text-xs text-muted-foreground"
                        >
                            loyers {{ eur(month.rents) }} · charges {{ eur(month.expenses) }} ·
                            crédit {{ eur(month.loanPayment) }}
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">Aucun mouvement sur ce bien.</p>
    </section>
</template>
