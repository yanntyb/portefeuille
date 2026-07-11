# Courbes d'investissement par titre — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter deux graphes réutilisant le contexte `Valuation` : sur la fiche instrument, une courbe Valeur vs Investi du titre ; sur le dashboard, une courbe d'investi cumulé par titre (une ligne par titre) dans un nouveau graphe.

**Architecture:** Réutilise `ValuationCalculator` (pur) et `ValuationSeriesData`. Fiche = nouvelle action `BuildAssetValuationSeries` qui filtre les transactions sur un asset et rejoue `calculate`. Dashboard = nouvelle méthode pure `investedByAsset` (investi en escalier par asset sur axe partagé, sans prix) + nouveau port `InstrumentDirectoryPort` (adapter Market) pour nommer les séries + action `BuildInvestedByAssetSeries`. Les deux sont livrés en props Inertia `deferred`.

**Tech Stack:** Laravel 12, PHP 8.4, Inertia v3, Vue 3 + TS, ApexCharts, shadcn-vue, Tailwind v4, Pest 4.

## Global Constraints

- Cross-contexte par id + Port uniquement ; les adapters sont le seul endroit lisant `Market\*` / `Portfolio\*`.
- Textes UI **en français** ; états vides en français.
- DTOs de sortie = `readonly` + `JsonSerializable` dans `Valuation\Datas`.
- Types explicites partout ; accolades obligatoires ; PHPDoc `@return list<...>` / `@param list<...>` sur les array.
- Après modif PHP : `vendor/bin/pint --dirty --format agent` avant commit. Front : `bun run typecheck` puis `bun run build`.
- Props livrées en `Inertia::defer` (comme `valuationSeries` / `priceHistory` existants).
- Formule d'investi **identique** à `ValuationCalculator::calculate` (achat : `+= qty*prix + fees` ; vente : `-= qty*PRU - fees`, `PRU = buyCost/buyQty`).
- L'action attache les noms ; le calculateur reste agnostique des noms (fallback `#<assetId>`).

---

## File Structure

**Créés — Valuation :**
- `app/Contexts/Valuation/Datas/AssetInvestedSeriesData.php` — JsonSerializable (assetId, name, invested)
- `app/Contexts/Valuation/Datas/InvestedByAssetSeriesData.php` — JsonSerializable (labels, series) + `::empty()`
- `app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php` — `namesFor(array): array<int,string>`
- `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php` — adapter Market
- `app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php`
- `app/Contexts/Valuation/Actions/BuildInvestedByAssetSeries.php`

**Modifiés :**
- `app/Contexts/Valuation/Services/ValuationCalculator.php` — méthode `investedByAsset()`
- `app/Contexts/Valuation/ValuationProvider.php` — param `instrumentDirectory` + binding
- `app/Providers/AppServiceProvider.php` — passe `MarketInstrumentDirectory::class`
- `app/Contexts/InstrumentView/Http/InstrumentDetailController.php` — prop deferred `valuation`
- `app/Contexts/Portfolio/Http/DashboardController.php` — prop deferred `investedByAsset`
- `resources/js/Pages/Instruments/Show.vue` — 2ᵉ graphe Valeur/Investi
- `resources/js/Pages/Dashboard.vue` — carte « Investi par titre »

**Tests créés :**
- `app/Contexts/Valuation/Datas/InvestedByAssetSeriesDataTest.php`
- `app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php`
- `app/Contexts/Valuation/Actions/BuildInvestedByAssetSeriesTest.php`
- `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php`
- (méthode `investedByAsset` testée dans le `ValuationCalculatorTest.php` existant)
- (feature) `tests/Feature/InstrumentDetailPageTest.php` (ajout), `tests/Feature/DashboardPageTest.php` (ajout)

Note conventions Valuation : les tests unit du contexte vivent **à côté du code** sous `app/Contexts/Valuation/...Test.php` (voir `ValuationCalculatorTest.php`, `BuildPortfolioValuationSeriesTest.php`). Suivre ce placement.

---

## Task 1: DTOs invested-par-titre

