import { afterEach, describe, expect, it, vi } from 'vitest';
import { displayValue, eur, fractionPct, frDate, frDayMonth, frLongDate, frMonthYear, gainClass, isoToday, pct, sharePct, signedEur, syncedAtLabel } from '@/lib/format';

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

describe('displayValue', () => {
    it('arrondit à la précision demandée', () => {
        expect(displayValue(1.005, 2)).toBe(1);
        expect(displayValue(1.006, 2)).toBe(1.01);
        expect(displayValue(-0.04, 1)).toBe(0);
    });

    it('rend un zéro non signé, jamais le -0 que Intl signerait encore', () => {
        expect(Object.is(displayValue(-1e-14), 0)).toBe(true);
        expect(Object.is(displayValue(-0.004), 0)).toBe(true);
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

    it('ne signe pas un résidu flottant que l\'affichage lit comme zéro', () => {
        expect(normalizeSpaces(signedEur(-1e-14))).toBe('0,00 €');
        expect(normalizeSpaces(signedEur(1e-14))).toBe('0,00 €');
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

    it('signe en positif ce qui s\'affiche à zéro, résidu flottant compris', () => {
        expect(normalizeSpaces(pct(-1e-14))).toBe('+0,0 %');
        expect(normalizeSpaces(pct(-0.04))).toBe('+0,0 %');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(pct(null)).toBe('—');
    });
});

describe('sharePct', () => {
    it('rend une part avec une décimale et la virgule française', () => {
        expect(sharePct(42.13)).toBe('42,1 %');
    });

    it('ne signe pas une part positive : elle se lit en valeur absolue', () => {
        expect(sharePct(6.6)).toBe('6,6 %');
    });

    it('laisse le signe négatif d\'une classe en négatif', () => {
        expect(sharePct(-25)).toBe('-25,0 %');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(sharePct(null)).toBe('—');
    });
});

describe('fractionPct', () => {
    it('met à l\'échelle une fraction avec deux décimales', () => {
        expect(fractionPct(0.0655)).toBe('6,55 %');
    });

    it('rend zéro sans signe', () => {
        expect(fractionPct(0)).toBe('0,00 %');
    });

    it('arrondit à deux décimales', () => {
        expect(fractionPct(0.7083)).toBe('70,83 %');
    });
});

describe('frDate', () => {
    it('rend une date ISO au format français', () => {
        expect(frDate('2026-07-01')).toBe('01/07/2026');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frDate('pas une date')).toBe('pas une date');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(frDate(null)).toBe('—');
    });
});

describe('gainClass', () => {
    it('teinte un gain, une perte, et laisse le neutre en sourdine', () => {
        expect(gainClass(10)).toBe('text-gain');
        expect(gainClass(-10)).toBe('text-loss');
        expect(gainClass(0)).toBe('text-muted-foreground');
        expect(gainClass(null)).toBe('text-muted-foreground');
    });

    it('laisse en sourdine ce qui s\'affiche à zéro, et teinte dès la première décimale lue', () => {
        expect(gainClass(-1e-14)).toBe('text-muted-foreground');
        expect(gainClass(-0.004)).toBe('text-muted-foreground');
        expect(gainClass(-0.02)).toBe('text-loss');
    });
});

describe('syncedAtLabel', () => {
    it('rend l\'absence de synchronisation en clair', () => {
        expect(syncedAtLabel(null)).toBe('Données hors-ligne');
    });

    it('omet les minutes à l\'heure juste', () => {
        expect(syncedAtLabel(new Date('2026-08-18T11:00:00').getTime())).toBe('Données du 18/08 à 11h');
    });

    it('garde les minutes quand elles ne sont pas nulles', () => {
        expect(syncedAtLabel(new Date('2026-08-18T23:30:00').getTime())).toBe('Données du 18/08 à 23h30');
    });

    it('rend une heure à un chiffre sans zéro de tête', () => {
        expect(syncedAtLabel(new Date('2026-08-18T09:00:00').getTime())).toBe('Données du 18/08 à 9h');
    });

    it('rend minuit sans lever d\'erreur', () => {
        expect(syncedAtLabel(new Date('2026-08-18T00:00:00').getTime())).toBe('Données du 18/08 à 0h');
    });
});

describe('frDayMonth', () => {
    it('rend une date ISO en jour et mois, sans année', () => {
        expect(frDayMonth('2026-07-01')).toBe('01 juil.');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frDayMonth('pas une date')).toBe('pas une date');
    });
});

describe('frMonthYear', () => {
    it('rend un mois ISO en mois court et année', () => {
        expect(frMonthYear('2026-07-01')).toBe('juil. 2026');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frMonthYear('pas une date')).toBe('pas une date');
    });
});

describe('frLongDate', () => {
    it('rend une date ISO en jour, mois en entier et année', () => {
        expect(frLongDate('2026-08-20')).toBe('20 août 2026');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frLongDate('pas une date')).toBe('pas une date');
    });
});

describe('isoToday', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('rend la date locale, et non celle d\'UTC, tard le soir', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 3, 15, 23, 45, 0));

        /**
         * `toISOString().slice(0, 10)` rendrait le 16 en été comme le 15 selon le fuseau : à 23 h 45
         * à Paris, l'UTC a changé de jour. Le formulaire proposerait alors une date d'opération
         * fausse à qui saisit le soir.
         */
        expect(isoToday()).toBe('2026-04-15');
    });

    it('rend la date locale tôt le matin', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 0, 1, 0, 30, 0));

        expect(isoToday()).toBe('2026-01-01');
    });
});
