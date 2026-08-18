import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

/**
 * Build séparé du service worker : il doit être servi depuis la racine pour avoir un scope `/`,
 * donc ni hash de nom ni découpage en chunks. Les greffons Laravel et Vue n'ont rien à y faire.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    build: {
        emptyOutDir: false,
        outDir: 'public',
        rollupOptions: {
            input: fileURLToPath(new URL('./resources/js/pwa/sw.ts', import.meta.url)),
            output: {
                format: 'iife',
                entryFileNames: 'sw-runtime.js',
                inlineDynamicImports: true,
            },
        },
    },
});
