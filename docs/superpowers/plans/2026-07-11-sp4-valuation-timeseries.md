# SP4 — Valuation : courbe valeur dans le temps — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nouveau contexte `Valuation` qui reconstruit la valeur du portefeuille (valeur + investi) dans le temps, affichée sur le dashboard via une prop Inertia deferred avec skeleton.

**Architecture:** Ports Valuation (`TransactionHistoryPort`, `PriceHistoryPort`) + adapters anti-corruption (lisent Portfolio/Market) → `ValuationCalculator` pur → action `BuildPortfolioValuationSeries` → prop deferred consommée par `<Deferred>` dans `Dashboard.vue`.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Inertia v3 (deferred props), Vue 3, ApexCharts.

## Global Constraints

- TDD strict : rouge → vert → refactor à chaque tâche.
- PHP : accolades obligatoires, types de retour explicites, promotion de constructeur, PHPDoc (pas de commentaires inline).
- Eloquent seulement, pas de `DB::`. Relations avec return types.
- Après toute modif PHP : `vendor/bin/pint --dirty --format agent`.
- Tout texte visible utilisateur en français.
- Committer après chaque tâche.
- Contextes DDD sous `app/Contexts/`. Cross-contexte par **Port + adapter** uniquement (pas de relation Eloquent entre contextes). Pas de mot « plugins ».
- Ne PAS toucher `database/seeders/DatabaseSeeder.php`.
- Deferred prop : serveur `Inertia::defer(fn () => ...)` ; client `<Deferred data="...">` + slot `#fallback` ; test `->missing(...)` puis `->loadDeferredProps(fn (Assert $r) => $r->has(...))`.

---

### Task 1: DTOs `Valuation\Datas`

**Files:**
- Create: `app/Contexts/Valuation/Datas/TransactionRecordData.php`
- Create: `app/Contexts/Valuation/Datas/PriceRecordData.php`
- Create: `app/Contexts/Valuation/Datas/ValuationSeriesData.php`
- Test: `app/Contexts/Valuation/Datas/ValuationSeriesDataTest.php`

**Interfaces:**
- Produces:
  - `TransactionRecordData(Carbon $date, int $assetId, bool $isSell, float $quantity, float $unitPrice, float $fees)`
  - `PriceRecordData(int $assetId, string $date, float $close)`
  - `ValuationSeriesData(list<string> $labels, list<float> $valuations, list<float> $invested)` — `JsonSerializable` (keys `labels`/`valuations`/`invested`), static `empty(): self`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Valuation\Datas\ValuationSeriesData;

it('serializes to the expected json shape', function () {
    $series = new ValuationSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        valuations: [1000.0, 1200.0],
        invested: [1000.0, 1000.0],
    );

    expect($series->jsonSerialize())->toBe([
        'labels' => ['2026-01-01', '2026-02-01'],
        'valuations' => [1000.0, 1200.0],
        'invested' => [1000.0, 1000.0],
    ]);
});