**Files:**
- Create: `app/Contexts/Valuation/Datas/AssetInvestedSeriesData.php`, `app/Contexts/Valuation/Datas/InvestedByAssetSeriesData.php`
- Test: `app/Contexts/Valuation/Datas/InvestedByAssetSeriesDataTest.php`

**Interfaces:**
- Produces:
  - `AssetInvestedSeriesData(int $assetId, string $name, array $invested)` → json `assetId,name,invested`
  - `InvestedByAssetSeriesData(array $labels, array $series)` + `::empty()` → json `labels,series`

- [ ] **Step 1: Write the failing test**

`app/Contexts/Valuation/Datas/InvestedByAssetSeriesDataTest.php`
```php
<?php

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;

it('serializes an asset invested series', function () {
    $serie = new AssetInvestedSeriesData(assetId: 7, name: 'ACME', invested: [100.0, 250.0]);

    expect($serie->jsonSerialize())->toBe([
        'assetId' => 7,
        'name' => 'ACME',
        'invested' => [100.0, 250.0],
    ]);
});

it('serializes the invested-by-asset wrapper', function () {
    $data = new InvestedByAssetSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        series: [new AssetInvestedSeriesData(7, 'ACME', [100.0, 250.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01']);
    expect($json['series'][0])->toBeInstanceOf(AssetInvestedSeriesData::class);
});

it('builds an empty invested-by-asset series', function () {
    expect(InvestedByAssetSeriesData::empty()->jsonSerialize())->toBe(['labels' => [], 'series' => []]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=InvestedByAssetSeriesDataTest`
Expected: FAIL (classes introuvables)

- [ ] **Step 3: Write the DTOs**

`app/Contexts/Valuation/Datas/AssetInvestedSeriesData.php`
```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetInvestedSeriesData implements JsonSerializable
{
    /** @param list<float> $invested */
    public function __construct(
        public int $assetId,
        public string $name,
        public array $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'name' => $this->name,
            'invested' => $this->invested,
        ];
    }
}
```

`app/Contexts/Valuation/Datas/InvestedByAssetSeriesData.php`
```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class InvestedByAssetSeriesData implements JsonSerializable
{
    /**
     * @param list<string> $labels
     * @param list<AssetInvestedSeriesData> $series
     */
    public function __construct(
        public array $labels,
        public array $series,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'series' => $this->series,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=InvestedByAssetSeriesDataTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Datas/AssetInvestedSeriesData.php app/Contexts/Valuation/Datas/InvestedByAssetSeriesData.php app/Contexts/Valuation/Datas/InvestedByAssetSeriesDataTest.php
git commit -m "feat: DTOs invested-par-titre (Valuation)"
```

---

## Task 2: `ValuationCalculator::investedByAsset`

**Files:**
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` (ajouter des cas)

**Interfaces:**
- Consumes: `TransactionRecordData`, `AssetInvestedSeriesData`, `InvestedByAssetSeriesData`, helpers privés existants `valueAtDate`, statique `downsampleIndices`.
- Produces: `ValuationCalculator::investedByAsset(array $transactions, int $maxPoints = 200): InvestedByAssetSeriesData`. Investi cumulé par asset (sans prix), sur `labels` = union triée des dates de transaction ; forward-fill (0 avant 1ʳᵉ tx du titre) ; `name` = `'#'.$assetId` (l'action remplace). Formule investi identique à `calculate`.

- [ ] **Step 1: Write the failing tests**

Ajouter à la fin de `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` (le helper `tx()` existe déjà en haut du fichier) :
```php
it('accumulates invested per asset on a shared date axis', function () {
    $series = (new ValuationCalculator)->investedByAsset([
        tx('2026-01-01', 1, false, 10, 100),          // asset 1 invests 1000
        tx('2026-02-01', 2, false, 5, 50),            // asset 2 invests 250
        tx('2026-03-01', 1, false, 2, 150),           // asset 1 invests +300 => 1300
    ]);

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01', '2026-03-01']);

    $byId = collect($series->series)->keyBy('assetId');
    // asset 1: 1000 at d1, forward-fill 1000 at d2, 1300 at d3
    expect($byId[1]->invested)->toBe([1000.0, 1000.0, 1300.0]);
    expect($byId[1]->name)->toBe('#1');
    // asset 2: 0 before its first tx, 250 from d2 onward
    expect($byId[2]->invested)->toBe([0.0, 250.0, 250.0]);
});

