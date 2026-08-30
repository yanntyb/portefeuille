<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { eur, frDayMonth, gainClass, signedEur } from '@/lib/format';
import { transactionYears, type TransactionYear } from '@/lib/instrument';
import type { WealthTransactionLine } from '@/lib/wealth';

const props = defineProps<{ transactions?: WealthTransactionLine[] | null }>();

const lines = computed<WealthTransactionLine[]>(() => props.transactions ?? []);

const years = computed<TransactionYear<WealthTransactionLine>[]>(() => transactionYears(lines.value));

/**
 * Toutes les années démarrent repliées, comme sur la fiche actif : la section porte l'historique
 * entier du patrimoine, et un seul millésime déroulé remplirait déjà l'écran.
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
const amountOf = (line: WealthTransactionLine): number => (line.isSell ? -line.total : line.total);
</script>

<template>
    <!--
        Repliée à l'arrivée, et la prop différée ne part qu'au dépli : le `Deferred` vit sous le pli
        de `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert. Le tableau de bord ne
        paie donc l'historique entier que pour qui le demande.
    -->
    <CollapsibleSection section="wealth-transactions" title="Transactions">
        <template v-if="props.transactions !== null && props.transactions !== undefined">
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

                    <!--
                        Les colonnes se déclarent sur le groupe et non sur la ligne : une grille par
                        ligne se dimensionnerait sur son seul contenu, et deux lignes voisines ne
                        s'aligneraient pas. `grid-cols-subgrid` fait hériter les pistes du groupe.
                    -->
                    <div
                        v-if="isYearOpen(group.year)"
                        class="grid grid-cols-[auto_1fr_auto_auto] items-center gap-x-3 pb-2 pl-[22px]"
                    >
                        <template v-for="(line, index) in group.lines" :key="`${group.year}-${index}`">
                            <button
                                type="button"
                                data-transaction-row
                                class="col-span-4 grid grid-cols-subgrid items-center gap-x-3 py-2 text-left text-sm"
                                :aria-expanded="openLine === `${group.year}-${index}`"
                                :aria-label="`${line.assetName} ${line.typeLabel} ${line.quantity}`"
                                @click="toggleLine(`${group.year}-${index}`)"
                            >
                                <span class="text-muted-foreground">{{ frDayMonth(line.date) }}</span>
                                <span data-transaction-asset class="truncate font-medium">
                                    {{ line.assetName }}
                                </span>
                                <span class="text-right" :class="line.isSell ? 'text-loss' : 'text-gain'">
                                    {{ line.quantity }}
                                </span>
                                <span class="text-right font-medium" :class="gainClass(amountOf(line))">
                                    {{ signedEur(amountOf(line)) }}
                                </span>
                            </button>

                            <!-- Les frais tiennent dans le détail : hors de la fiche, l'actif prend leur colonne. -->
                            <p
                                v-if="openLine === `${group.year}-${index}`"
                                data-transaction-detail
                                class="col-span-4 pb-2 text-xs text-muted-foreground"
                            >
                                {{ line.typeLabel }} · {{ eur(line.unitPrice) }} l'unité<template
                                    v-if="line.fees"
                                >
                                    · frais {{ eur(line.fees) }}</template
                                >
                            </p>
                        </template>
                    </div>
                </div>
            </div>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune transaction pour l'instant.
            </p>
        </template>

        <Deferred v-else data="transactions">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