it('builds an empty series', function () {
    $series = ValuationSeriesData::empty();

    expect($series->labels)->toBe([])
        ->and($series->valuations)->toBe([])
        ->and($series->invested)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ValuationSeriesDataTest`
Expected: FAIL (classes absentes).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Valuation/Datas/TransactionRecordData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

use Illuminate\Support\Carbon;

readonly class TransactionRecordData
{
    public function __construct(
        public Carbon $date,
        public int $assetId,
        public bool $isSell,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
    ) {}
}
```

`app/Contexts/Valuation/Datas/PriceRecordData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

readonly class PriceRecordData
{
    public function __construct(
        public int $assetId,
        public string $date,
        public float $close,
    ) {}
}
```

`app/Contexts/Valuation/Datas/ValuationSeriesData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class ValuationSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $valuations
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $valuations,
        public array $invested,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'valuations' => $this->valuations,
            'invested' => $this->invested,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ValuationSeriesDataTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Datas
git commit -m "feat: DTOs Valuation (transaction record, price record, series)"
```

---

### Task 2: `ValuationCalculator` (pur)

**Files:**
- Create: `app/Contexts/Valuation/Services/ValuationCalculator.php`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`

**Interfaces:**
- Consumes: DTOs (Task 1).
- Produces: `ValuationCalculator::calculate(list<TransactionRecordData> $transactions, list<PriceRecordData> $prices): ValuationSeriesData`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

function tx(string $date, int $assetId, bool $isSell, float $qty, float $price, float $fees = 0.0): TransactionRecordData
{
    return new TransactionRecordData(Carbon::parse($date), $assetId, $isSell, $qty, $price, $fees);
}

it('returns an empty series without transactions', function () {
    expect((new ValuationCalculator)->calculate([], []))->toEqual(
        \App\Contexts\Valuation\Datas\ValuationSeriesData::empty()
    );
});

it('values a single buy across two price dates', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 120)],
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('reduces value and invested after a sell', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 1, true, 4, 150)],
        [new PriceRecordData(1, '2026-01-01', 100), new PriceRecordData(1, '2026-02-01', 150)],
    );

    // day 1: qty 10 @100 = 1000, invested 1000
    // day 2: qty 6 @150 = 900, invested 1000 - (4 * PRU100) = 600
    expect($series->valuations)->toBe([1000.0, 900.0])
        ->and($series->invested)->toBe([1000.0, 600.0]);
});

it('ignores an asset that has no price', function () {
    $series = (new ValuationCalculator)->calculate(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 5, 50)],
        [new PriceRecordData(1, '2026-01-01', 100)], // only asset 1 priced
    );

    // asset 2 contributes 0 to value (no close), invested still counts both buys
    expect($series->valuations)->toBe([1000.0])
        ->and($series->invested)->toBe([1250.0]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ValuationCalculatorTest`
Expected: FAIL (calculateur absent).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Valuation/Services/ValuationCalculator.php` :

```php
<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;

class ValuationCalculator
{
    /**
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     */
    public function calculate(array $transactions, array $prices): ValuationSeriesData
    {
        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => $a->date <=> $b->date);

        /** @var array<int, list<array{date: string, value: float}>> $quantities */
        $quantities = [];
        /** @var list<array{date: string, value: float}> $investedSeries */
        $investedSeries = [];
        $buyQty = [];
        $buyCost = [];
        $totalInvested = 0.0;

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $quantities[$assetId] ??= [];

            $previous = end($quantities[$assetId]);
            $previousQty = $previous === false ? 0.0 : $previous['value'];
            $delta = $transaction->isSell ? -$transaction->quantity : $transaction->quantity;
            $quantities[$assetId][] = ['date' => $day, 'value' => $previousQty + $delta];

            if ($transaction->isSell) {
                $qty = $buyQty[$assetId] ?? 0.0;
                $cost = $buyCost[$assetId] ?? 0.0;
                $pru = $qty > 0.0 ? $cost / $qty : 0.0;
                $totalInvested -= $transaction->quantity * $pru - $transaction->fees;
            } else {
                $buyQty[$assetId] = ($buyQty[$assetId] ?? 0.0) + $transaction->quantity;
                $buyCost[$assetId] = ($buyCost[$assetId] ?? 0.0) + $transaction->quantity * $transaction->unitPrice;
                $totalInvested += $transaction->quantity * $transaction->unitPrice + $transaction->fees;
            }

            $investedSeries[] = ['date' => $day, 'value' => $totalInvested];
        }

        $days = collect($prices)->map(fn (PriceRecordData $p) => $p->date)->unique()->sort()->values()->all();
        $assetIds = array_keys($quantities);

        /** @var array<string, array<int, float>> $priceByDay */
        $priceByDay = [];
        foreach ($prices as $price) {
            $priceByDay[$price->date][$price->assetId] = $price->close;
        }

        $labels = [];
        $valuations = [];
        $invested = [];
        $lastClose = [];

        foreach ($days as $day) {
            $value = 0.0;
            foreach ($assetIds as $assetId) {
                if (isset($priceByDay[$day][$assetId])) {
                    $lastClose[$assetId] = $priceByDay[$day][$assetId];
                }
                $close = $lastClose[$assetId] ?? null;
                if ($close === null) {
                    continue;
                }
                $value += $this->valueAtDate($quantities[$assetId], $day) * $close;
            }

            $labels[] = $day;
            $valuations[] = round($value, 2);
            $invested[] = round($this->valueAtDate($investedSeries, $day), 2);
        }

        return new ValuationSeriesData($labels, $valuations, $invested);
    }

    /**
     * @param  list<array{date: string, value: float}>  $series
     */
    private function valueAtDate(array $series, string $day): float
    {
        $value = 0.0;
        foreach ($series as $entry) {
            if ($entry['date'] > $day) {
                break;
            }
            $value = $entry['value'];
        }

        return $value;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ValuationCalculatorTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Services
git commit -m "feat: ValuationCalculator pur (valeur et investi dans le temps)"
```

---

### Task 3: Ports + adapters + binding

**Files:**
- Create: `app/Contexts/Valuation/Ports/TransactionHistoryPort.php`
- Create: `app/Contexts/Valuation/Ports/PriceHistoryPort.php`
- Create: `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistory.php`
- Create: `app/Contexts/Valuation/Infrastructure/MarketPriceHistory.php`
- Create: `app/Contexts/Valuation/ValuationProvider.php`
- Modify: `app/Providers/AppServiceProvider.php` (appeler `ValuationProvider::registers`)
- Test: `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistoryTest.php`
- Test: `app/Contexts/Valuation/Infrastructure/MarketPriceHistoryTest.php`

**Interfaces:**
- Consumes: DTOs (Task 1), `Portfolio\Models\Transaction` + `Portfolio\Enums\TransactionType`, `Market\Contracts\PriceRepositoryContract` + `Market\Models\Price`.
- Produces:
  - `TransactionHistoryPort::forUser(int $userId): list<TransactionRecordData>` (triées date asc, asset_id non nul).
  - `PriceHistoryPort::forAssetsSince(array $assetIds, Carbon $since): list<PriceRecordData>`.
  - Bindings container : `TransactionHistoryPort` → `PortfolioTransactionHistory`, `PriceHistoryPort` → `MarketPriceHistory`.

- [ ] **Step 1: Write the failing tests**

`app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistoryTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

it('maps a user transactions to records ordered by date', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'fees' => 2, 'date' => '2026-01-01',
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 4, 'unit_price' => 150, 'date' => '2026-02-01',
    ]);

    $records = app(TransactionHistoryPort::class)->forUser($user->id);

    expect($records)->toHaveCount(2)
        ->and($records[0]->date->format('Y-m-d'))->toBe('2026-01-01')
        ->and($records[0]->isSell)->toBeFalse()
        ->and($records[0]->quantity)->toBe(10.0)
        ->and($records[0]->unitPrice)->toBe(100.0)
        ->and($records[0]->fees)->toBe(2.0)
        ->and($records[1]->isSell)->toBeTrue()
        ->and($records[1]->assetId)->toBe($asset->id);
});
```

`app/Contexts/Valuation/Infrastructure/MarketPriceHistoryTest.php` :

```php
<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

