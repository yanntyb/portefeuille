<script setup lang="ts">
import { eur, gainClass, signedEur } from '@/lib/format';

const props = withDefaults(defineProps<{
    invested: number | null;
    /** Montant signé : il porte sa propre teinte. */
    gain: number | null;
    /** Décimales des montants : les totaux arrondissent, le détail d'une position non. */
    digits?: number;
}>(), { digits: 2 });
</script>

<template>
    <!-- Les deux repères qui suivent partout le grand chiffre : investi puis gain, sur une ligne. -->
    <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
        <span class="whitespace-nowrap">
            Investi
            <strong class="font-semibold text-foreground tabular-nums">
                {{ eur(props.invested, props.digits) }}
            </strong>
        </span>
        <span data-gain class="whitespace-nowrap">
            Gain
            <strong class="font-semibold tabular-nums" :class="gainClass(props.gain)">
                {{ signedEur(props.gain, props.digits) }}
            </strong>
        </span>
    </p>
</template>
