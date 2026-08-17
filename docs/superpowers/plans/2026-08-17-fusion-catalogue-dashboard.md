# Fusion du catalogue d'instruments dans le tableau de bord — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Faire de la liste du tableau de bord la liste unique des instruments — les positions sans recherche, tout le catalogue dès la première frappe — et supprimer la page `/instruments`.

**Architecture:** Le tableau de bord reçoit le catalogue et ses tendances en propriétés Inertia différées, et les joint aux positions par identifiant d'actif dans un module TypeScript pur, testé au vitest. La liste compacte existante devient une liste à deux remplissages, détenu et non détenu. Aucune action serveur nouvelle : `GetInstrumentCatalog` et `GetCatalogTrends` sont réutilisées telles quelles.

**Tech Stack:** Laravel 12 sur PHP 8.5, Inertia v3, Vue 3 `<script setup>` en TypeScript, Tailwind v4, Pest (Feature + Browser), vitest, Pint.

**Spec:** `docs/superpowers/specs/2026-08-17-fusion-catalogue-dashboard-design.md`

## Global Constraints

- Tout texte visible par l'utilisateur est en français, accents compris.
- Les dossiers réels sont `resources/js/components/` (minuscule) et `resources/js/Pages/` (majuscule) ; les imports passent par l'alias `@/components/...` et `@/lib/...`.
- Vue : un seul élément racine par composant, `<script setup lang="ts">`, types explicites sur les paramètres et retours.
- PHP : accolades systématiques, promotion de propriétés dans le constructeur, types de retour explicites, PHPDoc plutôt que commentaires en ligne.
- Après toute modification PHP : `vendor/bin/pint --dirty --format agent`.
- Commit après chaque tâche, message en français, préfixe conventionnel (`feat:`, `test:`, `refactor:`, `docs:`).
- Ne supprimer aucun test hors des deux suppressions explicitement approuvées dans ce plan (tâche 5).

---

### Task 1: Le module de fusion `lib/instrumentList.ts`

TypeScript pur, aucune UI. Produit la liste unifiée que les tâches suivantes consomment.

**Files:**
- Modify: `resources/js/lib/catalog.ts:44-56` (rendre `filterCatalog` générique)
- Create: `resources/js/lib/instrumentList.ts`
- Test: `resources/js/lib/instrumentList.test.ts`

**Interfaces:**
- Consomme : `HoldingLine`, `HoldingWeight`, `holdingWeights` de `@/lib/portfolio` ; `CatalogLine`, `CatalogRow`, `CatalogTrend`, `joinTrends`, `filterCatalog` de `@/lib/catalog`.
- Produit :
  - `interface InstrumentRow extends CatalogRow { gain: number | null; gainPct: number | null; share: number | null; barWidth: string | null; opacity: number | null }`
  - `mergeInstrumentRows(holdings: HoldingLine[], catalog: CatalogLine[] | undefined, trends: CatalogTrend[] | undefined): InstrumentRow[]`
  - `visibleInstrumentRows(rows: InstrumentRow[], query: string): InstrumentRow[]`

- [ ] **Step 1: Rendre `filterCatalog` générique**

`InstrumentRow` étend `CatalogRow` ; sans généricité, filtrer une liste d'`InstrumentRow` en rendrait des `CatalogRow` et perdrait les champs de position. Dans `resources/js/lib/catalog.ts`, remplacer la signature de `filterCatalog` (le corps ne change pas) :

```ts
export const filterCatalog = <Row extends CatalogRow>(rows: Row[], query: string): Row[] => {
    const needle = normalize(query.trim());

    if (needle === '') {
        return rows;
    }

    return rows.filter((row) =>
        [row.name, row.ticker, row.isin].some(
            (field) => field !== null && normalize(field).includes(needle),
        ),
    );
};
```

- [ ] **Step 2: Vérifier que les tests du catalogue restent verts**

Run: `bun run test:js -- catalog`
Expected: PASS, `resources/js/lib/catalog.test.ts` inchangé et vert.

- [ ] **Step 3: Écrire le test de fusion qui échoue**

