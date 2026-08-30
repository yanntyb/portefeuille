<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import HeroMetaList from '@/components/HeroMetaList.vue';
import { eur } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';

const props = withDefaults(defineProps<{
    value: number | null;
    /** Montant signé derrière la pastille, qui en tire sa teinte. */
    gain: number | null;
    /** Texte de la pastille, ou `null` quand il n'y a pas de gain à afficher. */
    gainLabel: string | null;
    /** Vide quand l'appelant pose ses repères ailleurs, plus bas dans la page. */
    entries?: HeroMetaEntry[];
}>(), { entries: () => [] });
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1.5">
        <div class="flex flex-wrap items-baseline gap-3">
            <p data-hero-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">{{ eur(props.value) }}</p>

            <GainPill v-if="props.gainLabel !== null" data-hero-gain-pct :value="props.gain" :label="props.gainLabel" />
        </div>

        <!-- Ce qu'un appelant veut lire entre le grand chiffre et ses repères : rien par défaut. -->
        <slot name="beneath-value" />

        <HeroMetaList :entries="props.entries" class="pt-1.5" />
    </div>
</template>
