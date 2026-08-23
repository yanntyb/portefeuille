<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Component, ComputedRef } from 'vue';
import { useThemeStore } from '@/stores/theme';
import type { ThemeMode } from '@/stores/theme';

const theme = useThemeStore();

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
    (): ThemeAppearance => APPEARANCES[theme.mode] ?? APPEARANCES.auto,
);
</script>

<template>
    <button
        type="button"
        data-theme-toggle
        :data-theme-mode="theme.mode"
        :aria-label="`Changer de thème (actuellement : ${appearance.label})`"
        :title="`Thème : ${appearance.label}`"
        class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition-colors hover:text-foreground"
        @click="theme.cycle()"
    >
        <component :is="appearance.icon" class="size-4" />
    </button>
</template>