Créer `resources/js/lib/instrumentList.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import type { CatalogLine, CatalogTrend } from '@/lib/catalog';
import { mergeInstrumentRows, visibleInstrumentRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const holding = (assetId: number, assetName: string, marketValue: number): HoldingLine => ({
    assetId,
    assetName,
    ticker: assetName.slice(0, 3).toUpperCase(),
    type: 'stock',
    typeLabel: 'Action',
    quantity: 10,
    avgCost: 80,
    lastPrice: marketValue / 10,
    marketValue,
    gain: 200,
    gainPct: 25,
});

const line = (id: number, name: string, held = false): CatalogLine => ({
    id,
    name,
    ticker: name.slice(0, 3).toUpperCase(),
    isin: `FR000000000${id}`,
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 100,
    held,
    quantity: held ? 10 : null,
    marketValue: held ? 1000 : null,
});

const names = (rows: InstrumentRow[]): string[] => rows.map((row) => row.name);

describe('mergeInstrumentRows', () => {
    it('porte le gain et le poids de la position sur la ligne détenue', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 3000)], [line(1, 'Alpha', true)], undefined);

        expect(rows[0].held).toBe(true);
        expect(rows[0].gain).toBe(200);
        expect(rows[0].gainPct).toBe(25);
        expect(rows[0].share).toBe(100);
        expect(rows[0].barWidth).not.toBeNull();
    });

    it('laisse une ligne non détenue sans gain ni poids', () => {
        const rows = mergeInstrumentRows([], [line(2, 'Beta')], undefined);

        expect(rows[0].held).toBe(false);
        expect(rows[0].gain).toBeNull();
        expect(rows[0].share).toBeNull();
        expect(rows[0].barWidth).toBeNull();
        expect(rows[0].opacity).toBeNull();
    });

    it('calcule les parts sur les seules positions, jamais sur le catalogue entier', () => {
        const rows = mergeInstrumentRows(
            [holding(1, 'Alpha', 3000), holding(2, 'Beta', 1000)],
            [line(1, 'Alpha', true), line(2, 'Beta', true), line(3, 'Gamma'), line(4, 'Delta')],
            undefined,
        );

        expect(rows.find((row) => row.name === 'Alpha')?.share).toBe(75);
        expect(rows.find((row) => row.name === 'Beta')?.share).toBe(25);
    });

    it('rattache la tendance de la période par identifiant d\'actif', () => {
        const trends: CatalogTrend[] = [{ assetId: 2, changePct: 12.5, points: [1, 2, 3] }];
        const rows = mergeInstrumentRows([], [line(1, 'Alpha'), line(2, 'Beta')], trends);

        expect(rows.find((row) => row.name === 'Beta')?.changePct).toBe(12.5);
        expect(rows.find((row) => row.name === 'Beta')?.points).toEqual([1, 2, 3]);
        expect(rows.find((row) => row.name === 'Alpha')?.changePct).toBeNull();
    });

    it('tient sur les seules positions tant que le catalogue est différé', () => {
        const rows = mergeInstrumentRows([holding(1, 'Alpha', 3000)], undefined, undefined);

        expect(names(rows)).toEqual(['Alpha']);
        expect(rows[0].held).toBe(true);
        expect(rows[0].share).toBe(100);
    });

    it('garde une position absente du catalogue plutôt que de la perdre', () => {
        const rows = mergeInstrumentRows([holding(9, 'Orpheline', 500)], [line(1, 'Alpha')], undefined);

        expect(names(rows)).toContain('Orpheline');
        expect(rows.find((row) => row.name === 'Orpheline')?.held).toBe(true);
    });
});

describe('visibleInstrumentRows', () => {
    const merged = (): InstrumentRow[] =>
        mergeInstrumentRows(
            [holding(1, 'Alpha', 1000), holding(2, 'Beta', 3000)],
            [line(1, 'Alpha', true), line(2, 'Beta', true), line(3, 'Gamma'), line(4, 'Delta')],
            undefined,
        );

    it('ne montre que les positions sur une recherche vide, la plus grosse en tête', () => {
        expect(names(visibleInstrumentRows(merged(), ''))).toEqual(['Beta', 'Alpha']);
        expect(names(visibleInstrumentRows(merged(), '   '))).toEqual(['Beta', 'Alpha']);
    });

    it('ouvre le catalogue entier dès qu\'on tape, détenus d\'abord', () => {
        expect(names(visibleInstrumentRows(merged(), 'a'))).toEqual(['Beta', 'Alpha', 'Delta', 'Gamma']);
    });

    it('range les non détenus par nom', () => {
        const rows = mergeInstrumentRows([], [line(3, 'Gamma'), line(4, 'Delta')], undefined);

        expect(names(visibleInstrumentRows(rows, 'a'))).toEqual(['Delta', 'Gamma']);
    });

    it('cherche aussi par ticker et par ISIN', () => {
        expect(names(visibleInstrumentRows(merged(), 'GAM'))).toEqual(['Gamma']);
        expect(names(visibleInstrumentRows(merged(), 'FR0000000003'))).toEqual(['Gamma']);
    });

    it('rend une liste vide quand rien ne correspond', () => {
        expect(visibleInstrumentRows(merged(), 'zzz')).toEqual([]);
    });
});
```

- [ ] **Step 4: Lancer le test pour le voir échouer**

Run: `bun run test:js -- instrumentList`
Expected: FAIL, « Failed to resolve import "@/lib/instrumentList" ».

- [ ] **Step 5: Écrire le module**

Créer `resources/js/lib/instrumentList.ts` :

