<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { frDate, sharePct } from '@/lib/format';
import type { Drawdown } from '@/lib/analysis';

const props = defineProps<{ drawdown: Drawdown | null }>();
</script>

<template>
    <section data-section="drawdown" class="flex shrink-0 flex-col gap-3 px-6">
        <header class="flex flex-col gap-0.5">
            <h2 class="text-sm font-semibold">Perte maximale</h2>
        </header>

        <dl v-if="props.drawdown !== null" class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-0.5">
                <dt class="text-[13px] text-muted-foreground">
                    <template v-if="props.drawdown.peakLabel">
                        du {{ frDate(props.drawdown.peakLabel) }} au {{ frDate(props.drawdown.troughLabel) }}
                    </template>
                    <template v-else>Aucune baisse depuis le plus-haut</template>
                </dt>
                <dd class="text-lg font-semibold tabular-nums">{{ sharePct(props.drawdown.maxDepth) }}</dd>
            </div>
            <div class="flex flex-col gap-0.5">
                <dt class="text-[13px] text-muted-foreground">Perte en cours</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ sharePct(props.drawdown.currentDepth) }}</dd>
            </div>
        </dl>

        <Deferred v-else data="drawdown">
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
