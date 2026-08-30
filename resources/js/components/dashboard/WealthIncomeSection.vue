<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { gainClass, signedEur } from '@/lib/format';
import type { IncomeOrigin, WealthIncome } from '@/lib/wealth';

const props = defineProps<{ income?: WealthIncome | null }>();

const hasIncome = computed<boolean>(() => (props.income?.monthlyTotal ?? 0) !== 0);

/** Une origine par classe d'actif qui verse quelque chose ; celles qui ne versent rien n'en ont pas. */
const origins = computed<IncomeOrigin[]>(() => props.income?.origins ?? []);
</script>

<template>
    <!--
        Un seul chiffre net : les dividendes des douze derniers mois mensualisés, plus le locatif
        déjà net de charges et d'échéances. Le détail se lit sur la page de chaque classe.

        Repliée à l'arrivée, comme l'historique qui la suit : la prop différée vit sous le pli de
        `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert, donc elle ne part qu'au
        dépli.
    -->
    <CollapsibleSection section="wealth-income" title="Revenus">
        <template v-if="props.income !== null">
            <!-- Le pli espace ses enfants plus large que le bloc revenus : sa propre grille les resserre. -->
            <div v-if="hasIncome" class="flex flex-col gap-4">
                <p class="flex flex-wrap items-baseline gap-2">
                    <span
                        data-income-monthly
                        class="text-2xl font-bold tabular-nums"
                        :class="gainClass(props.income?.monthlyTotal ?? 0)"
                    >
                        {{ signedEur(props.income?.monthlyTotal ?? 0) }}
                    </span>
                    <span class="text-[13.5px] text-muted-foreground">par mois</span>
                </p>

                <ul class="flex flex-col gap-2 text-sm">
                    <li
                        v-for="origin in origins"
                        :key="origin.label"
                        data-income-origin
                        class="flex items-center justify-between gap-3"
                    >
                        <span class="text-muted-foreground">{{ origin.label }}</span>
                        <span class="font-semibold tabular-nums" :class="gainClass(origin.amount)">
                            {{ signedEur(origin.amount) }}
                        </span>
                    </li>
                </ul>
            </div>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu pour l'instant.
            </p>
        </template>

        <Deferred v-else data="income">
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
