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

                <!--
                    Mêmes pistes que les transactions : les deux sections se suivent sur la fiche,
                    et une ligne de dividende qui se lirait autrement qu'une ligne d'achat ferait
                    lire deux historiques là où il n'y en a qu'un. Les colonnes se déclarent sur le
                    groupe, `grid-cols-subgrid` les fait hériter aux lignes, sans quoi chaque ligne
                    se dimensionnerait sur son seul contenu et deux voisines ne s'aligneraient pas.
                -->
                <div
                    v-if="isYearOpen(group.year)"
                    class="grid grid-cols-[auto_auto_1fr_auto] items-center gap-x-3 pb-2 pl-[22px]"
                >
                    <div
                        v-for="(receipt, index) in group.receipts"
                        :key="`${group.year}-${index}`"
                        data-dividend-row
                        class="col-span-4 grid grid-cols-subgrid items-center gap-x-3 py-2 text-left text-sm"
                    >
                        <span class="text-muted-foreground">{{ frDayMonth(receipt.exDate) }}</span>
                        <!--
                            Le calcul tient dans la ligne : quantité × montant par action = montant
                            versé. Il tenait sous un pli, mais c'est justement ce produit qui
                            explique pourquoi deux versements d'un même titre diffèrent.
                        -->
                        <!--
                            Un tiret, jamais un zéro, quand la quantité est inconnue : un dividende
                            confirmé dont le détachement dérivé a disparu n'a personne pour la dire,
                            et « 0 ×0 € » se lirait comme un fait mesuré à côté d'un montant réel.
                        -->
                        <span data-dividend-quantity class="text-right">
                            {{ receipt.quantity === null ? '—' : receipt.quantity }}
                        </span>
                        <span data-dividend-per-share class="text-xs text-muted-foreground">
                            {{ receipt.amountPerShare === null ? '' : `×${perShare(receipt.amountPerShare)}` }}
                        </span>
                        <span data-dividend-amount class="text-right font-medium">
                            {{ eur(receipt.amount) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </CollapsibleSection>
</template>
