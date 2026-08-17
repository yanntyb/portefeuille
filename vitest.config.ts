import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Configuration séparée de `vite.config.ts` : les greffons de build (Laravel, Vue, Tailwind) n'ont
 * rien à faire dans une exécution de tests unitaires. `happy-dom` est requis parce que
 * `lib/theme.ts` appelle `usePreferredDark()` au chargement du module, donc touche `matchMedia`.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.test.ts'],
    },
});
