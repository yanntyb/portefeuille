import { fileURLToPath, URL } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

/**
 * Configuration séparée de `vite.config.ts` : les greffons de build (Laravel, Vue, Tailwind) n'ont
 * rien à faire dans une exécution de tests unitaires. `happy-dom` est requis parce que
 * `lib/theme.ts` appelle `usePreferredDark()` au chargement du module, donc touche `matchMedia`.
 * Le greffon Vue n'est là que pour les tests de composant : sans lui, un `.vue` importé depuis un
 * test n'est pas compilé.
 */
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        setupFiles: ['./vitest.setup.ts'],
        include: ['resources/js/**/*.test.ts'],
    },
});
