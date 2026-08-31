<script setup lang="ts">
import { computed, ref } from 'vue';
import { ChevronRight, Pencil, Trash2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { eur, frDayMonth, signedEur } from '@/lib/format';
import {
    transactionYears,
    type NamedTransactionLine,
    type TransactionLine,
    type TransactionYear,
} from '@/lib/instrument';

/**
 * Le corps commun des trois sections transactions : fiche actif, tableau de bord et page
 * d'exposition. Elles ne diffèrent que par ce qu'une ligne a besoin de dire.
 *
 * `named` : plusieurs actifs se mélangent, donc l'actif prend une colonne et le détail — type,
 * prix unitaire, frais — se replie sous la ligne, faute de place à côté.
 * `bare` : un seul actif, la colonne libérée porte prix unitaire et frais en clair.
 */
const props = withDefaults(
    defineProps<{
        lines: TransactionLine[];
        variant: 'named' | 'bare';
        emptyLabel: string;
        /**
         * Ouvre la correction et la suppression. Faux par défaut : les appelants qui ne la
         * demandent pas ne bougent pas d'un pixel.
         */
        editable?: boolean;
    }>(),
    { editable: false },
);

const emit = defineEmits<{ edit: [line: TransactionLine]; delete: [line: TransactionLine] }>();

const years = computed<TransactionYear[]>(() => transactionYears(props.lines));

const isNamed = computed<boolean>(() => props.variant === 'named');

/** La piste souple change de place avec la variante, et `bare` en gagne une pour le crayon. */
const gridColumns = computed<string>(() => {
    if (isNamed.value) {
        return 'grid-cols-[auto_1fr_auto_auto]';
    }

    return props.editable
        ? 'grid-cols-[auto_auto_1fr_auto_auto]'
        : 'grid-cols-[auto_auto_1fr_auto]';
});

/** Doit suivre le nombre de pistes, sinon les lignes cessent de s'aligner sur le groupe. */
const rowSpan = computed<string>(() => (!isNamed.value && props.editable ? 'col-span-5' : 'col-span-4'));

/** L'actif ne se lit que sur les lignes qui le portent : la variante `bare` n'en a aucun. */
const assetNameOf = (line: TransactionLine): string => (line as NamedTransactionLine).assetName;

/**
 * Toutes les années démarrent repliées : la section suit un graphe, et un historique déroulé
 * repousserait hors de l'écran tout ce qui la suit. Les années listées et leur solde disent déjà
 * ce qui se cache derrière le pli.
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

/**
 * Où vivent les actions, et pourquoi elles ne sont pas au même endroit selon la variante.
 *
 * En `named`, la ligne est un dépli : on greffe sur le dépli, dans le bloc de détail — qui est un
 * frère de la ligne, pas un enfant. C'est aussi une contrainte technique : poser un bouton dans
 * l'élément `[data-transaction-row]`, qui est lui-même un `<button>`, serait du HTML invalide.
 * Et une lecture plus calme : la liste repliée ne se couvre pas d'icônes, les actions apparaissent
 * là où le lecteur a déjà dit « c'est de cette ligne que je parle ».
 *
 * En `bare`, tout est déjà déplié et il n'y a rien où se cacher : la ligne est un `<div>`, donc une
 * piste de grille de plus porte le crayon. Un seul bouton, pas deux — la suppression passe par la
 * modale de correction, pour ne pas doubler les icônes sur la page la plus dense.
 */

/** Le flux investi de la ligne : un achat entre en positif, une vente en sort. */
const amountOf = (line: TransactionLine): number => (line.isSell ? -line.total : line.total);
</script>

<template>
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

                La piste souple change de place avec la variante : l'actif s'étire quand il est
                nommé, le prix unitaire sinon.
            -->
            <div
                v-if="isYearOpen(group.year)"
                class="grid items-center gap-x-3 pb-2 pl-[22px]"
                :class="gridColumns"
            >
                <template v-for="(line, index) in group.lines" :key="`${group.year}-${index}`">
                    <component
                        :is="isNamed ? 'button' : 'div'"
                        :type="isNamed ? 'button' : undefined"
                        data-transaction-row
                        :class="[rowSpan, 'grid grid-cols-subgrid items-center gap-x-3 py-2 text-left text-sm']"
                        :aria-expanded="isNamed ? openLine === `${group.year}-${index}` : undefined"
                        :aria-label="
                            isNamed
                                ? `${assetNameOf(line)} ${line.typeLabel} ${line.quantity}`
                                : `${line.typeLabel} ${line.quantity}`
                        "
                        @click="isNamed ? toggleLine(`${group.year}-${index}`) : undefined"
                    >
                        <span class="text-muted-foreground">{{ frDayMonth(line.date) }}</span>

                        <span v-if="isNamed" data-transaction-asset class="truncate font-medium">
                            {{ assetNameOf(line) }}
                        </span>

                        <!--
                            Le sens se lit sur la seule quantité : teinter aussi le montant
                            doublerait le signal, et deux colonnes colorées par ligne feraient de
                            la liste un damier illisible.
                        -->
                        <span
                            data-transaction-quantity
                            class="text-right"
                            :class="line.isSell ? 'text-loss' : 'text-gain'"
                        >
                            {{ line.quantity }}
                        </span>

                        <!--
                            Colonne souple entre la quantité et le montant : prix unitaire puis
                            frais s'y suivent contre la quantité, pour que la ligne se lise d'un
                            trait — quantité × prix - frais. Colonne toujours présente, même sans
                            frais, sinon le montant remonterait d'une colonne.
                        -->
                        <span
                            v-if="!isNamed"
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <span data-transaction-detail>×{{ eur(line.unitPrice) }}</span>
                            <!--
                                L'opérateur suit le sens : les frais alourdissent ce qu'un achat
                                coûte et grèvent ce qu'une vente rapporte. Un « - » partout
                                mentirait sur la moitié des lignes.
                            -->
                            <span v-if="line.fees" data-transaction-fees>
                                {{ line.isSell ? '-' : '+' }} frais {{ eur(line.fees) }}
                            </span>
                        </span>

                        <span data-transaction-amount class="text-right font-medium">
                            {{ signedEur(amountOf(line)) }}
                        </span>

                        <!--
                            La ligne est un `<div>` en variante `bare` : un bouton peut y vivre. En
                            `named` elle est un `<button>`, et les actions vivent dans le détail.
                        -->
                        <Button
                            v-if="!isNamed && props.editable"
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            data-transaction-edit
                            :aria-label="`Modifier ${line.typeLabel} du ${frDayMonth(line.date)}`"
                            class="text-muted-foreground hover:text-foreground"
                            @click="emit('edit', line)"
                        >
                            <Pencil />
                        </Button>
                    </component>

                    <!--
                        Les frais tiennent dans le détail : hors de la fiche, l'actif prend leur
                        colonne. Les actions les rejoignent, à droite — ce bloc est un frère de la
                        ligne, donc on n'imbrique aucun bouton dans le `<button>` qu'elle est.
                    -->
                    <div
                        v-if="isNamed && openLine === `${group.year}-${index}`"
                        data-transaction-detail
                        class="col-span-4 flex items-center gap-3 pb-2 text-xs text-muted-foreground"
                    >
                        <span>
                            {{ eur(line.unitPrice) }} l'unité<template
                                v-if="line.fees"
                            >
                                · frais {{ eur(line.fees) }}</template
                            >
                        </span>

                        <span v-if="props.editable" class="ml-auto flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="xs"
                                data-transaction-edit
                                @click="emit('edit', line)"
                            >
                                <Pencil />
                                Modifier
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                size="xs"
                                data-transaction-delete
                                class="text-destructive hover:text-destructive"
                                @click="emit('delete', line)"
                            >
                                <Trash2 />
                                Supprimer
                            </Button>
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ props.emptyLabel }}</p>
</template>
