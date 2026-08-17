import { describe, expect, it } from 'vitest';
import { eur, frDate, gainClass, pct, signedEur, signedPct } from '@/lib/format';

/**
 * `Intl` en fr-FR pose des espaces fines insécables (U+202F) entre les milliers et avant l'euro,
 * et leur position varie selon la version d'ICU. Les assertions se lisent sur des espaces normales.
 */
const normalizeSpaces = (value: string): string => value.replace(/[  ]/g, ' ');

describe('eur', () => {
    it('rend un montant en euros avec deux décimales par défaut', () => {
        expect(normalizeSpaces(eur(1000))).toBe('1 000,00 €');
    });

    it('coupe les décimales quand on le lui demande', () => {
        expect(normalizeSpaces(eur(1000, 0))).toBe('1 000 €');
    });

    it('rend un tiret plutôt que zéro quand la valeur est absente', () => {
        expect(eur(null)).toBe('—');
    });
});

describe('signedEur', () => {
    it('montre le signe sur un gain pour qu\'il ne se lise pas comme un solde', () => {
        expect(normalizeSpaces(signedEur(200))).toBe('+200,00 €');
    });

    it('laisse le signe négatif porté par le formatage monétaire', () => {
        expect(normalizeSpaces(signedEur(-200))).toBe('-200,00 €');
    });

    it('ne signe pas un montant nul', () => {
        expect(normalizeSpaces(signedEur(0))).toBe('0,00 €');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(signedEur(null)).toBe('—');
    });
});

describe('pct', () => {
    it('signe un pourcentage positif et garde une décimale', () => {
        expect(normalizeSpaces(pct(25))).toBe('+25,0 %');
    });

    it('rend un pourcentage négatif sans double signe', () => {
        expect(normalizeSpaces(pct(-3.25))).toBe('-3,3 %');
    });

    it('traite zéro comme positif, le signe rassurant sur la lecture', () => {
        expect(normalizeSpaces(pct(0))).toBe('+0,0 %');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(pct(null)).toBe('—');
    });
});

describe('signedPct', () => {
    it('lit une base 100 comme son écart signé', () => {
        expect(normalizeSpaces(signedPct(112.4))).toBe('+12,4 %');
    });

    it('lit une base 100 sous le pair comme un écart négatif', () => {
        expect(normalizeSpaces(signedPct(87.6))).toBe('-12,4 %');
    });
});

describe('frDate', () => {
    it('rend une date ISO au format français', () => {
        expect(frDate('2026-07-01')).toBe('01/07/2026');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frDate('pas une date')).toBe('pas une date');
    });
});

describe('gainClass', () => {
    it('teinte un gain, une perte, et laisse le neutre en sourdine', () => {
        expect(gainClass(10)).toBe('text-gain');
        expect(gainClass(-10)).toBe('text-loss');
        expect(gainClass(0)).toBe('text-muted-foreground');
        expect(gainClass(null)).toBe('text-muted-foreground');
    });
});