```ts
import { filterCatalog, joinTrends, type CatalogLine, type CatalogRow, type CatalogTrend } from '@/lib/catalog';
import { holdingWeights, type HoldingLine, type HoldingWeight } from '@/lib/portfolio';

/** Une ligne de la liste unique : un instrument du catalogue, enrichi de la position quand il y en a une. */
export interface InstrumentRow extends CatalogRow {
    /** Gain latent de la position, nul sur un instrument non détenu. */
    gain: number | null;
    gainPct: number | null;
    /** Part du portefeuille en pourcentage, nulle sur un instrument non détenu. */
    share: number | null;
    /** Largeur CSS de la barre de poids, nulle sur un instrument non détenu. */
    barWidth: string | null;
    opacity: number | null;
}

/** Une position que le catalogue ne connaît pas encore, ramenée à la forme d'une ligne de catalogue. */
const asCatalogLine = (weight: HoldingWeight): CatalogLine => ({
    id: weight.line.assetId,
    name: weight.line.assetName,
    ticker: weight.line.ticker,
    isin: null,
    type: weight.line.type,
    typeLabel: weight.line.typeLabel,
    lastPrice: weight.line.lastPrice,
    held: true,
    quantity: weight.line.quantity,
    marketValue: weight.line.marketValue,
});

/**
 * Joint le catalogue, les positions et les tendances de la période. Le catalogue peut encore être
 * différé : les positions suffisent alors à peupler la liste, et les lignes manquantes arrivent
 * ensuite sans que la liste ait été vide entre-temps.
 */
export const mergeInstrumentRows = (
    holdings: HoldingLine[],
    catalog: CatalogLine[] | undefined,
    trends: CatalogTrend[] | undefined,
): InstrumentRow[] => {
    const weights = holdingWeights(holdings);
    const weightByAsset = new Map<number, HoldingWeight>(
        weights.map((weight: HoldingWeight): [number, HoldingWeight] => [weight.line.assetId, weight]),
    );

    const known = catalog ?? [];
    const listed = new Set<number>(known.map((line: CatalogLine): number => line.id));
    const unlisted = weights
        .filter((weight: HoldingWeight): boolean => !listed.has(weight.line.assetId))
        .map(asCatalogLine);

    return joinTrends([...known, ...unlisted], trends).map((row: CatalogRow): InstrumentRow => {
        const weight = weightByAsset.get(row.id);

        return {
            ...row,
            /** Les positions du tableau de bord font foi sur ce qui est détenu, pas le drapeau du catalogue. */
            held: weight !== undefined,
            marketValue: weight?.line.marketValue ?? row.marketValue,
            gain: weight?.line.gain ?? null,
            gainPct: weight?.line.gainPct ?? null,
            share: weight?.share ?? null,
            barWidth: weight?.barWidth ?? null,
            opacity: weight?.opacity ?? null,
        };
    });
};

/** Les positions d'abord, par valeur décroissante ; le reste du catalogue ensuite, par nom. */
const compareRows = (left: InstrumentRow, right: InstrumentRow): number => {
    if (left.held !== right.held) {
        return left.held ? -1 : 1;
    }

    if (left.held) {
        return (right.marketValue ?? 0) - (left.marketValue ?? 0);
    }

    return left.name.localeCompare(right.name, 'fr');
};

/** Sans recherche la liste montre le portefeuille ; dès la première frappe, tout le catalogue. */
export const visibleInstrumentRows = (rows: InstrumentRow[], query: string): InstrumentRow[] => {
    const visible = query.trim() === ''
        ? rows.filter((row: InstrumentRow): boolean => row.held)
        : filterCatalog(rows, query);

    return [...visible].sort(compareRows);
};
```

- [ ] **Step 6: Lancer les tests pour les voir passer**

Run: `bun run test:js -- instrumentList`
Expected: PASS, onze tests verts.

- [ ] **Step 7: Vérifier les types**

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/instrumentList.ts resources/js/lib/instrumentList.test.ts resources/js/lib/catalog.ts
git commit -m "feat: joint positions et catalogue en une liste unique d'instruments"
```

---

### Task 2: Le tableau de bord sert le catalogue

Le serveur envoie catalogue et tendances au tableau de bord, en propriétés différées. `/instruments` continue de fonctionner : rien n'est encore supprimé.

**Files:**
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Test: `tests/Feature/DashboardPageTest.php` (ajouts en fin de fichier)

**Interfaces:**
- Consomme : `GetInstrumentCatalog::__invoke(int $userId): InstrumentCatalogData`, `GetCatalogTrends::__invoke(ValuationRange $range): list<CatalogTrendData>`, `ValuationRange::fromRequest(?string $value): ValuationRange`.
- Produit : les propriétés Inertia `catalog` (différée, `{ lines: [...] }`), `catalogRange` (directe, chaîne `1M|6M|1Y|max`), `trends` (différée, liste de `{ assetId, changePct, points }`).

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `tests/Feature/DashboardPageTest.php` :

```php
it('defers the instrument catalogue with a held flag', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co', 'ticker' => 'HLD', 'isin' => 'FR0000000001']);
    Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $held->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('catalog')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('catalog.lines', 2)
                ->where('catalog.lines.1.held', true)
                ->where('catalog.lines.1.ticker', 'HLD')
                ->where('catalog.lines.1.isin', 'FR0000000001')
            )
        );
});

