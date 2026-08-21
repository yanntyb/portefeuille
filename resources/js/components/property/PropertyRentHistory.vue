<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import { eur, frMonthYear } from '@/lib/format';
import { rentMonthStatus, rentYears, type RentMonth, type RentYear } from '@/lib/realEstate';

const props = defineProps<{ months: RentMonth[] }>();

const years = computed<RentYear[]>(() => rentYears(props.months));

/** Seule l'année la plus récente s'ouvre : les précédentes relèvent de l'archive. */
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

/** L'étiquette n'apparaît que lorsqu'elle dit quelque chose : un mois plein se lit à son montant. */
const statusLabel = (month: RentMonth): string => {
    const status = rentMonthStatus(month);

    return status === 'plein' ? '' : status;
};
</script>

<template>
    <section data-section="rents" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Loyers</h2>

        <div v-if="years.length" class="flex min-w-0 flex-col">
            <div
                v-for="group in years"
                :key="group.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-rent-year="group.year"
                    class="flex items-center gap-1.5 py-3 text-sm"
                    :aria-expanded="isYearOpen(group.year)"
                    @click="toggleYear(group.year)"
                >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="isYearOpen(group.year) ? 'rotate-90' : ''"
                    />
                    <span class="font-semibold">{{ group.year }}</span>
                    <span
                        class="ml-auto font-semibold tabular-nums"
                        :class="group.received < group.expected ? 'text-loss' : ''"
                    >
                        {{ eur(group.received) }}
                    </span>
                </button>

                <div v-if="isYearOpen(group.year)" class="flex flex-col pb-2">
                    <template v-for="month in group.months" :key="month.month">
                        <button
                            type="button"
                            :data-rent-month="month.month"
                            class="flex items-center gap-3 py-2 pl-[22px] text-sm"
                            :aria-expanded="openMonth === month.month"
                            @click="toggleMonth(month.month)"
                        >
                            <span class="text-muted-foreground">{{ frMonthYear(month.month) }}</span>
                            <span class="ml-auto flex items-center gap-2 tabular-nums" :class="statusClass(month)">
                                <span class="text-xs">{{ statusLabel(month) }}</span>
                                <span class="font-medium">{{ eur(month.effective) }}</span>
                            </span>
                        </button>

                        <p
                            v-if="openMonth === month.month"
                            data-rent-detail
                            class="pb-2 pl-[22px] text-xs text-muted-foreground"
                        >
                            attendu {{ eur(month.expected) }} · perçu {{ eur(month.effective) }}
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">Aucun bail sur ce bien.</p>
    </section>
</template>
