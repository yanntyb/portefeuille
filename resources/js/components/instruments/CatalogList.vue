<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { CatalogLine } from '@/lib/catalog';

/**
 * Le catalogue d'une exposition. Distinct d'`InstrumentList` : celui-ci parle de positions — gain,
 * poids, enveloppe — quand une ligne de catalogue n'est parfois qu'un titre jamais acheté.
 */
const props = defineProps<{
    lines: CatalogLine[];
    emptyLabel: string;
}>();
</script>

<template>
    <ul v-if="props.lines.length" class="flex flex-col">
        <li
            v-for="line in props.lines"
            :key="line.id"
            data-catalog-row
            class="flex flex-col justify-center border-b border-separator last:border-b-0"
        >
            <Link
                :href="`/asset/${line.id}`"
                prefetch
                class="flex items-center gap-3 rounded-md px-3 py-3 hover:bg-muted"
            >
                <span class="flex min-w-0 flex-1 flex-col gap-1">
                    <span data-catalog-name class="truncate font-semibold">
                        {{ line.name }}
                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                    </span>

                    <span class="flex items-center gap-2 text-xs">
                        <span
                            data-catalog-type
                            class="shrink-0 rounded-full bg-muted px-2 py-0.5 font-semibold text-muted-foreground"
                        >
                            {{ line.typeLabel }}
                        </span>

                        <!-- Le catalogue mêle le détenu et le reste : sans cette marque, rien ne les sépare. -->
                        <span v-if="line.held" data-catalog-held class="shrink-0 font-semibold text-subtle-foreground">
                            Détenu
                        </span>
                    </span>
                </span>

                <span data-catalog-price class="w-24 shrink-0 text-right font-bold tabular-nums">
                    {{ eur(line.lastPrice) }}
                </span>
            </Link>
        </li>
    </ul>

    <p v-else data-catalog-empty class="py-8 text-center text-sm text-muted-foreground">
        {{ props.emptyLabel }}
    </p>
</template>
