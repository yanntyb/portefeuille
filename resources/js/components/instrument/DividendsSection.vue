<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight } from 'lucide-vue-next';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { eur, frDayMonth } from '@/lib/format';
import { dividendYears, type AssetDividendHistory, type DividendYear } from '@/lib/income';

const props = defineProps<{ dividends: AssetDividendHistory }>();

const years = computed<DividendYear[]>(() => dividendYears(props.dividends.receipts));

/**
 * Toutes les années repliées au dépliage de la section : la section elle-même s'ouvre déjà sur un
 * clic, et un groupe ouvert d'office pousserait les suivants hors de l'écran. Le pli ne se mémorise
 * pas d'une visite à l'autre.
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

/** Un montant par action se lit au millième : 0,51 € et 0,515 € ne sont pas le même dividende. */
const perShare = (value: number): string => eur(value, 3);
</script>

<template>
    <CollapsibleSection section="dividends" title="Dividendes">
        <div class="flex min-w-0 flex-col">
            <div
                v-for="group in years"
                :key="group.year"
                class="flex flex-col border-b border-border last:border-b-0"
            >
                <button
                    type="button"
                    :data-dividend-year="group.year"
                    class="flex items-center gap-1.5 py-3 text-sm"
                    :aria-expanded="isYearOpen(group.year)"
                    @click="toggleYear(group.year)"
                >
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform"
                        :class="isYearOpen(group.year) ? 'rotate-90' : ''"
                    />
                    <span class="font-semibold">{{ group.year }}</span>
                    <span class="ml-auto font-semibold">{{ eur(group.total) }}</span>
                </button>

                <div v-if="isYearOpen(group.year)" class="flex flex-col pb-2">
                    <template v-for="(receipt, index) in group.receipts" :key="`${group.year}-${index}`">
                        <button
                            type="button"
                            data-dividend-row
                            class="flex items-center gap-3 py-2 pl-[22px] text-sm"
                            :aria-expanded="openLine === `${group.year}-${index}`"
                            @click="toggleLine(`${group.year}-${index}`)"
                        >
                            <span class="text-muted-foreground">{{ frDayMonth(receipt.exDate) }}</span>
                            <span class="ml-auto font-medium" data-dividend-amount>{{ eur(receipt.amount) }}</span>
                        </button>

                        <p
                            v-if="openLine === `${group.year}-${index}`"
                            data-dividend-detail
                            class="pb-2 pl-[22px] text-xs text-muted-foreground"
                        >
                            <span data-dividend-quantity>{{ receipt.quantity }}</span> ×
                            <span data-dividend-per-share>{{ perShare(receipt.amountPerShare) }}</span> par action
                        </p>
                    </template>
                </div>
            </div>
        </div>
    </CollapsibleSection>
</template>
