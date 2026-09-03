<script setup lang="ts">
import DeferredBlock from '@/components/DeferredBlock.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorBreakdownRow } from '@/lib/sector';

/**
 * La ventilation d'une page : secteurs d'un instrument, du portefeuille ou du patrimoine, classes
 * d'une enveloppe — la même liste, des lignes déjà converties par `lib/sector.ts`. Quatre copies
 * du même `map` vivaient dans quatre composants.
 *
 * `section` : une section repliable à part entière. `block` : un bloc étiqueté au sein d'une
 * section qui se replie déjà (l'analyse d'une poche), sans second pli.
 */
const props = withDefaults(
    defineProps<{
        /** `null` : pas encore arrivée ; `[]` : rien à montrer. */
        rows: SectorBreakdownRow[] | null;
        variant?: 'section' | 'block';
        /** Ce que nomme `data-section` en variante `section`. */
        section?: string;
        title?: string;
        deferKey?: string;
        /** Replier la liste au-delà de six lignes : utile sur une répartition longue, inutile derrière un pli. */
        collapsible?: boolean;
        emptyLabel?: string;
    }>(),
    {
        variant: 'section',
        section: 'sectors',
        title: 'Secteurs',
        deferKey: 'sectors',
        collapsible: false,
        emptyLabel: 'Pas encore de données sectorielles.',
    },
);
</script>

<template>
    <CollapsibleSection v-if="props.variant === 'section'" :section="props.section" :title="props.title">
        <template v-if="props.rows !== null">
            <SectorBreakdownList v-if="props.rows.length" :rows="props.rows" :collapsible="props.collapsible" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ props.emptyLabel }}</p>
        </template>

        <DeferredBlock v-else :data="props.deferKey" />
    </CollapsibleSection>

    <div v-else data-sectors-block class="flex flex-col gap-2">
        <span class="text-xs font-semibold text-muted-foreground uppercase">{{ props.title }}</span>

        <template v-if="props.rows !== null">
            <SectorBreakdownList v-if="props.rows.length" :rows="props.rows" :collapsible="props.collapsible" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ props.emptyLabel }}</p>
        </template>

        <DeferredBlock v-else :data="props.deferKey" />
    </div>
</template>