it('defers the catalogue trends and loads them on demand', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create(['name' => 'Trending Co']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('catalogRange', 'max')
            ->missing('trends')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trends', 1)
                ->where('trends.0.assetId', $asset->id)
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});

it('accepts the range query param for the catalogue trends', function () {
    User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subMonths(6), 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 150]);

    $this->get('/?range=1M')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('catalogRange', '1M')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('trends.0.changePct', fn ($value) => (float) $value === 50.0)
                ->has('trends.0.points', 2)
            )
        );
});
```

Les `use` en tête du fichier couvrent déjà `User`, `Instrument`, `Price`, `Holding`, `Wallet` et `Assert` : ne rien ajouter.

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `php artisan test --compact --filter="catalogue"`
Expected: FAIL, les propriétés `catalog`, `catalogRange` et `trends` sont absentes de la page `Dashboard`.

- [ ] **Step 3: Ajouter les propriétés au contrôleur**

Remplacer `app/Contexts/Portfolio/Http/DashboardController.php` :

```php
<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetCatalogTrends;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetInstrumentCatalog $getCatalog,
        private GetCatalogTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $range = ValuationRange::fromRequest(request()->query('range'));

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        return Inertia::render('Dashboard', [
            'overview' => $overview,
            /** Le catalogue est différé : sans recherche, la liste se contente des positions déjà servies. */
            'catalog' => Inertia::defer(fn () => ($this->getCatalog)($user?->id ?? 0)),
            'catalogRange' => $range->value,
            'trends' => Inertia::defer(fn () => ($this->getTrends)($range)),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : []),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, null, ValuationGranularity::Week)
                : EvolutionSeriesData::empty()),
            'sectorBreakdown' => Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : []),
        ]);
    }
}
```

- [ ] **Step 4: Lancer les tests pour les voir passer**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS, tous les tests du fichier verts.

- [ ] **Step 5: Formater**

Run: `vendor/bin/pint --dirty --format agent`
Expected: aucune erreur de style restante.

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Portfolio/Http/DashboardController.php tests/Feature/DashboardPageTest.php
git commit -m "feat: sert le catalogue d'instruments au tableau de bord en propriétés différées"
```

---

### Task 3: La liste unique dans l'interface

Le tableau de bord affiche la recherche et la liste unifiée. `/instruments` fonctionne toujours ; sa suppression vient à la tâche 5.

**Files:**
- Create: `resources/js/components/InstrumentSearch.vue`
- Create: `resources/js/components/InstrumentList.vue`
- Create: `resources/js/components/dashboard/InstrumentsSection.vue`
- Delete: `resources/js/components/HoldingsList.vue`
- Delete: `resources/js/components/dashboard/HoldingsSection.vue`
- Modify: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Browser/DashboardTest.php:80-90`, `tests/Browser/PrefetchTest.php:25-37`

**Interfaces:**
- Consomme : `mergeInstrumentRows`, `visibleInstrumentRows`, `InstrumentRow` de la tâche 1 ; les propriétés `catalog`, `catalogRange`, `trends` de la tâche 2 ; `catalogCount`, `isRangeKey`, `rangeOptions`, `RangeKey`, `CatalogLine`, `CatalogTrend` de `@/lib/catalog` ; `HoldingLine` de `@/lib/portfolio`.
- Produit : les attributs de test `data-section="instruments"`, `data-instrument-row`, `data-instrument-name`, `data-instrument-value`, `data-instrument-change`, `data-instrument-bar`, `data-instrument-weight`, `data-instrument-trend`, `data-instrument-gain`, `data-instrument-count`, `data-instrument-empty`, `data-instrument-search`, `data-instrument-search-clear`.

- [ ] **Step 1: Créer le champ de recherche**

`CatalogSearch.vue` n'est pas déplacé par `git mv` : il sert encore `/instruments` jusqu'à la tâche 5. Créer `resources/js/components/InstrumentSearch.vue` :

```vue
<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

const query = defineModel<string>({ required: true });

const clear = (): void => {
    query.value = '';
};
</script>

