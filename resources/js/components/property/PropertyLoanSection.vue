<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { eur, frMonthYear } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import {
    loanProgress,
    loanYears,
    type AmortizationLine,
    type LoanSchedule,
    type LoanSummary,
} from '@/lib/realEstate';

const props = defineProps<{ loan: LoanSummary; lines?: AmortizationLine[] }>();

const summary = computed<HeroMetaEntry[]>(() => loanProgress(props.loan));

/** L'année en cours vient de l'horloge du lecteur : aucune prop ne la transporte. */
const currentYear = new Date().getFullYear();

const schedule = computed<LoanSchedule>(() => loanYears(props.lines ?? [], currentYear));

/** L'année en cours s'ouvre : c'est celle dont on veut voir les échéances. */
const openYears = ref<string[]>([String(currentYear)]);

const isYearOpen = (year: string): boolean => openYears.value.includes(year);

const toggleYear = (year: string): void => {
    openYears.value = isYearOpen(year)
        ? openYears.value.filter((open) => open !== year)
        : [...openYears.value, year];
};

const isFutureOpen = ref<boolean>(false);

const futureLabel = computed<string>(() => {
    const years = schedule.value.future?.years.length ?? 0;

    return years === 1 ? 'À venir (1 an)' : `À venir (${years} ans)`;
});

/** Sans décimales : sur cinq colonnes chiffrées, les centimes se lisent dans les repères au-dessus. */
const amount = (value: number): string => eur(value, 0);
</script>

<template>
    <section data-section="loan" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Crédit</h2>

        <!-- Un repère par ligne : sur deux colonnes, « Capital remboursé » chevauchait son montant. -->
        <p data-loan-summary class="flex flex-col gap-1.5 text-[13.5px] text-muted-foreground">
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
                            <th class="py-1 pr-4 font-normal">Année</th>
                            <th class="py-1 pr-4 text-right font-normal">Mensualités</th>
                            <th class="py-1 pr-4 text-right font-normal">Intérêts</th>
                            <th class="py-1 pr-4 text-right font-normal">Capital</th>
                            <th class="py-1 text-right font-normal">Restant dû</th>
                        </tr>
                    </thead>

                    <tbody v-for="year in schedule.past" :key="year.year">
                        <!-- La ligne entière ouvre l'année : au pouce, un chevron seul serait une cible trop fine. -->
                        <tr
                            :data-loan-year="year.year"
                            role="button"
                            tabindex="0"
                            :aria-expanded="isYearOpen(year.year)"
                            class="cursor-pointer border-t border-separator"
                            :class="year.isCurrent ? 'font-semibold' : ''"
                            @click="toggleYear(year.year)"
                            @keydown.enter.prevent="toggleYear(year.year)"
                            @keydown.space.prevent="toggleYear(year.year)"
                        >
                            <td class="py-1.5 pr-4">
                                <span class="flex items-center gap-1.5">
                                    <ChevronRight
                                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                                        :class="isYearOpen(year.year) ? 'rotate-90' : ''"
                                    />
                                    {{ year.year }}
                                </span>
                            </td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(year.payments) }}</td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(year.interest) }}</td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(year.principal) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ amount(year.remaining) }}</td>
                        </tr>

                        <tr
                            v-for="line in isYearOpen(year.year) ? year.months : []"
                            :key="line.month"
                            :data-loan-month="line.month"
                            class="text-muted-foreground"
                        >
                            <td class="py-1 pr-4 pl-[22px]">{{ frMonthYear(line.month) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(line.payment) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(line.interest) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(line.principal) }}</td>
                            <td class="py-1 text-right tabular-nums">{{ amount(line.remaining) }}</td>
                        </tr>
                    </tbody>

                    <tbody v-if="schedule.future">
                        <tr
                            data-loan-future
                            role="button"
                            tabindex="0"
                            :aria-expanded="isFutureOpen"
                            class="cursor-pointer border-t border-separator"
                            @click="isFutureOpen = !isFutureOpen"
                            @keydown.enter.prevent="isFutureOpen = !isFutureOpen"
                            @keydown.space.prevent="isFutureOpen = !isFutureOpen"
                        >
                            <td class="py-1.5 pr-4">
                                <span class="flex items-center gap-1.5">
                                    <ChevronRight
                                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                                        :class="isFutureOpen ? 'rotate-90' : ''"
                                    />
                                    {{ futureLabel }}
                                </span>
                            </td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(schedule.future.payments) }}</td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(schedule.future.interest) }}</td>
                            <td class="py-1.5 pr-4 text-right tabular-nums">{{ amount(schedule.future.principal) }}</td>
                            <td class="py-1.5 text-right tabular-nums">—</td>
                        </tr>

                        <!-- Les années à venir se lisent en bloc : elles ne se déplient pas au mois à leur tour. -->
                        <tr
                            v-for="year in isFutureOpen ? schedule.future.years : []"
                            :key="year.year"
                            :data-loan-future-year="year.year"
                            class="text-muted-foreground"
                        >
                            <td class="py-1 pr-4 pl-[22px]">{{ year.year }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(year.payments) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(year.interest) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ amount(year.principal) }}</td>
                            <td class="py-1 text-right tabular-nums">{{ amount(year.remaining) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Deferred>
    </section>
</template>