it('reduces invested by cost basis on a sell (per asset)', function () {
    $series = (new ValuationCalculator)->investedByAsset([
        tx('2026-01-01', 1, false, 10, 100),          // invested 1000, PRU 100
        tx('2026-02-01', 1, true, 4, 150),            // invested -= 4*100 => 600
    ]);

    $byId = collect($series->series)->keyBy('assetId');
    expect($byId[1]->invested)->toBe([1000.0, 600.0]);
});

it('returns an empty invested-by-asset series without transactions', function () {
    expect((new ValuationCalculator)->investedByAsset([]))->toEqual(
        \App\Contexts\Valuation\Datas\InvestedByAssetSeriesData::empty()
    );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ValuationCalculatorTest`
Expected: FAIL (`investedByAsset` non définie) sur les 3 nouveaux, les anciens toujours verts

- [ ] **Step 3: Add the method**

Ajouter dans `app/Contexts/Valuation/Services/ValuationCalculator.php` (imports en tête : `use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;` et `use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;`) la méthode publique :
```php
    /**
     * Investi cumulé par asset dans le temps (fonction en escalier sur les dates
     * de transaction, sans prix). `name` est laissé à `#<assetId>` — l'action
     * qui consomme cette méthode y substitue le vrai nom.
     *
     * @param  list<TransactionRecordData>  $transactions
     */
    public function investedByAsset(array $transactions, int $maxPoints = 200): InvestedByAssetSeriesData
    {
        if ($transactions === []) {
            return InvestedByAssetSeriesData::empty();
        }

        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $perAsset */
        $perAsset = [];
        $buyQty = [];
        $buyCost = [];
        $invested = [];
        /** @var array<string, true> $dates */
        $dates = [];

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $dates[$day] = true;
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

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValuationCalculatorTest`
Expected: PASS (anciens + 3 nouveaux)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php
git commit -m "feat: ValuationCalculator::investedByAsset (investi cumule par titre)"
```

---

## Task 3: Action `BuildAssetValuationSeries` (fiche)

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php`
- Test: `app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php`

**Interfaces:**
- Consumes: `TransactionHistoryPort::forUser`, `PriceHistoryPort::forAssetsSince`, `ValuationCalculator::calculate`, `TransactionRecordData`, `ValuationSeriesData`.
- Produces: `BuildAssetValuationSeries::__invoke(int $userId, int $assetId): ValuationSeriesData`. Filtre les transactions de l'user sur `assetId` ; `::empty()` si aucune ; prix du seul titre depuis la 1ʳᵉ tx ; `calculate`.

- [ ] **Step 1: Write the failing test**

`app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php`
```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;

it('builds the value/invested series for a single title, ignoring other titles', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    $other = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $other->id,
        'quantity' => 99, 'unit_price' => 999, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 120]);

    $series = app(BuildAssetValuationSeries::class)($user->id, $asset->id);

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('returns an empty series when the user has no transaction for the title', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();

    $series = app(BuildAssetValuationSeries::class)($user->id, $asset->id);

    expect($series->labels)->toBe([])
        ->and($series->valuations)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildAssetValuationSeriesTest`
Expected: FAIL (`BuildAssetValuationSeries` introuvable)

- [ ] **Step 3: Write the action**

`app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php`
```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildAssetValuationSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(int $userId, int $assetId): ValuationSeriesData
    {
        $transactions = array_values(array_filter(
            $this->transactions->forUser($userId),
            fn (TransactionRecordData $transaction) => $transaction->assetId === $assetId,
        ));

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $prices = $this->prices->forAssetsSince([$assetId], $transactions[0]->date);

        return $this->calculator->calculate($transactions, $prices);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuildAssetValuationSeriesTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php
git commit -m "feat: action BuildAssetValuationSeries (valeur/investi par titre)"
```

---

## Task 4: Port `InstrumentDirectoryPort` + adapter + wiring

**Files:**
- Create: `app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php`, `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php`
- Modify: `app/Contexts/Valuation/ValuationProvider.php`, `app/Providers/AppServiceProvider.php`
- Test: `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php`

**Interfaces:**
- Consumes: `Market\Models\Instrument`.
- Produces:
  - `InstrumentDirectoryPort::namesFor(array $assetIds): array` → `array<int, string>`
  - `MarketInstrumentDirectory implements InstrumentDirectoryPort`
  - `ValuationProvider::registers(app, $transactionHistory, $priceHistory, $instrumentDirectory)` binde aussi `InstrumentDirectoryPort`.

- [ ] **Step 1: Write the failing test**

`app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php`
```php
<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

it('maps asset ids to instrument names', function () {
    $a = Instrument::factory()->create(['name' => 'ACME']);
    $b = Instrument::factory()->create(['name' => 'Globex']);

    $names = app(InstrumentDirectoryPort::class)->namesFor([$a->id, $b->id, 999]);

    expect($names[$a->id])->toBe('ACME');
    expect($names[$b->id])->toBe('Globex');
    expect($names)->not->toHaveKey(999);
});

it('returns an empty map for no ids', function () {
    expect(app(InstrumentDirectoryPort::class)->namesFor([]))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MarketInstrumentDirectoryTest`
Expected: FAIL (port non lié)

- [ ] **Step 3: Write the port + adapter**

`app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php`
```php
<?php

namespace App\Contexts\Valuation\Ports;

interface InstrumentDirectoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
```

`app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php`
```php
<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;

class MarketInstrumentDirectory implements InstrumentDirectoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->pluck('name', 'id')
            ->map(fn (?string $name): string => (string) $name)
            ->all();
    }
}
```

- [ ] **Step 4: Extend the provider + wire it**

Dans `app/Contexts/Valuation/ValuationProvider.php` : ajouter `use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;`, un 4ᵉ paramètre et son binding :
```php
    /**
     * @param  class-string<TransactionHistoryPort>  $transactionHistory
     * @param  class-string<PriceHistoryPort>  $priceHistory
     * @param  class-string<InstrumentDirectoryPort>  $instrumentDirectory
     */
    public static function registers(
        Application $app,
        string $transactionHistory,
        string $priceHistory,
        string $instrumentDirectory,
    ): void {
        $app->bind(TransactionHistoryPort::class, $transactionHistory);
        $app->bind(PriceHistoryPort::class, $priceHistory);
        $app->bind(InstrumentDirectoryPort::class, $instrumentDirectory);
    }
```

Dans `app/Providers/AppServiceProvider.php` : ajouter `use App\Contexts\Valuation\Infrastructure\MarketInstrumentDirectory;` et passer l'argument à l'appel existant :
```php
        ValuationProvider::registers(
            app: $this->app,
            transactionHistory: PortfolioTransactionHistory::class,
            priceHistory: MarketPriceHistory::class,
            instrumentDirectory: MarketInstrumentDirectory::class,
        );
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=MarketInstrumentDirectoryTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php app/Contexts/Valuation/ValuationProvider.php app/Providers/AppServiceProvider.php app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php
git commit -m "feat: InstrumentDirectoryPort + adapter Market (noms des titres)"
```

---

## Task 5: Action `BuildInvestedByAssetSeries` (dashboard)

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildInvestedByAssetSeries.php`
- Test: `app/Contexts/Valuation/Actions/BuildInvestedByAssetSeriesTest.php`

**Interfaces:**
- Consumes: `TransactionHistoryPort::forUser`, `ValuationCalculator::investedByAsset`, `InstrumentDirectoryPort::namesFor`, `AssetInvestedSeriesData`, `InvestedByAssetSeriesData`.
- Produces: `BuildInvestedByAssetSeries::__invoke(int $userId): InvestedByAssetSeriesData`. `::empty()` si aucune tx. Attache les noms (fallback `#<assetId>` conservé si nom absent).

- [ ] **Step 1: Write the failing test**

`app/Contexts/Valuation/Actions/BuildInvestedByAssetSeriesTest.php`
```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildInvestedByAssetSeries;

it('builds one named invested series per title', function () {
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
        'quantity' => 5, 'unit_price' => 50, 'date' => '2026-02-01',
    ]);

    $data = app(BuildInvestedByAssetSeries::class)($user->id);

    expect($data->labels)->toBe(['2026-01-01', '2026-02-01']);
    $byName = collect($data->series)->keyBy('name');
    expect($byName)->toHaveKeys(['Apple', 'Amazon']);
    expect($byName['Apple']->invested)->toBe([1000.0, 1000.0]);
    expect($byName['Amazon']->invested)->toBe([0.0, 250.0]);
});

it('returns an empty series when the user has no transactions', function () {
    $user = User::factory()->create();

    expect(app(BuildInvestedByAssetSeries::class)($user->id)->series)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildInvestedByAssetSeriesTest`
Expected: FAIL (`BuildInvestedByAssetSeries` introuvable)

- [ ] **Step 3: Write the action**

`app/Contexts/Valuation/Actions/BuildInvestedByAssetSeries.php`
```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\AssetInvestedSeriesData;
use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildInvestedByAssetSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private InstrumentDirectoryPort $directory,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(int $userId): InvestedByAssetSeriesData
    {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return InvestedByAssetSeriesData::empty();
        }

        $raw = $this->calculator->investedByAsset($transactions);

        $names = $this->directory->namesFor(array_map(
            fn (AssetInvestedSeriesData $serie): int => $serie->assetId,
            $raw->series,
        ));

        $series = array_map(
            fn (AssetInvestedSeriesData $serie): AssetInvestedSeriesData => new AssetInvestedSeriesData(
                assetId: $serie->assetId,
                name: $names[$serie->assetId] ?? $serie->name,
                invested: $serie->invested,
            ),
            $raw->series,
        );

        return new InvestedByAssetSeriesData($raw->labels, $series);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuildInvestedByAssetSeriesTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Actions/BuildInvestedByAssetSeries.php app/Contexts/Valuation/Actions/BuildInvestedByAssetSeriesTest.php
git commit -m "feat: action BuildInvestedByAssetSeries (investi par titre nomme)"
```

---

## Task 6: Fiche — graphe Valeur vs Investi

**Files:**
- Modify: `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`, `resources/js/Pages/Instruments/Show.vue`
- Test: `tests/Feature/InstrumentDetailPageTest.php` (ajout)

**Interfaces:**
- Consumes: `BuildAssetValuationSeries` (via container), `ValuationSeriesData`.
- Produces: prop deferred `valuation` sur `Instruments/Show` ; 2ᵉ graphe area dans `Show.vue`.

- [ ] **Step 1: Add the failing feature assertion**

Ajouter ce test dans `tests/Feature/InstrumentDetailPageTest.php` :
```php
it('defers the per-title valuation series and loads it on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->missing('valuation')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels', 1)
                ->has('valuation.valuations', 1)
                ->has('valuation.invested', 1)
            )
        );
});
```
Vérifie que le fichier importe déjà `Transaction`, `Wallet`, `Price`, `Assert` — sinon ajouter les `use` en tête (voir les autres tests du fichier). `use Inertia\Testing\AssertableInertia as Assert;` doit être présent.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="InstrumentDetailPageTest"`
Expected: FAIL sur le nouveau (`valuation` absent)

- [ ] **Step 3: Add the deferred prop in the controller**

Dans `app/Contexts/InstrumentView/Http/InstrumentDetailController.php` : ajouter `use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;`, puis dans le tableau `Inertia::render('Instruments/Show', [...])` ajouter après `priceHistory` :
```php
            'valuation' => Inertia::defer(
                fn () => app(BuildAssetValuationSeries::class)($userId, $id)
            ),
```

- [ ] **Step 4: Run test to verify it passes (backend)**

Run: `php artisan test --compact --filter="InstrumentDetailPageTest"`
Expected: PASS

- [ ] **Step 5: Add the second chart in `Show.vue`**

Dans la balise `<script setup>` de `resources/js/Pages/Instruments/Show.vue` :

a) après l'interface `PriceHistory` (vers la ligne 59-62), ajouter :
```ts
interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
}
```
b) étendre `defineProps` :
```ts
const props = defineProps<{ instrument: Instrument; priceHistory?: PriceHistory; valuation?: ValuationSeries }>();
```
c) après le bloc `priceChartOptions` (après sa parenthèse fermante, vers la ligne ~114), ajouter :
```ts
const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const valuationChartSeries = computed(() => [
    { name: 'Valeur', data: props.valuation?.valuations ?? [] },
    { name: 'Investi', data: props.valuation?.invested ?? [] },
]);

const valuationChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'top' },
}));
```

Dans le `<template>`, juste après la `</Card>` de la section « Cours » (celle contenant `<Deferred data="priceHistory">`, se termine vers la ligne ~183), insérer :
```vue
            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Valeur vs Investi</CardTitle>
                    <CardDescription>Évolution de ma position sur ce titre</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="valuation">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasValuation"
                            type="area"
                            height="300"
                            :options="valuationChartOptions"
                            :series="valuationChartSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'historique de valorisation.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>
