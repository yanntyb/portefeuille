import { describe, expect, it } from 'vitest';
import {
    catalogCount,
    filterCatalog,
    isRangeKey,
    joinTrends,
    relevanceRank,
    type CatalogLine,
    type CatalogRow,
} from '@/lib/catalog';

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

const row = (id: number, name: string, ticker: string | null, isin: string | null = null, held = false): CatalogRow => ({
    ...line(id, name, ticker, isin, held),
    changePct: 5,
    points: [1, 2, 3],
});

describe('filterCatalog', () => {
    it('rend toute la liste sur une recherche vide', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'BET')];

        expect(filterCatalog(rows, '')).toHaveLength(2);
        expect(filterCatalog(rows, '   ')).toHaveLength(2);
    });

    it('cherche dans le nom', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Gamma', 'GAM')];

        expect(filterCatalog(rows, 'amm').map((found) => found.name)).toEqual(['Gamma']);
    });

    it('cherche dans le ticker', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'XYZW')];

        expect(filterCatalog(rows, 'xyz').map((found) => found.name)).toEqual(['Beta']);
    });

    it('cherche dans l\'ISIN', () => {
        const rows = [row(1, 'Société Générale', 'SOC', 'FR0000130809'), row(2, 'Alpha', 'ALP', 'FR0000000001')];

        expect(filterCatalog(rows, 'fr00001308').map((found) => found.name)).toEqual(['Société Générale']);
    });

    it('ignore les accents, pour qu\'on retrouve « Société Générale » en tapant « societe gen »', () => {
        const rows = [row(1, 'Société Générale', 'SOC'), row(2, 'Alpha', 'ALP')];

        expect(filterCatalog(rows, 'societe').map((found) => found.name)).toEqual(['Société Générale']);
        expect(filterCatalog(rows, 'societe gen').map((found) => found.name)).toEqual(['Société Générale']);
    });

    it('cherche sur une sous-chaîne continue, espaces compris, et non sur des mots isolés', () => {
        const rows = [row(1, 'Société Générale', 'SOC')];

        expect(filterCatalog(rows, 'generale societe')).toEqual([]);
    });

    it('ignore la casse', () => {
        expect(filterCatalog([row(1, 'Alpha', 'ALP')], 'ALPHA')).toHaveLength(1);
    });

    it('rend une liste vide quand rien ne correspond', () => {
        expect(filterCatalog([row(1, 'Alpha', 'ALP')], 'zzz')).toEqual([]);
    });

    it('ne casse pas sur un ticker ou un ISIN absent', () => {
        expect(filterCatalog([row(1, 'Alpha', null, null)], 'alpha')).toHaveLength(1);
    });
});

describe('relevanceRank', () => {
    it('met le ticker tapé en tête, avant le nom qui commence pareil', () => {
        expect(relevanceRank(row(1, 'Beta', 'ALP'), 'alp')).toBe(0);
        expect(relevanceRank(row(2, 'Alpha', 'BET'), 'alp')).toBe(1);
    });

    it('renvoie les correspondances en milieu de mot derrière les préfixes', () => {
        expect(relevanceRank(row(1, 'Société Générale', 'GLE'), 'gene')).toBe(2);
    });

    it('ignore les accents comme la recherche elle-même', () => {
        expect(relevanceRank(row(1, 'Société Générale', 'GLE'), 'societe')).toBe(1);
    });

    it('ne casse pas sur un ticker absent', () => {
        expect(relevanceRank(row(1, 'Alpha', null), 'alp')).toBe(1);
    });

    it('ne classe rien sur une recherche vide', () => {
        expect(relevanceRank(row(1, 'Alpha', 'ALP'), '  ')).toBe(2);
    });
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

describe('catalogCount', () => {
    it('compte les instruments et ceux qui sont détenus', () => {
        const rows = [row(1, 'Alpha', 'ALP', null, true), row(2, 'Beta', 'BET'), row(3, 'Gamma', 'GAM')];

        expect(catalogCount(rows)).toBe('3 instruments · 1 détenu');
    });

    it('accorde le pluriel sur les détenus', () => {
        const rows = [row(1, 'Alpha', 'ALP', null, true), row(2, 'Beta', 'BET', null, true)];

        expect(catalogCount(rows)).toBe('2 instruments · 2 détenus');
    });

    it('tait les détenus quand il n\'y en a aucun', () => {
        expect(catalogCount([row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'BET')])).toBe('2 instruments');
    });

    it('accorde le singulier sur un instrument unique', () => {
        expect(catalogCount([row(1, 'Alpha', 'ALP')])).toBe('1 instrument');
    });

    it('se recompte sur une liste filtrée', () => {
        const rows = [row(1, 'Alpha Fund', 'ALP'), row(2, 'Bravo Fund', 'BRA'), row(3, 'Gamma', 'GAM')];

        expect(catalogCount(filterCatalog(rows, 'fund'))).toBe('2 instruments');
    });

    it('rend un compte nul sur une liste vide', () => {
        expect(catalogCount([])).toBe('0 instrument');
    });

    it('accorde le singulier sur les instruments quand un seul est détenu parmi plusieurs détenus possibles', () => {
        const rows = [row(1, 'Alpha', 'ALP', null, true)];

        expect(catalogCount(rows)).toBe('1 instrument · 1 détenu');
    });
});
