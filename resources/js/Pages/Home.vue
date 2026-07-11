<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    appName: string;
}>();
</script>

<template>
    <Head title="Accueil" />

    <main class="flex min-h-screen flex-col items-center justify-center gap-4 bg-neutral-50 p-6 text-center dark:bg-neutral-950">
        <h1 class="text-3xl font-semibold text-neutral-900 dark:text-neutral-100">
            {{ appName }}
        </h1>
        <p class="text-neutral-600 dark:text-neutral-400">
            Inertia + Vue 3 + TypeScript est en place.
        </p>
    </main>
</template>
