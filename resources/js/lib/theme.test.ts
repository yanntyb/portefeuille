import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

/**
 * `usePreferredDark` interroge `matchMedia`, que happy-dom fige à « clair » : on le remplace par un
 * `ref` pilotable, seule façon de vérifier que le mode automatique suit bien le système.
 */
const { preferredDark } = await vi.hoisted(async () => ({ preferredDark: (await import('vue')).ref(false) }));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    usePreferredDark: () => preferredDark,
}));

const { THEME_STORAGE_KEY, cycleTheme, isDark, nextThemeMode, themeMode, useStoredTheme } = await import('@/lib/theme');

beforeEach((): void => {
    preferredDark.value = false;
    themeMode.value = 'auto';
    document.documentElement.classList.remove('dark');
});

describe('cycle du bouton de thème', () => {
    it('part de l\'automatique pour aller vers le clair, puis le sombre, puis revient', () => {
        expect(nextThemeMode('auto')).toBe('light');
        expect(nextThemeMode('light')).toBe('dark');
        expect(nextThemeMode('dark')).toBe('auto');
    });

    it('avance le mode courant à chaque clic', () => {
        cycleTheme();

        expect(themeMode.value).toBe('light');

        cycleTheme();

        expect(themeMode.value).toBe('dark');
    });

    it('retient le choix pour la visite suivante, que le script du layout relira', async () => {
        cycleTheme();
        await nextTick();

        expect(localStorage.getItem(THEME_STORAGE_KEY)).toBe('light');
    });
});

describe('thème résolu', () => {
    it('reste clair quand le mode clair est choisi, même si le système est sombre', () => {
        preferredDark.value = true;
        themeMode.value = 'light';

        expect(isDark.value).toBe(false);
    });

    it('reste sombre quand le mode sombre est choisi, même si le système est clair', () => {
        themeMode.value = 'dark';

        expect(isDark.value).toBe(true);
    });

    it('suit le système en mode automatique', () => {
        preferredDark.value = true;

        expect(isDark.value).toBe(true);

        preferredDark.value = false;

        expect(isDark.value).toBe(false);
    });

    it('retombe sur le système si le stockage contient autre chose qu\'un mode connu', () => {
        preferredDark.value = true;
        themeMode.value = 'nuit-etoilee' as never;

        expect(isDark.value).toBe(true);
    });
});

describe('application au document', () => {
    it('pose la classe `dark` sur la racine dès l\'accrochage, sans attendre un changement', () => {
        themeMode.value = 'dark';

        useStoredTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('retire la classe quand on repasse au clair', async () => {
        themeMode.value = 'dark';

        useStoredTheme();

        themeMode.value = 'light';
        await nextTick();

        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });
});
