# Liquidités — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner à l'application un solde d'espèces par enveloppe, pour qu'une vente cesse de faire disparaître son produit des courbes et que « Investi » devienne les apports nets.

**Architecture:** Trois nouveaux types sur `transactions` (`deposit`, `withdrawal`, `dividend`) et deux colonnes (`amount`, `auto`). Le solde n'est jamais stocké : un service pur `Portfolio\Services\CashLedger` le dérive des mouvements, produit les versements manquants pour tenir l'invariant « un solde n'est jamais négatif », et impute chaque euro à son origine. `TransactionObserver` régénère en grappe les lignes `auto`, comme il le fait déjà pour `realized_gain`. `ValuationCalculator` gagne une composante `cash`, et `Wealth` une classe « Liquidités ».

**Tech Stack:** Laravel 12 / PHP 8.5, Pest, Inertia v3 + Vue 3, Vitest, Tailwind v4, ECharts.

**Spec:** `docs/superpowers/specs/2026-09-01-liquidites-design.md`

## Global Constraints

- **Français partout** dans l'interface : libellés, messages de validation, textes d'aide.
- **Une seule migration par table.** Les colonnes nouvelles se posent dans la migration de création (`2026_08_19_000005_create_transactions_table.php`), jamais dans une migration d'ajout. Après modification : `php artisan migrate:fresh` puis les seeders voulus (`BackupSeeder` n'est pas appelé par `DatabaseSeeder`).
- **Pint** après toute modification PHP : `vendor/bin/pint --dirty --format agent`.
- **Tests** : `php artisan test --compact --filter=<nom>`. JS : `bun run test:js`.
- **`Services/` est pur** : ni Eloquent, ni port, ni conteneur. Entrées nues ou Datas du contexte, test co-localisé construit avec `new`, sans `RefreshDatabase`.
- **`$guarded = ['id']`** sur `Transaction` : les actions écrivent un tableau littéral explicite, jamais `create($validated)`.
- **Commit après chaque tâche**, message en français, type conventionnel (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`).
- **Ne pas réordonner les fixtures** de `tests/Pest.php` ni de `SnapshotInvariantTest` : l'`id` des transactions entre dans le hash. Ajouter en fin de fixture seulement.
- **`SnapshotInvariantTest` échouera** dès la tâche 11 et jusqu'à la tâche 14, où son hash est régénéré une fois, délibérément.

---

### Task 1 : schéma, types et fabrique

**Files:**
- Modify: `database/migrations/2026_08_19_000005_create_transactions_table.php`
- Modify: `app/Contexts/Portfolio/Enums/TransactionType.php`
- Modify: `app/Contexts/Portfolio/Enums/TransactionTypeTest.php`
- Modify: `app/Contexts/Portfolio/Models/Transaction.php`
- Modify: `app/Contexts/Portfolio/Factories/TransactionFactory.php`
- Test: `tests/Feature/CashTransactionSchemaTest.php` (créer)

**Interfaces:**
- Produces: `TransactionType::Deposit`, `TransactionType::Withdrawal`, `TransactionType::Dividend` ; colonnes `transactions.amount` (`decimal(12,2)`, nullable) et `transactions.auto` (`boolean`, défaut `false`, indexée) ; états de fabrique `deposit()`, `withdrawal()`, `dividend()`, `auto()`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/CashTransactionSchemaTest.php` :

```php
<?php

use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

it('enregistre un versement sans actif ni quantité', function () {
    $deposit = Transaction::factory()->deposit()->create(['amount' => 1000]);

    expect($deposit->type)->toBe(TransactionType::Deposit)
        ->and($deposit->asset_id)->toBeNull()
        ->and($deposit->quantity)->toBeNull()
        ->and($deposit->unit_price)->toBeNull()
        ->and((float) $deposit->amount)->toBe(1000.0)
        ->and($deposit->auto)->toBeFalse();
});

it('marque une ligne déduite par le système', function () {
    expect(Transaction::factory()->deposit()->auto()->create(['amount' => 500])->auto)->toBeTrue();
});

it('enregistre un dividende porté par son actif', function () {
    $dividend = Transaction::factory()->dividend()->create(['amount' => 42.5]);

    expect($dividend->type)->toBe(TransactionType::Dividend)
        ->and($dividend->asset_id)->not->toBeNull()
        ->and((float) $dividend->amount)->toBe(42.5);
});
```

Et dans `app/Contexts/Portfolio/Enums/TransactionTypeTest.php`, ajouter :

```php
it('libelle les mouvements d\'espèces en français', function () {
    expect(TransactionType::Deposit->getLabel())->toBe('Versement')
        ->and(TransactionType::Withdrawal->getLabel())->toBe('Retrait')
        ->and(TransactionType::Dividend->getLabel())->toBe('Dividende');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CashTransactionSchema`
Expected: FAIL — `Call to undefined method ...::deposit()`

- [ ] **Step 3: Write minimal implementation**

Dans la migration, à l'intérieur du `Schema::create`, après `realized_gain` :

```php
$table->decimal('amount', 12, 2)->nullable();
$table->boolean('auto')->default(false)->index();
```

Compléter le PHPDoc de la migration, dans le style du fichier :

```php
 * `amount` porte le montant des mouvements d'espèces — versement, retrait, dividende — que
 * `quantity × unit_price` ne sait pas exprimer. Elle n'est jamais signée : le sens vient du
 * type, sans quoi un retrait de -200 € saisi par erreur se comporterait comme un versement.
 *
 * `auto` distingue une ligne déduite par le système d'une ligne saisie. Les versements que
 * `RecomputeCashDeposits` écrit pour financer un achat sont réécrits à chaque correction ;
 * une ligne saisie ne l'est jamais.
```

Dans `TransactionType` :

```php
case Deposit = 'deposit';
case Withdrawal = 'withdrawal';
case Dividend = 'dividend';
```

et dans `getLabel()` :

```php
self::Deposit => 'Versement',
self::Withdrawal => 'Retrait',
self::Dividend => 'Dividende',
```

Dans `Transaction`, ajouter au PHPDoc `@property ?string $amount` et `@property bool $auto`, puis aux casts :

```php
'amount' => 'decimal:2',
'auto' => 'boolean',
```

Dans `TransactionFactory` :

```php
public function deposit(): static
{
    return $this->state([
        'type' => TransactionType::Deposit,
        'asset_id' => null,
        'quantity' => null,
        'unit_price' => null,
        'amount' => fake()->randomFloat(2, 100, 5000),
    ]);
}

public function withdrawal(): static
{
    return $this->deposit()->state(['type' => TransactionType::Withdrawal]);
}

public function dividend(): static
{
    return $this->state([
        'type' => TransactionType::Dividend,
        'quantity' => null,
        'unit_price' => null,
        'amount' => fake()->randomFloat(2, 1, 200),
    ]);
}

/** Une ligne déduite par le système, que le recalcul réécrit. */
public function auto(): static
{
    return $this->state(['auto' => true]);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan migrate:fresh --seed && php artisan test --compact --filter="CashTransactionSchema|TransactionType"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: les mouvements d'espèces ont leur type et leur montant"
```

---

### Task 2 : le signe du flux

**Files:**
- Modify: `app/Contexts/Portfolio/Services/TransactionFlow.php`
- Test: `app/Contexts/Portfolio/Services/TransactionFlowTest.php`

**Interfaces:**
- Consumes: `TransactionType` (Task 1).
- Produces: `TransactionFlow::cashDelta(TransactionType $type, ?float $quantity, ?float $unitPrice, float $fees, ?float $amount): float` — signé, positif quand l'argent entre.

- [ ] **Step 1: Write the failing test**

Ajouter à `TransactionFlowTest.php` :

```php
use App\Contexts\Portfolio\Enums\TransactionType;

it('sort le montant d\'un achat, frais compris', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Buy, 10.0, 100.0, 5.0, null))->toBe(-1005.0);
});

it('fait entrer le produit d\'une vente, net de frais', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Sell, 10.0, 100.0, 5.0, null))->toBe(995.0);
});

it('fait entrer un versement et un dividende', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Deposit, null, null, 0.0, 1000.0))->toBe(1000.0)
        ->and((new TransactionFlow)->cashDelta(TransactionType::Dividend, null, null, 0.0, 42.5))->toBe(42.5);
});

it('sort un retrait, quel que soit le signe saisi', function () {
    expect((new TransactionFlow)->cashDelta(TransactionType::Withdrawal, null, null, 0.0, 200.0))->toBe(-200.0)
        ->and((new TransactionFlow)->cashDelta(TransactionType::Withdrawal, null, null, 0.0, -200.0))->toBe(-200.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TransactionFlow`
Expected: FAIL — `Call to undefined method ...::cashDelta()`

- [ ] **Step 3: Write minimal implementation**

```php
/**
 * Le mouvement d'espèces d'une opération, signé : positif quand l'argent entre sur le compte,
 * négatif quand il en sort. Site unique du signe, comme `of()` est celui du montant.
 *
 * La valeur absolue est prise sur `$amount` : `amount` n'est jamais signée en base, et un
 * montant négatif saisi par erreur ne doit pas retourner le sens de l'opération.
 */
public function cashDelta(TransactionType $type, ?float $quantity, ?float $unitPrice, float $fees, ?float $amount): float
{
    return match ($type) {
        TransactionType::Buy => -$this->of((float) $quantity, (float) $unitPrice, $fees, false),
        TransactionType::Sell => $this->of((float) $quantity, (float) $unitPrice, $fees, true),
        TransactionType::Deposit, TransactionType::Dividend => abs((float) $amount),
        TransactionType::Withdrawal => -abs((float) $amount),
    };
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=TransactionFlow`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: le flux d'une opération porte son signe"
```

---

### Task 3 : `CashLedger` — solde et invariant

**Files:**
- Create: `app/Contexts/Portfolio/Services/CashLedger.php`
- Create: `app/Contexts/Portfolio/Services/CashLedgerTest.php`
- Create: `app/Contexts/Portfolio/Datas/CashMovementData.php`

**Interfaces:**
- Consumes: rien (service pur).
- Produces:
  - `CashMovementData` (readonly) : `string $date` (`Y-m-d`), `int $walletId`, `float $delta`, `?AssetClass $exposure`, `bool $isDeposit`, `bool $auto`, `?int $transactionId`.
  - `CashLedger::balanceAt(array $movements, int $walletId, string $date): float`
  - `CashLedger::missingDeposits(array $movements): list<array{walletId: int, date: string, amount: float}>`

Le service ne connaît que des `CashMovementData` : la lecture Eloquent viendra en tâche 5.

- [ ] **Step 1: Write the failing test**

`app/Contexts/Portfolio/Services/CashLedgerTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Services\CashLedger;

function movement(string $date, float $delta, int $walletId = 1, ?AssetClass $exposure = null, bool $isDeposit = false): CashMovementData
{
    return new CashMovementData(
        date: $date,
        walletId: $walletId,
        delta: $delta,
        exposure: $exposure,
        isDeposit: $isDeposit,
        auto: false,
        transactionId: null,
    );
}

it('somme les mouvements d\'une enveloppe jusqu\'à une date', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-10', -400.0),
        movement('2026-03-10', -100.0),
    ];

    expect((new CashLedger)->balanceAt($movements, 1, '2026-02-15'))->toBe(600.0);
});

