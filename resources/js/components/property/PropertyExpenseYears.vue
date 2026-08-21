<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import { eur } from '@/lib/format';
import { expenseRows, type ExpenseYear } from '@/lib/realEstate';
import type { SectorBreakdownRow } from '@/lib/sector';

const props = defineProps<{ years: ExpenseYear[] }>();

/** Seule l'année la plus récente s'ouvre : les précédentes relèvent de l'archive. */
const openYears = ref<string[]>(props.years.slice(0, 1).map((year) => String(year.year)));

const isYearOpen = (year: number): boolean => openYears.value.includes(String(year));

const toggleYear = (year: number): void => {
    const key = String(year);

    openYears.value = isYearOpen(year)
        ? openYears.value.filter((open) => open !== key)
        : [...openYears.value, key];
};

/** Même liste à barres que la répartition sectorielle : deux découpages d'un total se lisent pareil. */
const rowsByYear = computed<Record<number, SectorBreakdownRow[]>>(() =>
    Object.fromEntries(props.years.map((year) => [year.year, expenseRows(year)])),
);
</script>

<template>
    <section data-section="expenses" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Charges</h2>

        <div v-if="props.years.length" class="flex min-w-0 flex-col">
            <div
                v-for="year in props.years"
                :key="year.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-expense-year="year.year"
                    class="flex items-center gap-1.5 py-3 text-sm"
                    :aria-expanded="isYearOpen(year.year)"
                    @click="toggleYear(year.year)"
                >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="isYearOpen(year.year) ? 'rotate-90' : ''"
                    />
                    <span class="font-semibold">{{ year.year }}</span>
                    <span class="ml-auto font-semibold tabular-nums">{{ eur(year.total) }}</span>
                </button>

                <div v-if="isYearOpen(year.year)" class="pb-4 pl-[22px]">
                    <SectorBreakdownList :rows="rowsByYear[year.year]" />
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">Aucune charge enregistrée.</p>
    </section>
</template>
