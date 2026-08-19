import { describe, expect, it } from 'vitest';
import { isRangeKey, joinTrends, type CatalogLine } from '@/lib/catalog';

const line = (id: number, name: string, ticker: string | null, isin: string | null, held = false): CatalogLine => ({
    id,
    name,
    ticker,
    isin,
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 100,
    held,
    quantity: held ? 10 : null,
    marketValue: held ? 1000 : null,
});

describe('joinTrends', () => {
    it('rattache la tendance de la période à sa ligne', () => {
        const rows = joinTrends(
            [line(1, 'Alpha', 'ALP', null), line(2, 'Beta', 'BET', null)],
            [{ assetId: 2, changePct: 12.5, points: [1, 2] }],
        );

        expect(rows[1].changePct).toBe(12.5);
        expect(rows[1].points).toEqual([1, 2]);
    });

    it('laisse une ligne sans tendance vide plutôt qu\'absente', () => {
        const rows = joinTrends([line(1, 'Alpha', 'ALP', null)], []);

        expect(rows[0].changePct).toBeNull();
        expect(rows[0].points).toEqual([]);
    });

    it('supporte des tendances encore différées', () => {
        const rows = joinTrends([line(1, 'Alpha', 'ALP', null)], undefined);

        expect(rows[0].changePct).toBeNull();
    });
});

describe('isRangeKey', () => {
    it('reconnaît les périodes offertes et rejette le reste', () => {
        expect(isRangeKey('1M')).toBe(true);
        expect(isRangeKey('max')).toBe(true);
        expect(isRangeKey('3M')).toBe(false);
        expect(isRangeKey(undefined)).toBe(false);
    });
});