it('ignore les mouvements d\'une autre enveloppe', function () {
    $movements = [
        movement('2026-01-10', 1000.0, walletId: 1, isDeposit: true),
        movement('2026-01-11', 5000.0, walletId: 2, isDeposit: true),
    ];

    expect((new CashLedger)->balanceAt($movements, 1, '2026-12-31'))->toBe(1000.0);
});

it('déduit le versement manquant d\'un achat non financé', function () {
    expect((new CashLedger)->missingDeposits([movement('2026-03-03', -1000.0)]))
        ->toBe([['walletId' => 1, 'date' => '2026-03-03', 'amount' => 1000.0]]);
});

it('ne déduit rien quand le cash existant couvre l\'achat', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-03-03', -600.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))->toBe([]);
});

it('ne déduit que le manque quand le cash couvre en partie', function () {
    $movements = [
        movement('2026-01-10', 400.0, isDeposit: true),
        movement('2026-03-03', -1000.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))
        ->toBe([['walletId' => 1, 'date' => '2026-03-03', 'amount' => 600.0]]);
});

it('laisse une vente financer un achat postérieur', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-01', -1000.0),
        movement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        movement('2026-08-15', -1200.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))->toBe([]);
});

it('déduit par enveloppe, sans jamais financer l\'une par l\'autre', function () {
    $movements = [
        movement('2026-01-10', 1000.0, walletId: 1, isDeposit: true),
        movement('2026-02-01', -300.0, walletId: 2),
    ];

    expect((new CashLedger)->missingDeposits($movements))
        ->toBe([['walletId' => 2, 'date' => '2026-02-01', 'amount' => 300.0]]);
});

