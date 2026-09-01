<script setup lang="ts">
import { computed } from 'vue';
import { eur, gainClass, signedEur } from '@/lib/format';

const props = withDefaults(defineProps<{
    invested: number | null;
    /** Montant signé : il porte sa propre teinte. */
    gain: number | null;
    /** Gain déjà encaissé. Absent ou nul, la ligne garde ses deux repères habituels. */
    realizedGain?: number | null;
    /**
     * Cash de l'utilisateur qui n'appartient à aucune exposition : la divergence assumée entre le
     * total d'une page d'exposition et la bande « Liquidités » du patrimoine.
     */
    originCash?: number | null;
    /** Décimales des montants : les totaux arrondissent, le détail d'une position non. */
    digits?: number;
}>(), { realizedGain: null, originCash: null, digits: 2 });

/** Sans vente, rien à ventiler : le gain porte quand même « (latent) », sans repère « réalisé ». */
const hasRealized = computed<boolean>(() => props.realizedGain !== null && props.realizedGain !== 0);

/** Absent ou nul, rien ne reste à replacer : le repère se tait plutôt que d'annoncer « 0 € ». */
const hasOriginCash = computed<boolean>(() => props.originCash !== null && props.originCash !== 0);
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
            Un seul repère « Gain », ses deux montants serrés sous lui : séparés, le réalisé
            passait à la ligne à la première gêne, à égalité avec « Investi ».
        -->
        <span data-gain class="flex flex-wrap items-baseline gap-x-2">
            Gain
            <span class="whitespace-nowrap">
                <strong class="font-semibold tabular-nums" :class="gainClass(props.gain)">
                    {{ signedEur(props.gain, props.digits) }}
                </strong>
                (latent)
            </span>
            <span v-if="hasRealized" data-realized-gain class="whitespace-nowrap">
                <strong class="font-semibold tabular-nums" :class="gainClass(props.realizedGain)">
                    {{ signedEur(props.realizedGain, props.digits) }}
                </strong>
                (réalisé)
            </span>
        </span>
        <span v-if="hasOriginCash" data-origin-cash class="whitespace-nowrap">
            dont
            <strong class="font-semibold text-foreground tabular-nums">
                {{ eur(props.originCash, props.digits) }}
            </strong>
            à replacer
        </span>
    </p>
</template>
