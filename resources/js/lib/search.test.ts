import { describe, expect, it } from 'vitest';
import { fold } from '@/lib/search';

describe('fold', () => {
    it('ramène la casse au bas', () => {
        expect(fold('Société')).toBe('societe');
    });

    it('retire les accents pour que « societe » trouve « Société »', () => {
        expect(fold('Société Générale').includes(fold('societe'))).toBe(true);
    });

    it('laisse intact un texte déjà déplié', () => {
        expect(fold('amzn')).toBe('amzn');
    });

    it('déplie aussi les signes composés', () => {
        expect(fold('Nestlé Ångström')).toBe('nestle angstrom');
    });
});