it('tient l\'invariant : jamais de solde négatif une fois les versements déduits', function () {
    $movements = [
        movement('2026-03-03', -453.0),
        movement('2026-03-17', -248.25),
        movement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        movement('2026-09-01', -2000.0),
    ];

    $ledger = new CashLedger;
    $complete = [...$movements];

    foreach ($ledger->missingDeposits($movements) as $deposit) {
        $complete[] = movement($deposit['date'], $deposit['amount'], $deposit['walletId'], isDeposit: true);
    }

    foreach (['2026-03-03', '2026-03-17', '2026-08-01', '2026-09-01'] as $day) {
        expect($ledger->balanceAt($complete, 1, $day))->toBeGreaterThanOrEqual(0.0);
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CashLedger`
Expected: FAIL — `Class "App\Contexts\Portfolio\Services\CashLedger" not found`

- [ ] **Step 3: Write minimal implementation**

`app/Contexts/Portfolio/Datas/CashMovementData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Market\Enums\AssetClass;

/**
 * Un mouvement d'espèces tel que `CashLedger` le lit : une date, une enveloppe, un montant signé,
 * et de quoi dire d'où vient l'argent qui entre.
 *
 * `exposure` n'est portée que par un crédit issu du marché — vente ou dividende. Un versement
 * n'expose à rien : c'est un apport, et `isDeposit` le dit.
 */
readonly class CashMovementData
{
    public function __construct(
        public string $date,
        public int $walletId,
        public float $delta,
        public ?AssetClass $exposure,
        public bool $isDeposit,
        public bool $auto,
        public ?int $transactionId,
    ) {}
}
```

`app/Contexts/Portfolio/Services/CashLedger.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\CashMovementData;

/**
 * Le compte espèces d'une enveloppe : ce qu'elle détient en liquide à une date, et ce qu'il a
 * fallu y verser pour que chaque achat soit financé.
 *
 * Rien n'est stocké — un solde est une somme de mouvements, comme l'investi est une somme
 * d'achats. Le service reste pur : il ne lit que des `CashMovementData`, jamais la base.
 *
 * Invariant qui gouverne tout : le solde d'une enveloppe n'est négatif à aucune date de son
 * historique. Un achat que le cash ne couvre pas a été financé par un apport ; `missingDeposits()`
 * rend cet apport, que `RecomputeCashDeposits` écrit.
 */
class CashLedger
{
    /**
     * Le solde d'une enveloppe au soir d'une date.
     *
     * @param  list<CashMovementData>  $movements
     */
    public function balanceAt(array $movements, int $walletId, string $date): float
    {
        $balance = 0.0;

        foreach ($movements as $movement) {
            if ($movement->walletId === $walletId && $movement->date <= $date) {
                $balance += $movement->delta;
            }
        }

        return round($balance, 2);
    }

    /**
     * Les versements qu'il faut déduire pour qu'aucun solde ne passe négatif, dans l'ordre
     * chronologique. Un seul passage par enveloppe : le manque se comble à la date où il apparaît,
     * donc un versement déduit finance aussi tout ce qui suit tant qu'il reste du solde.
     *
     * @param  list<CashMovementData>  $movements
     * @return list<array{walletId: int, date: string, amount: float}>
     */
    public function missingDeposits(array $movements): array
    {
        $balances = [];
        $deposits = [];

        foreach ($this->chronological($movements) as $movement) {
            $balance = ($balances[$movement->walletId] ?? 0.0) + $movement->delta;

            if ($balance < 0.0) {
                $missing = round(-$balance, 2);
                $deposits[] = [
                    'walletId' => $movement->walletId,
                    'date' => $movement->date,
                    'amount' => $missing,
                ];
                $balance = 0.0;
            }

            $balances[$movement->walletId] = round($balance, 2);
        }

        return $deposits;
    }

    /**
     * Les crédits avant les débits à date égale : un achat et la vente qui le finance saisis le
     * même jour ne doivent pas déduire un versement pour un manque d'un instant.
     *
     * @param  list<CashMovementData>  $movements
     * @return list<CashMovementData>
     */
    private function chronological(array $movements): array
    {
        usort($movements, fn (CashMovementData $a, CashMovementData $b): int => ($a->date <=> $b->date)
            ?: (($a->delta < 0 ? 1 : 0) <=> ($b->delta < 0 ? 1 : 0)));

        return $movements;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CashLedger`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: le solde d'espèces d'une enveloppe et son invariant"
```

---

### Task 4 : `CashLedger` — imputation d'origine et apports nets

**Files:**
- Modify: `app/Contexts/Portfolio/Services/CashLedger.php`
- Modify: `app/Contexts/Portfolio/Services/CashLedgerTest.php`

**Interfaces:**
- Produces:
  - `CashLedger::compositionAt(array $movements, string $date): array{deposits: float, exposures: array<string, float>}` — de quoi le cash restant est fait, toutes enveloppes confondues, clé = `AssetClass::value`.
  - `CashLedger::netContributions(array $movements): array{total: float, byExposure: array<string, float>}` — versements moins retraits, imputés à l'exposition de l'achat financé.

- [ ] **Step 1: Write the failing test**

Ajouter à `CashLedgerTest.php` — la fonction `movement()` gagne un paramètre pour l'exposition d'un débit :

```php
it('impute le cash restant à l\'origine qui l\'a produit', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        movement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
    ];

    expect((new CashLedger)->compositionAt($movements, '2026-12-31'))
        ->toBe(['deposits' => 0.0, 'exposures' => ['equity' => 1285.5]]);
});

it('consomme les crédits du plus ancien au plus récent', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-01', 500.0, exposure: AssetClass::Equity),
        movement('2026-03-01', -1200.0, exposure: AssetClass::Crypto),
    ];

    expect((new CashLedger)->compositionAt($movements, '2026-12-31'))
        ->toBe(['deposits' => 0.0, 'exposures' => ['equity' => 300.0]]);
});

it('ne crée aucun apport quand une vente finance un rachat', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        movement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        movement('2026-08-15', -1200.0, exposure: AssetClass::Equity),
    ];

    expect((new CashLedger)->netContributions($movements))
        ->toBe(['total' => 1000.0, 'byExposure' => ['equity' => 1000.0]]);
});

it('impute l\'apport à l\'exposition de l\'achat qu\'il finance', function () {
    $movements = [
        movement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        movement('2026-03-01', -500.0, exposure: AssetClass::Crypto),
    ];

    $deposits = (new CashLedger)->missingDeposits($movements);
    $complete = [...$movements];

    foreach ($deposits as $deposit) {
        $complete[] = movement($deposit['date'], $deposit['amount'], $deposit['walletId'], isDeposit: true);
    }

    expect((new CashLedger)->netContributions($complete))
        ->toBe(['total' => 1500.0, 'byExposure' => ['equity' => 1000.0, 'crypto' => 500.0]]);
});

it('retranche les retraits des apports nets', function () {
    $movements = [
        movement('2026-01-10', 1000.0, isDeposit: true),
        movement('2026-02-01', -300.0, isWithdrawal: true),
    ];

    expect((new CashLedger)->netContributions($movements)['total'])->toBe(700.0);
});
```

`CashMovementData` gagne `bool $isWithdrawal` ; mettre à jour la fonction `movement()` du fichier de test en conséquence (paramètre nommé, défaut `false`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=CashLedger`
Expected: FAIL — `Call to undefined method ...::compositionAt()`

- [ ] **Step 3: Write minimal implementation**

Ajouter `public bool $isWithdrawal = false` à `CashMovementData` (dernier paramètre, pour ne casser aucun appel positionnel), puis à `CashLedger` :

```php
/**
 * De quoi le cash restant est fait : ce qui vient d'un apport, et ce qui vient de chaque
 * exposition. Un débit consomme les crédits du plus ancien au plus récent — la lecture par
 * exposition d'une page se lit ici, jamais sur un second solde.
 *
 * @param  list<CashMovementData>  $movements
 * @return array{deposits: float, exposures: array<string, float>}
 */
public function compositionAt(array $movements, string $date): array
{
    /** @var list<array{exposure: ?string, amount: float}> $credits */
    $credits = [];

    foreach ($this->chronological($movements) as $movement) {
        if ($movement->date > $date) {
            continue;
        }

        if ($movement->delta >= 0.0) {
            $credits[] = [
                'exposure' => $movement->exposure?->value,
                'amount' => $movement->delta,
            ];

            continue;
        }

        $this->consume($credits, -$movement->delta);
    }

    $deposits = 0.0;
    $exposures = [];

    foreach ($credits as $credit) {
        if ($credit['amount'] <= 0.0) {
            continue;
        }

        if ($credit['exposure'] === null) {
            $deposits = round($deposits + $credit['amount'], 2);

            continue;
        }

        $exposures[$credit['exposure']] = round(($exposures[$credit['exposure']] ?? 0.0) + $credit['amount'], 2);
    }

    return ['deposits' => $deposits, 'exposures' => $exposures];
}

/**
 * Ce que le porteur a réellement sorti de sa poche : versements moins retraits. L'imputation
 * suit l'achat financé — réinvestir le produit d'une vente ne crée aucun apport, le sortir
 * vers une autre exposition déplace celui d'origine.
 *
 * @param  list<CashMovementData>  $movements
 * @return array{total: float, byExposure: array<string, float>}
 */
public function netContributions(array $movements): array
{
    /** @var list<array{exposure: ?string, amount: float}> $credits */
    $credits = [];
    $total = 0.0;
    $byExposure = [];

    foreach ($this->chronological($movements) as $movement) {
        if ($movement->delta >= 0.0) {
            $credits[] = ['exposure' => $movement->isDeposit ? null : $movement->exposure?->value, 'amount' => $movement->delta];

            if ($movement->isDeposit) {
                $total = round($total + $movement->delta, 2);
            }

            continue;
        }

        $spent = $this->consume($credits, -$movement->delta);

        if ($movement->isWithdrawal) {
            $total = round($total - $spent['deposits'], 2);

            continue;
        }

        if ($movement->exposure !== null && $spent['deposits'] > 0.0) {
            $key = $movement->exposure->value;
            $byExposure[$key] = round(($byExposure[$key] ?? 0.0) + $spent['deposits'], 2);
        }
    }

    return ['total' => $total, 'byExposure' => $byExposure];
}

/**
 * Épuise les crédits les plus anciens à hauteur du débit, et rend ce qui a été pris à un apport
 * plutôt qu'à une exposition.
 *
 * @param  list<array{exposure: ?string, amount: float}>  $credits
 * @return array{deposits: float}
 */
private function consume(array &$credits, float $debit): array
{
    $fromDeposits = 0.0;

    foreach ($credits as $index => $credit) {
        if ($debit <= 0.0) {
            break;
        }

        $taken = min($credit['amount'], $debit);
        $credits[$index]['amount'] = round($credit['amount'] - $taken, 2);
        $debit = round($debit - $taken, 2);

        if ($credit['exposure'] === null) {
            $fromDeposits = round($fromDeposits + $taken, 2);
        }
    }

    return ['deposits' => $fromDeposits];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=CashLedger`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: chaque euro de liquidité garde son origine"
```

---

### Task 5 : lecture des mouvements et régénération des lignes déduites

**Files:**
- Create: `app/Contexts/Portfolio/Actions/GetCashMovements.php`
- Create: `app/Contexts/Portfolio/Actions/GetCashMovementsTest.php`
- Create: `app/Contexts/Portfolio/Actions/RecomputeCashDeposits.php`
- Create: `app/Contexts/Portfolio/Actions/RecomputeCashDepositsTest.php`
- Modify: `app/Contexts/Portfolio/Observers/TransactionObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (lier `GetCashMovements` en `scoped`, sur le modèle de `GetPortfolioOverview`)

**Interfaces:**
- Consumes: `CashLedger` (Tasks 3-4), `CashMovementData`, `TransactionFlow::cashDelta()`.
- Produces:
  - `GetCashMovements::__invoke(int $userId): list<CashMovementData>` — mémoïsé par utilisateur pour la durée de la requête.
  - `RecomputeCashDeposits::__invoke(int $userId, int $walletId): void`

- [ ] **Step 1: Write the failing test**

`app/Contexts/Portfolio/Actions/RecomputeCashDepositsTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);
});

function buy(float $quantity, float $price, string $date = '2026-03-03'): Transaction
{
    return Transaction::factory()->buy()->create([
        'user_id' => test()->user->id,
        'wallet_id' => test()->wallet->id,
        'asset_id' => test()->asset->id,
        'date' => $date,
        'quantity' => $quantity,
        'unit_price' => $price,
        'fees' => 0,
    ]);
}

function deposits(): \Illuminate\Support\Collection
{
    return Transaction::query()->where('type', TransactionType::Deposit)->orderBy('date')->get();
}

it('déduit un versement du montant d\'un achat non financé', function () {
    buy(10, 100);

    expect(deposits())->toHaveCount(1)
        ->and((float) deposits()->first()->amount)->toBe(1000.0)
        ->and(deposits()->first()->auto)->toBeTrue()
        ->and(deposits()->first()->date->format('Y-m-d'))->toBe('2026-03-03');
});

it('réécrit le versement déduit quand l\'achat change de montant', function () {
    $transaction = buy(10, 100);
    $transaction->update(['quantity' => 12]);

    expect(deposits())->toHaveCount(1)
        ->and((float) deposits()->first()->amount)->toBe(1200.0);
});

it('efface le versement déduit quand l\'achat disparaît', function () {
    buy(10, 100)->delete();

    expect(deposits())->toHaveCount(0);
});

it('ne touche jamais un versement saisi à la main', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 10000,
        'auto' => false,
    ]);

    buy(10, 100);

    expect(deposits())->toHaveCount(1)
        ->and(deposits()->first()->auto)->toBeFalse()
        ->and((float) deposits()->first()->amount)->toBe(10000.0);
});

it('ne déduit rien quand une vente antérieure finance l\'achat', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 1000,
        'auto' => false,
    ]);

    buy(10, 100, '2026-01-02');
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'asset_id' => $this->asset->id,
        'date' => '2026-02-01',
        'quantity' => 10,
        'unit_price' => 150,
        'fees' => 0,
    ]);
    buy(10, 140, '2026-03-01');

    expect(deposits()->where('auto', true))->toHaveCount(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=RecomputeCashDeposits`
Expected: FAIL — aucun versement déduit n'existe (`toHaveCount(1)` reçoit `0`)

- [ ] **Step 3: Write minimal implementation**

`GetCashMovements` lit les transactions de l'utilisateur, jointes à `assets` pour l'exposition, et les traduit en `CashMovementData` via `TransactionFlow::cashDelta()`. Elle exclut les lignes `auto = true` quand `$includeAuto` vaut `false` — c'est ce que `RecomputeCashDeposits` demande pour repartir des seuls faits saisis :

```php
public function __invoke(int $userId, bool $includeAuto = true): array
```

`RecomputeCashDeposits` :

```php
/**
 * Les versements déduits d'une enveloppe, réécrits d'un bloc.
 *
 * Même raison que `RecomputeRealizedGains` : une ligne déduite est la conséquence d'un achat,
 * donc tout achat corrigé, déplacé ou supprimé la rend fausse. On efface ce que le système a
 * écrit, on rejoue l'historique des seuls faits saisis, et on réécrit ce qui manque encore.
 *
 * Les lignes saisies à la main (`auto = false`) ne sont jamais touchées : saisir après coup un
 * vrai virement fait donc disparaître de lui-même le versement déduit qu'il couvre.
 *
 * Écriture par `saveQuietly()` / `withoutEvents()` : l'observateur se rappellerait sans fin.
 */
public function __invoke(int $userId, int $walletId): void
```

Corps complet :

```php
public function __invoke(int $userId, int $walletId): void
{
    Transaction::withoutEvents(function () use ($userId, $walletId): void {
        Transaction::query()
            ->where('user_id', $userId)
            ->where('wallet_id', $walletId)
            ->where('auto', true)
            ->delete();

        /** Les faits saisis seuls : repartir des lignes déduites reconduirait les périmées. */
        $movements = ($this->movements)($userId, includeAuto: false);

        foreach ($this->ledger->missingDeposits($movements) as $deposit) {
            if ($deposit['walletId'] !== $walletId) {
                continue;
            }

            /** Tableau littéral : `$guarded = ['id']` laisserait tout passer en assignation de masse. */
            Transaction::query()->create([
                'user_id' => $userId,
                'wallet_id' => $walletId,
                'asset_id' => null,
                'date' => $deposit['date'],
                'type' => TransactionType::Deposit,
                'quantity' => null,
                'unit_price' => null,
                'fees' => 0,
                'amount' => $deposit['amount'],
                'auto' => true,
            ]);
        }
    });
}
```

`GetCashMovements` étant mémoïsée par utilisateur pour la durée de la requête, elle doit oublier son cache après une écriture : lui donner une méthode `forget(int $userId): void` que `RecomputeCashDeposits` appelle avant sa lecture, sans quoi une correction se recalculerait contre l'état d'avant.

Dans `TransactionObserver::project()`, après `RecomputeRealizedGains`, appeler `($this->recomputeCashDeposits)($transaction->user_id, $transaction->wallet_id)`. Attention : `project()` sort tôt quand `asset_id` est nul — déplacer ce garde-fou pour que les espèces se recalculent aussi sur un versement saisi, la position et le gain restant conditionnés à l'actif. Le bloc `updated()` qui traite le déplacement d'enveloppe recalcule aussi l'enveloppe d'origine.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="RecomputeCashDeposits|GetCashMovements|TransactionProjection"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: les versements manquants se déduisent et se réécrivent en grappe"
```

---

### Task 6 : saisie — règles par type et plafond de retrait

**Files:**
- Modify: `app/Contexts/Portfolio/Http/TransactionRequest.php`
- Modify: `app/Contexts/Portfolio/Datas/TransactionInputData.php`
- Modify: `app/Contexts/Portfolio/Actions/CreateTransaction.php`
- Modify: `app/Contexts/Portfolio/Actions/UpdateTransaction.php`
- Test: `app/Contexts/Portfolio/Http/StoreTransactionControllerTest.php`

**Interfaces:**
- Produces: `TransactionInputData` gagne `?int $assetId`, `?float $quantity`, `?float $unitPrice`, `float $amount`.

- [ ] **Step 1: Write the failing test**

Ajouter à `StoreTransactionControllerTest.php` :

```php
it('enregistre un versement sans actif ni quantité', function () {
    $this->actingAs($this->user)
        ->post('/transactions', [
            'walletId' => $this->wallet->id,
            'date' => '2026-03-01',
            'type' => 'deposit',
            'amount' => 1000,
        ])
        ->assertRedirect();

    expect(Transaction::query()->where('type', TransactionType::Deposit)->where('auto', false)->count())->toBe(1);
});

it('refuse un versement sans montant', function () {
    $this->actingAs($this->user)
        ->post('/transactions', [
            'walletId' => $this->wallet->id,
            'date' => '2026-03-01',
            'type' => 'deposit',
        ])
        ->assertSessionHasErrors('amount');
});

it('refuse un achat sans quantité', function () {
    $this->actingAs($this->user)
        ->post('/transactions', [
            'walletId' => $this->wallet->id,
            'assetId' => $this->asset->id,
            'date' => '2026-03-01',
            'type' => 'buy',
            'unitPrice' => 100,
        ])
        ->assertSessionHasErrors('quantity');
});

it('refuse un retrait supérieur au solde de l\'enveloppe', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'date' => '2026-01-01',
        'amount' => 500,
    ]);

    $this->actingAs($this->user)
        ->post('/transactions', [
            'walletId' => $this->wallet->id,
            'date' => '2026-03-01',
            'type' => 'withdrawal',
            'amount' => 800,
        ])
        ->assertSessionHasErrors('amount');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=StoreTransactionController`
Expected: FAIL — `assetId` requis, `amount` inconnu

- [ ] **Step 3: Write minimal implementation**

Dans `rules()`, rendre les règles conditionnelles au type, en gardant les commentaires existants :

```php
$isTrade = in_array($this->input('type'), [TransactionType::Buy->value, TransactionType::Sell->value], true);
$isDividend = $this->input('type') === TransactionType::Dividend->value;
```

- `assetId` : `required` si `$isTrade || $isDividend`, sinon `prohibited` ;
- `quantity` / `unitPrice` : `required` si `$isTrade`, sinon `prohibited` ;
- `amount` : `['required', 'numeric', 'gt:0', 'decimal:0,2', 'lte:9999999999.99']` si non `$isTrade`, sinon `prohibited`.

Dans `after()`, ajouter un second contrôle, frère de celui de la survente :

```php
/**
 * On ne retire pas plus que le compte espèces ne porte : le solde d'une enveloppe n'est
 * négatif à aucune date, et un retrait à découvert ferait déduire un versement pour le
 * combler — l'application inventerait un apport que le porteur n'a pas fait.
 */
```

Il lit le solde via `app(GetCashMovements::class)` + `CashLedger::balanceAt()` à la date saisie, en ignorant la ligne éditée.

`TransactionInputData::fromValidated()` accepte les clés absentes (`?? null`), et `CreateTransaction` / `UpdateTransaction` écrivent `'amount' => $input->amount` dans leur tableau littéral. `auto` n'est jamais écrit depuis une saisie : il reste à son défaut `false`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="StoreTransactionController|UpdateTransaction|CreateTransaction|DeleteTransaction"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: la saisie accepte les mouvements d'espèces et plafonne les retraits"
```

---

### Task 7 : le cash dans les séries

**Files:**
- Modify: `app/Contexts/Valuation/Datas/TransactionRecordData.php`
- Modify: `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistory.php`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php`
- Modify: `app/Contexts/Valuation/Datas/ValuationSeriesData.php`, `AssetSeriesData.php`
- Modify: `app/Contexts/Valuation/Infrastructure/LaravelSeriesCache.php`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`, `app/Contexts/Valuation/Infrastructure/LaravelSeriesCacheTest.php`

**Interfaces:**
- Consumes: `TransactionFlow::cashDelta()` (Task 2).
- Produces: `ValuationSeriesData` gagne `list<float> $cash` ; `TransactionRecordData` gagne `TransactionType $type`, `?float $amount`, `?AssetClass $exposure` (et conserve `isSell` pour ne rien casser).

- [ ] **Step 1: Write the failing test**

Dans `ValuationCalculatorTest.php` :

```php
it('garde le produit d\'une vente en liquidités', function () {
    $series = (new ValuationCalculator)->calculateDaily(
        [
            record('2026-01-01', buy: true, quantity: 10, price: 100),
            record('2026-02-01', buy: false, quantity: 10, price: 120),
        ],
        [1 => ['2026-01-01' => 100.0, '2026-02-01' => 120.0, '2026-02-02' => 120.0]],
    );

    $last = count($series->labels) - 1;

    expect($series->valuations[$last])->toBe(0.0)
        ->and($series->cash[$last])->toBe(1200.0);
});
```

(`record()` est l'assistant déjà présent dans le fichier ; l'étendre plutôt que d'en créer un second.)

Dans `LaravelSeriesCacheTest.php`, sur le modèle des cas existants :

```php
it('périme la série quand un achat devient une vente', function () {
    $calls = 0;
    $transaction = buyFor($this);
    rememberSerie($this->user->id, $calls);

    /**
     * Ni la cardinalité ni les sommes de quantité, de prix ou d'actif ne bougent : sans le
     * comptage des ventes dans l'empreinte, la série périmée serait servie.
     */
    $transaction->update(['type' => TransactionType::Sell]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('périme la série quand le montant d\'un versement change', function () {
    $calls = 0;
    $deposit = Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    rememberSerie($this->user->id, $calls);

    $deposit->update(['amount' => 1500]);

    rememberSerie($this->user->id, $calls);

    expect($calls)->toBe(2);
});
```

Attention au piège du fichier : `LaravelSeriesCacheTest` construit **une instance par appel** (`rememberSerie()`), parce que l'empreinte est mémoïsée le temps d'une requête et que c'est d'une requête à la suivante que l'invalidation doit se voir. Ne pas réutiliser une instance entre deux `remember()`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="ValuationCalculator|LaravelSeriesCache"`
Expected: FAIL — propriété `cash` inexistante

- [ ] **Step 3: Write minimal implementation**

`PortfolioTransactionHistory` cesse de filtrer `whereNotNull('asset_id')` (un versement n'a pas d'actif) et joint `assets` pour l'exposition. `ValuationCalculator::calculateDaily()` accumule une série `cash` en parallèle de `$investedSeries`, alimentée par le delta signé de chaque mouvement, forward-remplie comme les autres. `evolution()` fait de même par actif pour la part de cash issue d'une exposition.

L'empreinte de `LaravelSeriesCache::stampFor()` gagne trois agrégats :

```php
->selectRaw('coalesce(sum(amount), 0) as amounts')
->selectRaw('coalesce(sum(auto), 0) as autos')
->selectRaw("coalesce(sum(case when type = 'sell' then 1 else 0 end), 0) as sells")
```

Compléter le PHPDoc du fichier : le comptage des ventes est là parce qu'un achat basculé en vente ne change ni la cardinalité ni les sommes de montants, et que `max(updated_at)` ne descend pas sous la seconde.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="ValuationCalculator|LaravelSeriesCache|BuildEvolutionSeries|BuildAssetValuationSeries"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: les courbes portent les liquidités"
```

---

### Task 8 : les totaux passent aux apports nets

**Files:**
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`
- Modify: `app/Contexts/Portfolio/Datas/PortfolioOverviewData.php`
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php:88`
- Test: `app/Contexts/Portfolio/Datas/PortfolioOverviewDataTest.php`, `app/Contexts/MarketView/Infrastructure/PortfolioTotalsTest.php`, `tests/Feature/PortfolioOverviewIgnoresDividendIncomeTest.php`

**Interfaces:**
- Consumes: `CashLedger::netContributions()`, `GetCashMovements` (Tasks 4-5).
- Produces: `PortfolioOverviewData` gagne `float $netContributions` et `float $cash`.

- [ ] **Step 1: Write the failing test**

Dans `app/Contexts/MarketView/Infrastructure/PortfolioTotalsTest.php` — le fichier a déjà ses fixtures, réutiliser leur style :

```php
it('ne compte plus les dividendes en supplément du gain réalisé', function () {
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->dividend()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-15', 'amount' => 50,
    ]);

    /** 200 € de plus-value, et rien de plus : le dividende est entré par le compte espèces. */
    expect(app(PortfolioTotals::class)->positionFor($this->user->id, $asset->id)->realizedGain)->toBe(200.0);
});

it('mesure l\'investi aux apports nets, pas au coût des titres', function () {
    $asset = Instrument::factory()->create(['ticker' => 'ACME', 'asset_class' => AssetClass::Equity]);
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'date' => '2026-03-01', 'quantity' => 10, 'unit_price' => 110, 'fees' => 0,
    ]);

    /**
     * 1 000 € sortis de la poche, une seule fois : le rachat est financé par la vente, il ne
     * crée aucun apport. L'ancien « coût des titres » aurait dit 1 100 €.
     */
    expect(app(GetPortfolioOverview::class)($this->user, null)->netContributions)->toBe(1000.0);
});
```

Relire `tests/Feature/PortfolioOverviewIgnoresDividendIncomeTest.php` avant de commencer : il fige aujourd'hui le comportement inverse et doit être **révisé, pas supprimé** (un test ne se supprime pas sans accord — le mettre à jour et l'expliquer dans le message de commit).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="PortfolioTotals|PortfolioOverview"`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

