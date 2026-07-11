# Dashboard — câblage positions courantes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer les données de démo du dashboard par les vraies positions du portefeuille, calculées côté serveur et transmises en props Inertia typées.

**Architecture:** Read models Eloquent (`Wallet`, `Holding`) sur les tables existantes → action invokable `GetPortfolioOverview` qui agrège valeur/coût/gain et répartition par type → controller invokable fin qui délègue à l'action → props Inertia consommées par `Dashboard.vue`.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Inertia v3 + Vue 3.5 + TS, shadcn-vue, ApexCharts.

## Global Constraints

- Développement en TDD strict : test rouge → vert → refactor, à chaque tâche.
- PHP : accolades obligatoires, types de retour explicites, promotion de constructeur, PHPDoc plutôt que commentaires inline.
- Eloquent seulement, pas de `DB::`. Relations avec return types.
- Après toute modif PHP : `vendor/bin/pint --dirty --format agent`.
- Tout texte visible utilisateur en français.
- Committer après chaque tâche.
- Contextes DDD sous `app/Contexts/<Context>/`. Pas de mot « plugins ».
- Tests : `php artisan test --compact --filter=<...>`.

---

### Task 1: Read model `Wallet` + factory

Nécessaire pour satisfaire la FK `holdings_projection.wallet_id` lors du seed.

**Files:**
- Create: `app/Contexts/Portfolio/Models/Wallet.php`
- Create: `app/Contexts/Portfolio/Factories/WalletFactory.php`
- Test: `app/Contexts/Portfolio/Models/WalletTest.php`

**Interfaces:**
- Consumes: `App\Contexts\Identity\Models\User` (existant).
- Produces: `Wallet` (table `wallets`, `belongsTo user`), `Wallet::factory()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Wallet;

it('belongs to a user and persists a name', function () {
    $user = User::factory()->create();

    $wallet = Wallet::factory()->for($user)->create(['name' => 'PEA']);

    expect($wallet->name)->toBe('PEA')
        ->and($wallet->user->is($user))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=WalletTest`
Expected: FAIL (class `Wallet` not found).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Models/Wallet.php` :

```php
<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property int $user_id
 * @property string $name
 */
