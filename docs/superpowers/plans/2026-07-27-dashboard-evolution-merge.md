# Fusion graphs Évolution + Investi par titre — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fusionner les deux graphiques temporels du Dashboard en un seul : aires empilées de l'investi par actif (gris) + ligne Valeur (indigo) + bande gain/perte verte/rouge.

**Architecture:** Nouveau service backend `ValuationCalculator::evolution()` qui aligne l'investi par actif sur les labels de la série de valorisation (déjà windowée par range/granularité) et l'expose via un DTO `EvolutionSeriesData` + une action `BuildEvolutionSeries`. Le controller remplace les deux props `valuationSeries`/`investedByAsset` par un seul prop déféré `evolutionSeries`. Côté front, une fabrique `buildEvolutionChart()` (dans `lib/chart.ts`) produit séries + options ApexCharts (pré-cumul manuel des aires, bandes `rangeArea`, ligne absolue, tooltip custom), consommée par `Dashboard.vue`.

**Tech Stack:** PHP 8.4 / Laravel 12 / Pest ; Vue 3 + Inertia v3 ; ApexCharts via vue3-apexcharts ; Tailwind v4.

## Global Constraints

- Contexts DDD : tout le backend vit sous `app/Contexts/Valuation/…` (tests colocalisés `*Test.php` à côté des classes).
- Type declarations explicites partout (params + retours). Property promotion dans les constructeurs.
- DTO = `readonly class … implements JsonSerializable` avec `jsonSerialize()` et `empty()`.
- Labels de série = chaînes ISO `Y-m-d`.
- Pint : lancer `vendor/bin/pint --dirty --format agent` après toute modif PHP.
- Front : après modif, `bun run build` doit passer ; UI en français.
- Tests : `php artisan test --compact --filter=…`. Ne pas supprimer de test sans accord (ici on en **modifie**).
- Palette : aires en gris/neutre (slate) ; Valeur indigo `#4f46e5` ; gain vert `#10b981` ; perte rouge `#ef4444`.

---

### Task 1: DTO `EvolutionSeriesData`

**Files:**
- Create: `app/Contexts/Valuation/Datas/EvolutionSeriesData.php`
- Test: `app/Contexts/Valuation/Datas/EvolutionSeriesDataTest.php`

**Interfaces:**
- Consumes: `AssetInvestedSeriesData` (existant : `assetId:int, name:string, invested:list<float>`).
- Produces: `EvolutionSeriesData(list<string> $labels, list<float> $value, list<float> $totalInvested, list<AssetInvestedSeriesData> $perAsset)`, `::empty()`, `jsonSerialize()` → clés `labels,value,totalInvested,perAsset`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;