`GetPortfolioOverview::summarize()` prend les apports nets et le solde de cash en plus du gain réalisé. `PortfolioTotals::positionFor()` cesse d'ajouter `$this->income->assetHistoryFor(...)->totalReceived` au `realizedGain` ; son PHPDoc dit désormais pourquoi (le dividende entre par le cash, l'addition le compterait deux fois).

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="PortfolioTotals|PortfolioOverview|InstrumentDetailPage"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "refactor: l'investi devient les apports nets"
```

---

### Task 9 : dividendes — validation en transaction

**Files:**
- Modify: `app/Contexts/Income/Sources/Dividend/Datas/PositionRecordData.php` (ajout `walletId`)
- Modify: `app/Contexts/Income/Sources/Dividend/Services/DividendCalculator.php` (regroupement par actif **et** enveloppe)
- Modify: `app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php`
- Modify: `app/Contexts/Income/Sources/Dividend/DividendIncomeSource.php` (dédoublonnage)
- Create: `app/Contexts/Portfolio/Actions/ConfirmDividend.php`
- Create: `app/Contexts/Portfolio/Actions/ConfirmDividendTest.php`
- Create: `app/Contexts/Portfolio/Http/ConfirmDividendController.php`, `ConfirmDividendRequest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Produces: `ConfirmDividend::__invoke(int $userId, int $walletId, int $assetId, string $exDate, float $amount): Transaction` ; route `POST /dividendes` nommée `dividends.confirm`.

