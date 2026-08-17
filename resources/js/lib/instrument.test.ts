import { describe, expect, it } from 'vitest';
import { heroMeta, heroValueOf, investedOf, type Instrument, type InstrumentPosition } from '@/lib/instrument';

/** `Intl` en fr-FR pose U+202F comme séparateur de milliers et U+00A0 avant l'euro : normalise les deux. */
const normalizeSpaces = (value: string): string => value.replace(/[\xa0\u202f]/g, ' ');

const position = (overrides: Partial<InstrumentPosition> = {}): InstrumentPosition => ({
    quantity: 10,
    avgCost: 80,
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    ...overrides,
});

const instrument = (overrides: Partial<Instrument> = {}): Instrument => ({
    id: 1,
    name: 'ACME ETF',
    ticker: 'ACME',
    isin: 'FR0000000001',
    type: 'etf',
    typeLabel: 'ETF',
    lastPrice: 100,
    lastPriceDate: '2026-07-01',
    position: position(),
    transactions: [],
    sectors: [],
    ...overrides,
});

describe('investedOf', () => {
    it('déduit le montant investi du prix de revient et de la quantité', () => {
        expect(investedOf(position())).toBe(800);
    });

    it('ne déduit rien sans prix de revient', () => {
        expect(investedOf(position({ avgCost: null }))).toBeNull();
    });
});

describe('heroValueOf', () => {
    it('met en avant la valeur de marché quand l\'instrument est détenu', () => {
        expect(heroValueOf(instrument())).toBe(1000);
    });

    it('retombe sur le dernier cours quand il n\'est pas détenu', () => {
        expect(heroValueOf(instrument({ position: null }))).toBe(100);
    });
});

describe('heroMeta', () => {
    it('énonce titres, prix de revient, investi et cours quand l\'instrument est détenu', () => {
        const entries = heroMeta(instrument());

        expect(entries.map((entry) => entry.label)).toEqual(['Titres', 'PRU', 'Investi', 'Cours']);
        expect(entries.map((entry) => normalizeSpaces(entry.value))).toEqual([
            '10',
            '80,00 €',
            '800,00 €',
            '100,00 €',
        ]);
    });

    it('énonce la date du dernier cours quand l\'instrument n\'est pas détenu', () => {
        const entries = heroMeta(instrument({ position: null }));

        expect(entries).toEqual([{ label: '', value: 'au 01/07/2026' }]);
    });

    it('n\'énonce rien quand l\'instrument n\'est ni détenu ni coté', () => {
        expect(heroMeta(instrument({ position: null, lastPriceDate: null }))).toEqual([]);
    });

    it('rend un tiret sur un prix de revient absent plutôt que de masquer la ligne', () => {
        const entries = heroMeta(instrument({ position: position({ avgCost: null }) }));

        expect(entries[1]).toEqual({ label: 'PRU', value: '—' });
        expect(entries[2]).toEqual({ label: 'Investi', value: '—' });
    });
});
