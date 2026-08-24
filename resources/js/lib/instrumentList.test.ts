import { describe, expect, it } from 'vitest';
import type { CatalogTrend } from '@/lib/catalog';
import {
    areTrendsPending,
    holdingRows,
    isDeferredPending,
    type InstrumentRow,
} from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const holding = (assetId: number, assetName: string, marketValue: number): HoldingLine => ({
    assetId,
    assetName,
    ticker: assetName.slice(0, 3).toUpperCase(),
    type: 'stock',
    typeLabel: 'Action',
    assetClass: 'equity',
    assetClassLabel: 'Actions',
    quantity: 10,
    avgCost: 80,
    lastPrice: marketValue / 10,
    marketValue,
    gain: 200,
    gainPct: 25,
});

const names = (rows: InstrumentRow[]): string[] => rows.map((row) => row.name);

describe('holdingRows', () => {
    it('porte le gain et le poids de la position sur la ligne', () => {
        const rows = holdingRows([holding(1, 'Alpha', 3000)], undefined);

        expect(rows[0].held).toBe(true);
        expect(rows[0].gain).toBe(200);
        expect(rows[0].gainPct).toBe(25);
        expect(rows[0].share).toBe(100);
        expect(rows[0].barWidth).not.toBeNull();
    });

    it('range les positions par valeur, la plus grosse en tête', () => {
        const rows = holdingRows([holding(1, 'Alpha', 1000), holding(2, 'Beta', 3000)], undefined);

        expect(names(rows)).toEqual(['Beta', 'Alpha']);
    });

    it('calcule les parts sur le seul portefeuille', () => {
        const rows = holdingRows([holding(1, 'Alpha', 3000), holding(2, 'Beta', 1000)], undefined);

        expect(rows.find((row) => row.name === 'Alpha')?.share).toBe(75);
        expect(rows.find((row) => row.name === 'Beta')?.share).toBe(25);
    });

    it('rattache la tendance de la période par identifiant d\'actif', () => {
        const trends: CatalogTrend[] = [{ assetId: 2, changePct: 12.5, points: [1, 2, 3] }];
        const rows = holdingRows([holding(1, 'Alpha', 3000), holding(2, 'Beta', 1000)], trends);

        expect(rows.find((row) => row.name === 'Beta')?.changePct).toBe(12.5);
        expect(rows.find((row) => row.name === 'Beta')?.points).toEqual([1, 2, 3]);
        expect(rows.find((row) => row.name === 'Alpha')?.changePct).toBeNull();
    });

    it('rend une liste vide quand le portefeuille est vide', () => {
        expect(holdingRows([], undefined)).toEqual([]);
    });

    it('accepte `null`, rendu par la fusion réseau / instantané, comme une absence de tendances', () => {
        const rows = holdingRows([holding(1, 'Alpha', 3000)], null);

        expect(rows[0].changePct).toBeNull();
        expect(rows[0].points).toEqual([]);
    });
});

describe('isDeferredPending', () => {
    it('est en attente quand la valeur est absente et la clé non rescapée', () => {
        expect(isDeferredPending(undefined, 'trends', undefined)).toBe(true);
    });

    it('n\'est plus en attente quand la valeur est absente mais la clé rescapée', () => {
        expect(isDeferredPending(undefined, 'trends', ['trends'])).toBe(false);
    });

    it('n\'est jamais en attente dès que la valeur est arrivée, quelle que soit la liste rescapée', () => {
        expect(isDeferredPending([], 'trends', undefined)).toBe(false);
        expect(isDeferredPending([], 'trends', ['trends'])).toBe(false);
    });

    it('traite une liste de rescapées absente comme vide', () => {
        expect(isDeferredPending(undefined, 'trends', undefined)).toBe(isDeferredPending(undefined, 'trends', []));
    });

    it('est en attente quand la valeur est `null` et la clé non rescapée', () => {
        // `null` est le rendu de `aheadOfNetwork` quand ni la prop réseau ni l'instantané n'ont la
        // donnée : le squelette doit se comporter exactement comme pour `undefined`.
        expect(isDeferredPending(null, 'trends', undefined)).toBe(true);
    });

    it('n\'est plus en attente quand la valeur est `null` mais la clé rescapée', () => {
        expect(isDeferredPending(null, 'trends', ['trends'])).toBe(false);
    });

    it('traite `null` et `undefined` de façon identique, à clé et liste rescapées égales', () => {
        expect(isDeferredPending(null, 'trends', undefined)).toBe(isDeferredPending(undefined, 'trends', undefined));
        expect(isDeferredPending(null, 'trends', ['trends'])).toBe(isDeferredPending(undefined, 'trends', ['trends']));
    });
});

describe('areTrendsPending', () => {
    it('attend tant que les tendances ne sont pas arrivées', () => {
        expect(areTrendsPending(undefined, undefined)).toBe(true);
    });

    it('n\'attend plus dès que les tendances sont là', () => {
        expect(areTrendsPending([], undefined)).toBe(false);
    });

    it('n\'attend plus quand le worker a rescapé les tendances', () => {
        expect(areTrendsPending(undefined, ['trends'])).toBe(false);
    });

    it('ignore une clé rescapée qui ne la concerne pas', () => {
        expect(areTrendsPending(undefined, ['sectorBreakdown'])).toBe(true);
    });

    it('attend aussi quand les tendances fusionnées valent `null`', () => {
        // Cas réel : `InstrumentsSection.vue` reçoit désormais `trends` via `aheadOfNetwork`, qui
        // rend `null` (jamais `undefined`) quand ni la prop ni l'instantané ne portent la donnée.
        expect(areTrendsPending(null, undefined)).toBe(true);
    });

    it('n\'attend plus quand `null` est rescapée', () => {
        expect(areTrendsPending(null, ['trends'])).toBe(false);
    });

    it('ignore, avec `null`, une clé rescapée qui ne la concerne pas', () => {
        expect(areTrendsPending(null, ['sectorBreakdown'])).toBe(true);
    });
});
