<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

const query = defineModel<string>({ required: true });

const clear = (): void => {
    query.value = '';
};
</script>

<template>
    <div class="relative w-full">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

        <input
            v-model="query"
            data-catalog-search
            type="text"
            autocomplete="off"
            placeholder="Rechercher un nom, un ticker, un ISIN"
            aria-label="Rechercher un instrument"
            class="h-9 w-full rounded-md border border-border bg-background pl-9 pr-9 text-sm transition-colors placeholder:text-muted-foreground focus:border-ring focus:outline-none"
        />

        <button
            v-if="query !== ''"
            data-catalog-search-clear
            type="button"
            aria-label="Effacer la recherche"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