it('serializes the evolution series', function () {
    $data = new EvolutionSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        value: [1000.0, 1200.0],
        totalInvested: [1000.0, 1000.0],
        perAsset: [new AssetInvestedSeriesData(7, 'ACME', [1000.0, 1000.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($json['value'])->toBe([1000.0, 1200.0])
        ->and($json['totalInvested'])->toBe([1000.0, 1000.0])
        ->and($json['perAsset'][0])->toBeInstanceOf(AssetInvestedSeriesData::class);
});

it('builds an empty evolution series', function () {
    expect(EvolutionSeriesData::empty()->jsonSerialize())->toBe([
        'labels' => [], 'value' => [], 'totalInvested' => [], 'perAsset' => [],
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=EvolutionSeriesData`
Expected: FAIL (class `EvolutionSeriesData` not found).

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class EvolutionSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $value
     * @param  list<float>  $totalInvested
     * @param  list<AssetInvestedSeriesData>  $perAsset
     */
    public function __construct(
        public array $labels,
        public array $value,
        public array $totalInvested,
        public array $perAsset,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'value' => $this->value,
            'totalInvested' => $this->totalInvested,
            'perAsset' => $this->perAsset,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=EvolutionSeriesData`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Datas/EvolutionSeriesData.php app/Contexts/Valuation/Datas/EvolutionSeriesDataTest.php
git commit -m "feat: DTO EvolutionSeriesData"
```

---

### Task 2: `ValuationCalculator::evolution()` + extraction du helper per-asset

**Files:**
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php` (ajout `evolution()` + private `perAssetInvestedTimelines()`, refactor de `investedByAsset()` pour réutiliser le helper)
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` (ajout de cas)

**Interfaces:**
- Consumes: `calculateDaily()`, `windowAndAggregate()`, `valueAtDate()` (privé, existant), `EvolutionSeriesData`, `AssetInvestedSeriesData`.
- Produces: `evolution(list<TransactionRecordData> $transactions, list<PriceRecordData> $prices, ValuationRange $range, ValuationGranularity $granularity): EvolutionSeriesData`. Invariant : `sum_k perAsset[k].invested[i] == totalInvested[i]` (aux arrondis 2 décimales près). `perAsset[k].name === '#'.$assetId` (l'action substitue le vrai nom).
- Produces (privé) : `perAssetInvestedTimelines(list<TransactionRecordData> $transactions): array<int, list<array{date: string, value: float}>>`.

- [ ] **Step 1: Write the failing tests** (ajouter à la fin de `ValuationCalculatorTest.php`, la fonction helper `tx()` existe déjà en tête de fichier)

```php
use App\Contexts\Valuation\Datas\PriceRecordData as P;

it('aligns per-asset invested on the valuation labels (evolution)', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 2, false, 5, 50)],
        [
            new P(1, '2026-01-01', 100), new P(1, '2026-02-01', 120),
            new P(2, '2026-02-01', 50),
        ],
        ValuationRange::Max,
        ValuationGranularity::Day,
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        // valeur = 10*120 (asset1) + 5*50 (asset2) au 2026-02-01
        ->and($series->value)->toBe([1000.0, 1450.0])
        ->and($series->totalInvested)->toBe([1000.0, 1250.0]);

    $byName = collect($series->perAsset)->keyBy('name');
    expect($byName['#1']->invested)->toBe([1000.0, 1000.0])
        ->and($byName['#2']->invested)->toBe([0.0, 250.0]);
});

it('keeps sum of per-asset invested equal to totalInvested (evolution invariant)', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 4, 25)],
        [new P(1, '2026-01-01', 100), new P(2, '2026-01-01', 25)],
        ValuationRange::Max,
        ValuationGranularity::Day,
    );

    foreach ($series->labels as $i => $label) {
        $sum = collect($series->perAsset)->sum(fn ($s) => $s->invested[$i]);
        expect(round($sum, 2))->toBe($series->totalInvested[$i]);
    }
});