<template>
    <div class="relative w-full">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />

        <input
            v-model="query"
            data-instrument-search
            type="text"
            autocomplete="off"
            placeholder="Rechercher un nom, un ticker, un ISIN"
            aria-label="Rechercher un instrument"
            class="h-9 w-full rounded-md border border-border bg-background pl-9 pr-9 text-sm transition-colors placeholder:text-muted-foreground focus:border-ring focus:outline-none"
        />

        <button
            v-if="query !== ''"
            data-instrument-search-clear
            type="button"
            aria-label="Effacer la recherche"
            class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground transition-colors hover:text-foreground"
            @click="clear"
        >
            <X class="size-3.5" />
        </button>
    </div>
</template>
```

- [ ] **Step 2: Créer la liste à deux remplissages**

Créer `resources/js/components/InstrumentList.vue` :

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import type { InstrumentRow } from '@/lib/instrumentList';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

defineProps<{
    rows: InstrumentRow[];
    loading: boolean;
    emptyLabel: string;
}>();

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
</script>

<template>
    <ul v-if="rows.length" class="flex flex-col">
        <li
            v-for="row in rows"
            :key="row.id"
            data-instrument-row
            :data-held="row.held ? 'true' : 'false'"
            class="flex flex-col gap-1.5 border-b border-separator py-3 last:border-b-0"
        >
            <div class="flex items-center gap-3">
                <Link
                    :href="`/instruments/${row.id}`"
                    prefetch
                    data-instrument-name
                    class="block min-w-0 flex-1 truncate font-semibold hover:underline"
                >
                    {{ row.name }}
                    <span v-if="row.ticker" class="text-muted-foreground">({{ row.ticker }})</span>
                </Link>

                <!-- Ce que vaut la ligne : la position pour un instrument détenu, son cours sinon. -->
                <span data-instrument-value class="w-24 shrink-0 text-right font-bold tabular-nums">
                    {{ row.held ? eur(row.marketValue, 0) : eur(row.lastPrice) }}
                </span>

                <!-- Même colonne, deux mesures : le gain latent d'une position, la variation de la période sinon. -->
                <span
                    data-instrument-change
                    class="w-20 shrink-0 text-right text-sm font-semibold tabular-nums"
                    :class="gainClass(row.held ? row.gainPct : row.changePct)"
                >
                    {{ pct(row.held ? row.gainPct : row.changePct) }}
                </span>
            </div>

            <!-- Le détail passe sur une seconde ligne : la colonne est trop étroite pour huit colonnes. -->
            <div class="flex items-center gap-3 text-xs">
                <template v-if="row.held">
                    <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
                        <span
                            data-instrument-bar
                            class="block h-full rounded-full bg-sector-bar"
                            :style="{ width: row.barWidth ?? '0%', opacity: row.opacity ?? 1 }"
                        ></span>
                    </span>
                    <span data-instrument-weight class="w-12 shrink-0 tabular-nums text-subtle-foreground">
                        {{ share(row.share ?? 0) }}
                    </span>
                </template>

                <span v-else data-instrument-weight class="shrink-0 text-subtle-foreground">non détenu</span>

                <!-- Largeurs de queue identiques à la première ligne : tendance sous la valeur, gain sous le pourcentage. -->
                <span data-instrument-trend class="ml-auto w-24 shrink-0">
                    <Sparkline
                        v-if="row.points.length > 1"
                        :values="row.points"
                        :width="SPARKLINE_WIDTH"
                    />
                    <span v-else-if="loading" class="block h-5 w-full animate-pulse rounded bg-muted"></span>
                </span>

                <span
                    v-if="row.held"
                    data-instrument-gain
                    class="w-20 shrink-0 text-right tabular-nums"
                    :class="gainClass(row.gain)"
                >
                    {{ signedEur(row.gain, 0) }}
                </span>
                <span v-else class="w-20 shrink-0"></span>
            </div>
        </li>
    </ul>

    <p v-else data-instrument-empty class="py-8 text-center text-sm text-muted-foreground">
        {{ emptyLabel }}
    </p>
</template>
```

