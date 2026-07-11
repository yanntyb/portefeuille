# SP3 — Transaction → projection holdings_projection — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Porter le domaine Transaction dans le contexte Portfolio et alimenter automatiquement `holdings_projection` (quantité + PRU) + `realized_gain`, via un Observer mince qui délègue à des Actions.

**Architecture:** `Transaction` (Eloquent, table `transactions`) observé par `TransactionObserver`. L'observer délègue à deux actions invokables : `CalculateRealizedGain` (gain sur ventes) et `ProjectHolding` (recalcule/upsert/delete la ligne `holdings_projection` via le modèle `Holding` existant). Le `DashboardDemoSeeder` est réécrit pour créer des transactions, prouvant la chaîne bout en bout.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Eloquent.

## Global Constraints

- Développement en TDD strict : test rouge → vert → refactor à chaque tâche.
- PHP : accolades obligatoires, types de retour explicites, promotion de constructeur, PHPDoc plutôt que commentaires inline.
- Eloquent seulement, pas de `DB::`. Relations avec return types.
- Après toute modif PHP : `vendor/bin/pint --dirty --format agent`.
- Tout texte visible utilisateur en français.
- Committer après chaque tâche.
- Contextes DDD sous `app/Contexts/Portfolio/`. Cross-contexte par id (pas de relation Eloquent vers `Market\Instrument`). Pas de mot « plugins ».
- Règles de calcul : `quantity = Σ achats.quantity − Σ ventes.quantity` ; `avg_cost = Σ(achats.quantity × achats.unit_price) ÷ Σ achats.quantity` ; `quantity <= 0` → pas de ligne ; `realized_gain = round((unit_price − PRU) × quantity − fees, 2)` avec PRU sur les achats de date ≤ celle de la vente ; `null` pour un achat.

---

### Task 1: Enum `TransactionType`

**Files:**
- Create: `app/Contexts/Portfolio/Enums/TransactionType.php`
- Test: `app/Contexts/Portfolio/Enums/TransactionTypeTest.php`

**Interfaces:**
- Produces: `TransactionType::Buy` (`'buy'`), `TransactionType::Sell` (`'sell'`), `values(): list<string>`, `getLabel(): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Portfolio\Enums\TransactionType;

it('exposes values and french labels', function () {
    expect(TransactionType::Buy->value)->toBe('buy')
        ->and(TransactionType::Sell->value)->toBe('sell')
        ->and(TransactionType::values())->toBe(['buy', 'sell'])
        ->and(TransactionType::Buy->getLabel())->toBe('Achat')
        ->and(TransactionType::Sell->getLabel())->toBe('Vente');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionTypeTest`
Expected: FAIL (enum absent).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Enums/TransactionType.php` :

```php
<?php

namespace App\Contexts\Portfolio\Enums;

enum TransactionType: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionTypeTest`
Expected: PASS.

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Enums/TransactionType.php app/Contexts/Portfolio/Enums/TransactionTypeTest.php
git commit -m "feat: enum TransactionType dans Portfolio"
```

---

### Task 2: Modèle `Transaction` + factory (sans observer)

L'attribut `#[ObservedBy]` est ajouté en Task 5. Ici le modèle est testé isolément.

**Files:**
- Create: `app/Contexts/Portfolio/Models/Transaction.php`
- Create: `app/Contexts/Portfolio/Factories/TransactionFactory.php`
- Test: `app/Contexts/Portfolio/Models/TransactionTest.php`

**Interfaces:**
- Consumes: `TransactionType` (Task 1), `Wallet`/`User` existants, `Instrument::factory()` pour `asset_id`.
- Produces: `Transaction` (table `transactions`, casts, relations `wallet()`/`user()`), `Transaction::factory()` avec états `buy()` / `sell()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('casts attributes and links wallet and user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    $transaction = Transaction::factory()->sell()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 5,
        'unit_price' => 100,
    ]);

    expect($transaction->type)->toBe(TransactionType::Sell)
        ->and((float) $transaction->quantity)->toBe(5.0)
        ->and($transaction->date)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($transaction->wallet->is($wallet))->toBeTrue()
        ->and($transaction->user->is($user))->toBeTrue();
});

it('defaults to a buy', function () {
    expect(Transaction::factory()->make()->type)->toBe(TransactionType::Buy);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionTest`
Expected: FAIL (modèle absent).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Models/Transaction.php` :

```php
<?php