it('returns an empty evolution series without transactions', function () {
    expect((new ValuationCalculator)->evolution([], [], ValuationRange::Max, ValuationGranularity::Day))
        ->toEqual(\App\Contexts\Valuation\Datas\EvolutionSeriesData::empty());
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: FAIL (méthode `evolution` inexistante) ; les cas existants restent verts.

- [ ] **Step 3: Implémenter le helper + `evolution()` et refactorer `investedByAsset()`**

Ajouter les `use` en tête de `ValuationCalculator.php` :

```php
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
```

Ajouter la méthode privée (avant `valueAtDate`) :

```php
/**
 * Timelines d'investi cumulé par asset (fonction en escalier sur les dates de
 * transaction), clé = assetId dans l'ordre d'apparition.
 *
 * @param  list<TransactionRecordData>  $transactions
 * @return array<int, list<array{date: string, value: float}>>
 */
private function perAssetInvestedTimelines(array $transactions): array
{
    usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
        ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

    /** @var array<int, list<array{date: string, value: float}>> $perAsset */
    $perAsset = [];
    $buyQty = [];
    $buyCost = [];
    $invested = [];

    foreach ($transactions as $transaction) {
        $day = $transaction->date->format('Y-m-d');
        $assetId = $transaction->assetId;
        $invested[$assetId] ??= 0.0;
        $perAsset[$assetId] ??= [];

        if ($transaction->isSell) {
            $qty = $buyQty[$assetId] ?? 0.0;
            $cost = $buyCost[$assetId] ?? 0.0;
            $pru = $qty > 0.0 ? $cost / $qty : 0.0;
            $invested[$assetId] -= $transaction->quantity * $pru - $transaction->fees;
        } else {
            $buyQty[$assetId] = ($buyQty[$assetId] ?? 0.0) + $transaction->quantity;
            $buyCost[$assetId] = ($buyCost[$assetId] ?? 0.0) + $transaction->quantity * $transaction->unitPrice;
            $invested[$assetId] += $transaction->quantity * $transaction->unitPrice + $transaction->fees;
        }

        $perAsset[$assetId][] = ['date' => $day, 'value' => $invested[$assetId]];
    }

    return $perAsset;
}
```

Refactorer `investedByAsset()` pour réutiliser le helper (remplacer tout le corps entre le guard vide et le `return` final) :

```php
public function investedByAsset(array $transactions, int $maxPoints = 200): InvestedByAssetSeriesData
{
    if ($transactions === []) {
        return InvestedByAssetSeriesData::empty();
    }

    $perAsset = $this->perAssetInvestedTimelines($transactions);

    /** @var array<string, true> $dates */
    $dates = [];
    foreach ($perAsset as $entries) {
        foreach ($entries as $entry) {
            $dates[$entry['date']] = true;
        }
    }

    $labels = array_keys($dates);
    sort($labels);

    $indices = self::downsampleIndices(count($labels), $maxPoints);
    if (count($indices) < count($labels)) {
        $labels = array_map(fn (int $i): string => $labels[$i], $indices);
    }

    $series = [];
    foreach ($perAsset as $assetId => $entries) {
        $series[] = new AssetInvestedSeriesData(
            assetId: $assetId,
            name: '#'.$assetId,
            invested: array_map(fn (string $day): float => round($this->valueAtDate($entries, $day), 2), $labels),
        );
    }

    return new InvestedByAssetSeriesData($labels, $series);
}
```

Ajouter la méthode publique `evolution()` (après `investedByAsset()`) :

```php
/**
 * Série d'évolution alignée : sur les labels de la valorisation windowée,
 * expose la valeur totale, l'investi total et l'investi par asset.
 *
 * @param  list<TransactionRecordData>  $transactions
 * @param  list<PriceRecordData>  $prices
 */
public function evolution(
    array $transactions,
    array $prices,
    ValuationRange $range,
    ValuationGranularity $granularity,
): EvolutionSeriesData {
    if ($transactions === []) {
        return EvolutionSeriesData::empty();
    }

    $windowed = $this->windowAndAggregate(
        $this->calculateDaily($transactions, $prices),
        $range,
        $granularity,
    );

    if ($windowed->labels === []) {
        return EvolutionSeriesData::empty();
    }

    $perAssetTimelines = $this->perAssetInvestedTimelines($transactions);

    $perAsset = [];
    foreach ($perAssetTimelines as $assetId => $entries) {
        $perAsset[] = new AssetInvestedSeriesData(
            assetId: $assetId,
            name: '#'.$assetId,
            invested: array_map(fn (string $day): float => round($this->valueAtDate($entries, $day), 2), $windowed->labels),
        );
    }

    return new EvolutionSeriesData(
        labels: $windowed->labels,
        value: $windowed->valuations,
        totalInvested: $windowed->invested,
        perAsset: $perAsset,
    );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: PASS (cas existants + 3 nouveaux). Puis vérifier la non-régression du refactor :
Run: `php artisan test --compact --filter=InvestedByAsset`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php
git commit -m "feat: ValuationCalculator::evolution + extraction timelines par actif"
```

---

### Task 3: Action `BuildEvolutionSeries`

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildEvolutionSeries.php`
- Test: `app/Contexts/Valuation/Actions/BuildEvolutionSeriesTest.php`

**Interfaces:**
- Consumes: `TransactionHistoryPort::forUser(int): list<TransactionRecordData>`, `PriceHistoryPort::forAssetsSince(list<int>, Carbon): list<PriceRecordData>`, `InstrumentDirectoryPort::namesFor(list<int>): array<int,string>`, `ValuationCalculator::evolution(...)`, `EvolutionSeriesData`, `AssetInvestedSeriesData`.
- Produces: `__invoke(int $userId, ValuationRange $range = Max, ValuationGranularity $granularity = Month): EvolutionSeriesData` avec `perAsset[].name` = vrai nom d'instrument.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;

it('builds the merged evolution series with named per-asset invested', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $apple = Instrument::factory()->create(['name' => 'Apple']);
    $amazon = Instrument::factory()->create(['name' => 'Amazon']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $apple->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $amazon->id,
        'quantity' => 5, 'unit_price' => 50, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $apple->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $amazon->id, 'date' => '2026-01-01', 'close' => 50]);

    $data = app(BuildEvolutionSeries::class)($user->id, ValuationRange::Max, ValuationGranularity::Day);

    expect($data->labels)->toBe(['2026-01-01'])
        ->and($data->value)->toBe([1250.0])
        ->and($data->totalInvested)->toBe([1250.0]);

    $byName = collect($data->perAsset)->keyBy('name');
    expect($byName)->toHaveKeys(['Apple', 'Amazon'])
        ->and($byName['Apple']->invested)->toBe([1000.0])
        ->and($byName['Amazon']->invested)->toBe([250.0]);
});

it('returns an empty evolution series when the user has no transactions', function () {
    $user = User::factory()->create();

    expect(app(BuildEvolutionSeries::class)($user->id)->perAsset)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildEvolutionSeries`
Expected: FAIL (classe `BuildEvolutionSeries` inexistante).

- [ ] **Step 3: Write implementation**

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildEvolutionSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): EvolutionSeriesData {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return EvolutionSeriesData::empty();
        }

        $since = $transactions[0]->date;
        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $since);

        $raw = $this->calculator->evolution($transactions, $prices, $range, $granularity);

        $names = $this->directory->namesFor(array_map(
            fn (AssetInvestedSeriesData $serie): int => $serie->assetId,
            $raw->perAsset,
        ));

        $perAsset = array_map(
            fn (AssetInvestedSeriesData $serie): AssetInvestedSeriesData => new AssetInvestedSeriesData(
                assetId: $serie->assetId,
                name: $names[$serie->assetId] ?? $serie->name,
                invested: $serie->invested,
            ),
            $raw->perAsset,
        );

        return new EvolutionSeriesData($raw->labels, $raw->value, $raw->totalInvested, $perAsset);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuildEvolutionSeries`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Actions/BuildEvolutionSeries.php app/Contexts/Valuation/Actions/BuildEvolutionSeriesTest.php
git commit -m "feat: action BuildEvolutionSeries"
```