Le composant a deux nœuds racines par nature (`ul` ou `p` selon l'état) : Vue l'accepte ici parce que `v-if`/`v-else` n'en rend qu'un seul à la fois.

- [ ] **Step 3: Créer la section du tableau de bord**

Créer `resources/js/components/dashboard/InstrumentsSection.vue` :

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ChartRangeToggle from '@/components/ChartRangeToggle.vue';
import InstrumentList from '@/components/InstrumentList.vue';
import InstrumentSearch from '@/components/InstrumentSearch.vue';
import { catalogCount, isRangeKey, rangeOptions, type CatalogLine, type CatalogTrend, type RangeKey } from '@/lib/catalog';
import { mergeInstrumentRows, visibleInstrumentRows, type InstrumentRow } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const props = defineProps<{
    holdings: HoldingLine[];
    catalog?: { lines: CatalogLine[] };
    catalogRange?: string;
    trends?: CatalogTrend[];
}>();

const query = ref<string>('');
const selectedRange = ref<RangeKey>(isRangeKey(props.catalogRange) ? props.catalogRange : 'max');
const reloading = ref<boolean>(false);

/** Le catalogue et ses tendances arrivent différés ; seul le squelette des tendances est visible. */
const loading = computed<boolean>(() => props.trends === undefined || reloading.value);

const rows = computed<InstrumentRow[]>(() =>
    visibleInstrumentRows(
        mergeInstrumentRows(props.holdings, props.catalog?.lines, props.trends),
        query.value,
    ),
);

const countLabel = computed<string>(() => catalogCount(rows.value));

/** Sans recherche la liste est le portefeuille : son vide parle de positions, pas d'instruments. */
const emptyLabel = computed<string>(() =>
    query.value.trim() === ''
        ? 'Aucune position pour le moment.'
        : 'Aucun instrument ne correspond à cette recherche.',
);

const selectRange = (key: RangeKey): void => {
    selectedRange.value = key;

    router.reload({
        only: ['trends'],
        data: { range: key },
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};
</script>

<template>
    <section data-section="instruments" class="flex flex-col gap-5 px-6" aria-label="Instruments">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p data-instrument-count class="text-sm text-muted-foreground">{{ countLabel }}</p>

            <ChartRangeToggle
                :options="rangeOptions"
                :model-value="selectedRange"
                @update:model-value="selectRange"
            />
        </div>

        <InstrumentSearch v-model="query" />

        <InstrumentList :rows="rows" :loading="loading" :empty-label="emptyLabel" />
    </section>
</template>
```

- [ ] **Step 4: Brancher la section dans la page**

Dans `resources/js/Pages/Dashboard.vue`, remplacer l'import de `HoldingsSection` par celui d'`InstrumentsSection`, ajouter les nouvelles propriétés, et remplacer l'usage dans le gabarit.

Bloc `<script setup>` — remplacer la ligne d'import et le bloc `defineProps` :

```ts
import InstrumentsSection from '@/components/dashboard/InstrumentsSection.vue';
import type { CatalogLine, CatalogTrend } from '@/lib/catalog';

defineProps<{
    overview: PortfolioOverview;
    catalog?: { lines: CatalogLine[] };
    catalogRange?: string;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
}>();
```

Gabarit — remplacer la ligne `<HoldingsSection ... />` :

```vue
<InstrumentsSection
    :holdings="overview.holdings"
    :catalog="catalog"
    :catalog-range="catalogRange"
    :trends="trends"
/>
```

`evolutionSeries` ne descend plus dans la liste : les sparklines viennent désormais des tendances du catalogue, qui obéissent au sélecteur de période. La propriété reste pour `EvolutionSection`.

- [ ] **Step 5: Supprimer les composants remplacés**

```bash
git rm resources/js/components/HoldingsList.vue resources/js/components/dashboard/HoldingsSection.vue
```

- [ ] **Step 6: Vérifier les types et la compilation**

Run: `bun run typecheck && bun run build`
Expected: aucune erreur ; aucune référence restante à `HoldingsList` ou `HoldingsSection`.

- [ ] **Step 7: Adapter les tests navigateur qui visaient l'ancienne section**

Dans `tests/Browser/DashboardTest.php`, remplacer le dernier test (lignes 80-90) :

```php
it('nomme la section des instruments pour les technologies d\'assistance et offre la recherche', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertScript("document.querySelector('[data-section=instruments]').getAttribute('aria-label')", 'Instruments')
        ->assertScript("document.querySelectorAll('[data-instrument-search]').length", 1)
        ->assertScript(
            "document.querySelector('[data-instrument-search]').getAttribute('aria-label')",
            'Rechercher un instrument',
        )
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length >= 1", true)
        ->assertNoJavaScriptErrors();
});
```

Dans `tests/Browser/PrefetchTest.php`, le deuxième test survole désormais `[data-instrument-name]` — remplacer la ligne 34 :

```php
    $page->hover('[data-instrument-name]')->wait(1);
```

- [ ] **Step 8: Écrire le test navigateur de la recherche sur le tableau de bord**

`git mv tests/Browser/CatalogSearchTest.php tests/Browser/InstrumentSearchTest.php`, puis remplacer tout le contenu du fichier :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

/** Un instrument dont le cours passe de `open` à `close` sur les dix derniers jours. */
function seedCatalogInstrument(string $name, float $open, float $close): Instrument
{
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create([
        'name' => $name,
        'ticker' => strtoupper(substr($name, 0, 3)),
    ]);

    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->subDays(10)->format('Y-m-d'),
        'close' => $open,
    ]);
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->format('Y-m-d'),
        'close' => $close,
    ]);

    return $instrument;
}

it('montre les positions sans recherche, ouvre le catalogue dès la première frappe, et revient', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $held = seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Bravo Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $held->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->actingAs($user);

    $names = "Array.from(document.querySelectorAll('[data-instrument-name]')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit('/')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 1)
        ->assertScript($names, 'Alpha Fund (ALP)')
        ->type('[data-instrument-search]', 'fund')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 2)
        ->assertScript($names, 'Alpha Fund (ALP)|Bravo Fund (BRA)')
        ->assertScript("document.querySelector('[data-instrument-row]').getAttribute('data-held')", 'true')
        ->type('[data-instrument-search]', 'zzz')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 0)
        ->assertScript(
            "document.querySelector('[data-instrument-empty]').textContent.replace(/\\s+/g, ' ').trim()",
            'Aucun instrument ne correspond à cette recherche.',
        )
        ->click('[data-instrument-search-clear]')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length", 1)
        ->assertNoJavaScriptErrors();
});

it('cherche un instrument que le portefeuille ne détient pas', function () {
    ['user' => $user] = portfolioFixture();

    seedCatalogInstrument('Zeta Trust', 100, 120);

    $this->actingAs($user);

    visit('/')
        ->assertDontSee('Zeta Trust')
        ->type('[data-instrument-search]', 'zeta')
        ->assertSee('Zeta Trust')
        ->assertScript("document.querySelector('[data-instrument-row]').getAttribute('data-held')", 'false')
        ->assertSee('non détenu')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 9: Lancer les tests navigateur touchés**

Run: `php artisan test --compact --filter="InstrumentSearchTest|DashboardTest|PrefetchTest"`
Expected: PASS. Si un test échoue faute d'assets à jour, lancer `bun run build` puis relancer.

- [ ] **Step 10: Commit**

```bash
git add resources/js tests/Browser
git commit -m "feat: fait de la liste du tableau de bord la liste unique des instruments"
```

---

### Task 4: Le fil d'Ariane de la fiche instrument

Le cran « Instruments » du fil d'Ariane perd sa cible à la tâche 5 ; on le retire d'abord pour que la suppression de la route ne laisse jamais de lien mort.

**Files:**
- Modify: `resources/js/Pages/Instruments/Show.vue:63-69`
- Test: `tests/Browser/BreadcrumbTest.php:57-68`

**Interfaces:**
- Consomme : le composant `AppBreadcrumb` et sa propriété `items: { label: string; href?: string }[]`.
- Produit : un fil d'Ariane à deux crans, `Tableau de bord › <nom de l'instrument>`.

- [ ] **Step 1: Adapter le test du fil d'Ariane complet**

Dans `tests/Browser/BreadcrumbTest.php`, remplacer le dernier test :

```php
it('affiche le fil d\'Ariane complet sur une fiche instrument', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Tableau de bord')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'ACME')
        ->assertDontSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Instruments')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Lancer le test pour le voir échouer**

Run: `php artisan test --compact --filter=BreadcrumbTest`
Expected: FAIL sur `assertDontSeeIn`, le cran « Instruments » étant encore rendu.

- [ ] **Step 3: Retirer le cran intermédiaire**

Dans `resources/js/Pages/Instruments/Show.vue`, remplacer le bloc `<AppBreadcrumb>` :

```vue
<AppBreadcrumb
    :items="[
        { label: 'Tableau de bord', href: '/' },
        { label: props.instrument.name },
    ]"
/>
```

- [ ] **Step 4: Lancer le test pour le voir passer**

Run: `bun run build && php artisan test --compact --filter=BreadcrumbTest`
Expected: PASS, les quatre tests verts.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Instruments/Show.vue tests/Browser/BreadcrumbTest.php
git commit -m "refactor: ramène le fil d'Ariane de la fiche instrument à deux crans"
```

---

### Task 5: Suppression de la page `/instruments`

Dernière tâche : la page catalogue et tout ce qui n'existait que pour elle disparaissent.

**Files:**
- Modify: `routes/web.php:12`
- Delete: `app/Contexts/InstrumentView/Http/InstrumentCatalogController.php`
- Delete: `resources/js/Pages/Instruments/Index.vue`
- Delete: `resources/js/components/instruments/CatalogHeader.vue`
- Delete: `resources/js/components/instruments/CatalogList.vue`
- Delete: `resources/js/components/instruments/CatalogSearch.vue`
- Delete: `tests/Feature/InstrumentCatalogPageTest.php`
- Modify: `tests/Browser/SmokeTest.php:47-56`
- Modify: `tests/Browser/PrefetchTest.php:11-23,39-51`
- Modify: `docs/page-data.md:40,92`

**Interfaces:**
- Consomme : la route `instruments.show`, seule survivante du groupe instruments.
- Produit : plus aucune route ni composant nommés `instruments.index` ou `Catalog*`.

**Suppressions de tests approuvées par le propriétaire du dépôt** — ne pas en supprimer d'autres :
1. `tests/Feature/InstrumentCatalogPageTest.php` en entier : ses trois cas ont été reportés sur `DashboardPageTest` à la tâche 2.
2. Dans `tests/Browser/PrefetchTest.php`, les deux cas qui visitent `/instruments` (« précharge la fiche instrument au survol d'une ligne du catalogue » et « précharge le catalogue au survol du fil d'Ariane ») : leur page et leur lien n'existent plus. Le cas du tableau de bord reste et couvre le préchargement.

- [ ] **Step 1: Supprimer les tests dont le sujet disparaît**

```bash
git rm tests/Feature/InstrumentCatalogPageTest.php
```

Dans `tests/Browser/PrefetchTest.php`, ne garder que la fonction utilitaire et le cas du tableau de bord — le fichier entier devient :

```php
<?php

/**
 * Reporte si le navigateur a déjà demandé le chemin donné.
 */
function hasRequestedPath(string $path): string
{
    return "performance.getEntriesByType('resource').some(entry => new URL(entry.name).pathname === '{$path}')";
}

it('précharge la fiche instrument au survol d\'une ligne du tableau de bord', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture(['name' => 'Alpha', 'ticker' => 'ALP']);

    $this->actingAs($user);

    $page = visit('/')->assertSee('Alpha');

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeFalse();

    $page->hover('[data-instrument-name]')->wait(1);

    expect($page->script(hasRequestedPath("/instruments/{$instrument->id}")))->toBeTrue();
});
```

- [ ] **Step 2: Reporter le passage catalogue du test de fumée**

Dans `tests/Browser/SmokeTest.php`, remplacer le dernier test :

```php
it('charge la liste des instruments du tableau de bord, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('ACME')
        ->assertScript("document.querySelectorAll('[data-instrument-row]').length >= 1", true)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 3: Supprimer la route et son contrôleur**

Dans `routes/web.php`, supprimer la ligne 12 (`Route::get('/instruments', InstrumentCatalogController::class)->name('instruments.index');`) ainsi que l'import `use App\Contexts\InstrumentView\Http\InstrumentCatalogController;` devenu inutile.

```bash
git rm app/Contexts/InstrumentView/Http/InstrumentCatalogController.php
```

- [ ] **Step 4: Supprimer la page et les composants du catalogue**

```bash
git rm resources/js/Pages/Instruments/Index.vue
git rm resources/js/components/instruments/CatalogHeader.vue
git rm resources/js/components/instruments/CatalogList.vue
git rm resources/js/components/instruments/CatalogSearch.vue
```

Le dossier `resources/js/components/instruments/` disparaît avec son dernier fichier.

- [ ] **Step 5: Vérifier qu'il ne reste aucune référence**

Run: `grep -rn "instruments.index\|CatalogList\|CatalogHeader\|CatalogSearch\|Instruments/Index\|data-catalog" app resources routes tests`
Expected: aucune sortie. Une seule exception tolérée : `data-catalog-*` ne doit plus apparaître nulle part.

Run: `php artisan route:list --except-vendor`
Expected: quatre routes, `instruments.index` absente.

- [ ] **Step 6: Mettre à jour la carte des données de page**

Dans `docs/page-data.md`, ligne 40, supprimer le nœud `I["/instruments<br/>12,6 Ko / 26 ms"]` du sous-graphe des pages.

Ligne 92, rediriger les deux arêtes du catalogue vers le tableau de bord et les marquer différées :

```
    GIC -.->|"catalog — defer<br/>25 lignes, recherche filtree en memoire"| D
    GCT -.->|"trends — defer + reload only<br/>1M 56 ms, 6M 96 ms, 1Y 141 ms, max 342 ms<br/>sparkline et variation en %"| D
```

Dans le second diagramme, remplacer le libellé `P2` :

```
        P2["le catalogue entier part dans la page du tableau de bord,<br/>en prop differee, recherche purement client"]
```

- [ ] **Step 7: Lancer la suite complète**

Run: `bun run build && php artisan test --compact`
Expected: PASS sur l'ensemble, aucune référence à `/instruments` en tant que page d'index.

Run: `bun run test:js && bun run typecheck`
Expected: PASS, aucune erreur de type.

- [ ] **Step 8: Formater le PHP touché**

Run: `vendor/bin/pint --dirty --format agent`
Expected: aucune erreur de style restante.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "refactor: supprime la page catalogue, fondue dans le tableau de bord"
```

---

## Vérification finale

À l'issue de la tâche 5, l'ensemble doit être vert :

```bash
bun run build
bun run test:js
bun run typecheck
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Et à l'œil, sur `/` :

- sans recherche, toutes les positions, la plus grosse en tête, avec barre de poids et gain ;
- en tapant, tout le catalogue, détenus d'abord, non détenus marqués « non détenu » avec leur dernier prix ;
- le sélecteur de période redessine les sparklines de toutes les lignes ;
- sur mobile, la page « Valeur » du carrousel défile verticalement sans casser la pagination horizontale.