it('maps prices for the given assets since a date', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 120]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2025-01-01', 'close' => 50]); // before since

    $records = app(PriceHistoryPort::class)->forAssetsSince([$asset->id], Carbon::parse('2026-01-01'));

    expect($records)->toHaveCount(2)
        ->and($records[0]->assetId)->toBe($asset->id)
        ->and(collect($records)->pluck('close')->all())->toContain(100.0, 120.0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="PortfolioTransactionHistoryTest|MarketPriceHistoryTest"`
Expected: FAIL (ports/adapters absents, binding manquant).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Valuation/Ports/TransactionHistoryPort.php` :

```php
<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Valuation\Datas\TransactionRecordData;

interface TransactionHistoryPort
{
    /** @return list<TransactionRecordData> */
    public function forUser(int $userId): array;
}
```

`app/Contexts/Valuation/Ports/PriceHistoryPort.php` :

```php
<?php

namespace App\Contexts\Valuation\Ports;

use App\Contexts\Valuation\Datas\PriceRecordData;
use Illuminate\Support\Carbon;

interface PriceHistoryPort
{
    /**
     * @param  list<int>  $assetIds
     * @return list<PriceRecordData>
     */
    public function forAssetsSince(array $assetIds, Carbon $since): array;
}
```

`app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistory.php` :

```php
<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;

class PortfolioTransactionHistory implements TransactionHistoryPort
{
    /** @return list<TransactionRecordData> */
    public function forUser(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $transaction) => new TransactionRecordData(
                date: $transaction->date,
                assetId: (int) $transaction->asset_id,
                isSell: $transaction->type === TransactionType::Sell,
                quantity: (float) $transaction->quantity,
                unitPrice: (float) $transaction->unit_price,
                fees: (float) $transaction->fees,
            ))
            ->values()
            ->all();
    }
}
```

`app/Contexts/Valuation/Infrastructure/MarketPriceHistory.php` :

```php
<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

class MarketPriceHistory implements PriceHistoryPort
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /**
     * @param  list<int>  $assetIds
     * @return list<PriceRecordData>
     */
    public function forAssetsSince(array $assetIds, Carbon $since): array
    {
        return $this->prices->forAssets($assetIds, $since)
            ->map(fn (Price $price) => new PriceRecordData(
                assetId: (int) $price->asset_id,
                date: $price->date->format('Y-m-d'),
                close: (float) $price->close,
            ))
            ->values()
            ->all();
    }
}
```

`app/Contexts/Valuation/ValuationProvider.php` :

```php
<?php

namespace App\Contexts\Valuation;

use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ValuationProvider extends ServiceProvider
{
    /**
     * @param  class-string<TransactionHistoryPort>  $transactionHistory
     * @param  class-string<PriceHistoryPort>  $priceHistory
     */
    public static function registers(
        Application $app,
        string $transactionHistory,
        string $priceHistory,
    ): void {
        $app->bind(TransactionHistoryPort::class, $transactionHistory);
        $app->bind(PriceHistoryPort::class, $priceHistory);
    }
}
```

Dans `app/Providers/AppServiceProvider.php`, ajouter les imports et l'appel dans `register()` après le bloc `MarketProvider::registers(...)` :

```php
use App\Contexts\Valuation\Infrastructure\MarketPriceHistory;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;
use App\Contexts\Valuation\ValuationProvider;
```

```php
        ValuationProvider::registers(
            app: $this->app,
            transactionHistory: PortfolioTransactionHistory::class,
            priceHistory: MarketPriceHistory::class,
        );
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="PortfolioTransactionHistoryTest|MarketPriceHistoryTest"`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Ports app/Contexts/Valuation/Infrastructure app/Contexts/Valuation/ValuationProvider.php app/Providers/AppServiceProvider.php
git commit -m "feat: ports et adapters Valuation (transactions Portfolio, prix Market)"
```

---

### Task 4: Action `BuildPortfolioValuationSeries`

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildPortfolioValuationSeries.php`
- Test: `app/Contexts/Valuation/Actions/BuildPortfolioValuationSeriesTest.php`

**Interfaces:**
- Consumes: `TransactionHistoryPort`, `PriceHistoryPort`, `ValuationCalculator` (Tasks 2/3), DTOs.
- Produces: `BuildPortfolioValuationSeries::__invoke(int $userId): ValuationSeriesData` (résolvable via container ; ports bindés en Task 3).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;

it('builds a series from a user transactions and prices', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 120]);

    $series = app(BuildPortfolioValuationSeries::class)($user->id);

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01'])
        ->and($series->valuations)->toBe([1000.0, 1200.0])
        ->and($series->invested)->toBe([1000.0, 1000.0]);
});

