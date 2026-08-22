<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { pageContainer, type PageWidth } from '@/lib/layout';

interface BreadcrumbItem {
    label: string;
    href?: string;
}

const props = withDefaults(defineProps<{ items?: BreadcrumbItem[]; width?: PageWidth }>(), {
    items: () => [],
    width: 'narrow',
});
</script>

<template>
    <footer data-bottom-bar class="sticky bottom-0 z-40 bg-background/95 backdrop-blur">
        <!-- Hauteur imposée pour que la barre garde la même épaisseur sans fil d'Ariane, sur le tableau de bord. -->
        <div :class="[pageContainer(props.width), 'flex min-h-11 items-center gap-3 px-6']">
            <nav
                v-if="props.items.length"
                aria-label="Fil d'Ariane"
                class="flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto py-3 text-sm"
            >
                <template v-for="(item, index) in props.items" :key="index">
                    <ChevronRight v-if="index > 0" class="size-4 shrink-0 text-muted-foreground" />
                    <Link
                        v-if="item.href"
                        :href="item.href"
                        prefetch
                        class="shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ item.label }}
                    </Link>
                    <span v-else class="shrink-0 font-medium text-foreground" aria-current="page">{{ item.label }}</span>
                </template>
            </nav>

            <ThemeToggle class="ml-auto shrink-0" />
        </div>
    </footer>
</template>