---

### Task 4: Controller — prop unique `evolutionSeries`

**Files:**
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Test: `tests/Feature/DashboardPageTest.php` (remplacer les 2 cas `valuationSeries`/`investedByAsset` par `evolutionSeries` ; ajuster le cas params range/granularity)

**Interfaces:**
- Consumes: `BuildEvolutionSeries`, `EvolutionSeriesData`.
- Produces: prop Inertia **déférée** `evolutionSeries` (remplace `valuationSeries` + `investedByAsset`). `valuationRange`, `valuationGranularity`, `performances`, `overview` inchangés.

- [ ] **Step 1: Mettre à jour les tests (feront échouer le controller actuel)**

Dans `tests/Feature/DashboardPageTest.php`, **remplacer** le cas `it('defers the valuation series and loads it on demand', …)` ET le cas `it('defers the invested-by-asset series and loads it on demand', …)` par un seul :

```php
it('defers the evolution series and loads it on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('evolutionSeries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('evolutionSeries.labels', 1)
                ->has('evolutionSeries.value', 1)
                ->has('evolutionSeries.totalInvested', 1)
                ->has('evolutionSeries.perAsset', 1)
                ->where('evolutionSeries.perAsset.0.name', 'ACME')
            )
        );
});
```

Dans le cas `it('accepts range and granularity query params for the dashboard series', …)`, **remplacer** le bloc `loadDeferredProps` par :

```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('evolutionSeries.labels')
                ->has('evolutionSeries.perAsset')
            )
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL (prop `evolutionSeries` absente / `valuationSeries` toujours émise).

- [ ] **Step 3: Modifier le controller**

Remplacer les `use` des deux actions/DTO retirés par `BuildEvolutionSeries` + `EvolutionSeriesData`, et les deux props par une seule. Corps de `__invoke` (bloc props) :

```php
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
```

```php
        return Inertia::render('Dashboard', [
            'overview' => $overview,
            'valuationRange' => $range->value,
            'valuationGranularity' => $granularity->value,
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($user->id)
                : []),
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($user->id, $range, $granularity)
                : EvolutionSeriesData::empty()),
        ]);
