import { usePreferredDark } from '@vueuse/core';
import { watch } from 'vue';
import type { Ref } from 'vue';

/** Préférence système, réactive : les graphes s'y accrochent pour choisir leur palette. */
export const isDark: Ref<boolean> = usePreferredDark();

/**
 * Suit le thème système en posant la classe `dark` sur la racine. Le premier rendu est
 * déjà traité par le script inline du layout ; ce watcher gère les changements à chaud.
 */
export function useSystemTheme(): void {
    watch(
        isDark,
        (dark: boolean): void => {
            document.documentElement.classList.toggle('dark', dark);
        },
        { immediate: true },
    );
}
