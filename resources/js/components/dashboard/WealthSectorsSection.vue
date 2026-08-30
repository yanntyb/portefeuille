<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import { sharePct } from '@/lib/format';
import type { SectorBreakdownRow } from '@/lib/sector';
import type { WealthSector } from '@/lib/wealth';

const props = defineProps<{ sectors?: WealthSector[] | null }>();

const rows = computed<SectorBreakdownRow[]>(() =>
    (props.sectors ?? []).map((sector: WealthSector): SectorBreakdownRow => ({
        label: sector.label,
        share: sector.pct,
        amount: sector.value,
    })),
);

const hasSectors = computed<boolean>(() => rows.value.length > 0);

/** Les tranches arrivent triées : la première est la dominante, celle qui tient dans le titre. */
const dominant = computed<WealthSector | null>(() => props.sectors?.[0] ?? null);
</script>

<template>
    <!--
        Repliée à l'arrivée, comme les revenus : le secteur dominant et sa part se lisent dans le
        titre, la ventilation se demande. Le `Deferred` vit donc dans l'en-tête, toujours rendue —
        la prop part au chargement de la page, pas au dépli.
    -->
    <CollapsibleSection section="wealth-sectors" title="Secteurs">
        <template #value>
            <span v-if="props.sectors !== null" data-sectors-dominant class="flex items-baseline gap-1.5">
                <template v-if="dominant">
                    <span class="text-[17px] leading-none font-bold">{{ dominant.label }}</span>
                    <span class="text-[13.5px] leading-none tabular-nums text-muted-foreground">
                        {{ sharePct(dominant.pct) }}
                    </span>
                </template>
                <span v-else class="text-[13.5px] leading-none text-muted-foreground">—</span>
            </span>

            <Deferred v-else data="sectors">
                <template #fallback>
                    <span class="block h-4 w-24 animate-pulse rounded-md bg-muted"></span>
                </template>

                <template #rescue>
                    <span class="text-[13.5px] leading-none text-muted-foreground">—</span>
                </template>

                <span />
            </Deferred>
        </template>

        <SectorBreakdownList v-if="props.sectors !== null && hasSectors" :rows="rows" />

        <p v-else-if="props.sectors !== null" class="py-8 text-center text-sm text-muted-foreground">
            Pas encore de données sectorielles.
        </p>

        <!-- Un second `Deferred` sur la même prop : le pli seul distingue l'attente de l'indisponible. -->
        <Deferred v-else data="sectors">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
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