```

(Retirer les `use` de `BuildInvestedByAssetSeries`, `BuildPortfolioValuationSeries`, `InvestedByAssetSeriesData`, `ValuationSeriesData` désormais inutilisés dans ce fichier. Les classes/actions restent dans le repo — non supprimées.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Http/DashboardController.php tests/Feature/DashboardPageTest.php
git commit -m "feat: dashboard expose evolutionSeries (prop unique)"
```

---

### Task 5: Fabrique front `buildEvolutionChart()`

**Files:**
- Modify: `resources/js/lib/chart.ts` (ajout `buildEvolutionChart`, export ; réutilise `formatTooltipDate`)

**Interfaces:**
- Consumes: `formatTooltipDate` (module-privé existant), type `ApexOptions`.
- Produces (export) :
```ts
type EvolutionInput = {
    labels: string[];
    value: number[];
    totalInvested: number[];
    perAsset: { name: string; invested: number[] }[];
    valueFormatter: (value: number) => string;
};
export function buildEvolutionChart(input: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions }
```
Ordre des séries produit : aires cumulées de la plus grande (fond) à la plus petite (avant), puis bande gain, bande perte, puis ligne `Valeur`. `colors`/`stroke.width`/`fill.opacity` alignés sur cet ordre.

- [ ] **Step 1: Implémentation** (ajouter dans `resources/js/lib/chart.ts`, après `buildTimeSeriesOptions`)

```ts
const GREY_SCALE = ['#e2e8f0', '#cbd5e1', '#94a3b8', '#64748b', '#475569', '#334155'];
const VALUE_LINE_COLOR = '#4f46e5';
const GAIN_COLOR = '#10b981';
const LOSS_COLOR = '#ef4444';
const BAND_NAMES = ['__gain__', '__loss__'];

type EvolutionInput = {
    labels: string[];
    value: number[];
    totalInvested: number[];
    perAsset: { name: string; invested: number[] }[];
    valueFormatter: (value: number) => string;
};

export function buildEvolutionChart({
    labels,
    value,
    totalInvested,
    perAsset,
    valueFormatter,
}: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions } {
    const point = (i: number, y: number | number[] | null): { x: string; y: number | number[] | null } => ({ x: labels[i], y });

    const cumulative: number[][] = perAsset.map((_, k) =>
        labels.map((_label, i) => perAsset.slice(0, k + 1).reduce((sum, asset) => sum + (asset.invested[i] ?? 0), 0)),
    );

    const areaSeries: ApexAxisChartSeries = [];
    const areaColors: string[] = [];
    for (let k = perAsset.length - 1; k >= 0; k -= 1) {
        areaSeries.push({
            name: perAsset[k].name,
            type: 'area',
            data: labels.map((_label, i) => point(i, cumulative[k][i])),
        });
        areaColors.push(GREY_SCALE[k % GREY_SCALE.length]);
    }

    const gainBand = {
        name: BAND_NAMES[0],
        type: 'rangeArea',
        data: labels.map((_label, i) =>
            value[i] >= totalInvested[i] ? point(i, [totalInvested[i], value[i]]) : point(i, [null, null] as unknown as number[])),
    };
    const lossBand = {
        name: BAND_NAMES[1],
        type: 'rangeArea',
        data: labels.map((_label, i) =>
            value[i] < totalInvested[i] ? point(i, [value[i], totalInvested[i]]) : point(i, [null, null] as unknown as number[])),
    };
    const valueLine = {
        name: 'Valeur',
        type: 'line',
        data: labels.map((_label, i) => point(i, value[i])),
    };

    const series: ApexAxisChartSeries = [...areaSeries, gainBand, lossBand, valueLine];
    const colors = [...areaColors, GAIN_COLOR, LOSS_COLOR, VALUE_LINE_COLOR];
    const strokeWidth = [...areaSeries.map(() => 0), 0, 0, 2];
    const fillOpacity = [...areaSeries.map(() => 0.9), 0.35, 0.35, 1];

    const options: ApexOptions = {
        chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
        colors,
        stroke: { curve: 'smooth', width: strokeWidth },
        fill: { type: 'solid', opacity: fillOpacity },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
        xaxis: {
            type: 'datetime',
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { hideOverlappingLabels: true, style: { colors: 'oklch(0.708 0 0)' } },
        },
        yaxis: { labels: { formatter: (v: number): string => valueFormatter(v), style: { colors: 'oklch(0.708 0 0)' } } },
        legend: {
            position: 'left',
            horizontalAlign: 'left',
            labels: { colors: '#fff' },
            onItemClick: { toggleDataSeries: false },
            formatter: (name: string): string => (BAND_NAMES.includes(name) ? '' : name),
        },
        responsive: LEGEND_BELOW_ON_MOBILE,
        tooltip: {
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex }): string => {
                const i = dataPointIndex;
                const gain = value[i] - totalInvested[i];
                const gainColor = gain >= 0 ? GAIN_COLOR : LOSS_COLOR;
                const gainSign = gain >= 0 ? '+' : '−';

                const header = `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${formatTooltipDate(labels[i])}</div>`;

                const row = (color: string, label: string, text: string): string =>
                    `<div class="apexcharts-tooltip-series-group apexcharts-active" style="display: flex;">`
                    + `<span class="apexcharts-tooltip-marker" style="background-color: ${color};"></span>`
                    + `<div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">`
                    + `<div class="apexcharts-tooltip-y-group">`
                    + `<span class="apexcharts-tooltip-text-y-label">${label}: </span>`
                    + `<span class="apexcharts-tooltip-text-y-value">${text}</span>`
                    + `</div></div></div>`;

                const valueRow = row(VALUE_LINE_COLOR, 'Valeur', valueFormatter(value[i]));
                const gainRow = row(gainColor, gain >= 0 ? 'Gain' : 'Perte', `${gainSign} ${valueFormatter(Math.abs(gain))}`);
                const assetRows = perAsset
                    .map((asset, k) => row(GREY_SCALE[k % GREY_SCALE.length], asset.name, valueFormatter(asset.invested[i] ?? 0)))
                    .join('');

                return header + valueRow + gainRow + assetRows;
            },
        },
    };

    return { series, options };
}
```

- [ ] **Step 2: Build pour valider TS/bundling**

Run: `bun run build`
Expected: `✓ built`. (Aucun test JS : le repo n'a pas de runner front ; la validation runtime se fait en Task 6 via Playwright.)

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/chart.ts
git commit -m "feat: buildEvolutionChart (aires empilées + bande gain/perte + ligne Valeur)"
```

