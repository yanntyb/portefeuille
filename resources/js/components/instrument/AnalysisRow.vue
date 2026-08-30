<script setup lang="ts">
import IndicatorInfoDialog from '@/components/IndicatorInfoDialog.vue';
import { gainClass } from '@/lib/format';
import type { AnalysisRow } from '@/lib/instrumentAnalysis';

const props = defineProps<{ row: AnalysisRow }>();
</script>

<template>
    <!-- Le libellé et son aide à gauche, la valeur sur le bord droit : même colonne que les repères
         de l'en-tête, pour que l'œil suive une seule verticale de chiffres. -->
    <span
        :data-analysis-row="props.row.indicator"
        class="flex items-center justify-between gap-3 whitespace-nowrap"
    >
        <span class="flex items-center gap-0.5 text-muted-foreground">
            {{ props.row.label }}
            <IndicatorInfoDialog :indicator="props.row.indicator" />
        </span>

        <strong
            class="font-semibold tabular-nums"
            :class="props.row.gain === undefined ? 'text-foreground' : gainClass(props.row.gain ?? null)"
        >
            {{ props.row.value }}
        </strong>
    </span>
</template>
