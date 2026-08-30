<script setup lang="ts">
import { computed } from 'vue';
import { eur, gainClass, signedEur } from '@/lib/format';

const props = withDefaults(defineProps<{
    invested: number | null;
    /** Montant signé : il porte sa propre teinte. */
    gain: number | null;
    /** Gain déjà encaissé. Absent ou nul, la ligne garde ses deux repères habituels. */
    realizedGain?: number | null;
    /** Décimales des montants : les totaux arrondissent, le détail d'une position non. */
    digits?: number;
}>(), { realizedGain: null, digits: 2 });

/** Sans vente, rien à ventiler : « Gain » reste « Gain », et la ligne ne gagne pas de repère. */
const hasRealized = computed<boolean>(() => props.realizedGain !== null && props.realizedGain !== 0);
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
        <!--
            Deux variantes plutôt qu'un libellé interpolé : Vue supprime les nœuds blancs qui
            entourent une interpolation, et le texte du repère se collerait à ses voisins.
        -->
        <span v-if="hasRealized" data-gain class="whitespace-nowrap">
            Gain latent
            <strong class="font-semibold tabular-nums" :class="gainClass(props.gain)">
                {{ signedEur(props.gain, props.digits) }}
            </strong>
        </span>
        <span v-else data-gain class="whitespace-nowrap">
            Gain
            <strong class="font-semibold tabular-nums" :class="gainClass(props.gain)">
                {{ signedEur(props.gain, props.digits) }}
            </strong>
        </span>
        <span v-if="hasRealized" data-realized-gain class="whitespace-nowrap">
            Gain réalisé
            <strong class="font-semibold tabular-nums" :class="gainClass(props.realizedGain)">
                {{ signedEur(props.realizedGain, props.digits) }}
            </strong>
        </span>
    </p>
</template>