---

### Task 6: Dashboard.vue — intégrer le graph fusionné, retirer l'ancien

**Files:**
- Modify: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Browser/DashboardChartLegendTest.php` (retirer l'assertion « Investi par titre » ; passer le compte de légendes-gauche de 3 à 2)

**Interfaces:**
- Consumes: `buildEvolutionChart` (Task 5), prop `evolutionSeries` (Task 4).
- Produces: une seule card « Évolution » avec le graph fusionné ; suppression de la card « Investi par titre ».

- [ ] **Step 1: Mettre à jour le test browser**

Dans `tests/Browser/DashboardChartLegendTest.php` : supprimer la ligne `->assertSee('Investi par titre')`, et remplacer `->assertCount('.apx-legend-position-left', 3)` par `->assertCount('.apx-legend-position-left', 2)`. Conserver `assertSee('Évolution')`, `assertSee('Répartition')`, `assertSee('Valeur')`, `assertSee('GLOBEX')`, `assertNoJavaScriptErrors()`.

- [ ] **Step 2: `<script setup>` — remplacer les interfaces et computed valuation/investedByAsset**

Remplacer les interfaces `ValuationSeries`, `AssetInvestedSeries`, `InvestedByAssetSeries` par :

```ts
interface EvolutionSeries {
    labels: string[];
    value: number[];
    totalInvested: number[];
    perAsset: { name: string; invested: number[] }[];
}
```

Dans `defineProps`, remplacer `valuationSeries?: ValuationSeries; investedByAsset?: InvestedByAssetSeries;` par `evolutionSeries?: EvolutionSeries;`.

Mettre à jour l'import chart :

```ts
import { buildDonutOptions, buildEvolutionChart } from '@/lib/chart';
```

Remplacer les computed `hasValuation`, `valuationChartSeries`, `valuationChartOptions`, `hasInvestedByAsset`, `investedByAssetSeries`, `investedByAssetOptions` par :

```ts
const hasEvolution = computed<boolean>(() => (props.evolutionSeries?.labels.length ?? 0) > 0);

