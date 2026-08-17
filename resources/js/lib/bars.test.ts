import { describe, expect, it } from 'vitest';
import { largestOf, relativeBarWidth } from '@/lib/bars';

describe('largestOf', () => {
    it('rend la plus grande valeur du lot', () => {
        expect(largestOf([20, 80, 50])).toBe(80);
    });

    it('rend zéro sur un lot vide plutôt que moins l\'infini', () => {
        expect(largestOf([])).toBe(0);
    });

    it('ne descend jamais sous zéro', () => {
        expect(largestOf([-10, -50])).toBe(0);
    });
});

describe('relativeBarWidth', () => {
    it('remplit toute la piste pour la plus grande valeur', () => {
        expect(relativeBarWidth(80, 80)).toBe('100%');
    });

    it('mesure les autres valeurs contre la plus grande, pas contre le total', () => {
        expect(relativeBarWidth(20, 80)).toBe('25%');
    });

    it('rend une barre nulle quand il n\'y a rien à mesurer', () => {
        expect(relativeBarWidth(10, 0)).toBe('0%');
    });

    it('rend une barre nulle pour une valeur nulle', () => {
        expect(relativeBarWidth(0, 80)).toBe('0%');
    });
});
