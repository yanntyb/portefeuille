import { describe, expect, it } from 'vitest';
import { activePageIndex, dotClass, pageScrollOffset } from '@/lib/carousel';

describe('activePageIndex', () => {
    it('arrondit à la page la plus proche pendant le glissement', () => {
        expect(activePageIndex(0, 390, 4)).toBe(0);
        expect(activePageIndex(100, 390, 4)).toBe(0);
        expect(activePageIndex(250, 390, 4)).toBe(1);
        expect(activePageIndex(390, 390, 4)).toBe(1);
    });

    it('borne l\'index au nombre de pages malgré le rebond élastique', () => {
        expect(activePageIndex(-80, 390, 4)).toBe(0);
        expect(activePageIndex(9000, 390, 4)).toBe(3);
    });

    it('rend zéro tant que la piste n\'a pas de largeur mesurable', () => {
        expect(activePageIndex(0, 0, 4)).toBe(0);
        expect(activePageIndex(120, 0, 4)).toBe(0);
    });

    it('rend zéro quand il n\'y a aucune page', () => {
        expect(activePageIndex(0, 390, 0)).toBe(0);
    });
});

describe('pageScrollOffset', () => {
    it('place chaque page à un multiple de la largeur de piste', () => {
        expect(pageScrollOffset(0, 390)).toBe(0);
        expect(pageScrollOffset(2, 390)).toBe(780);
    });
});

describe('dotClass', () => {
    it('grossit et opacifie le point de la page courante', () => {
        expect(dotClass(1, 1)).toBe('scale-125 opacity-100');
    });

    it('rapetisse et estompe les autres points', () => {
        expect(dotClass(0, 1)).toBe('scale-100 opacity-40');
        expect(dotClass(3, 1)).toBe('scale-100 opacity-40');
    });
});