```

- [ ] **Step 6: Typecheck + build + re-run feature test**

Run: `bun run typecheck && bun run build && php artisan test --compact --filter="InstrumentDetailPageTest"`
Expected: typecheck clean, build OK, tests PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Http/InstrumentDetailController.php resources/js/Pages/Instruments/Show.vue tests/Feature/InstrumentDetailPageTest.php
git commit -m "feat: fiche instrument affiche la courbe valeur/investi du titre"
```

---

## Task 7: Dashboard — graphe Investi par titre

**Files:**
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`, `resources/js/Pages/Dashboard.vue`
- Test: `tests/Feature/DashboardPageTest.php` (ajout)

**Interfaces:**
- Consumes: `BuildInvestedByAssetSeries` (via container), `InvestedByAssetSeriesData`.
- Produces: prop deferred `investedByAsset` sur `Dashboard` ; nouvelle carte graphe lignes dans `Dashboard.vue`.

- [ ] **Step 1: Add the failing feature assertion**

Ajouter ce test dans `tests/Feature/DashboardPageTest.php` :
```php
it('defers the invested-by-asset series and loads it on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('investedByAsset')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('investedByAsset.series', 1)
                ->where('investedByAsset.series.0.name', 'ACME')
            )
        );
});
```
(`Transaction`, `Wallet`, `Instrument`, `Assert` sont déjà importés dans ce fichier.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL sur le nouveau (`investedByAsset` absent)

- [ ] **Step 3: Add the deferred prop in the controller**

Dans `app/Contexts/Portfolio/Http/DashboardController.php` : ajouter `use App\Contexts\Valuation\Actions\BuildInvestedByAssetSeries;` et `use App\Contexts\Valuation\Datas\InvestedByAssetSeriesData;`, puis dans le tableau `Inertia::render('Dashboard', [...])` ajouter après `valuationSeries` :
```php
            'investedByAsset' => Inertia::defer(fn () => $user !== null
                ? app(BuildInvestedByAssetSeries::class)($user->id)
                : InvestedByAssetSeriesData::empty()),
