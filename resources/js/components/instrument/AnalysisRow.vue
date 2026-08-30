<script setup lang="ts">
import { gainClass } from '@/lib/format';
import type { AnalysisRow } from '@/lib/instrumentAnalysis';

const props = defineProps<{ row: AnalysisRow }>();
</script>

<template>
    <!-- Le libellé à gauche, la valeur sur le bord droit : même colonne que les repères de
         l'en-tête, pour que l'œil suive une seule verticale de chiffres. -->
    <span
        :data-analysis-row="props.row.indicator"
        class="flex items-center justify-between gap-3 whitespace-nowrap"
    >
        <span class="text-muted-foreground">{{ props.row.label }}</span>

        <strong
            class="font-semibold tabular-nums"
            :class="props.row.gain === undefined ? 'text-foreground' : gainClass(props.row.gain ?? null)"
        >
            {{ props.row.value }}
        </strong>
    </span>
</template>
