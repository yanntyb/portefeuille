import { createPinia, setActivePinia, type Pinia } from 'pinia';

/**
 * Instance unique. L'application monte deux racines Vue — l'application Inertia et le bandeau du
 * service worker — et deux instances leur donneraient deux états séparés, donc un bandeau aveugle
 * à ce que la page sait.
 *
 * `setActivePinia` est appelé ici, à l'import : `app.ts` utilise des stores avant
 * `createInertiaApp`, donc hors de tout composant, ce qui exige une instance déjà active.
 */
export const pinia: Pinia = createPinia();

setActivePinia(pinia);
