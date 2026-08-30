<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { eur, frDayMonth, signedEur } from '@/lib/format';
import { transactionYears, type TransactionLine, type TransactionYear } from '@/lib/instrument';

const props = defineProps<{ transactions: TransactionLine[] }>();

const years = computed<TransactionYear[]>(() => transactionYears(props.transactions));

/**
 * Toutes les années démarrent repliées : la section suit le graphe de valorisation, et un
 * historique déroulé y repousserait performances et secteurs hors de l'écran. Les années listées
 * et leur solde disent déjà ce qui se cache derrière le pli.
 */
const openYears = ref<string[]>([]);

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
    <CollapsibleSection section="transactions" title="Transactions">
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
                    <span data-transaction-year-net class="ml-auto font-semibold">
                        {{ signedEur(group.net) }}
                    </span>
                </button>

                <!--
                    Les colonnes se déclarent sur le groupe et non sur la ligne : une grille par
                    ligne se dimensionnerait sur son seul contenu, et deux lignes voisines ne
                    s'aligneraient pas. `grid-cols-subgrid` fait hériter les pistes du groupe.
                -->
                <div
                    v-if="isYearOpen(group.year)"
                    class="grid grid-cols-[auto_auto_1fr_auto] items-center gap-x-3 pb-2 pl-[22px]"
                >
                    <template v-for="(line, index) in group.lines" :key="`${group.year}-${index}`">
                        <button
                            type="button"
                            data-transaction-row
                            class="col-span-4 grid grid-cols-subgrid items-center gap-x-3 py-2 text-left text-sm"
                            :aria-expanded="openLine === `${group.year}-${index}`"
                            :aria-label="`${line.typeLabel} ${line.quantity}`"
                            @click="toggleLine(`${group.year}-${index}`)"
                        >
                            <span class="text-muted-foreground">{{ frDayMonth(line.date) }}</span>
                            <!--
                                Le sens se lit sur la seule quantité : teinter aussi le montant
                                doublerait le signal, et deux colonnes colorées par ligne feraient
                                de la liste un damier illisible.
                            -->
                            <span
                                data-transaction-quantity
                                class="text-right"
                                :class="line.isSell ? 'text-loss' : 'text-gain'"
                            >
                                {{ line.quantity }}
                            </span>
                            <!-- Une cellule vide plutôt qu'aucune : sinon le montant remonterait d'une colonne. -->
                            <span
                                v-if="line.fees"
                                data-transaction-fees
                                class="text-right text-xs text-muted-foreground"
                            >
                                frais {{ eur(line.fees) }}
                            </span>
                            <span v-else />
                            <span data-transaction-amount class="text-right font-medium">
                                {{ signedEur(amountOf(line)) }}
                            </span>
                        </button>

                        <p
                            v-if="openLine === `${group.year}-${index}`"
                            data-transaction-detail
                            class="col-span-4 pb-2 text-xs text-muted-foreground"
                        >
                            {{ eur(line.unitPrice) }} l'unité
                        </p>
                    </template>
                </div>
            </div>
        </div>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Aucune transaction sur cet actif.
        </p>
    </CollapsibleSection>
</template>