const evolutionChart = computed(() =>
    buildEvolutionChart({
        labels: props.evolutionSeries?.labels ?? [],
        value: props.evolutionSeries?.value ?? [],
        totalInvested: props.evolutionSeries?.totalInvested ?? [],
        perAsset: props.evolutionSeries?.perAsset ?? [],
        valueFormatter: eur,
    }),
);
```

Mettre à jour `reloadSeries` : `only: ['evolutionSeries']`.

- [ ] **Step 3: `<template>` — fusionner les cards**

Remplacer le contenu de `<CardContent class="px-0 sm:px-6">` de la card Évolution (le bloc `<Deferred data="valuationSeries">…</Deferred>`) par :

```vue
                <CardContent class="px-0 sm:px-6">
                    <Deferred data="evolutionSeries">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasEvolution"
                            type="line"
                            height="300"
                            :options="evolutionChart.options"
                            :series="evolutionChart.series"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'historique de valorisation.
                        </p>
                    </Deferred>
                </CardContent>
```

Mettre à jour la `<CardDescription>` de la card Évolution : `Valeur, investi par titre et performance`.

**Supprimer entièrement** la card « Investi par titre » (le bloc `<Card …><CardHeader><CardTitle>Investi par titre</CardTitle>…</Card>` avec son `<Deferred data="investedByAsset">`).

- [ ] **Step 4: Build**

Run: `bun run build`
Expected: `✓ built`.

- [ ] **Step 5: Vérification runtime (Playwright)**

Charger `https://argent.test/dashboard` (desktop 1000px puis mobile 420px) et vérifier :
1. Une seule card graphique temporelle « Évolution » ; plus de card « Investi par titre ».
2. Aires grises empilées (une par actif), ligne indigo « Valeur » au-dessus, bande verte (portefeuille en gain sur le jeu de démo).
3. Survol : tooltip listant date + Valeur + Gain (vert) + investi par actif.
4. Légende : actifs (gris) + Valeur, sans entrées `__gain__`/`__loss__` visibles ; en mobile légende en bas alignée à gauche.
5. Aucune erreur console (`read_console_messages onlyErrors`).

Snapshot de contrôle : `document.querySelectorAll('.apexcharts-canvas').length === 2` (Évolution + donut).

- [ ] **Step 6: Lancer les tests browser impactés**

Run: `php artisan test --compact --filter=DashboardChartLegend`
Expected: PASS (légendes-gauche = 2, plus de « Investi par titre »).

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Dashboard.vue tests/Browser/DashboardChartLegendTest.php
git commit -m "feat: dashboard graph fusionné Évolution (investi par actif empilé + Valeur + gain/perte)"
```

---

## Notes d'exécution / risques

- **Alignement x des `rangeArea`** : toutes les séries utilisent le format `{ x: label, y }` (x explicite) pour garantir l'alignement avec les aires/ligne. Les points inactifs d'une bande valent `y: [null, null]` (gap). Si ApexCharts rejette `[null,null]`, replier sur un point omis mais garder x régulier — vérifier à l'étape Playwright de la Task 6.
- **Masquage légende des bandes** : via `legend.formatter` retournant `''` pour `__gain__`/`__loss__`. Si un marqueur vide résiduel apparaît, c'est cosmétique et validé/ajusté à l'étape Playwright.
- **Toggle légende désactivé** (`onItemClick.toggleDataSeries: false`) car masquer une couche casserait le pré-cumul manuel.
- Le tooltip « overlap » de `buildTimeSeriesOptions` (page Instrument) reste inchangé.
- Actions/DTO devenus inutilisés côté dashboard (`BuildPortfolioValuationSeries`, `BuildInvestedByAssetSeries`, `ValuationSeriesData` pour ce flux) sont **conservés** (toujours testés) — nettoyage éventuel hors périmètre.

## Self-review (fait)

- Couverture spec : DTO (T1), calcul aligné + invariant somme + cas perte (T2), noms + action (T3), prop unique déférée (T4), graph mixte pré-cumul + bandes + tooltip + légende (T5), layout/fusion + suppression card + tests browser (T6). ✓
- Placeholders : aucun (code complet à chaque étape). ✓
- Cohérence des types : `EvolutionSeriesData(labels,value,totalInvested,perAsset)` identique T1→T2→T3→T4 ; `buildEvolutionChart` input identique T5→T6 ; `perAsset[].name` string, `invested` list<float>. ✓
