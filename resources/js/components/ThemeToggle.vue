<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component, ComputedRef } from 'vue';
import { cycleTheme, themeMode, type ThemeMode } from '@/lib/theme';

interface ThemeAppearance {
    icon: Component;
    label: string;
}

/** Une icône par mode : l'écran dit « je suis le système », le soleil et la lune un choix assumé. */
const APPEARANCES: Record<ThemeMode, ThemeAppearance> = {
    auto: { icon: Monitor, label: 'automatique' },
    light: { icon: Sun, label: 'clair' },
    dark: { icon: Moon, label: 'sombre' },
};

const appearance: ComputedRef<ThemeAppearance> = computed(
    (): ThemeAppearance => APPEARANCES[themeMode.value] ?? APPEARANCES.auto,
);
</script>

<template>
    <button
        type="button"
        data-theme-toggle
        :data-theme-mode="themeMode"
        :aria-label="`Changer de thème (actuellement : ${appearance.label})`"
        :title="`Thème : ${appearance.label}`"
        class="inline-flex items-center justify-center rounded-md border border-border p-2 text-muted-foreground transition-colors hover:text-foreground"
        @click="cycleTheme()"
    >
        <component :is="appearance.icon" class="size-4" />
    </button>
</template>