- [ ] **Step 1: Write the failing test**

`app/Contexts/Portfolio/Actions/ConfirmDividendTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\ConfirmDividend;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\CashLedger;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-01', 'quantity' => 100, 'unit_price' => 10, 'fees' => 0,
    ]);

    Dividend::query()->create([
        'asset_id' => $this->asset->id, 'ex_date' => '2026-02-01', 'amount_per_share' => 0.5,
    ]);
});

it('encaisse un dividende attendu et crédite le compte espèces', function () {
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);

    $movements = app(GetCashMovements::class)($this->user->id);

    expect(Transaction::query()->where('type', TransactionType::Dividend)->count())->toBe(1)
        ->and(app(CashLedger::class)->balanceAt($movements, $this->wallet->id, '2026-02-01'))->toBe(50.0);
});

it('retient le montant net saisi plutôt que le montant calculé', function () {
    /** 100 × 0,50 € = 50 € bruts ; 34,90 € nets réellement reçus. */
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 34.90);

    expect((float) Transaction::query()->where('type', TransactionType::Dividend)->value('amount'))->toBe(34.90);
});

it('sort le détachement encaissé de la dérivation', function () {
    $before = app(GetIncomeSummary::class)($this->user->id)->totalReceived;

    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 34.90);

    /** Le détachement est encaissé : il compte pour son montant réel, jamais deux fois. */
    expect($before)->toBe(50.0)
        ->and(app(GetIncomeSummary::class)($this->user->id)->totalReceived)->toBe(34.90);
});

it('donne une ligne par enveloppe détentrice', function () {
    $cto = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);

    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $cto->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-01', 'quantity' => 40, 'unit_price' => 10, 'fees' => 0,
    ]);

    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);
    app(ConfirmDividend::class)($this->user->id, $cto->id, $this->asset->id, '2026-02-01', 20.0);

    expect(Transaction::query()->where('type', TransactionType::Dividend)->count())->toBe(2);
});

it('refuse un dividende déjà encaissé pour la même enveloppe et la même date', function () {
    app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0);

    expect(fn () => app(ConfirmDividend::class)($this->user->id, $this->wallet->id, $this->asset->id, '2026-02-01', 50.0))
        ->toThrow(RuntimeException::class);
});
```