#[UseFactory(WalletFactory::class)]
class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $table = 'wallets';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Contexts/Portfolio/Factories/WalletFactory.php` :

```php
<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=WalletTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Models/Wallet.php app/Contexts/Portfolio/Factories/WalletFactory.php app/Contexts/Portfolio/Models/WalletTest.php
git commit -m "feat: read model Wallet et factory dans Portfolio"
```

---

### Task 2: Read model `Holding` + factory

**Files:**
- Create: `app/Contexts/Portfolio/Models/Holding.php`
- Create: `app/Contexts/Portfolio/Factories/HoldingFactory.php`
- Test: `app/Contexts/Portfolio/Models/HoldingTest.php`

**Interfaces:**
- Consumes: `Wallet::factory()` (Task 1), `App\Contexts\Market\Models\Instrument`, `App\Contexts\Identity\Models\User`.
- Produces: `Holding` (table `holdings_projection`, `$incrementing = false`, `$primaryKey = 'asset_id'`, casts `quantity`/`avg_cost` en `decimal:4`, relations `asset()`/`wallet()`/`user()`), `Holding::factory()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('links a holding to its asset, wallet and user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);

    $holding = Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    expect((float) $holding->quantity)->toBe(10.0)
        ->and($holding->asset->name)->toBe('ACME')
        ->and($holding->wallet->is($wallet))->toBeTrue()
        ->and($holding->user->is($user))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HoldingTest`
Expected: FAIL (class `Holding` not found).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Models/Holding.php` :

```php
<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Factories\HoldingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $asset_id
 * @property int $wallet_id
 * @property int $user_id
 * @property string $quantity
 * @property ?string $avg_cost
 */
#[UseFactory(HoldingFactory::class)]
class Holding extends Model
{
    /** @use HasFactory<HoldingFactory> */
    use HasFactory;

    protected $table = 'holdings_projection';

    public $incrementing = false;

    protected $primaryKey = 'asset_id';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'avg_cost' => 'decimal:4',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'asset_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`app/Contexts/Portfolio/Factories/HoldingFactory.php` :

```php
<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holding>
 */
class HoldingFactory extends Factory
{
    protected $model = Holding::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'asset_id' => Instrument::factory(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'avg_cost' => fake()->randomFloat(4, 10, 500),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=HoldingTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Models/Holding.php app/Contexts/Portfolio/Factories/HoldingFactory.php app/Contexts/Portfolio/Models/HoldingTest.php
git commit -m "feat: read model Holding et factory sur holdings_projection"
```

---

### Task 3: DTOs de l'overview

**Files:**
- Create: `app/Contexts/Portfolio/Datas/HoldingLineData.php`
- Create: `app/Contexts/Portfolio/Datas/AllocationSliceData.php`
- Create: `app/Contexts/Portfolio/Datas/PortfolioOverviewData.php`
- Test: `app/Contexts/Portfolio/Datas/PortfolioOverviewDataTest.php`

**Interfaces:**
- Consumes: `App\Contexts\Market\Enums\InstrumentType` (existant).
- Produces:
  - `HoldingLineData(string $assetName, ?string $ticker, InstrumentType $type, float $quantity, ?float $avgCost, ?float $lastPrice, ?float $marketValue, ?float $gain, ?float $gainPct)` — implémente `JsonSerializable`, clés JSON : `assetName, ticker, type, typeLabel, quantity, avgCost, lastPrice, marketValue, gain, gainPct`.
  - `AllocationSliceData(string $label, float $value, float $pct, string $color)` — `JsonSerializable`, clés : `label, value, pct, color`.
  - `PortfolioOverviewData(float $totalValue, float $totalCost, float $totalGain, float $totalGainPct, array $holdings, array $allocation)` — `JsonSerializable`, clés : `totalValue, totalCost, totalGain, totalGainPct, holdings, allocation`. Méthode statique `empty(): self`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;

it('serializes an overview to the expected json shape', function () {
    $overview = new PortfolioOverviewData(
        totalValue: 1000.0,
        totalCost: 800.0,
        totalGain: 200.0,
        totalGainPct: 25.0,
        holdings: [new HoldingLineData(
            assetName: 'ACME',
            ticker: 'ACM',
            type: InstrumentType::Stock,
            quantity: 10.0,
            avgCost: 80.0,
            lastPrice: 100.0,
            marketValue: 1000.0,
            gain: 200.0,
            gainPct: 25.0,
        )],
        allocation: [new AllocationSliceData(label: 'Stock', value: 1000.0, pct: 100.0, color: '#4f46e5')],
    );

    $json = $overview->jsonSerialize();

    expect($json['totalValue'])->toBe(1000.0)
        ->and($json['holdings'][0]['assetName'])->toBe('ACME')
        ->and($json['holdings'][0]['type'])->toBe('stock')
        ->and($json['holdings'][0]['typeLabel'])->toBe('Stock')
        ->and($json['allocation'][0]['color'])->toBe('#4f46e5');
});

it('builds an empty overview', function () {
    $overview = PortfolioOverviewData::empty();

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([])
        ->and($overview->allocation)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PortfolioOverviewDataTest`
Expected: FAIL (classes DTO absentes).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Datas/HoldingLineData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

readonly class HoldingLineData implements JsonSerializable
{
    public function __construct(
        public string $assetName,
        public ?string $ticker,
        public InstrumentType $type,
        public float $quantity,
        public ?float $avgCost,
        public ?float $lastPrice,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetName' => $this->assetName,
            'ticker' => $this->ticker,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'quantity' => $this->quantity,
            'avgCost' => $this->avgCost,
            'lastPrice' => $this->lastPrice,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
```

`app/Contexts/Portfolio/Datas/AllocationSliceData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class AllocationSliceData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $value,
        public float $pct,
        public string $color,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'pct' => $this->pct,
            'color' => $this->color,
        ];
    }
}
```

`app/Contexts/Portfolio/Datas/PortfolioOverviewData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class PortfolioOverviewData implements JsonSerializable
{
    /**
     * @param  list<HoldingLineData>  $holdings
     * @param  list<AllocationSliceData>  $allocation
     */
    public function __construct(
        public float $totalValue,
        public float $totalCost,
        public float $totalGain,
        public float $totalGainPct,
        public array $holdings,
        public array $allocation,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalCost' => $this->totalCost,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'holdings' => array_map(fn (HoldingLineData $h) => $h->jsonSerialize(), $this->holdings),
            'allocation' => array_map(fn (AllocationSliceData $a) => $a->jsonSerialize(), $this->allocation),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=PortfolioOverviewDataTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Datas
git commit -m "feat: DTOs PortfolioOverview (overview, ligne, allocation)"
```

---

### Task 4: Action `GetPortfolioOverview`

Le cœur du calcul. TDD sur les cas : valeur/coût/gain, agrégats, répartition par type, prix manquant.

**Files:**
- Create: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`
- Test: `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php`

**Interfaces:**
- Consumes: `Holding` (Task 2), les DTOs (Task 3), `App\Contexts\Market\Contracts\PriceRepositoryContract::latestForAsset(int $id): ?Price` (existant, `Price->close` string), `Instrument`/`InstrumentType`, `PriceFactory` (`close` en attribut).
- Produces: `GetPortfolioOverview` invokable, `__invoke(User $user): PortfolioOverviewData`, résolvable via le container (le binding `PriceRepositoryContract` existe déjà dans `AppServiceProvider`).

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

function makeHolding(User $user, InstrumentType $type, float $close, float $qty, float $avgCost): Instrument
{
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $qty,
        'avg_cost' => $avgCost,
    ]);

    return $asset;
}

it('computes value, cost and gain for a single holding', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->totalCost)->toBe(800.0)
        ->and($overview->totalGain)->toBe(200.0)
        ->and($overview->totalGainPct)->toBe(25.0)
        ->and($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->marketValue)->toBe(1000.0)
        ->and($overview->holdings[0]->gainPct)->toBe(25.0);
});