it('returns an empty series when the user has no transactions', function () {
    $user = User::factory()->create();

    $series = app(BuildPortfolioValuationSeries::class)($user->id);

    expect($series->labels)->toBe([])
        ->and($series->valuations)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildPortfolioValuationSeriesTest`
Expected: FAIL (action absente).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Valuation/Actions/BuildPortfolioValuationSeries.php` :

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioValuationSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(int $userId): ValuationSeriesData
    {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $since = $transactions[0]->date;
        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $since);

        return $this->calculator->calculate($transactions, $prices);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=BuildPortfolioValuationSeriesTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Actions
git commit -m "feat: action BuildPortfolioValuationSeries"
```

---

### Task 5: Dashboard — prop deferred + courbe

**Files:**
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `tests/Feature/DashboardPageTest.php` (ajouter un test deferred)

**Interfaces:**
- Consumes: `BuildPortfolioValuationSeries` (Task 4), `ValuationSeriesData` (Task 1).
- Produces: prop deferred `valuationSeries` (forme `{labels, valuations, invested}`).

- [ ] **Step 1: Write the failing test**

Ajouter ce test à la fin de `tests/Feature/DashboardPageTest.php` (garder les tests existants). `User`, `InstrumentType`, `Instrument`, `Price`, `Wallet`, `Assert` sont déjà importés ; **ajouter l'import manquant** en tête :

```php
use App\Contexts\Portfolio\Models\Transaction;
```

```php
it('defers the valuation series and loads it on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('valuationSeries')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuationSeries.labels', 1)
                ->has('valuationSeries.valuations', 1)
            )
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL (prop `valuationSeries` absente même après `loadDeferredProps`).

- [ ] **Step 3: Write minimal implementation**

Dans `app/Contexts/Portfolio/Http/DashboardController.php`, ajouter les imports :

```php
use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
```

et remplacer le `return Inertia::render(...)` par :

```php
        return Inertia::render('Dashboard', [
            'overview' => $overview,
            'valuationSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioValuationSeries::class)($user->id)
                : ValuationSeriesData::empty()),
        ]);
```

Dans `resources/js/Pages/Dashboard.vue` :

Ajouter `Deferred` à l'import Inertia :

```ts
import { Deferred, Head } from '@inertiajs/vue3';
```

Ajouter l'interface et la rendre optionnelle dans les props (après l'interface `PortfolioOverview`) :