Vérifier le nom réel du modèle des détachements (`Market\Models\Dividend` ou équivalent) avant d'écrire l'import, et fixer `ticker` explicitement (règle `.ai/rules/factories.md`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="ConfirmDividend|DividendIncomeSource|DividendCalculator"`
Expected: FAIL

- [ ] **Step 3: Write minimal implementation**

`DividendCalculator::receipts()` clé sur `(assetId, walletId)` et rend un `walletId` par reçu. `DividendIncomeSource::receiptsFor()` écarte tout détachement dont il existe une transaction `Dividend` sur `(asset_id, wallet_id, date)` et lit la transaction à la place. `ConfirmDividend` écrit un tableau littéral et laisse l'observateur faire le reste.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="Dividend"`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: un dividende attendu s'encaisse en transaction"
```

---

### Task 10 : la classe de patrimoine « Liquidités »

**Files:**
- Create: `app/Contexts/Wealth/Infrastructure/CashClass.php`
- Create: `app/Contexts/Wealth/Infrastructure/CashClassTest.php`
- Create: `app/Contexts/Wealth/Ports/CashPort.php` + son adaptateur `Wealth\Infrastructure\PortfolioCash`
- Modify: `app/Contexts/Wealth/WealthProvider.php`, `app/Providers/AppServiceProvider.php` (passage de `CashClass` dans `$extra`, **après** `RealEstateClass`)
- Test: `app/Contexts/Wealth/Actions/WealthInvariantTest.php`

**Ne pas toucher à `Market\Enums\AssetClass`** : les liquidités ne sont pas une exposition de marché. Y ajouter un cas leur donnerait une page liste, un slug et une classe de portefeuille via `PortfolioAssetClass`, et casserait `IncomeSource::forAssetClass()`.

**Interfaces:**
- Produces: `CashClass` implémentant `AssetClassPort` avec `key() = 'cash'`, `label() = 'Liquidités'`, `incomeLabel() = null`, `monthlyIncomeFor() = 0.0`.

- [ ] **Step 1: Write the failing test**

`app/Contexts/Wealth/Infrastructure/CashClassTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Infrastructure\CashClass;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->create(['ticker' => 'ACME']);
});

