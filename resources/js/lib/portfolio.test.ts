import { describe, expect, it } from 'vitest';
import { holdingWeights, type HoldingLine } from '@/lib/portfolio';

const line = (assetName: string, marketValue: number | null, assetId = 1): HoldingLine => ({
    assetId,
    assetName,
    ticker: assetName.slice(0, 3).toUpperCase(),
    type: 'stock',
    typeLabel: 'Action',
    quantity: 10,
    avgCost: 80,
    lastPrice: 100,
    marketValue,
    gain: 200,
    gainPct: 25,
});

describe('holdingWeights', () => {
    it('trie les positions de la plus lourde à la plus légère', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.line.assetName)).toEqual(['ACME', 'BETA']);
    });

    it('pèse chaque position sur le total des lignes, qui fait donc 100 %', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.share)).toEqual([80, 20]);
    });

    it('mesure les barres contre la plus lourde, pas contre le total', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.barWidth)).toEqual(['100%', '25%']);
    });

    it('ne coupe que le rendu : les parts restent calculées sur tout le portefeuille', () => {
        const twelve = Array.from({ length: 12 }, (_unused, index) => line(`LINE${index + 1}`, (index + 1) * 100, index + 1));

        const weights = holdingWeights(twelve, 10);

        // 1 200 € sur les 7 800 € des douze lignes, pas sur les dix rendues.
        expect(weights).toHaveLength(10);
        expect(weights[0].line.marketValue).toBe(1200);
        expect(weights[0].share).toBeCloseTo(15.384, 2);
    });

    it('rend toutes les lignes quand aucune limite n\'est posée', () => {
        const twelve = Array.from({ length: 12 }, (_unused, index) => line(`LINE${index + 1}`, (index + 1) * 100, index + 1));

        expect(holdingWeights(twelve)).toHaveLength(12);
    });

    it('traite une valeur de marché absente comme nulle plutôt que de casser le total', () => {
        const weights = holdingWeights([line('ACME', 1000), line('BETA', null)]);

        expect(weights.map((weight) => weight.share)).toEqual([100, 0]);
    });

    it('rend un tableau vide sur un portefeuille vide', () => {
        expect(holdingWeights([])).toEqual([]);
    });
});
