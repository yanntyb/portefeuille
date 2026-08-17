import { describe, expect, it } from 'vitest';
import { pageContainer } from '@/lib/layout';

describe('pageContainer', () => {
    it('rend un conteneur centré et borné pour chaque largeur', () => {
        expect(pageContainer('narrow')).toBe('mx-auto w-full max-w-[520px]');
        expect(pageContainer('wide')).toBe('mx-auto w-full max-w-6xl');
    });

    it('aligne le fil d\'Ariane et le contenu : même largeur, même conteneur', () => {
        expect(pageContainer('narrow')).toBe(pageContainer('narrow'));
        expect(pageContainer('wide')).toBe(pageContainer('wide'));
    });

    it('distingue les deux largeurs de page', () => {
        expect(pageContainer('narrow')).not.toBe(pageContainer('wide'));
    });
});
