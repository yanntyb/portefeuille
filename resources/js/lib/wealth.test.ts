import { describe, expect, it } from 'vitest';
import { assetClassWeights, type AssetClass, type WealthOverview } from '@/lib/wealth';

const assetClass = (overrides: Partial<AssetClass> = {}): AssetClass => ({
    key: 'equity',
    label: 'Actions',
    href: '/instruments',
    color: 'value',
    value: 1000,
    invested: 800,
    gain: 200,
    gainPct: 25,
    ...overrides,
});

const overview = (classes: AssetClass[], totalValue?: number): WealthOverview => ({
    totalValue: totalValue ?? classes.reduce((sum: number, line: AssetClass): number => sum + line.value, 0),
    totalInvested: 0,
    totalGain: 0,
    totalGainPct: null,
    classes,
});

describe('assetClassWeights', () => {
    it('pèse chaque classe contre le patrimoine entier', () => {
        const weights = assetClassWeights(overview([
            assetClass({ key: 'securities', value: 2500 }),
            assetClass({ key: 'realEstate', value: 7000 }),
            assetClass({ key: 'crypto', value: 500 }),
        ]));

        expect(weights.map((weight) => weight.share)).toEqual([25, 70, 5]);
        expect(weights.map((weight) => weight.barWidth)).toEqual(['25%', '70%', '5%']);
    });

    it('garde l\'ordre du registre plutôt que celui des poids', () => {
        const weights = assetClassWeights(overview([
            assetClass({ key: 'securities', value: 1000 }),
            assetClass({ key: 'realEstate', value: 9000 }),
        ]));

        expect(weights.map((weight) => weight.line.key)).toEqual(['securities', 'realEstate']);
    });

    it('écarte une classe sans valeur : sa ligne n\'apprendrait rien', () => {
        const weights = assetClassWeights(overview([
            assetClass({ key: 'securities', value: 1000 }),
            assetClass({ key: 'crypto', value: 0 }),
        ]));

        expect(weights.map((weight) => weight.line.key)).toEqual(['securities']);
    });

    it('rapporte la part au total du patrimoine, pas à la somme des lignes gardées', () => {
        const weights = assetClassWeights(overview([assetClass({ value: 1000 })], 4000));

        expect(weights[0].share).toBe(25);
    });

    it('ne fabrique pas de part quand le total est nul', () => {
        const weights = assetClassWeights(overview([assetClass({ value: 1000 })], 0));

        expect(weights[0].share).toBe(0);
        expect(weights[0].barWidth).toBe('0%');
    });

    it('laisse une classe négative porter son signe mais vide sa barre', () => {
        const weights = assetClassWeights(overview([
            assetClass({ key: 'securities', value: 1000 }),
            assetClass({ key: 'realEstate', value: -250 }),
        ], 1000));

        expect(weights[1].share).toBe(-25);
        expect(weights[1].barWidth).toBe('0%');
    });
});
