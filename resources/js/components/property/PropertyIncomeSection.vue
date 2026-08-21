<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import { eur, frMonthYear, gainClass, signedEur } from '@/lib/format';
import {
    incomeYears,
    type ExpenseYear,
    type IncomeMonth,
    type IncomeYear,
    type MonthlyCashFlow,
    type RentMonth,
} from '@/lib/realEstate';

const props = defineProps<{
    flows: MonthlyCashFlow[];
    rents: RentMonth[];
    expenseYears: ExpenseYear[];
}>();

const years = computed<IncomeYear[]>(() => incomeYears(props.flows, props.rents, props.expenseYears));

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

/**
 * « Partiel » et « impayé » partagent la même teinte : l'app n'a que deux tons sémantiques
 * (gain/loss) en plus du neutre, et le libellé affiché à côté du montant distingue déjà les deux
 * cas. Inventer une troisième teinte (ambre…) romprait avec ce vocabulaire binaire.
 */
const statusClass = (month: IncomeMonth): string => {
    if (month.status === 'impayé' || month.status === 'partiel') {
        return 'text-loss';
    }

    return month.status === 'vacance' ? 'text-muted-foreground' : '';
};

/** L'étiquette n'apparaît que lorsqu'elle dit quelque chose : un mois plein se lit à son montant. */
const statusLabel = (month: IncomeMonth): string =>
    month.status === null || month.status === 'plein' ? '' : month.status;

/** Le loyer attendu ne s'affiche que lorsqu'il diffère du perçu : sinon il répète le montant. */
const rentDetail = (month: IncomeMonth): string =>
    month.expected > month.rents
        ? `loyer ${eur(month.rents)} sur ${eur(month.expected)}`
        : `loyer ${eur(month.rents)}`;
</script>

<template>
    <section data-section="income" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Revenus &amp; charges</h2>

        <div v-if="years.length" class="flex min-w-0 flex-col">
            <div
                v-for="group in years"
                :key="group.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-income-year="group.year"
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
                            :data-income-month="month.month"
                            class="flex items-center gap-3 py-2 pl-[22px] text-sm"
                            :aria-expanded="openMonth === month.month"
                            @click="toggleMonth(month.month)"
                        >
                            <span class="text-muted-foreground">{{ frMonthYear(month.month) }}</span>
                            <span class="ml-auto flex items-center gap-2 tabular-nums">
                                <span
                                    v-if="statusLabel(month)"
                                    data-income-status
                                    class="text-xs"
                                    :class="statusClass(month)"
                                >
                                    {{ statusLabel(month) }}
                                </span>
                                <span class="font-medium" :class="gainClass(month.net)">
                                    {{ signedEur(month.net) }}
                                </span>
                            </span>
                        </button>

                        <p
                            v-if="openMonth === month.month"
                            data-income-detail
                            class="pb-2 pl-[22px] text-xs text-muted-foreground"
                        >
                            {{ rentDetail(month) }} · charges {{ eur(month.expenses) }} ·
                            crédit {{ eur(month.loanPayment) }}
                        </p>
                    </template>

                    <!-- Les charges de l'année ferment son groupe : leur ventilation répond au total. -->
                    <div v-if="group.expenseRows.length" class="flex flex-col gap-2.5 pt-3 pb-4 pl-[22px]">
                        <p class="flex items-baseline justify-between gap-3 text-[13px] text-muted-foreground">
                            Charges {{ group.year }}
                            <strong class="font-semibold tabular-nums text-foreground">
                                {{ eur(group.expenseTotal) }}
                            </strong>
                        </p>

                        <SectorBreakdownList :rows="group.expenseRows" />
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">Aucun mouvement sur ce bien.</p>
    </section>
</template>