```

- [ ] **Step 4: Run test to verify it passes (backend)**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS

- [ ] **Step 5: Add the chart card in `Dashboard.vue`**

Dans le `<script setup>` de `resources/js/Pages/Dashboard.vue` :

a) après l'interface `ValuationSeries` (vers la ligne 52-56), ajouter :
```ts
interface AssetInvestedSeries {
    assetId: number;
    name: string;
    invested: number[];
}

interface InvestedByAssetSeries {
    labels: string[];
    series: AssetInvestedSeries[];
}
```
b) étendre `defineProps` :
```ts
const props = defineProps<{ overview: PortfolioOverview; valuationSeries?: ValuationSeries; investedByAsset?: InvestedByAssetSeries }>();
```
c) après le bloc `valuationChartOptions` (après sa parenthèse fermante, vers la ligne ~114), ajouter :
```ts
const hasInvestedByAsset = computed<boolean>(() => (props.investedByAsset?.series.length ?? 0) > 0);

const investedByAssetSeries = computed(() =>
    (props.investedByAsset?.series ?? []).map((serie) => ({ name: serie.name, data: serie.invested })),
);

const investedByAssetOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    stroke: { curve: 'stepline', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.investedByAsset?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'bottom' },
}));
```

Dans le `<template>`, juste après la `</Card>` de la section « Évolution » (celle contenant `<Deferred data="valuationSeries">`, se termine vers la ligne ~150) et avant la `<section class="grid gap-4 sm:grid-cols-3">` des tuiles, insérer :
```vue
            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Investi par titre</CardTitle>
                    <CardDescription>Montant investi cumulé sur chaque titre</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="investedByAsset">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasInvestedByAsset"
                            type="line"
                            height="300"
                            :options="investedByAssetOptions"
                            :series="investedByAssetSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'investissement.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>