it('vaut le solde d\'espèces de toutes les enveloppes', function () {
    $cto = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);

    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $cto->id, 'date' => '2026-01-01', 'amount' => 500,
    ]);

    expect(app(CashClass::class)->snapshotFor($this->user->id)->value)->toBe(1500.0);
});

it('n\'investit que la part encore issue d\'un apport, et porte le reste en gain', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-02', 'quantity' => 10, 'unit_price' => 100, 'fees' => 0,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 10, 'unit_price' => 120, 'fees' => 0,
    ]);

    $snapshot = app(CashClass::class)->snapshotFor($this->user->id);

    /** 1 200 € en caisse, dont 1 000 € d'apport : les 200 € de plus-value dorment là. */
    expect($snapshot->value)->toBe(1200.0)
        ->and($snapshot->invested)->toBe(1000.0);
});

it('ne déclare aucune origine de revenu', function () {
    expect(app(CashClass::class)->incomeLabel())->toBeNull()
        ->and(app(CashClass::class)->monthlyIncomeFor($this->user->id))->toBe(0.0);
});

it('vient en dernier dans le registre', function () {
    $keys = array_map(fn ($class): string => $class->key(), app(AssetClassRegistry::class)->all());

    expect(end($keys))->toBe('cash')
        ->and(array_slice($keys, 0, 4))->toBe(['equity', 'bond', 'commodity', 'crypto']);
});
```

L'assertion d'ordre ne nomme pas la clé de `RealEstateClass` : elle vérifie ce qui compte — les expositions d'abord, les liquidités en dernier — sans se casser si la clé immobilière change.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="CashClass|WealthInvariant"`
Expected: FAIL — classe absente

- [ ] **Step 3: Write minimal implementation**

`CashClass` est écrite à la main, comme `RealEstateClass` : elle n'est pas un portefeuille. `snapshotFor()` rend `value` = solde total, `invested` = `compositionAt()['deposits']`, `realized` = 0 — le gain se déduit de `value - invested`. `sectorSlicesFor()` rend une tranche unique à son nom. `seriesFor()` s'aligne sur la série de cash de `ValuationCalculator`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="CashClass|WealthInvariant|Wealth"`
Expected: PASS (sauf `SnapshotInvariantTest`, régénéré en Task 14)

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: les liquidités entrent au patrimoine"
```

---

### Task 11 : le solde par enveloppe sur la page Comptes

