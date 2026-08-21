<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import { eur, gainClass } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';

const props = defineProps<{
    value: number | null;
    /** Montant signé derrière la pastille, qui en tire sa teinte. */
    gain: number | null;
    /** Texte de la pastille, ou `null` quand il n'y a pas de gain à afficher. */
    gainLabel: string | null;
    entries: HeroMetaEntry[];
}>();
</script>

<template>
    <div class="flex min-w-0 flex-col gap-1.5">
        <div class="flex flex-wrap items-baseline gap-3">
            <p data-hero-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">{{ eur(props.value) }}</p>

            <GainPill v-if="props.gainLabel !== null" data-hero-gain-pct :value="props.gain" :label="props.gainLabel" />
        </div>

        <!-- Grille plutôt que flux : les libellés s'alignent en colonne, les valeurs sur leur bord droit. -->
        <p data-hero-meta class="grid grid-cols-2 gap-x-8 gap-y-1.5 pt-1.5 text-[13.5px] text-muted-foreground">
            <span
                v-for="entry in props.entries"
                :key="entry.label || entry.value"
                :data-hero-gain="entry.gain === undefined ? undefined : ''"
                class="flex items-baseline justify-between gap-3 whitespace-nowrap"
            >
                <template v-if="entry.label">
                    {{ entry.label }}
                    <strong
                        class="font-semibold tabular-nums"
                        :class="entry.gain === undefined ? 'text-foreground' : gainClass(entry.gain)"
                    >
                        {{ entry.value }}
                    </strong>
                </template>
                <template v-else>{{ entry.value }}</template>
            </span>
        </p>
    </div>
</template>