it('aggregates allocation by instrument type', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 6, avgCost: 50);   // 600
    makeHolding($user, InstrumentType::Crypto, close: 100, qty: 4, avgCost: 50);  // 400

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->allocation)->toHaveCount(2);

    $byLabel = collect($overview->allocation)->keyBy('label');
    expect($byLabel['Stock']->pct)->toBe(60.0)
        ->and($byLabel['Cryptocurrency']->pct)->toBe(40.0);
});

it('excludes a holding without a known price from totals and allocation', function () {
    $user = User::factory()->create();
    makeHolding($user, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80); // valued
    $wallet = Wallet::factory()->for($user)->create();
    $noPrice = Instrument::factory()->ofType(InstrumentType::Bond)->create();
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $noPrice->id,
        'quantity' => 5,
        'avg_cost' => 90,
    ]);

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->allocation)->toHaveCount(1)
        ->and($overview->holdings)->toHaveCount(2);

    $bondLine = collect($overview->holdings)->firstWhere('type', InstrumentType::Bond);
    expect($bondLine->lastPrice)->toBeNull()
        ->and($bondLine->marketValue)->toBeNull();
});

it('returns an empty overview when the user has no holdings', function () {
    $user = User::factory()->create();

    $overview = app(GetPortfolioOverview::class)($user);

    expect($overview->totalValue)->toBe(0.0)
        ->and($overview->holdings)->toBe([])
        ->and($overview->allocation)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetPortfolioOverviewTest`
Expected: FAIL (classe `GetPortfolioOverview` absente).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\AllocationSliceData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;

class GetPortfolioOverview
{
    public function __construct(private PriceRepositoryContract $prices) {}

    public function __invoke(User $user): PortfolioOverviewData
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->get();

        $lines = [];
        $totalValue = 0.0;
        $totalCost = 0.0;
        /** @var array<string, float> $valueByType */
        $valueByType = [];

        foreach ($holdings as $holding) {
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;

            $price = $this->prices->latestForAsset($holding->asset_id);
            $lastPrice = $price !== null ? (float) $price->close : null;

            $marketValue = $lastPrice !== null ? $quantity * $lastPrice : null;
            $cost = $avgCost !== null ? $quantity * $avgCost : null;
            $gain = ($marketValue !== null && $cost !== null) ? $marketValue - $cost : null;
            $gainPct = ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;

            $lines[] = new HoldingLineData(
                assetName: $holding->asset->name,
                ticker: $holding->asset->ticker,
                type: $holding->asset->type,
                quantity: $quantity,
                avgCost: $avgCost,
                lastPrice: $lastPrice,
                marketValue: $marketValue,
                gain: $gain,
                gainPct: $gainPct,
            );

            if ($marketValue !== null) {
                $totalValue += $marketValue;
                if ($cost !== null) {
                    $totalCost += $cost;
                }
                $key = $holding->asset->type->value;
                $valueByType[$key] = ($valueByType[$key] ?? 0.0) + $marketValue;
            }
        }

        $totalGain = $totalValue - $totalCost;
        $totalGainPct = $totalCost > 0.0 ? $totalGain / $totalCost * 100 : 0.0;

        $allocation = [];
        foreach ($valueByType as $typeValue => $value) {
            $type = InstrumentType::from($typeValue);
            $allocation[] = new AllocationSliceData(
                label: $type->getLabel(),
                value: $value,
                pct: $totalValue > 0.0 ? $value / $totalValue * 100 : 0.0,
                color: $this->hexColorFor($type),
            );
        }

        return new PortfolioOverviewData(
            totalValue: $totalValue,
            totalCost: $totalCost,
            totalGain: $totalGain,
            totalGainPct: $totalGainPct,
            holdings: $lines,
            allocation: $allocation,
        );
    }

    private function hexColorFor(InstrumentType $type): string
    {
        return match ($type) {
            InstrumentType::Stock => '#4f46e5',
            InstrumentType::ETF => '#0ea5e9',
            InstrumentType::Crypto => '#f59e0b',
            InstrumentType::Bond => '#8b5cf6',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GetPortfolioOverviewTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Actions
git commit -m "feat: action GetPortfolioOverview (valeur, gain, allocation par type)"
```

---

### Task 5: Controller invokable + route + feature test

**Files:**
- Create: `app/Contexts/Portfolio/Http/DashboardController.php`
- Modify: `routes/web.php` (remplacer la closure `/dashboard`)
- Modify: `tests/Feature/DashboardPageTest.php` (étendre)

**Interfaces:**
- Consumes: `GetPortfolioOverview` (Task 4), `PortfolioOverviewData::empty()` (Task 3), `User`.
- Produces: route nommée `dashboard` → `DashboardController` invokable, prop Inertia `overview`.

- [ ] **Step 1: Write the failing test**

Remplacer intégralement `tests/Feature/DashboardPageTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the Dashboard with an empty overview when there is no data', function () {
    User::factory()->create();

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 0.0)
            ->has('overview.holdings', 0)
            ->has('overview.allocation', 0)
        );
});

it('renders the Dashboard with the user portfolio overview', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', fn ($value) => (float) $value === 1000.0)
            ->where('overview.totalGain', fn ($value) => (float) $value === 200.0)
            ->has('overview.holdings', 1)
            ->has('overview.allocation', 1)
            ->where('overview.holdings.0.assetName', 'ACME')
        );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL (controller absent / prop `overview` absente).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Http/DashboardController.php` :

```php
<?php

namespace App\Contexts\Portfolio\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(private GetPortfolioOverview $getPortfolioOverview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user)
            : PortfolioOverviewData::empty();

        return Inertia::render('Dashboard', [
            'overview' => $overview,
        ]);
    }
}
```

Dans `routes/web.php`, ajouter l'import en tête :

```php
use App\Contexts\Portfolio\Http\DashboardController;
```

et remplacer :

```php
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');
```

par :

```php
Route::get('/dashboard', DashboardController::class)->name('dashboard');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Http/DashboardController.php routes/web.php tests/Feature/DashboardPageTest.php
git commit -m "feat: DashboardController invokable delegue a GetPortfolioOverview"
```

---

### Task 6: Frontend — brancher `Dashboard.vue` sur `overview`

Remplace les données de démo. Retire la courbe valeur-temps (phase 2), garde le donut, ajoute une table des positions (shadcn Table). Vérification par `typecheck` + `build` (pas de framework de test JS ici).

**Files:**
- Create (via CLI): `resources/js/components/ui/table/*`
- Modify: `resources/js/Pages/Dashboard.vue`

**Interfaces:**
- Consumes: prop Inertia `overview` (forme JSON de `PortfolioOverviewData`, clés camelCase).

- [ ] **Step 1: Ajouter le composant Table shadcn**

Run: `bunx --bun shadcn-vue@latest add table -y -o`
Expected: crée `resources/js/components/ui/table/…` (Table, TableHeader, TableBody, TableRow, TableHead, TableCell, …).

- [ ] **Step 2: Réécrire `resources/js/Pages/Dashboard.vue`**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import type { ApexOptions } from 'apexcharts';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

interface HoldingLine {
    assetName: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
    quantity: number;
    avgCost: number | null;
    lastPrice: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
}

interface AllocationSlice {
    label: string;
    value: number;
    pct: number;
    color: string;
}

interface PortfolioOverview {
    totalValue: number;
    totalCost: number;
    totalGain: number;
    totalGainPct: number;
    holdings: HoldingLine[];
    allocation: AllocationSlice[];
}

const props = defineProps<{ overview: PortfolioOverview }>();

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });

const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

const hasAllocation = computed<boolean>(() => props.overview.allocation.length > 0);

const allocationSeries = computed<number[]>(() => props.overview.allocation.map((slice) => slice.value));

const allocationOptions = computed<ApexOptions>(() => ({
    chart: { fontFamily: 'inherit' },
    labels: props.overview.allocation.map((slice) => slice.label),
    colors: props.overview.allocation.map((slice) => slice.color),
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val: number): string => `${Math.round(Number(val))}%` },
    stroke: { width: 0 },
    tooltip: { y: { formatter: (val: number): string => eur(val) } },
}));
</script>

<template>
    <Head title="Tableau de bord" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header>
                <h1 class="text-2xl font-semibold">Tableau de bord</h1>
                <p class="text-sm text-muted-foreground">Suivi de vos investissements</p>
            </header>

            <section class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardDescription>Valeur totale</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(overview.totalValue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Gains / pertes</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ eur(overview.totalGain) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Rendement</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(overview.totalGain)">
                            {{ pct(overview.totalGainPct) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Positions</CardTitle>
                        <CardDescription>Détail de vos lignes</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="overview.holdings.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Actif</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead class="text-right">Quantité</TableHead>
                                    <TableHead class="text-right">Dernier prix</TableHead>
                                    <TableHead class="text-right">Valeur</TableHead>
                                    <TableHead class="text-right">+/-</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="line in overview.holdings" :key="line.assetName + line.ticker">
                                    <TableCell class="font-medium">
                                        {{ line.assetName }}
                                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                    </TableCell>
                                    <TableCell>{{ line.typeLabel }}</TableCell>
                                    <TableCell class="text-right">{{ line.quantity }}</TableCell>
                                    <TableCell class="text-right">
                                        <span v-if="line.lastPrice === null" class="text-muted-foreground">prix indisponible</span>
                                        <span v-else>{{ eur(line.lastPrice) }}</span>
                                    </TableCell>
                                    <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                                    <TableCell class="text-right" :class="gainClass(line.gain)">{{ pct(line.gainPct) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Aucune position pour le moment.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Répartition</CardTitle>
                        <CardDescription>Par type d'actif</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <VueApexCharts
                            v-if="hasAllocation"
                            type="donut"
                            height="300"
                            :options="allocationOptions"
                            :series="allocationSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas de données de répartition.
                        </p>
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
</template>
```

- [ ] **Step 3: Vérifier le typecheck**

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 4: Vérifier le build**

Run: `bun run build`
Expected: build OK, chunk `Dashboard-*.js` présent.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ui/table resources/js/Pages/Dashboard.vue
git commit -m "feat: dashboard branche sur overview reel (KPI, positions, donut)"
```

---

## Notes de vérification finale

- Après la dernière tâche, lancer la suite complète : `php artisan test --compact`.
- Pour voir des données réelles dans le navigateur (`argent.test/dashboard`), il faut au moins un user + un wallet + un instrument avec prix + un holding. Un seeder de démo dédié n'est PAS dans ce plan (hors périmètre) — utiliser Tinker ou un seeder existant si besoin de visualiser.
- N+1 assumé sur `latestForAsset` (un appel par position) : acceptable au vu du faible nombre de positions en v1 ; à optimiser si nécessaire plus tard.
