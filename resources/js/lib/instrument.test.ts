import { describe, expect, it } from 'vitest';
import {
    heroMeta,
    heroValueOf,
    investedOf,
    transactionYears,
    type Instrument,
    type InstrumentPosition,
    type TransactionLine,
} from '@/lib/instrument';

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
    it('énonce investi, gain, cours et prix de revient quand l\'instrument est détenu', () => {
        const entries = heroMeta(instrument());

        expect(entries.map((entry) => entry.label)).toEqual(['Investi', 'Gain', 'Cours', 'PRU']);
        expect(entries.map((entry) => normalizeSpaces(entry.value))).toEqual([
            '800,00 €',
            '+200,00 €',
            '100,00 €',
            '80,00 €',
        ]);
    });

    it('marque le seul gain d\'un montant coloré, les autres stats restant neutres', () => {
        const entries = heroMeta(instrument({ position: position({ gain: -50 }) }));

        expect(entries[1].gain).toBe(-50);
        expect(entries.filter((entry) => entry.gain !== undefined)).toHaveLength(1);
    });

    it('rend un tiret sur un gain absent plutôt que de masquer la ligne', () => {
        const entries = heroMeta(instrument({ position: position({ gain: null }) }));

        expect(entries[1]).toEqual({ label: 'Gain', value: '—', gain: null });
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

        expect(entries[0]).toEqual({ label: 'Investi', value: '—' });
        expect(entries[3]).toEqual({ label: 'PRU', value: '—' });
    });
});

const line = (overrides: Partial<TransactionLine> = {}): TransactionLine => ({
    date: '2026-03-12',
    isSell: false,
    typeLabel: 'Achat',
    quantity: 3,
    unitPrice: 82.5,
    fees: 2.5,
    total: 247.5,
    ...overrides,
});

describe('transactionYears', () => {
    it('regroupe les transactions par année, la plus récente en tête', () => {
        const years = transactionYears([
            line({ date: '2024-11-28' }),
            line({ date: '2026-03-12' }),
            line({ date: '2025-06-04' }),
        ]);

        expect(years.map((group) => group.year)).toEqual(['2026', '2025', '2024']);
    });

    it('garde dans chaque année ses seules transactions', () => {
        const years = transactionYears([
            line({ date: '2026-03-12' }),
            line({ date: '2026-02-03' }),
            line({ date: '2025-06-04' }),
        ]);

        expect(years[0].count).toBe(2);
        expect(years[0].lines.map((entry) => entry.date)).toEqual(['2026-03-12', '2026-02-03']);
        expect(years[1].lines.map((entry) => entry.date)).toEqual(['2025-06-04']);
    });

    it('compte une vente en négatif dans le flux investi de l\'année', () => {
        const years = transactionYears([
            line({ date: '2026-03-12', total: 250 }),
            line({ date: '2026-02-03', total: 180, isSell: true, typeLabel: 'Vente' }),
        ]);

        expect(years[0].net).toBe(70);
    });

    it('ne rend aucun groupe sans transaction', () => {
        expect(transactionYears([])).toEqual([]);
    });
});