**Files:**
- Modify: `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php`, `app/Contexts/Portfolio/Datas/AccountLineData.php`
- Modify: `app/Contexts/Wealth/Datas/WealthAccountData.php`, `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php`
- Modify: `resources/js/lib/wealth.ts` + le composant des comptes du tableau de bord
- Test: `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php`, `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Produces: `AccountLineData` et `WealthAccountData` gagnent `float $cashBalance`.

- [ ] **Step 1: Write the failing test**

Dans `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php` :

```php
it('porte le compte espèces de chaque enveloppe', function () {
    $wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $empty = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);

    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'date' => '2026-01-01', 'amount' => 1000,
    ]);
    Transaction::factory()->withdrawal()->create([
        'user_id' => $this->user->id, 'wallet_id' => $wallet->id, 'date' => '2026-02-01', 'amount' => 300,
    ]);

    $accounts = collect(app(PortfolioAccounts::class)->accountsFor($this->user->id))->keyBy('walletName');

    expect($accounts['PEA']->cashBalance)->toBe(700.0)
        ->and($accounts['CTO']->cashBalance)->toBe(0.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="PortfolioAccounts|DashboardPage"`
Expected: FAIL — propriété `cashBalance` inexistante

- [ ] **Step 3: Write minimal implementation**

`GetAccountBreakdown` lit les mouvements une fois via `GetCashMovements` (liée en `scoped`, donc partagée avec le tableau de bord) et pose `cashBalance` par enveloppe avec `CashLedger::balanceAt($movements, $walletId, today())`. `AccountLineData` et `WealthAccountData` gagnent la propriété ; `wealth.ts` gagne son champ ; le composant des comptes l'affiche sous le nom de l'enveloppe, formaté par `eur()`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter="PortfolioAccounts|DashboardPage" && bun run test:js && bun run typecheck`
Expected: PASS

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: chaque enveloppe affiche son compte espèces"
```

---

### Task 12 : l'interface du tableau de bord et des expositions

**Files:**
- Modify: `resources/js/components/InvestedGainMeta.vue` + `.test.ts`
- Modify: `resources/js/components/dashboard/WealthSummarySection.vue`
- Modify: `resources/js/components/instruments/ValuationSection.vue`
- Modify: `resources/js/lib/portfolio.ts`, `resources/js/lib/wealth.ts`, `resources/js/lib/chart*.ts` (bande « Liquidités », jeton de teinte par thème)

**Interfaces:**
- Consumes: `netContributions`, `cash`, `cashByOrigin` servis par les tâches 8 et 10.

- [ ] **Step 1: Write the failing test**

Dans `resources/js/components/InvestedGainMeta.test.ts`, sur le modèle des cas existants :

```ts
it('annonce le cash qui reste à replacer', () => {
    const wrapper = mount(InvestedGainMeta, {
        props: { invested: 5304, gain: 840, realizedGain: 104, originCash: 1285.5 },
    });

    expect(wrapper.find('[data-origin-cash]').text()).toContain('à replacer');
    expect(wrapper.find('[data-origin-cash]').text()).toContain('1 285');
});

it('tait le repère quand il n\'y a rien à replacer', () => {
    const wrapper = mount(InvestedGainMeta, {
        props: { invested: 5304, gain: 840, realizedGain: 104, originCash: 0 },
    });

    expect(wrapper.find('[data-origin-cash]').exists()).toBe(false);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test:js`
Expected: FAIL — `[data-origin-cash]` introuvable

- [ ] **Step 3: Write minimal implementation**

Nouvelle prop `originCash?: number | null` (défaut `null`), affichée derrière un `hasOriginCash` calculé exactement comme `hasRealized` (`null` ou `0` ⇒ absent). Le libellé : `dont {{ eur(props.originCash, props.digits) }} à replacer`. Ajouter le jeton de teinte neutre des Liquidités à la palette de `chart*.ts`, en clair **et** en sombre — ECharts peint son SVG lui-même et ne résout pas les variables CSS (`.ai/rules/lib.md`).

- [ ] **Step 4: Run tests to verify they pass**

Run: `bun run test:js && bun run typecheck`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: l'interface montre les liquidités et ce qu'il reste à replacer"
```

---

### Task 13 : la saisie d'un mouvement d'espèces

**Files:**
- Modify: `resources/js/components/transactions/TransactionForm.vue` + `.test.ts`
- Modify: `resources/js/components/transactions/TransactionYearList.vue` + `.test.ts`

**Interfaces:**
- Consumes: la route `transactions.store` étendue (Task 6).

- [ ] **Step 1: Write the failing test**

Dans `TransactionForm.test.ts` :

```ts
it('remplace actif, quantité et prix par un montant sur un versement', async () => {
    const wrapper = mount(TransactionForm, { props: baseProps() });

    await wrapper.find('[data-type="deposit"]').trigger('click');

    expect(wrapper.find('[data-field="amount"]').exists()).toBe(true);
    expect(wrapper.find('[data-field="assetId"]').exists()).toBe(false);
    expect(wrapper.find('[data-field="quantity"]').exists()).toBe(false);
    expect(wrapper.find('[data-field="unitPrice"]').exists()).toBe(false);
});

it('saisit un montant à la virgule', async () => {
    const wrapper = mount(TransactionForm, { props: baseProps() });

    await wrapper.find('[data-type="deposit"]').trigger('click');
    await wrapper.find('[data-field="amount"] input').setValue('1 234,56');

    expect(wrapper.find('[data-field="amount"] input').attributes('inputmode')).toBe('decimal');
});
```

Dans `TransactionYearList.test.ts` :

```ts
it('dit qu\'un versement déduit n\'a pas été saisi', () => {
    const wrapper = mount(TransactionYearList, {
        props: yearProps([{ id: 1, type: 'deposit', auto: true, amount: 1000, date: '2026-03-03' }]),
    });

    expect(wrapper.text()).toContain('Versement déduit');
    expect(wrapper.find('[data-action="edit"]').exists()).toBe(false);
});
```

(`baseProps()` / `yearProps()` : réutiliser les fabriques de props déjà présentes dans chaque fichier.)

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test:js`
Expected: FAIL — le type « deposit » n'existe pas dans le formulaire

- [ ] **Step 3: Write minimal implementation**

Respecter `.ai/rules/transactions.md` sans exception : `useForm(data)` à **un seul argument** (deux arguments donnent un formulaire à précognition, qui validerait pendant la frappe) ; à l'envoi `only: refreshableKeys(page.props)` + `preserveState: true` + `preserveScroll: true` + `snapshot.sync()` dans `onSuccess` ; montants en `type="text"` + `inputmode="decimal"` avec `parseDecimalInput` (un `type="number"` se vide sur une virgule au clavier français) ; blocage hors-ligne par `useOnline` de `stores/network.ts`. Dans la variante `named` de `TransactionYearList`, la ligne EST un `<button>` : la mention de la ligne déduite va dans le bloc de détail, qui en est un frère.

- [ ] **Step 4: Run tests to verify they pass**

Run: `bun run test:js && bun run typecheck`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: le formulaire saisit versements, retraits et dividendes"
```

---

### Task 14 : filet d'instantané et règles du dépôt

**Files:**
- Modify: `tests/Feature/SnapshotInvariantTest.php` (hash de référence)
- Modify: `.ai/rules/portfolio.md`
- Create: une règle sur les lignes déduites, via l'outil Boost `record-rule` (glob `app/Contexts/Portfolio/**`)

- [ ] **Step 1: Run the full suite** — `php artisan test --compact`
- [ ] **Step 2: Régénérer le hash** — une seule fois, après avoir lu le diff du JSON et vérifié que chaque changement est voulu (classe `cash`, champs `netContributions`, `cash`, `amount`, `auto`). Ne pas réordonner les fixtures.
- [ ] **Step 3: Corriger `.ai/rules/portfolio.md`** — la section `AccountType` justifie l'absence du plafond de versement par « `TransactionType` n'a que `Buy`/`Sell`, aucun mouvement d'espèces ». La prémisse tombe : réécrire la raison (le plafond porte sur les versements bruts cumulés, pas sur les flux nets ; hors périmètre tant que la lecture n'est pas tranchée). Mentionner aussi que `TransactionFlow` est le site unique du signe.
- [ ] **Step 4: Enregistrer la règle des lignes déduites** — qui les écrit (`RecomputeCashDeposits`), pourquoi elles se réécrivent en grappe, pourquoi elles ne se corrigent pas à la main, et l'invariant de solde non négatif.
- [ ] **Step 5: Run the full suite and commit**

```bash
php artisan test --compact && bun run test:js && bun run typecheck
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "test: le filet d'instantané intègre les liquidités"
```

---

## Notes d'exécution

**Ordre imposé.** Les tâches 1 à 6 forment le socle et se suivent. 7 à 10 dépendent du socle mais sont indépendantes entre elles. 11 à 13 sont l'interface et supposent les données servies. 14 clôt.

**Ce qui restera rouge en cours de route.** `SnapshotInvariantTest` dès la tâche 8, jusqu'à la 14. C'est attendu et documenté ; ne pas le « réparer » en cours de chantier en régénérant le hash à chaque tâche.

**À signaler plutôt qu'à décider seul.** Si `PortfolioOverviewIgnoresDividendIncomeTest` s'avère figer une règle plus large que le seul point corrigé en tâche 8, le dire avant de le modifier — un test ne se supprime pas sans accord.
