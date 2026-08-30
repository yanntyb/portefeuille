import { describe, expect, it } from 'vitest';
import { progressSettings } from '@/lib/progress';

describe('progressSettings', () => {
    it('accuse le clic sans retard et sans roue de chargement', () => {
        expect(progressSettings.delay).toBe(0);
        expect(progressSettings.showSpinner).toBe(false);
    });

    it('laisse Inertia injecter ses styles, que la feuille du thème surcharge', () => {
        expect(progressSettings.includeCSS).toBe(true);
    });
});