```ts
interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
}
```

Remplacer la ligne `const props = defineProps<{ overview: PortfolioOverview }>();` par :

```ts
const props = defineProps<{ overview: PortfolioOverview; valuationSeries?: ValuationSeries }>();
```

Ajouter, après `allocationOptions`, les computed de la courbe :

```ts
const hasValuation = computed<boolean>(() => (props.valuationSeries?.labels.length ?? 0) > 0);

const valuationChartSeries = computed(() => [
    { name: 'Valeur', data: props.valuationSeries?.valuations ?? [] },
    { name: 'Investi', data: props.valuationSeries?.invested ?? [] },
]);

const valuationChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        categories: props.valuationSeries?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
    legend: { position: 'top' },
}));
```

Dans le `<template>`, insérer cette carte juste après le `</header>` (avant la section des KPI) :

```html
            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Évolution</CardTitle>
                    <CardDescription>Valeur du portefeuille vs investi</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="valuationSeries">
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

- [ ] **Step 4: Run test + typecheck + build**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS (3 tests).

Run: `bun run typecheck`
Expected: clean.

Run: `bun run build`
Expected: succeeds, `Dashboard-*.js` chunk present.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Http/DashboardController.php resources/js/Pages/Dashboard.vue tests/Feature/DashboardPageTest.php
git commit -m "feat: dashboard courbe valeur dans le temps (prop deferred + skeleton)"
```

---

### Task 6: Seeder — historique de prix synthétique

**Files:**
- Modify: `database/seeders/DashboardDemoSeeder.php`
- Modify: `tests/Feature/DashboardDemoSeederTest.php`

**Interfaces:**
- Consumes: `Price` (Market), `BuildPortfolioValuationSeries` (Task 4) pour l'assertion end-to-end.

