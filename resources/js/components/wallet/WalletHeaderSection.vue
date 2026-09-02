<script setup lang="ts">
import { computed } from 'vue';
import HeroFigures from '@/components/HeroFigures.vue';
import { eur, pct } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import type { WealthAccount } from '@/lib/wealth';

const props = defineProps<{ account: WealthAccount }>();

/** Accord du singulier : « 1 an », jamais « 1 ans ». Même règle que la carte du tableau de bord. */
const years = (n: number): string => (n === 1 ? '1 an' : `${n} ans`);

const maturity = computed<string | null>(() => {
    const { ageInYears, maturityYears } = props.account;

    if (maturityYears === null || ageInYears === null) {
        return null;
    }

    return ageInYears >= maturityYears
        ? `Seuil de ${years(maturityYears)} franchi`
        : `Seuil de ${years(maturityYears)} dans ${years(maturityYears - ageInYears)}`;
});

/** L'ancienneté ne s'affiche pas sans date d'ouverture : un compte « 0 an » mentirait. */
const age = computed<string | null>(() => {
    if (props.account.ageInYears === null) {
        return null;
    }

    const opened = `Ouverte depuis ${years(props.account.ageInYears)}`;

    return maturity.value === null ? opened : `${opened} · ${maturity.value}`;
});

/** Repère unique, rendu par `HeroMetaList` : un second affichage ferait doublon à l'écran. */
const entries = computed<HeroMetaEntry[]>(() => [
    { label: 'Espèces', value: eur(props.account.cashBalance, 0) },
]);
</script>

<template>
    <section data-section="wallet-header" class="flex shrink-0 flex-col gap-1.5 px-6">
        <HeroFigures
            :value="props.account.marketValue"
            :gain="props.account.gain"
            :gain-label="pct(props.account.gainPct)"
            :entries="entries"
        />

        <p data-wallet-regime class="text-xs text-muted-foreground">{{ props.account.taxRegimeLabel }}</p>

        <p v-if="age" data-wallet-age class="text-xs text-subtle-foreground">{{ age }}</p>

        <p
            v-if="props.account.ineligibleAssetNames.length"
            data-wallet-alert
            class="text-xs font-semibold text-loss"
        >
            Non éligible à cette enveloppe : {{ props.account.ineligibleAssetNames.join(', ') }}
        </p>
    </section>
</template>