namespace App\Contexts\Portfolio\Models;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property Carbon $date
 * @property ?int $asset_id
 * @property int $wallet_id
 * @property int $user_id
 * @property TransactionType $type
 * @property string $quantity
 * @property string $unit_price
 * @property string $fees
 * @property ?string $realized_gain
 */
#[UseFactory(TransactionFactory::class)]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $table = 'transactions';

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => TransactionType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'fees' => 'decimal:2',
            'realized_gain' => 'decimal:2',
        ];
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

`app/Contexts/Portfolio/Factories/TransactionFactory.php` :

```php
<?php

namespace App\Contexts\Portfolio\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'asset_id' => Instrument::factory(),
            'date' => now()->subMonth(),
            'type' => TransactionType::Buy,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'fees' => 0,
        ];
    }

    public function buy(): static
    {
        return $this->state(['type' => TransactionType::Buy]);
    }

    public function sell(): static
    {
        return $this->state(['type' => TransactionType::Sell]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Models/Transaction.php app/Contexts/Portfolio/Factories/TransactionFactory.php app/Contexts/Portfolio/Models/TransactionTest.php
git commit -m "feat: modele Transaction et factory dans Portfolio"
```

---

### Task 3: Action `ProjectHolding`

**Files:**
- Create: `app/Contexts/Portfolio/Actions/ProjectHolding.php`
- Test: `app/Contexts/Portfolio/Actions/ProjectHoldingTest.php`

**Interfaces:**
- Consumes: `Transaction` (Task 2), `Holding` (existant), `TransactionType`.
- Produces: `ProjectHolding::__invoke(int $userId, int $assetId, int $walletId): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

function seedTx(User $user, Wallet $wallet, Instrument $asset, string $type, float $qty, float $price): void
{
    Transaction::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'type' => $type,
        'quantity' => $qty,
        'unit_price' => $price,
    ]);
}

it('projects quantity and weighted average cost from buys and sells', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    seedTx($user, $wallet, $asset, 'buy', 10, 100);   // cost 1000
    seedTx($user, $wallet, $asset, 'buy', 10, 140);   // cost 1400
    seedTx($user, $wallet, $asset, 'sell', 5, 200);   // reduces qty only

    app(ProjectHolding::class)($user->id, $asset->id, $wallet->id);

    $holding = Holding::query()->where('asset_id', $asset->id)->where('wallet_id', $wallet->id)->first();
    expect((float) $holding->quantity)->toBe(15.0)          // 20 bought - 5 sold
        ->and((float) $holding->avg_cost)->toBe(120.0);     // 2400 / 20
});

it('removes the holding when everything is sold', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    seedTx($user, $wallet, $asset, 'buy', 10, 100);
    seedTx($user, $wallet, $asset, 'sell', 10, 120);

    app(ProjectHolding::class)($user->id, $asset->id, $wallet->id);

    expect(Holding::query()->where('asset_id', $asset->id)->where('wallet_id', $wallet->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProjectHoldingTest`
Expected: FAIL (action absente).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Actions/ProjectHolding.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

class ProjectHolding
{
    public function __invoke(int $userId, int $assetId, int $walletId): void
    {
        $transactions = Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->where('wallet_id', $walletId)
            ->get();

        $buys = $transactions->where('type', TransactionType::Buy);
        $sells = $transactions->where('type', TransactionType::Sell);

        $buyQty = (float) $buys->sum('quantity');
        $buyCost = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity * (float) $t->unit_price);
        $soldQty = (float) $sells->sum('quantity');

        $quantity = $buyQty - $soldQty;

        if ($quantity <= 0.0) {
            Holding::query()
                ->where('asset_id', $assetId)
                ->where('wallet_id', $walletId)
                ->delete();

            return;
        }

