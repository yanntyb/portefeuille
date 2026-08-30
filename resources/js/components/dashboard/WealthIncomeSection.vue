<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { gainClass, signedEur } from '@/lib/format';
import type { IncomeOrigin, WealthIncome } from '@/lib/wealth';

const props = defineProps<{ income?: WealthIncome | null }>();

const monthlyTotal = computed<number>(() => props.income?.monthlyTotal ?? 0);

const hasIncome = computed<boolean>(() => monthlyTotal.value !== 0);

/** Une origine par classe d'actif qui verse quelque chose ; celles qui ne versent rien n'en ont pas. */
const origins = computed<IncomeOrigin[]>(() => props.income?.origins ?? []);
</script>

<template>
    <!--
        Un seul chiffre net : les dividendes des douze derniers mois mensualisés, plus le locatif
        déjà net de charges et d'échéances. Le détail se lit sur la page de chaque classe.

        Repliée à l'arrivée, mais son total tient dans son titre : c'est lui qui se lit d'un coup
        d'œil, et le dépli n'ajoute que la ventilation par origine. Le `Deferred` vit donc dans
        l'en-tête, toujours rendue : la prop part au chargement de la page, pas au dépli.
    -->
    <CollapsibleSection section="wealth-income" title="Revenus">
        <template #value>
            <span v-if="props.income !== null" class="flex items-baseline gap-1.5">
                <span
                    data-income-monthly
                    class="text-[17px] leading-none font-bold tabular-nums"
                    :class="gainClass(monthlyTotal)"
                >
                    {{ signedEur(monthlyTotal) }}
                </span>
                <span class="text-[13.5px] leading-none text-muted-foreground">par mois</span>
            </span>

            <Deferred v-else data="income">
                <template #fallback>
                    <span class="block h-4 w-24 animate-pulse rounded-md bg-muted"></span>
                </template>

                <template #rescue>
                    <span class="text-[13.5px] leading-none text-muted-foreground">—</span>
                </template>

                <span />
            </Deferred>
        </template>

        <ul v-if="props.income !== null && hasIncome" class="flex flex-col gap-2 text-sm">
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

        <p v-else-if="props.income !== null" class="py-8 text-center text-sm text-muted-foreground">
            Aucun revenu pour l'instant.
        </p>

        <!-- Un second `Deferred` sur la même prop : le pli seul distingue l'attente de l'indisponible. -->
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