```

- [ ] **Step 6: Typecheck + build + re-run feature test**

Run: `bun run typecheck && bun run build && php artisan test --compact --filter=DashboardPageTest`
Expected: typecheck clean, build OK, tests PASS

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Http/DashboardController.php resources/js/Pages/Dashboard.vue tests/Feature/DashboardPageTest.php
git commit -m "feat: dashboard affiche l'investi cumule par titre (nouveau graphe)"
```

---

## Task 8: Vérification finale

- [ ] **Step 1: Suite complète**

Run: `php artisan test --compact`
Expected: tous verts (existants + nouveaux)

- [ ] **Step 2: Typecheck + build**

Run: `bun run typecheck && bun run build`
Expected: OK

- [ ] **Step 3: Vérif manuelle (Herd)**

`/dashboard` : sous « Évolution », la carte « Investi par titre » charge (après deferred) une ligne en escalier par titre, légende = noms. `/instruments/{id}` d'un titre détenu : sous « Cours », la carte « Valeur vs Investi » charge la courbe area 2 lignes. Titre soldé : sa ligne d'investi redescend vers ~0. Titre sans transaction : état vide.

---

## Self-Review

**1. Spec coverage :**
- Fiche valeur/investi (action `BuildAssetValuationSeries` + prop deferred + graphe) → Tasks 3, 6. ✓
- Dashboard investi par titre (calc `investedByAsset` + port noms + action + prop + graphe) → Tasks 2, 4, 5, 7. ✓
- DTOs → Task 1. ✓
- Port de nommage + adapter Market + wiring provider → Task 4. ✓
- Formule investi identique à `calculate` → Task 2 (copiée verbatim). ✓
- Tous titres transactés (même soldés) → `investedByAsset` itère `perAsset` (tout asset ayant une tx), pas seulement détenus. ✓
- Graphe lignes (pas d'aire empilée) → Task 7 `type: 'line'` stepline. ✓
- Deferred + skeleton + états vides français → Tasks 6, 7. ✓
- Fiche garde le graphe Cours (ajout, pas remplacement) → Task 6 insère APRÈS la carte Cours. ✓

**2. Placeholder scan :** aucun TODO/TBD. `name = '#'.$assetId` est un fallback intentionnel (documenté), pas un placeholder incomplet — l'action le remplace (Task 5) et le conserve si le nom manque.

**3. Type consistency :** `investedByAsset(): InvestedByAssetSeriesData` (Task 2) consommé par `BuildInvestedByAssetSeries` (Task 5) — champs `labels`/`series`/`AssetInvestedSeriesData{assetId,name,invested}` cohérents entre DTO (Task 1), calc (Task 2), action (Task 5), et interfaces TS (Task 7). `namesFor(array): array<int,string>` cohérent entre port (Task 4) et action (Task 5). `ValuationProvider::registers` gagne un 4ᵉ param — seul appelant `AppServiceProvider` mis à jour au même endroit (Task 4).

**Nuance connue :** `ValuationProvider::registers` signature change (4ᵉ param requis). Vérifié : le seul appelant est `AppServiceProvider` (mis à jour en Task 4). Si un test appelle `registers` directement, l'ajuster — non attendu.

## Dette différée (reportée de la spec)

- Formule d'investi dupliquée entre `calculate()` et `investedByAsset()` → extraire un accumulateur pur commun si un 3ᵉ usage apparaît.
- Couleurs des séries invested-par-titre non alignées avec les couleurs de type d'actif.
- Graphe valeur-par-titre côté dashboard (non demandé) = itération future.
