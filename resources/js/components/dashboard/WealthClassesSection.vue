<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { eur as formatEur, sharePct } from '@/lib/format';
import { assetClassWeights, type AssetClassWeight, type WealthOverview } from '@/lib/wealth';

const props = defineProps<{ overview: WealthOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);

/**
 * Une classe sans valeur ne montre pas sa ligne : une ligne à zéro n'apprend rien. L'ordre est
 * celui du registre côté serveur, jamais un tri d'ici.
 */
const lines = computed<AssetClassWeight[]>(() => assetClassWeights(props.overview));
</script>

<template>
    <section data-section="wealth-classes" class="flex shrink-0 flex-col">
        <ul v-if="lines.length" class="flex flex-col gap-1 px-3">
            <li v-for="entry in lines" :key="entry.line.key" data-wealth-class>
                <!--
                    Sans page dédiée — les liquidités — la ligne reste une ligne : ni lien, ni survol,
                    ni chevron, qui promettraient un ailleurs où il n'y a rien à ouvrir.
                -->
                <component
                    :is="entry.line.href === null ? 'div' : Link"
                    v-bind="entry.line.href === null ? {} : { href: entry.line.href, prefetch: true }"
                    class="flex flex-col gap-2 rounded-md px-3 py-2.5 text-sm"
                    :class="entry.line.href === null ? '' : 'hover:bg-muted'"
                >
                    <span class="flex items-center justify-between gap-3">
                        <span class="font-medium">{{ entry.line.label }}</span>
                        <span class="flex shrink-0 items-center gap-3 tabular-nums">
                            <span>
                                <span class="font-semibold">{{ eur(entry.line.value) }}</span>
                                <span class="text-[13.5px] text-subtle-foreground">
                                    · <span data-wealth-share>{{ sharePct(entry.share) }}</span>
                                </span>
                            </span>
                            <ChevronRight
                                v-if="entry.line.href !== null"
                                class="size-4 text-muted-foreground"
                            />
                        </span>
                    </span>

                    <!-- La barre reste dans le lien : la ligne entière mène à la classe, sa part comprise. -->
                    <span class="block h-[7px] w-full overflow-hidden rounded-full bg-separator">
                        <span
                            data-wealth-bar
                            class="block h-full rounded-full bg-sector-bar"
                            :style="{ width: entry.barWidth }"
                        ></span>
                    </span>
                </component>
            </li>
        </ul>

        <p v-else class="px-6 py-8 text-center text-sm text-muted-foreground">
            Aucun patrimoine pour l'instant.
        </p>
    </section>
</template>
