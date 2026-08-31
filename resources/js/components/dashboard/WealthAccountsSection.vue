<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import GainPill from '@/components/GainPill.vue';
import { eur, pct } from '@/lib/format';
import type { WealthAccount } from '@/lib/wealth';

const props = defineProps<{ accounts?: WealthAccount[] | null }>();

const rows = computed<WealthAccount[]>(() => props.accounts ?? []);

const hasAccounts = computed<boolean>(() => rows.value.length > 0);

/** Accord du singulier : « 1 an », jamais « 1 ans ». */
const years = (n: number): string => (n === 1 ? '1 an' : `${n} ans`);

/** L'ancienneté ne s'affiche pas sans date d'ouverture : un compte « 0 an » mentirait. */
const age = (account: WealthAccount): string | null =>
    account.ageInYears === null ? null : years(account.ageInYears);

const maturity = (account: WealthAccount): string | null => {
    if (account.maturityYears === null || account.ageInYears === null) {
        return null;
    }

    return account.ageInYears >= account.maturityYears
        ? `Seuil de ${years(account.maturityYears)} franchi`
        : `Seuil de ${years(account.maturityYears)} dans ${years(account.maturityYears - account.ageInYears)}`;
};
</script>

<template>
    <!-- Repliée à l'arrivée, comme les secteurs : la lecture fiscale se demande. -->
    <CollapsibleSection section="wealth-accounts" title="Enveloppes">
        <template v-if="props.accounts !== null && props.accounts !== undefined">
            <ul v-if="hasAccounts" class="flex flex-col gap-3">
                <li
                    v-for="account in rows"
                    :key="account.walletId"
                    data-account-card
                    class="flex flex-col gap-1.5 rounded-md border border-separator px-3 py-3"
                >
                    <span class="flex items-center gap-3">
                        <span data-account-name class="min-w-0 flex-1 truncate font-semibold">
                            {{ account.walletName }}
                            <span class="text-muted-foreground">({{ account.accountTypeLabel }})</span>
                        </span>

                        <span data-account-value class="shrink-0 font-bold tabular-nums">
                            {{ eur(account.marketValue, 0) }}
                        </span>

                        <!-- `GainPill` prend une valeur (qui décide la teinte) et le texte à rendre. -->
                        <GainPill :value="account.gain" :label="pct(account.gainPct)" />
                    </span>

                    <span data-account-regime class="text-xs text-muted-foreground">
                        {{ account.taxRegimeLabel }}
                    </span>

                    <span
                        v-if="age(account)"
                        data-account-age
                        class="text-xs text-subtle-foreground"
                    >
                        Ouverte depuis {{ age(account) }}<template v-if="maturity(account)"> · {{ maturity(account) }}</template>
                    </span>

                    <span
                        v-if="account.ineligibleAssetNames.length"
                        data-account-alert
                        class="text-xs font-semibold text-loss"
                    >
                        Non éligible à cette enveloppe : {{ account.ineligibleAssetNames.join(', ') }}
                    </span>
                </li>
            </ul>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune enveloppe détenue pour le moment.
            </p>
        </template>

        <Deferred v-else data="accounts">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 2" :key="n" class="h-16 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
