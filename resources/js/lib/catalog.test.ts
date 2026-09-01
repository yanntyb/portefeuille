import { describe, expect, it } from 'vitest';
import { filterCatalog, joinTrends, type CatalogLine } from '@/lib/catalog';

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

describe('filterCatalog', () => {
    const lines = [
        line(1, 'Amazon', 'AMZN', 'US0231351067'),
        line(2, 'Société Générale', 'GLE', 'FR0000130809'),
        line(3, 'Sans ticker', null, null),
    ];

    it('rend tout le catalogue pour un terme vide', () => {
        expect(filterCatalog(lines, '   ')).toHaveLength(3);
    });

    it('retient le nom sans égard à la casse ni aux accents', () => {
        expect(filterCatalog(lines, 'societe generale').map((found) => found.id)).toEqual([2]);
    });

    it('retient aussi le ticker et l\'ISIN', () => {
        expect(filterCatalog(lines, 'amzn').map((found) => found.id)).toEqual([1]);
        expect(filterCatalog(lines, 'FR00001308').map((found) => found.id)).toEqual([2]);
    });

    it('ne bute pas sur une ligne sans ticker ni ISIN', () => {
        expect(filterCatalog(lines, 'sans').map((found) => found.id)).toEqual([3]);
    });

    it('rend une liste vide quand rien ne correspond', () => {
        expect(filterCatalog(lines, 'tesla')).toEqual([]);
    });
});
