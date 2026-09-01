import { describe, expect, it } from 'vitest';
import {
    heroMeta,
    heroValueOf,
    investedOf,
    transactionLabelOf,
    transactionYears,
    type NamedTransactionLine,
    type Instrument,
    type InstrumentPosition,
    type TransactionLine,
} from '@/lib/instrument';

const position = (overrides: Partial<InstrumentPosition> = {}): InstrumentPosition => ({
    quantity: 10,
    avgCost: 80,
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    realizedGain: 0,
    ...overrides,
});

const instrument = (overrides: Partial<Instrument> = {}): Instrument => ({
    id: 1,
    name: 'ACME ETF',
    ticker: 'ACME',
    isin: 'FR0000000001',
    type: 'etf',
    typeLabel: 'ETF',
    assetClass: 'equity',
    assetClassLabel: 'Actions',
    assetClassHref: '/actions',
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
    it('ne rend plus rien pour une position : ses repères vivent dans la section Analyse', () => {
        expect(heroMeta(instrument())).toEqual([]);
    });

    it('énonce la date du dernier cours quand l\'instrument n\'est pas détenu', () => {
        const entries = heroMeta(instrument({ position: null }));

        expect(entries).toEqual([{ label: '', value: 'au 01/07/2026' }]);
    });

    it('n\'énonce rien quand l\'instrument n\'est ni détenu ni coté', () => {
        expect(heroMeta(instrument({ position: null, lastPriceDate: null }))).toEqual([]);
    });
});

const line = (overrides: Partial<TransactionLine> = {}): TransactionLine => ({
    id: 1,
    walletId: 3,
    date: '2026-03-12',
    isSell: false,
    typeLabel: 'Achat',
    type: 'buy',
    quantity: 3,
    unitPrice: 82.5,
    fees: 2.5,
    total: 247.5,
    auto: false,
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

        expect(years[0].lines).toHaveLength(2);
        expect(years[0].lines.map((entry) => entry.date)).toEqual(['2026-03-12', '2026-02-03']);
        expect(years[1].lines.map((entry) => entry.date)).toEqual(['2025-06-04']);
    });

    it('compte un achat en négatif et une vente en positif, dans le flux de trésorerie de l\'année', () => {
        const years = transactionYears([
            line({ date: '2026-03-12', total: 250 }),
            line({ date: '2026-02-03', total: 180, isSell: true, type: 'sell', typeLabel: 'Vente' }),
        ]);

        expect(years[0].net).toBe(-70);
    });

    it('fait entrer un versement et un dividende, sortir un retrait, dans le même flux', () => {
        const years = transactionYears([
            line({ date: '2026-03-01', total: 1000, type: 'deposit', typeLabel: 'Versement' }),
            line({ date: '2026-03-02', total: 200, type: 'withdrawal', typeLabel: 'Retrait' }),
            line({ date: '2026-03-03', total: 42.5, type: 'dividend', typeLabel: 'Dividende' }),
        ]);

        /**
         * Un achat financé par un virement du même montant solde à zéro dans ce flux : c'est le
         * seul qui reste juste une fois les espèces présentes, un « flux investi » doublerait le
         * mouvement en comptant les deux en positif.
         */
        expect(years[0].net).toBe(1000 - 200 + 42.5);
    });

    it('ne rend aucun groupe sans transaction', () => {
        expect(transactionYears([])).toEqual([]);
    });
});

describe('transactionLabelOf', () => {
    /** `toLocaleString` sépare par des espaces insécables : les normaliser garde le test lisible. */
    const plain = (value: string): string => value.replace(/[\s\u202f\u00a0]/g, ' ');

    const named = (overrides: Partial<NamedTransactionLine> = {}): NamedTransactionLine => ({
        ...line(),
        assetId: 7,
        assetName: 'Air Liquide',
        ...overrides,
    });

    it('nomme un ordre par sa quantité, formatée à la française', () => {
        expect(plain(transactionLabelOf(named({ quantity: 2.5 }))))
            .toBe('Achat de 2,5 Air Liquide du 12/03/2026');
    });

    /**
     * Un dividende porte un nom d'actif mais aucune quantité : « Dividende de 0 Air Liquide »
     * s'affichait là où « Dividende 34,90 € » dit le fait.
     */
    it('nomme par le type et le montant une ligne sans quantité', () => {
        expect(plain(transactionLabelOf(named({ type: 'dividend', typeLabel: 'Dividende', quantity: 0, total: 34.9 }))))
            .toBe('Dividende 34,90 € du 12/03/2026');
    });

    it('nomme de même un mouvement d\'espèces, qui n\'a pas d\'actif', () => {
        expect(plain(transactionLabelOf(named({
            type: 'deposit', typeLabel: 'Versement', assetId: null, assetName: null, quantity: 0, total: 1000,
        })))).toBe('Versement 1 000,00 € du 12/03/2026');
    });
});
