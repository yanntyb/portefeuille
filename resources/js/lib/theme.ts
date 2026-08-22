import { usePreferredDark, useStorage } from '@vueuse/core';
import { computed, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/** Automatique suit le système ; clair et sombre sont des choix explicites du lecteur. */
export type ThemeMode = 'auto' | 'light' | 'dark';

/**
 * Clé partagée avec le script inline du layout, qui relit ce choix avant le premier rendu. Les deux
 * lectures doivent porter sur la même clé, sinon la page s'ouvre sur un thème puis bascule.
 */
export const THEME_STORAGE_KEY = 'argent-theme';

/** Ordre des clics du bouton : l'automatique reste atteignable, donc réversible. */
const CYCLE: readonly ThemeMode[] = ['auto', 'light', 'dark'];

/** Mode choisi, retenu d'une visite à l'autre. */
export const themeMode: Ref<ThemeMode> = useStorage<ThemeMode>(THEME_STORAGE_KEY, 'auto');

const preferredDark: Ref<boolean> = usePreferredDark();

/**
 * Thème effectivement affiché, réactif : les graphes s'y accrochent pour choisir leur palette. Tout
 * ce qui n'est pas un choix explicite retombe sur le système, y compris un stockage abîmé.
 */
export const isDark: ComputedRef<boolean> = computed((): boolean => {
    if (themeMode.value === 'light') {
        return false;
    }

    if (themeMode.value === 'dark') {
        return true;
    }

    return preferredDark.value;
});

/** Mode suivant dans le cycle du bouton. */
export function nextThemeMode(mode: ThemeMode): ThemeMode {
    const position = CYCLE.indexOf(mode);

    return CYCLE[(position + 1) % CYCLE.length];
}

/** Avance d'un cran dans le cycle. Appelé par le bouton de thème. */
export function cycleTheme(): void {
    themeMode.value = nextThemeMode(themeMode.value);
}

/**
 * Suit le thème retenu en posant la classe `dark` sur la racine. Le premier rendu est déjà traité
 * par le script inline du layout ; ce watcher gère les changements à chaud.
 */
export function useStoredTheme(): void {
    watch(
        isDark,
        (dark: boolean): void => {
            document.documentElement.classList.toggle('dark', dark);
        },
        { immediate: true },
    );
}
