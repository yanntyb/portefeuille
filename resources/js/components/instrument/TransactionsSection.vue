<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import { eur, frDayMonth, gainClass, signedEur } from '@/lib/format';
import { transactionYears, type TransactionLine, type TransactionYear } from '@/lib/instrument';

const props = defineProps<{ transactions: TransactionLine[] }>();

const years = computed<TransactionYear[]>(() => transactionYears(props.transactions));

const heading = computed<string>(() => `Transactions (${props.transactions.length})`);

/**
 * Seule l'année la plus récente s'ouvre : les précédentes relèvent de l'archive. « La plus
 * récente » et non l'année civile courante — sans opération cette année, un groupe ouvert vaut
 * mieux qu'une section entièrement repliée.
 */
const openYears = ref<string[]>(years.value.slice(0, 1).map((group) => group.year));

const isYearOpen = (year: string): boolean => openYears.value.includes(year);

const toggleYear = (year: string): void => {
    openYears.value = isYearOpen(year)
        ? openYears.value.filter((open) => open !== year)
        : [...openYears.value, year];
};

/** Une seule ligne détaillée à la fois : le détail se lit en regard de la ligne, pas en liste. */
const openLine = ref<string | null>(null);

const toggleLine = (key: string): void => {
    openLine.value = openLine.value === key ? null : key;
};

/** Le flux investi de la ligne : un achat entre en positif, une vente en sort. */
const amountOf = (line: TransactionLine): number => (line.isSell ? -line.total : line.total);
</script>

<template>
    <section data-section="transactions" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">{{ heading }}</h2>

        <div v-if="years.length" class="flex min-w-0 flex-col">
            <div
                v-for="group in years"
                :key="group.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-transaction-year="group.year"
                    class="flex items-center gap-1.5 py-3 text-sm"
                    :aria-expanded="isYearOpen(group.year)"
                    @click="toggleYear(group.year)"
                >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="isYearOpen(group.year) ? 'rotate-90' : ''"
                    />
                    <span class="font-semibold">{{ group.year }}</span>
                    <span class="ml-auto font-semibold" :class="gainClass(group.net)">
                        {{ signedEur(group.net) }}
                    </span>
                </button>

                <div v-if="isYearOpen(group.year)" class="flex flex-col pb-2">
                    <template v-for="(line, index) in group.lines" :key="`${group.year}-${index}`">
                        <button
                            type="button"
                            data-transaction-row
                            class="flex items-center gap-3 py-2 pl-[22px] text-sm"
                            :aria-expanded="openLine === `${group.year}-${index}`"
                            @click="toggleLine(`${group.year}-${index}`)"
                        >
                            <span class="text-muted-foreground">{{ frDayMonth(line.date) }}</span>
                            <span :class="line.isSell ? 'text-loss' : 'text-gain'">{{ line.typeLabel }}</span>
                            <span
                                v-if="line.fees"
                                data-transaction-fees
                                class="ml-auto text-xs text-muted-foreground"
                            >
                                frais {{ eur(line.fees) }}
                            </span>
                            <span
                                class="font-medium"
                                :class="[gainClass(amountOf(line)), line.fees ? '' : 'ml-auto']"
                            >
                                {{ signedEur(amountOf(line)) }}
                            </span>
                        </button>

                        <p
                            v-if="openLine === `${group.year}-${index}`"
                            data-transaction-detail
                            class="pb-2 pl-[22px] text-xs text-muted-foreground"
                        >
                            {{ line.quantity }} × {{ eur(line.unitPrice) }}
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Aucune transaction sur cet actif.
        </p>
    </section>
</template>
