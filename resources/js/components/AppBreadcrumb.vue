<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

interface BreadcrumbItem {
    label: string;
    href?: string;
}

defineProps<{ items: BreadcrumbItem[] }>();
</script>

<template>
    <header class="sticky top-0 z-40 bg-black shadow-md shadow-black/60">
        <nav aria-label="Fil d'Ariane" class="mx-auto flex max-w-6xl items-center gap-1.5 overflow-x-auto px-6 py-3 text-sm">
            <template v-for="(item, index) in items" :key="index">
                <ChevronRight v-if="index > 0" class="size-4 shrink-0 text-muted-foreground" />
                <Link
                    v-if="item.href"
                    :href="item.href"
                    class="shrink-0 text-muted-foreground transition-colors hover:text-foreground"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="shrink-0 font-medium text-foreground" aria-current="page">{{ item.label }}</span>
            </template>
        </nav>
    </header>
</template>