- [ ] **Step 1: Update the test first**

Ajouter ce test à `tests/Feature/DashboardDemoSeederTest.php` (garder les tests existants ; ajouter l'import `use App\Contexts\Market\Models\Price;` et `use App\Contexts\Valuation\Actions\BuildPortfolioValuationSeries;` en tête) :

```php
it('seeds a price history that yields a non-flat valuation curve', function () {
    User::query()->delete();
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    // multiple distinct price dates were seeded
    expect(Price::query()->distinct()->count('date'))->toBeGreaterThan(1);

    $series = app(BuildPortfolioValuationSeries::class)($user->id);

    // several points, and the curve actually moves
    expect(count($series->valuations))->toBeGreaterThan(1)
        ->and(count(array_unique($series->valuations)))->toBeGreaterThan(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardDemoSeederTest`
Expected: FAIL (le seeder ne crée qu'un prix par instrument → une seule date, courbe plate).

- [ ] **Step 3: Update the seeder**

Dans `database/seeders/DashboardDemoSeeder.php`, remplacer le bloc de création du prix unique :

```php
            if ($position['close'] !== null) {
                Price::query()->updateOrCreate(
                    ['asset_id' => $instrument->id, 'date' => today()],
                    [
                        'open' => $position['close'],
                        'high' => $position['close'],
                        'low' => $position['close'],
                        'close' => $position['close'],
                        'volume' => 0,
                    ],
                );
            }
```

par un appel à un helper :

```php
            if ($position['close'] !== null) {
                $this->seedPriceHistory($instrument->id, $position['buyPrice'], $position['close']);
            }
```

Dans le même fichier, backdater l'achat pour que la courbe couvre l'historique : remplacer, dans la création de la transaction d'achat, `'date' => today()->subMonth(),` par :

```php
                'date' => today()->subMonths(11),
```

(La vente NVDA reste à `today()->subDays(7)`, donc bien postérieure à l'achat.)

et ajouter cette méthode privée à la classe :

```php
    /**
     * Génère ~12 mois d'historique hebdomadaire, du prix d'achat vers le close actuel,
     * avec une légère ondulation, afin que la courbe de valorisation soit visible.
     */
    private function seedPriceHistory(int $assetId, float $start, float $end): void
    {
        $weeks = 52;
        $from = today()->subWeeks($weeks);

        for ($i = 0; $i <= $weeks; $i++) {
            $progress = $i / $weeks;
            $base = $start + ($end - $start) * $progress;
            $close = round($base * (1 + 0.03 * sin($i / 3.0)), 2);
            $date = $from->copy()->addWeeks($i);

            Price::query()->updateOrCreate(
                ['asset_id' => $assetId, 'date' => $date],
                ['open' => $close, 'high' => $close, 'low' => $close, 'close' => $close, 'volume' => 0],
            );
        }

        Price::query()->updateOrCreate(
            ['asset_id' => $assetId, 'date' => today()],
            ['open' => $end, 'high' => $end, 'low' => $end, 'close' => $end, 'volume' => 0],
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardDemoSeederTest`
Expected: PASS.

- [ ] **Step 5: Full suite + format + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add database/seeders/DashboardDemoSeeder.php tests/Feature/DashboardDemoSeederTest.php
git commit -m "feat: seeder genere un historique de prix pour la courbe de valorisation"
```

---

## Notes de vérification finale

- Après la dernière tâche : suite complète `php artisan test --compact`.
- Re-seed réel : `php artisan migrate:fresh --seed`, puis `argent.test/dashboard` — la carte « Évolution » affiche d'abord un skeleton pulsant puis la courbe valeur/investi.
- Les ports Valuation sont bindés dans `AppServiceProvider` via `ValuationProvider::registers` (même patron que `MarketProvider`).
- `ValuationCalculator` est pur (aucune dépendance DB) : testable sur données brutes ; les adapters font le pont vers Portfolio/Market.
- Le calcul de valorisation est déclenché en prop deferred : il ne pèse pas sur le render initial du dashboard.
