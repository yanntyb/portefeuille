<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { pct, sharePct } from '@/lib/format';
import type { Contribution } from '@/lib/analysis';

const props = defineProps<{ contributions: Contribution[] | null }>();
</script>

<template>
    <section data-section="contribution" class="flex shrink-0 flex-col gap-3 px-6">
        <header class="flex flex-col gap-0.5">
            <h2 class="text-sm font-semibold">Contribution au rendement</h2>
            <p class="text-[13px] text-muted-foreground">En points de performance de l'exposition</p>
        </header>

        <template v-if="props.contributions !== null">
            <p v-if="props.contributions.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                Aucune position mesurable
            </p>

            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="line in props.contributions"
                    :key="line.assetId"
                    class="flex items-center justify-between gap-3"
                >
                    <span class="truncate text-sm">{{ line.assetName }}</span>
                    <span class="flex items-center gap-2 tabular-nums">
                        <span class="text-sm font-semibold">{{ pct(line.contribution) }}</span>
                        <span class="text-[13px] text-muted-foreground">{{ sharePct(line.weight) }}</span>
                    </span>
                </li>
            </ul>
        </template>

        <Deferred v-else data="analysis">
            <template #fallback>
                <div class="h-16 animate-pulse rounded-lg bg-muted" />
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </section>
</template>
