<script setup lang="ts">
import { gainClass } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';

const props = defineProps<{ entries: HeroMetaEntry[] }>();
</script>

<template>
    <!-- Un repère par ligne : le libellé à gauche, la valeur sur le bord droit. Sur deux colonnes,
         les libellés longs et leur valeur se chevauchaient. -->
    <p v-if="props.entries.length" data-hero-meta class="flex flex-col gap-1.5 text-[13.5px] text-muted-foreground">
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
</template>