        Holding::query()->updateOrCreate(
            ['asset_id' => $assetId, 'wallet_id' => $walletId],
            [
                'user_id' => $userId,
                'quantity' => $quantity,
                'avg_cost' => $buyQty > 0.0 ? $buyCost / $buyQty : 0.0,
            ],
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ProjectHoldingTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Actions/ProjectHolding.php app/Contexts/Portfolio/Actions/ProjectHoldingTest.php
git commit -m "feat: action ProjectHolding (projection qty + PRU)"
```

---

### Task 4: Action `CalculateRealizedGain`

**Files:**
- Create: `app/Contexts/Portfolio/Actions/CalculateRealizedGain.php`
- Test: `app/Contexts/Portfolio/Actions/CalculateRealizedGainTest.php`

**Interfaces:**
- Consumes: `Transaction` (Task 2), `TransactionType`.
- Produces: `CalculateRealizedGain::__invoke(Transaction $transaction): ?float`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('returns null for a buy', function () {
    $buy = Transaction::factory()->buy()->make();

    expect(app(CalculateRealizedGain::class)($buy))->toBeNull();
});

it('computes realized gain against the average cost of prior buys', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => now()->subMonths(2),
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 140, 'date' => now()->subMonth(),
    ]);

    // PRU = (1000 + 1400) / 20 = 120
    $sell = Transaction::factory()->sell()->make([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 5, 'unit_price' => 200, 'fees' => 10, 'date' => now(),
    ]);

    // (200 - 120) * 5 - 10 = 390
    expect(app(CalculateRealizedGain::class)($sell))->toBe(390.0);
});

it('ignores buys made after the sell date', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => now()->subMonths(2),
    ]);
    Transaction::factory()->buy()->create([   // after the sell — must be excluded
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 300, 'date' => now(),
    ]);

    $sell = Transaction::factory()->sell()->make([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 5, 'unit_price' => 200, 'fees' => 0, 'date' => now()->subMonth(),
    ]);

    // PRU from prior buy only = 100 → (200 - 100) * 5 - 0 = 500
    expect(app(CalculateRealizedGain::class)($sell))->toBe(500.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CalculateRealizedGainTest`
Expected: FAIL (action absente).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Actions/CalculateRealizedGain.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

class CalculateRealizedGain
{
    public function __invoke(Transaction $transaction): ?float
    {
        if ($transaction->type !== TransactionType::Sell) {
            return null;
        }

        $buys = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('wallet_id', $transaction->wallet_id)
            ->where('asset_id', $transaction->asset_id)
            ->where('type', TransactionType::Buy)
            ->where('date', '<=', $transaction->date)
            ->get();

        $buyQty = (float) $buys->sum('quantity');
        $buyCost = (float) $buys->sum(fn (Transaction $t) => (float) $t->quantity * (float) $t->unit_price);
        $pru = $buyQty > 0.0 ? $buyCost / $buyQty : 0.0;

        return round(((float) $transaction->unit_price - $pru) * (float) $transaction->quantity - (float) $transaction->fees, 2);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CalculateRealizedGainTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Actions/CalculateRealizedGain.php app/Contexts/Portfolio/Actions/CalculateRealizedGainTest.php
git commit -m "feat: action CalculateRealizedGain (gain realise sur ventes)"
```

---

### Task 5: `TransactionObserver` + branchement

**Files:**
- Create: `app/Contexts/Portfolio/Observers/TransactionObserver.php`
- Modify: `app/Contexts/Portfolio/Models/Transaction.php` (ajouter l'attribut `#[ObservedBy]`)
- Test: `app/Contexts/Portfolio/Observers/TransactionProjectionTest.php`

**Interfaces:**
- Consumes: `CalculateRealizedGain` (Task 4), `ProjectHolding` (Task 3), `Transaction` (Task 2).
- Produces: `TransactionObserver` (creating/updating/created/updated/deleted) résolu par le container ; `Transaction` porte `#[ObservedBy(TransactionObserver::class)]`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create();
    $this->asset = Instrument::factory()->create();
});

function makeTx(object $ctx, string $type, float $qty, float $price, array $extra = []): Transaction
{
    return Transaction::factory()->create(array_merge([
        'user_id' => $ctx->user->id,
        'wallet_id' => $ctx->wallet->id,
        'asset_id' => $ctx->asset->id,
        'type' => $type,
        'quantity' => $qty,
        'unit_price' => $price,
    ], $extra));
}

it('projects a holding when a buy is recorded', function () {
    makeTx($this, 'buy', 10, 80);

    $holding = Holding::query()->where('asset_id', $this->asset->id)->where('wallet_id', $this->wallet->id)->first();
    expect((float) $holding->quantity)->toBe(10.0)
        ->and((float) $holding->avg_cost)->toBe(80.0);
});

it('reduces the projection and stores realized gain on a sell', function () {
    makeTx($this, 'buy', 10, 80, ['date' => now()->subMonth()]);
    $sell = makeTx($this, 'sell', 4, 100, ['fees' => 5, 'date' => now()]);

    $holding = Holding::query()->where('asset_id', $this->asset->id)->where('wallet_id', $this->wallet->id)->first();
    expect((float) $holding->quantity)->toBe(6.0)
        ->and((float) $sell->fresh()->realized_gain)->toBe(75.0); // (100-80)*4 - 5
});

it('deletes the projection when a delete empties the position', function () {
    $buy = makeTx($this, 'buy', 10, 80);
    expect(Holding::query()->where('asset_id', $this->asset->id)->exists())->toBeTrue();

    $buy->delete();

    expect(Holding::query()->where('asset_id', $this->asset->id)->exists())->toBeFalse();
});

it('reprojects the old and new wallet when a transaction moves', function () {
    $otherWallet = Wallet::factory()->for($this->user)->create();
    $buy = makeTx($this, 'buy', 10, 80);

    $buy->update(['wallet_id' => $otherWallet->id]);

    expect(Holding::query()->where('wallet_id', $this->wallet->id)->exists())->toBeFalse()
        ->and(Holding::query()->where('wallet_id', $otherWallet->id)->where('asset_id', $this->asset->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionProjectionTest`
Expected: FAIL (observer absent / projection non mise à jour).

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Observers/TransactionObserver.php` :

```php
<?php

namespace App\Contexts\Portfolio\Observers;

use App\Contexts\Portfolio\Actions\CalculateRealizedGain;
use App\Contexts\Portfolio\Actions\ProjectHolding;
use App\Contexts\Portfolio\Models\Transaction;

class TransactionObserver
{
    public function __construct(
        private CalculateRealizedGain $calculateRealizedGain,
        private ProjectHolding $projectHolding,
    ) {}

    public function creating(Transaction $transaction): void
    {
        $transaction->realized_gain = ($this->calculateRealizedGain)($transaction);
    }

    public function updating(Transaction $transaction): void
    {
        $transaction->realized_gain = ($this->calculateRealizedGain)($transaction);
    }

    public function created(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->project($transaction);

        if ($transaction->wasChanged('asset_id') || $transaction->wasChanged('wallet_id')) {
            $originalAssetId = $transaction->getOriginal('asset_id');
            if ($originalAssetId !== null) {
                ($this->projectHolding)(
                    (int) $transaction->getOriginal('user_id'),
                    (int) $originalAssetId,
                    (int) $transaction->getOriginal('wallet_id'),
                );
            }
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->project($transaction);
    }

    private function project(Transaction $transaction): void
    {
        if ($transaction->asset_id === null) {
            return;
        }

        ($this->projectHolding)($transaction->user_id, $transaction->asset_id, $transaction->wallet_id);
    }
}
```

Puis dans `app/Contexts/Portfolio/Models/Transaction.php`, ajouter l'import et l'attribut de classe :

```php
use App\Contexts\Portfolio\Observers\TransactionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
```

et au-dessus de la déclaration de classe, combiner avec l'attribut existant :

```php
#[ObservedBy(TransactionObserver::class)]
#[UseFactory(TransactionFactory::class)]
class Transaction extends Model
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionProjectionTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Full suite + format + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Observers/TransactionObserver.php app/Contexts/Portfolio/Models/Transaction.php app/Contexts/Portfolio/Observers/TransactionProjectionTest.php
git commit -m "feat: TransactionObserver branche projection et realized_gain"
```

---

### Task 6: Réécriture `DashboardDemoSeeder` via transactions

**Files:**
- Modify: `database/seeders/DashboardDemoSeeder.php`
- Modify: `tests/Feature/DashboardDemoSeederTest.php`

**Interfaces:**
- Consumes: `Transaction` + observer (Tasks 2/5), `Instrument`/`Price`, `Wallet`, `User`, `Holding`, `GetPortfolioOverview`.

- [ ] **Step 1: Update the test first**

Remplacer intégralement `tests/Feature/DashboardDemoSeederTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use Database\Seeders\DashboardDemoSeeder;

it('builds the demo portfolio from transactions', function () {
    User::query()->delete();
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    $overview = app(GetPortfolioOverview::class)($user);

    expect(Transaction::query()->count())->toBeGreaterThan(0)
        ->and(Holding::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0)
        ->and($overview->totalValue)->toBeGreaterThan(0.0)
        ->and($overview->allocation)->not->toBeEmpty();

    // a sell exists with a realized gain recorded
    expect(Transaction::query()->where('type', TransactionType::Sell)->whereNotNull('realized_gain')->exists())->toBeTrue();

    // the instrument without a price surfaces as a holding without market value
    $withoutPrice = collect($overview->holdings)->first(fn ($line) => $line->marketValue === null);
    expect($withoutPrice)->not->toBeNull();
});

it('is idempotent', function () {
    User::query()->delete();
    User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);
    $holdingCount = Holding::query()->count();
    $txCount = Transaction::query()->count();

    $this->seed(DashboardDemoSeeder::class);

    expect(Holding::query()->count())->toBe($holdingCount)
        ->and(Transaction::query()->count())->toBe($txCount);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardDemoSeederTest`
Expected: FAIL (le seeder crée encore des `Holding` en direct, aucune `Transaction`).

- [ ] **Step 3: Rewrite the seeder**

Remplacer intégralement `database/seeders/DashboardDemoSeeder.php` :

```php
<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Database\Seeder;

class DashboardDemoSeeder extends Seeder
{
    /**
     * Positions de démonstration exprimées en transactions.
     *
     * `close` null = prix indisponible. `sellQty` > 0 ajoute une vente
     * (exerce le calcul du gain réalisé et la réduction de position).
     *
     * @var list<array{name: string, ticker: string, type: InstrumentType, close: float|null, buyQty: float, buyPrice: float, sellQty: float, sellPrice: float}>
     */
    private const POSITIONS = [
        ['name' => 'Apple Inc.', 'ticker' => 'AAPL', 'type' => InstrumentType::Stock, 'close' => 220.0, 'buyQty' => 15, 'buyPrice' => 150.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'NVIDIA Corp.', 'ticker' => 'NVDA', 'type' => InstrumentType::Stock, 'close' => 120.0, 'buyQty' => 50, 'buyPrice' => 140.0, 'sellQty' => 10, 'sellPrice' => 130.0],
        ['name' => 'Amundi PEA S&P 500 UCITS ETF', 'ticker' => 'AMS', 'type' => InstrumentType::ETF, 'close' => 45.0, 'buyQty' => 200, 'buyPrice' => 30.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'Bitcoin', 'ticker' => 'BTC', 'type' => InstrumentType::Crypto, 'close' => 58000.0, 'buyQty' => 0.3, 'buyPrice' => 62000.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'OAT France 2032', 'ticker' => 'OAT32', 'type' => InstrumentType::Bond, 'close' => 98.0, 'buyQty' => 50, 'buyPrice' => 100.0, 'sellQty' => 0, 'sellPrice' => 0.0],
        ['name' => 'Ethereum', 'ticker' => 'ETH', 'type' => InstrumentType::Crypto, 'close' => null, 'buyQty' => 2, 'buyPrice' => 3000.0, 'sellQty' => 0, 'sellPrice' => 0.0],
    ];

    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        $wallet = Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'name' => 'Démo',
        ]);

        // Idempotence : purge (mass delete ne déclenche pas l'observer), puis reconstruit.
        Transaction::query()->where('wallet_id', $wallet->id)->delete();
        Holding::query()->where('wallet_id', $wallet->id)->delete();

        foreach (self::POSITIONS as $position) {
            $instrument = Instrument::query()->firstOrCreate(
                ['ticker' => $position['ticker']],
                ['name' => $position['name'], 'type' => $position['type']],
            );

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

            Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'asset_id' => $instrument->id,
                'type' => TransactionType::Buy,
                'date' => today()->subMonth(),
                'quantity' => $position['buyQty'],
                'unit_price' => $position['buyPrice'],
                'fees' => 0,
            ]);

            if ($position['sellQty'] > 0) {
                Transaction::query()->create([
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'asset_id' => $instrument->id,
                    'type' => TransactionType::Sell,
                    'date' => today()->subDays(7),
                    'quantity' => $position['sellQty'],
                    'unit_price' => $position['sellPrice'],
                    'fees' => 0,
                ]);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardDemoSeederTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Full suite + format + commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add database/seeders/DashboardDemoSeeder.php tests/Feature/DashboardDemoSeederTest.php
git commit -m "refactor: DashboardDemoSeeder construit le portefeuille via des transactions"
```

---

## Notes de vérification finale

- Après la dernière tâche : suite complète `php artisan test --compact`.
- Re-seed réel de la démo : `php artisan db:seed --class=DashboardDemoSeeder`, puis vérifier `argent.test/dashboard` (valeur/positions/donut dérivés des transactions, NVDA à qty 40 après vente, ETH en « N/D »).
- L'observer est résolu via le container (injection des actions) : aucune inscription manuelle nécessaire grâce à `#[ObservedBy]`.
- Ordre des tâches : le modèle `Transaction` (Task 2) est créé SANS `#[ObservedBy]` ; l'attribut n'est ajouté qu'en Task 5, une fois l'observer et ses actions en place — sinon référence à une classe inexistante.
