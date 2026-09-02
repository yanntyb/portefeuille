# Fin du jumelage, volet back — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retirer les ports, adaptateurs et Datas jumelles entre PortfolioView, Wealth et leurs voisins ; servir chaque page par un composeur partagé entre contrôleur et instantané hors-ligne.

**Architecture:** PortfolioView et Wealth injectent directement les actions de Portfolio, Valuation, Income et les contrats de Market, et rendent leurs Datas au front. Un `PageProps` (`App\Shared\Inertia`) porte les props sync et différées d'une page ; le contrôleur l'enveloppe dans `Inertia::defer()`, le snapshot l'appelle. Le journal d'opérations, recopié trois fois, devient une action de Portfolio.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, inertia-laravel 3.3.1, Larastan niveau 2, Pint.

**Spec:** `docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md`

## Global Constraints

- Lire `.ai/rules/index.md` et chaque règle dont le glob couvre un fichier touché AVANT d'éditer. Les règles `portfolio-view.md`, `wealth.md`, `infrastructure.md`, `tests.md` décrivent l'ancienne doctrine jusqu'à la tâche 15 : ne pas rétablir un port ou une Data jumelle pour leur obéir.
- Aucune dépendance ajoutée ni retirée (`composer.json`, `package.json` intacts).
- Après toute modification PHP : `vendor/bin/pint --dirty --format agent`, puis les tests du périmètre : `php artisan test --compact <chemin ou --filter>`.
- Aux tâches qui suppriment des fichiers (11, 13, 14) : `vendor/bin/phpstan analyse --memory-limit=1G` doit rendre zéro erreur.
- Commentaires et docblocks en français, comme le reste du dépôt. Docblocks plutôt que commentaires en ligne.
- Un commit par tâche, message en français, préfixe `feat:`/`refactor:`/`test:`/`docs:`. Chaque message se termine par ces deux lignes exactes :
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01K9NHPz4UqL4C63dHokq2j3
  ```
- Suppressions de tests autorisées par le spec (section « Tests ») uniquement : les tests co-localisés des adaptateurs retirés, `tests/Unit/PortfolioView/{DatasTest,ProviderBindingTest,PortfolioHoldingsTest,PortfolioTransactionsTest}`, `GetWealthAccountsTest`, `GetWealthTransactionsTest`, `PortfolioLedgerTest`, `DatabaseAssetPriceAdapterTest`, et les cas « par leurs seuls ports » des tests de contrôleurs. Rien d'autre.
- Le hash `SNAPSHOT_VERSION` de `tests/Feature/SnapshotInvariantTest.php` ne se met à jour qu'une fois, à la tâche 10, avec une entrée d'en-tête.
- Les noms de props Inertia, leurs groupes différés et les conditions (`hasSectors()`, gate dividendes) ne changent pas : le front ne doit voir aucune différence hors trois clés ajoutées.

---

## Carte des fichiers

| Fichier | Sort |
| --- | --- |
| `app/Shared/Inertia/PageProps.php`, `DeferredProp.php`, `PagePropsTest.php` | créés (tâche 1) |
| `app/Contexts/Portfolio/Datas/TransactionLineData.php`, `Actions/GetTransactionJournal.php` (+Test) | créés (2) |
| `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php` (+Test), `Datas/PositionLineData.php` (+Test) | modifiés (3) |
| `app/Contexts/PortfolioView/Services/ClassBreakdown.php`, `ChartStep.php` (+Tests) | créés (4) |
| `app/Contexts/PortfolioView/Actions/GetBasketAnalysis.php`, `GetInstrumentAnalysis.php` (+Tests déplacés) | créés depuis `Infrastructure/` (5) |
| `app/Contexts/PortfolioView/Actions/GetInstrumentDetail.php`, `GetClassCatalog.php`, `GetHoldingTrends.php`, `Datas/InstrumentDetailData.php` | modifiés, sans port (6) |
| `app/Contexts/PortfolioView/Pages/WalletPage.php`, `AssetClassPage.php`, `AssetPage.php` (+Tests) | créés (7, 8, 9) |
| `app/Contexts/PortfolioView/Http/*Controller.php` (+Tests) | réduits (7, 8, 9) |
| `app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshot.php`, `tests/Feature/SnapshotInvariantTest.php` | modifiés (10) |
| `app/Contexts/PortfolioView/Ports/`, `Infrastructure/`, 20 Datas, `PortfolioViewProvider.php`, 3 tests `tests/Unit/PortfolioView/` | supprimés (11) |
| `app/Contexts/Wealth/Pages/DashboardPage.php` (+Test), `Http/DashboardController.php`, `Actions/BuildWealthSnapshot.php` | créé / modifiés (12) |
| `app/Contexts/Wealth/Infrastructure/CashClass.php`, `WealthProvider.php` ; `Ports/{AccountsPort,TransactionsPort,CashPort}`, `Infrastructure/{PortfolioAccounts,PortfolioLedger,PortfolioCash}`, `Actions/{GetWealthAccounts,GetWealthTransactions}`, `Datas/{WealthAccountData,WealthTransactionLineData}` | modifiés / supprimés (13) |
| `app/Contexts/Portfolio/PortfolioProvider.php`, `app/Providers/AppServiceProvider.php`, `app/Contexts/Market/{MarketProvider,Ports/PriceProviderPort,Infrastructure/DatabaseAssetPriceAdapter,Infrastructure/YahooFinanceAdapter}.php` (+Tests) | modifiés / supprimés (14) |
| `.ai/rules/{contexts,portfolio-view,wealth,infrastructure,tests}.md`, spec | mis à jour (15) |

---

### Task 1: `PageProps` et `DeferredProp`

**Files:**
- Create: `app/Shared/Inertia/DeferredProp.php`
- Create: `app/Shared/Inertia/PageProps.php`
- Test: `app/Shared/Inertia/PagePropsTest.php`

**Interfaces:**
- Produces: `new DeferredProp(Closure $resolve, string $group)` ; `new PageProps(array $sync, array $deferred)` avec `toInertiaProps(): array`, `render(string $component): \Inertia\Response`, `resolve(): array`. Les tâches 7 à 12 construisent des `PageProps` et appellent `render()` (contrôleurs) ou `resolve()` (snapshots).

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;
use Inertia\DeferProp;

it('rend les props synchrones telles quelles et enveloppe chaque différée dans son groupe', function () {
    $page = new PageProps(
        sync: ['titre' => 'Enveloppe'],
        deferred: ['positions' => new DeferredProp(fn (): array => [1, 2], 'positions')],
    );

    $props = $page->toInertiaProps();

    expect($props['titre'])->toBe('Enveloppe')
        ->and($props['positions'])->toBeInstanceOf(DeferProp::class)
        ->and($props['positions']->group())->toBe('positions')
        ->and(($props['positions'])())->toBe([1, 2]);
});

it('résout toutes les différées en une seule passe, dans l\'ordre sync puis différé', function () {
    $appels = 0;
    $page = new PageProps(
        sync: ['a' => 1],
        deferred: [
            'b' => new DeferredProp(function () use (&$appels): int {
                $appels++;

                return 2;
            }, 'groupe'),
            'c' => new DeferredProp(fn (): array => [], 'groupe'),
        ],
    );

    expect($page->resolve())->toBe(['a' => 1, 'b' => 2, 'c' => []])
        ->and($appels)->toBe(1);
});

it('rend une réponse Inertia sur le composant demandé', function () {
    $page = new PageProps(sync: ['a' => 1], deferred: []);

    expect($page->render('Wallet/Show'))->toBeInstanceOf(Inertia\Response::class);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Shared/Inertia/PagePropsTest.php`
Expected: FAIL, `Class "App\Shared\Inertia\PageProps" not found`.

- [ ] **Step 3: Écrire `DeferredProp`**

```php
<?php

namespace App\Shared\Inertia;

use Closure;

/**
 * Une prop différée d'une page : ce qui la calcule, et le groupe Inertia sous lequel le client la
 * redemande. Un groupe par section repliable, pour qu'un dépli ne calcule que sa section.
 */
final readonly class DeferredProp
{
    public function __construct(public Closure $resolve, public string $group) {}
}
```

- [ ] **Step 4: Écrire `PageProps`**

```php
<?php

namespace App\Shared\Inertia;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Les props d'une page en deux tas : celles qui partent avec le document, celles qui arrivent
 * après. Le contrôleur enveloppe les secondes dans `Inertia::defer()`, l'instantané hors-ligne les
 * appelle. Une seule définition sert les deux, là où `BuildPortfolioViewSnapshot` recopiait le
 * corps de chaque contrôleur.
 */
final readonly class PageProps
{
    /**
     * @param  array<string, mixed>  $sync
     * @param  array<string, DeferredProp>  $deferred
     */
    public function __construct(public array $sync, public array $deferred) {}

    /**
     * Sync tel quel ; chaque différée devient un `DeferProp` de son groupe.
     *
     * @return array<string, mixed>
     */
    public function toInertiaProps(): array
    {
        $props = $this->sync;

        foreach ($this->deferred as $key => $prop) {
            $props[$key] = Inertia::defer($prop->resolve, $prop->group);
        }

        return $props;
    }

    public function render(string $component): Response
    {
        return Inertia::render($component, $this->toInertiaProps());
    }

    /**
     * Le JSON que la page rendrait toutes sections dépliées : sync, puis chaque différée appelée.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array
    {
        $props = $this->sync;

        foreach ($this->deferred as $key => $prop) {
            $props[$key] = ($prop->resolve)();
        }

        return $props;
    }
}
```

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Shared/Inertia/PagePropsTest.php`
Expected: 3 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Shared/Inertia
git commit -m "feat: PageProps, les props d'une page servies au contrôleur et au snapshot"
```

---

### Task 2: `Portfolio\Actions\GetTransactionJournal`

**Files:**
- Create: `app/Contexts/Portfolio/Datas/TransactionLineData.php`
- Create: `app/Contexts/Portfolio/Actions/GetTransactionJournal.php`
- Test: `app/Contexts/Portfolio/Actions/GetTransactionJournalTest.php`

**Interfaces:**
- Consumes: `Market\Datas\HoldingScope` (`classes`, `walletId`, `all()`), `Portfolio\Services\TransactionFlow::of()`, `Portfolio\Models\Transaction`.
- Produces: `GetTransactionJournal::__invoke(int $userId, ?HoldingScope $scope = null): list<TransactionLineData>` et `forAsset(int $userId, int $assetId): list<TransactionLineData>`. `TransactionLineData` : 13 propriétés publiques `id, walletId, date, assetId, assetName, isSell, typeLabel, type, quantity, unitPrice, fees, total, auto`, JSON dans cet ordre. Tâches 6, 7, 8, 12 l'appellent.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->wallet = Wallet::factory()->for($this->user)->create(['name' => 'PEA']);
    $this->asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);
    $this->journal = app(GetTransactionJournal::class);
});

it('rend les opérations du porteur, la plus récente en tête, date puis id', function () {
    $ancienne = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-01-10', 'quantity' => 1, 'unit_price' => 100,
    ]);
    $premiere = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-03-01', 'quantity' => 1, 'unit_price' => 100,
    ]);
    $seconde = Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-03-01', 'quantity' => 1, 'unit_price' => 100,
    ]);

    $lines = ($this->journal)($this->user->id);

    expect(array_column($lines, 'id'))->toBe([$seconde->id, $premiere->id, $ancienne->id])
        ->and($lines[0]->assetName)->toBe('ACME')
        ->and($lines[0]->assetId)->toBe($this->asset->id)
        ->and($lines[0]->walletId)->toBe($this->wallet->id)
        ->and($lines[0]->date)->toBe('2026-03-01');
});

it('ne lit rien d\'un autre porteur', function () {
    Transaction::factory()->buy()->create(['asset_id' => $this->asset->id]);

    expect(($this->journal)($this->user->id))->toBe([]);
});

it('marque une vente et garde son montant positif, frais déduits', function () {
    Transaction::factory()->sell()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id,
        'date' => '2026-02-01', 'quantity' => 2, 'unit_price' => 50, 'fees' => 1,
    ]);

    $line = ($this->journal)($this->user->id)[0];

    expect($line->isSell)->toBeTrue()
        ->and($line->type)->toBe('sell')
        ->and($line->total)->toBe(99.0);
});

it('garde un mouvement d\'espèces sans actif, nommé par personne', function () {
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-01', 'amount' => 500,
    ]);

    $line = ($this->journal)($this->user->id)[0];

    expect($line->assetId)->toBeNull()
        ->and($line->assetName)->toBeNull()
        ->and($line->total)->toBe(500.0);
});

it('écarte le cash et les autres classes sur un périmètre par classe', function () {
    $crypto = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin', 'ticker' => 'BTC']);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $crypto->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-03',
    ]);

    $lines = ($this->journal)($this->user->id, HoldingScope::ofClasses([AssetClass::Equity]));

    expect(array_column($lines, 'assetName'))->toBe(['ACME']);
});

it('garde le cash de l\'enveloppe et écarte les autres enveloppes sur un périmètre par enveloppe', function () {
    $autre = Wallet::factory()->for($this->user)->create(['name' => 'CTO']);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->deposit()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $autre->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-03',
    ]);

    $lines = ($this->journal)($this->user->id, HoldingScope::ofWallet($this->wallet->id));

    expect($lines)->toHaveCount(2)
        ->and(array_unique(array_column($lines, 'walletId')))->toBe([$this->wallet->id])
        ->and($lines[0]->assetId)->toBeNull();
});

it('ne rend que les opérations d\'un actif sur forAsset, du porteur seulement', function () {
    $autreActif = Instrument::factory()->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $this->asset->id, 'date' => '2026-02-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $autreActif->id, 'date' => '2026-02-02',
    ]);
    Transaction::factory()->buy()->create(['asset_id' => $this->asset->id]);

    $lines = $this->journal->forAsset($this->user->id, $this->asset->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->assetId)->toBe($this->asset->id);
});

it('nomme les actifs sans rouvrir une requête par ligne', function () {
    foreach (range(1, 5) as $i) {
        $asset = Instrument::factory()->create(['name' => "Titre {$i}", 'ticker' => "T{$i}"]);
        Transaction::factory()->buy()->create([
            'user_id' => $this->user->id, 'wallet_id' => $this->wallet->id, 'asset_id' => $asset->id, 'date' => "2026-01-0{$i}",
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    $lines = ($this->journal)($this->user->id);

    expect($lines)->toHaveCount(5)
        ->and(DB::getQueryLog())->toHaveCount(1);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/Portfolio/Actions/GetTransactionJournalTest.php`
Expected: FAIL, `Class "App\Contexts\Portfolio\Actions\GetTransactionJournal" not found`.

- [ ] **Step 3: Écrire `TransactionLineData`**

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Une ligne du journal d'opérations, telle que la lisent le tableau de bord, une exposition, une
 * enveloppe ou une fiche instrument. `assetId` et `assetName` sont nuls sur un mouvement
 * d'espèces. `total` vient de `TransactionFlow`, seul site du montant d'une ligne.
 */
readonly class TransactionLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $walletId,
        public string $date,
        public ?int $assetId,
        public ?string $assetName,
        public bool $isSell,
        public string $typeLabel,
        public string $type,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public float $total,
        public bool $auto,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'walletId' => $this->walletId,
            'date' => $this->date,
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'isSell' => $this->isSell,
            'typeLabel' => $this->typeLabel,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'fees' => $this->fees,
            'total' => $this->total,
            'auto' => $this->auto,
        ];
    }
}
```

- [ ] **Step 4: Écrire `GetTransactionJournal`**

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\TransactionLineData;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Services\TransactionFlow;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le journal d'opérations d'un porteur, la plus récente en tête, chaque ligne nommant son actif.
 *
 * Un seul site de lecture pour le tableau de bord, les expositions, les enveloppes et les fiches :
 * trois adaptateurs recopiaient cette requête. Le périmètre suit l'asymétrie de
 * `Valuation\Services\ScopedTransactions` : un filtre par classe écarte les mouvements sans actif
 * (un versement n'a pas de classe — le `whereIn` sur la jointure les élimine de lui-même), un filtre
 * par enveloppe les garde (le cash est tenu par wallet).
 */
class GetTransactionJournal
{
    public function __construct(private TransactionFlow $flow) {}

    /** @return list<TransactionLineData> */
    public function __invoke(int $userId, ?HoldingScope $scope = null): array
    {
        $scope ??= HoldingScope::all();

        return $this->read(
            $this->query($userId)
                ->when($scope->classes !== null, fn (Builder $query): Builder => $query->whereIn(
                    'assets.asset_class',
                    array_map(fn (AssetClass $class): string => $class->value, $scope->classes),
                ))
                ->when($scope->walletId !== null, fn (Builder $query): Builder => $query
                    ->where('transactions.wallet_id', $scope->walletId)),
        );
    }

    /** @return list<TransactionLineData> */
    public function forAsset(int $userId, int $assetId): array
    {
        return $this->read($this->query($userId)->where('transactions.asset_id', $assetId));
    }

    private function query(int $userId): Builder
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name');
    }

    /** @return list<TransactionLineData> */
    private function read(Builder $query): array
    {
        return $query->get()
            ->map(fn (Transaction $transaction): TransactionLineData => $this->line($transaction))
            ->values()
            ->all();
    }

    private function line(Transaction $transaction): TransactionLineData
    {
        $quantity = (float) $transaction->quantity;
        $unitPrice = (float) $transaction->unit_price;
        $fees = (float) $transaction->fees;
        $amount = $transaction->amount === null ? null : (float) $transaction->amount;
        $assetName = $transaction->getAttribute('asset_name');

        return new TransactionLineData(
            id: $transaction->id,
            walletId: $transaction->wallet_id,
            date: $transaction->date->format('Y-m-d'),
            assetId: $transaction->asset_id === null ? null : (int) $transaction->asset_id,
            assetName: $assetName === null ? null : (string) $assetName,
            isSell: $transaction->type === TransactionType::Sell,
            typeLabel: $transaction->type->getLabel(),
            type: $transaction->type->value,
            quantity: $quantity,
            unitPrice: $unitPrice,
            fees: $fees,
            total: $this->flow->of($transaction->type, $quantity, $unitPrice, $fees, $amount),
            auto: (bool) $transaction->auto,
        );
    }
}
```

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/Portfolio/Actions/GetTransactionJournalTest.php`
Expected: 8 passed. Si le test « vente » rend un `total` autre que 99.0, lire `TransactionFlow::of()` et ajuster l'attendu au montant qu'il rend pour une vente de 2 × 50 avec 1 de frais (le contrat est : positif, frais de vente déduits).

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Portfolio/Datas/TransactionLineData.php app/Contexts/Portfolio/Actions/GetTransactionJournal.php app/Contexts/Portfolio/Actions/GetTransactionJournalTest.php
git commit -m "feat: GetTransactionJournal, le journal d'opérations lu une fois par Portfolio"
```

---

### Task 3: `GetAccountBreakdown` prend un périmètre ; `PositionLineData` se sérialise

**Files:**
- Modify: `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php`
- Modify: `app/Contexts/Portfolio/Datas/PositionLineData.php`
- Test: `app/Contexts/Portfolio/Actions/GetAccountBreakdownTest.php` (un cas ajouté)
- Test: `app/Contexts/Portfolio/Datas/PositionLineDataTest.php` (créé)

**Interfaces:**
- Produces: `GetAccountBreakdown::__invoke(User $user, ?HoldingScope $scope = null): list<AccountLineData>` — seul `walletId` du périmètre filtre. `PositionLineData implements JsonSerializable`, clés `assetId, quantity, avgCost, marketValue, gain, gainPct, realizedGain`.

- [ ] **Step 1: Ajouter le test du périmètre**

À la fin de `GetAccountBreakdownTest.php` (le helper `holdIn()` existe déjà en tête de fichier) :

```php
it('ne rend que l\'enveloppe du périmètre quand il en porte une', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();
    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 50, qty: 4, avgCost: 50);

    $lines = app(GetAccountBreakdown::class)($user, HoldingScope::ofWallet($cto->id));

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->walletId)->toBe($cto->id)
        ->and(app(GetAccountBreakdown::class)($user, HoldingScope::ofWallet(999999)))->toBe([]);
});
```

Ajouter `use App\Contexts\Market\Datas\HoldingScope;` aux imports du test.

- [ ] **Step 2: Créer `PositionLineDataTest.php`**

```php
<?php

use App\Contexts\Portfolio\Datas\PositionLineData;

it('se sérialise avec son identifiant d\'actif en tête', function () {
    $position = new PositionLineData(
        assetId: 7,
        quantity: 10.0,
        avgCost: 80.0,
        marketValue: 1000.0,
        gain: 200.0,
        gainPct: 25.0,
        realizedGain: 0.0,
    );

    expect(json_decode(json_encode($position), true))->toBe([
        'assetId' => 7,
        'quantity' => 10.0,
        'avgCost' => 80.0,
        'marketValue' => 1000.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
        'realizedGain' => 0.0,
    ]);
});
```

- [ ] **Step 3: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/Portfolio/Actions/GetAccountBreakdownTest.php app/Contexts/Portfolio/Datas/PositionLineDataTest.php`
Expected: 2 échecs — signature à un argument (`ArgumentCountError` ou lignes non filtrées) et `json_encode` d'un objet sans `JsonSerializable` (tableau vide, `readonly` sans propriétés publiques sérialisées… l'attendu ne matche pas).

- [ ] **Step 4: Modifier `GetAccountBreakdown`**

Signature et filtre. Remplacer :

```php
    public function __invoke(User $user): array
    {
        $lines = ($this->overview)($user)->holdings;
```

par :

```php
    /**
     * @return list<AccountLineData>
     *
     * Seule l'enveloppe du périmètre filtre : une ligne par compte n'a pas de classe.
     */
    public function __invoke(User $user, ?HoldingScope $scope = null): array
    {
        $scope ??= HoldingScope::all();
        $lines = ($this->overview)($user)->holdings;
```

et remplacer le calcul de `$walletIds` :

```php
        $walletIds = array_unique([
            ...array_keys($byWallet),
            ...array_keys(array_filter($cashBalances, fn (float $balance): bool => $balance !== 0.0)),
        ]);
```

par :

```php
        $walletIds = array_values(array_filter(
            array_unique([
                ...array_keys($byWallet),
                ...array_keys(array_filter($cashBalances, fn (float $balance): bool => $balance !== 0.0)),
            ]),
            fn (int $walletId): bool => $scope->admitsWallet($walletId),
        ));
```

`HoldingScope` est déjà importé dans ce fichier (il sert à `GetRealizedGains::totalFor`).

- [ ] **Step 5: Modifier `PositionLineData`**

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

readonly class PositionLineData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public float $quantity,
        public ?float $avgCost,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
        public float $realizedGain,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'quantity' => $this->quantity,
            'avgCost' => $this->avgCost,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'realizedGain' => $this->realizedGain,
        ];
    }
}
```

Conserver le docblock de classe existant s'il y en a un.

- [ ] **Step 6: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/Portfolio`
Expected: tout vert, dont le nouveau cas et `PositionLineDataTest`.

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Portfolio
git commit -m "feat: GetAccountBreakdown filtre par enveloppe, PositionLineData se sérialise"
```

---

### Task 4: Services purs `ClassBreakdown` et `ChartStep`

**Files:**
- Create: `app/Contexts/PortfolioView/Services/ClassBreakdown.php`
- Create: `app/Contexts/PortfolioView/Services/ChartStep.php`
- Test: `app/Contexts/PortfolioView/Services/ClassBreakdownTest.php`
- Test: `app/Contexts/PortfolioView/Services/ChartStepTest.php`

**Interfaces:**
- Consumes: `Portfolio\Datas\HoldingLineData`, `PortfolioView\Datas\ClassSliceData(key, label, value, share)`, `Valuation\Enums\ValuationGranularity`.
- Produces: `ClassBreakdown::of(list<HoldingLineData> $lines): list<ClassSliceData>` triées par valeur décroissante, `[]` sur total nul. `ChartStep::for(list<string> $labels): ValuationGranularity` — `Day` jusqu'à 92 jours d'écart inclus, `Week` au-delà. Règle `.ai/rules/services.md` : pas d'Eloquent, pas de port, tests avec `new`.

- [ ] **Step 1: Écrire les tests qui échouent**

`ClassBreakdownTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\PortfolioView\Services\ClassBreakdown;

function ligne(AssetClass $class, ?float $marketValue): HoldingLineData
{
    return new HoldingLineData(
        assetId: 1, assetName: 'X', ticker: null, type: InstrumentType::Stock, assetClass: $class,
        walletId: 1, walletName: 'PEA', accountType: AccountType::Pea,
        quantity: 1.0, avgCost: null, lastPrice: null, marketValue: $marketValue, gain: null, gainPct: null,
    );
}

it('somme chaque classe et la range de la plus lourde à la plus légère', function () {
    $slices = (new ClassBreakdown)->of([
        ligne(AssetClass::Equity, 300.0),
        ligne(AssetClass::Crypto, 600.0),
        ligne(AssetClass::Equity, 100.0),
    ]);

    expect(array_map(fn ($s) => [$s->key, $s->value, $s->share], $slices))->toBe([
        ['crypto', 600.0, 60.0],
        ['equity', 400.0, 40.0],
    ])->and($slices[0]->label)->toBe(AssetClass::Crypto->getLabel());
});

it('compte une ligne sans valeur pour zéro et ne rend rien sur un total nul', function () {
    expect((new ClassBreakdown)->of([ligne(AssetClass::Equity, null)]))->toBe([])
        ->and((new ClassBreakdown)->of([]))->toBe([]);
});
```

`ChartStepTest.php` :

```php
<?php

use App\Contexts\PortfolioView\Services\ChartStep;
use App\Contexts\Valuation\Enums\ValuationGranularity;

it('garde le pas quotidien sans label et jusqu\'au trimestre', function () {
    expect((new ChartStep)->for([]))->toBe(ValuationGranularity::Day)
        ->and((new ChartStep)->for(['2026-01-01', '2026-04-03']))->toBe(ValuationGranularity::Day);
});

it('repasse au pas hebdomadaire au-delà de quatre-vingt-douze jours', function () {
    expect((new ChartStep)->for(['2026-01-01', '2026-04-04']))->toBe(ValuationGranularity::Week);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/PortfolioView/Services`
Expected: FAIL, classes introuvables.

- [ ] **Step 3: Écrire `ClassBreakdown`**

```php
<?php

namespace App\Contexts\PortfolioView\Services;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\PortfolioView\Datas\ClassSliceData;

/**
 * La répartition par classe d'actif des lignes d'une enveloppe : une part par classe, la plus
 * lourde en tête. Une ligne sans valeur de marché compte pour zéro.
 */
class ClassBreakdown
{
    /**
     * @param  list<HoldingLineData>  $lines
     * @return list<ClassSliceData>
     */
    public function of(array $lines): array
    {
        $byClass = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $value = $line->marketValue ?? 0.0;
            $byClass[$line->assetClass->value] = ($byClass[$line->assetClass->value] ?? 0.0) + $value;
            $total += $value;
        }

        if ($total <= 0.0) {
            return [];
        }

        $slices = array_map(
            fn (string $class, float $value): ClassSliceData => new ClassSliceData(
                key: $class,
                label: AssetClass::from($class)->getLabel(),
                value: $value,
                share: $value / $total * 100,
            ),
            array_keys($byClass),
            array_values($byClass),
        );

        usort(
            $slices,
            fn (ClassSliceData $left, ClassSliceData $right): int => $right->value <=> $left->value,
        );

        return $slices;
    }
}
```

- [ ] **Step 4: Écrire `ChartStep`**

```php
<?php

namespace App\Contexts\PortfolioView\Services;

use App\Contexts\Valuation\Enums\ValuationGranularity;
use Illuminate\Support\Carbon;

/**
 * Le pas du graphe d'un actif : quotidien tant que l'historique tient dans un trimestre,
 * hebdomadaire au-delà. Une décision de rendu, qui ne traverse pas Valuation.
 */
class ChartStep
{
    public const DAILY_STEP_MAX_DAYS = 92;

    /** @param  list<string>  $labels dates `Y-m-d`, croissantes */
    public function for(array $labels): ValuationGranularity
    {
        if ($labels === []) {
            return ValuationGranularity::Day;
        }

        $span = Carbon::parse($labels[0])->diffInDays(Carbon::parse($labels[count($labels) - 1]));

        return $span > self::DAILY_STEP_MAX_DAYS
            ? ValuationGranularity::Week
            : ValuationGranularity::Day;
    }
}
```

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Services`
Expected: tout vert (SparklineReducerTest compris).

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/PortfolioView/Services
git commit -m "feat: ClassBreakdown et ChartStep, deux décisions de rendu extraites des adaptateurs"
```

---

### Task 5: `GetBasketAnalysis` et `GetInstrumentAnalysis` deviennent des actions

**Files:**
- Create: `app/Contexts/PortfolioView/Actions/GetBasketAnalysis.php` (corps de `Infrastructure/BasketAnalysis.php`)
- Create: `app/Contexts/PortfolioView/Actions/GetInstrumentAnalysis.php` (corps de `Infrastructure/InstrumentAnalysis.php`)
- Move: `Infrastructure/BasketAnalysisTest.php` → `Actions/GetBasketAnalysisTest.php`
- Move: `Infrastructure/InstrumentAnalysisTest.php` → `Actions/GetInstrumentAnalysisTest.php`
- Les adaptateurs `Infrastructure/BasketAnalysis.php` et `InstrumentAnalysis.php` RESTENT jusqu'à la tâche 11 : les contrôleurs les consomment encore par leurs ports.

**Interfaces:**
- Consumes: `Portfolio\Actions\GetPortfolioOverview::__invoke(User, ?HoldingScope): PortfolioOverviewData` (lignes `HoldingLineData`), `Valuation\Actions\BuildExposureSeries`, `Valuation\Services\Drawdown`, `Market\Contracts\PriceRepositoryContract`, `Market\Services\{Correlation,BasketIndex,FiftyTwoWeekRange,PriceGap}`, `Portfolio\Actions\GetPortfolioPositions`, `Portfolio\Services\PositionWeight`.
- Produces: `GetBasketAnalysis::__invoke(User $user, HoldingScope $scope): BasketAnalysisData` ; `GetInstrumentAnalysis::__invoke(int $userId, int $assetId): ?InstrumentAnalysisData`. Tâches 7, 8, 9 les injectent.

- [ ] **Step 1: Déplacer les tests et les recibler**

```bash
git mv app/Contexts/PortfolioView/Infrastructure/BasketAnalysisTest.php app/Contexts/PortfolioView/Actions/GetBasketAnalysisTest.php
git mv app/Contexts/PortfolioView/Infrastructure/InstrumentAnalysisTest.php app/Contexts/PortfolioView/Actions/GetInstrumentAnalysisTest.php
```

Dans `GetBasketAnalysisTest.php` :
- `use App\Contexts\PortfolioView\Ports\BasketAnalysisPort;` → `use App\Contexts\PortfolioView\Actions\GetBasketAnalysis;`
- `app(BasketAnalysisPort::class)` → `app(GetBasketAnalysis::class)`
- chaque appel `$this->analysis->analysisFor($this->user->id, <scope>)` → `($this->analysis)($this->user, <scope>)` (l'action prend l'objet `User`, plus son id). Si un test passe l'id d'un autre utilisateur créé localement, passer cet objet `User`.
- une référence éventuelle à `BasketAnalysis::MAX_INSTRUMENTS` → `GetBasketAnalysis::MAX_INSTRUMENTS`.

Dans `GetInstrumentAnalysisTest.php` :
- `use App\Contexts\PortfolioView\Ports\InstrumentAnalysisPort;` → `use App\Contexts\PortfolioView\Actions\GetInstrumentAnalysis;`
- `app(InstrumentAnalysisPort::class)` → `app(GetInstrumentAnalysis::class)`
- `$this->analysis->forAsset(` → `($this->analysis)(`

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/PortfolioView/Actions/GetBasketAnalysisTest.php app/Contexts/PortfolioView/Actions/GetInstrumentAnalysisTest.php`
Expected: FAIL, classes introuvables.

- [ ] **Step 3: Écrire `GetBasketAnalysis`**

Copier `Infrastructure/BasketAnalysis.php` vers `Actions/GetBasketAnalysis.php` et appliquer :

```php
<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Services\BasketIndex;
use App\Contexts\Market\Services\Correlation;
use App\Contexts\Market\Services\FiftyTwoWeekRange;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\PortfolioView\Datas\AnalysisInstrumentData;
use App\Contexts\PortfolioView\Datas\BasketAnalysisData;
use App\Contexts\PortfolioView\Services\CorrelationWindow;
use App\Contexts\PortfolioView\Services\PriceHistoryWindow;
use App\Contexts\Valuation\Actions\BuildExposureSeries;
use App\Contexts\Valuation\Services\Drawdown;

class GetBasketAnalysis
{
    public const MAX_INSTRUMENTS = 8;

    public function __construct(
        private GetPortfolioOverview $overview,
        private PriceRepositoryContract $prices,
        private Correlation $correlation,
        private BasketIndex $basket,
        private Drawdown $drawdown,
        private FiftyTwoWeekRange $fiftyTwoWeeks,
        private BuildExposureSeries $exposureSeries,
    ) {}

    public function __invoke(User $user, HoldingScope $scope): BasketAnalysisData
    {
        $holdings = ($this->overview)($user, $scope)->holdings;
        $weights = $this->heaviestWeights($holdings);

        if ($weights === []) {
            return BasketAnalysisData::empty();
        }

        $closesByAsset = $this->closesByAsset(array_keys($weights));
        $index = $this->basket->of($closesByAsset, $weights);
        $valuations = ($this->exposureSeries)($user->id, $scope)->valuations;

        return new BasketAnalysisData(
            maxDrawdown: $this->drawdown->of($index->labels, $index->values)->maxDepth,
            high52wGapPct: $this->fiftyTwoWeeks->of($valuations)?->gapPct,
            instruments: $this->instrumentsOf($holdings, array_keys($weights)),
            correlations: $this->correlation->matrix($this->recentOf($closesByAsset))->rows,
        );
    }

    /**
     * @param  list<HoldingLineData>  $holdings
     * @return array<int, float> valeur par actif, les huit plus lourds, du plus lourd au plus léger
     */
    private function heaviestWeights(array $holdings): array
    {
        $values = [];
        foreach ($holdings as $line) {
            if ($line->marketValue === null || $line->marketValue <= 0.0) {
                continue;
            }
            $values[$line->assetId] = ($values[$line->assetId] ?? 0.0) + $line->marketValue;
        }
        arsort($values);

        return array_slice($values, 0, self::MAX_INSTRUMENTS, preserve_keys: true);
    }

    /**
     * @param  list<int>  $assetIds
     * @return array<int, array<string, float>> clôtures par actif, indexées par date
     */
    private function closesByAsset(array $assetIds): array
    {
        $closes = array_fill_keys($assetIds, []);
        foreach ($this->prices->dailyClosesForAssetsSince($assetIds, PriceHistoryWindow::since()) as $row) {
            $closes[$row['assetId']][$row['date']] = $row['close'];
        }

        return $closes;
    }

    /**
     * @param  array<int, array<string, float>>  $closesByAsset
     * @return array<int, array<string, float>>
     */
    private function recentOf(array $closesByAsset): array
    {
        $since = CorrelationWindow::since()->format('Y-m-d');

        return array_map(
            fn (array $closes): array => array_filter(
                $closes,
                fn (string $date): bool => $date >= $since,
                ARRAY_FILTER_USE_KEY,
            ),
            $closesByAsset,
        );
    }

    /**
     * @param  list<HoldingLineData>  $holdings
     * @param  list<int>  $assetIds
     * @return list<AnalysisInstrumentData>
     */
    private function instrumentsOf(array $holdings, array $assetIds): array
    {
        $labels = [];
        foreach ($holdings as $line) {
            $labels[$line->assetId] ??= $line->ticker ?? $line->assetName;
        }

        return array_map(
            fn (int $assetId): AnalysisInstrumentData => new AnalysisInstrumentData(
                assetId: $assetId,
                label: $labels[$assetId],
            ),
            $assetIds,
        );
    }
}
```

Reprendre le docblock de classe de `Infrastructure/BasketAnalysis.php` (l'explication des deux fenêtres et des huit poids) au-dessus de `class GetBasketAnalysis`.

- [ ] **Step 4: Écrire `GetInstrumentAnalysis`**

Copier `Infrastructure/InstrumentAnalysis.php` vers `Actions/GetInstrumentAnalysis.php` :
- `namespace App\Contexts\PortfolioView\Actions;`
- retirer `use App\Contexts\PortfolioView\Ports\InstrumentAnalysisPort;` et `implements InstrumentAnalysisPort`
- `class GetInstrumentAnalysis`
- `public function forAsset(int $userId, int $assetId): ?InstrumentAnalysisData` → `public function __invoke(int $userId, int $assetId): ?InstrumentAnalysisData`

Le corps ne change pas : il n'utilisait déjà aucun port de PortfolioView.

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Actions/GetBasketAnalysisTest.php app/Contexts/PortfolioView/Actions/GetInstrumentAnalysisTest.php`
Expected: tout vert (9 + 6 tests). Puis `php artisan test --compact app/Contexts/PortfolioView` : tout vert encore, les adaptateurs étant toujours en place.

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/PortfolioView/Actions app/Contexts/PortfolioView/Infrastructure
git commit -m "refactor: les analyses de panier et d'instrument sont des actions, pas des adaptateurs"
```

---

### Task 6: `GetInstrumentDetail`, `GetClassCatalog`, `GetHoldingTrends` sans ports

**Files:**
- Modify: `app/Contexts/PortfolioView/Actions/GetInstrumentDetail.php`
- Modify: `app/Contexts/PortfolioView/Actions/GetClassCatalog.php`
- Modify: `app/Contexts/PortfolioView/Actions/GetHoldingTrends.php`
- Modify: `app/Contexts/PortfolioView/Datas/InstrumentDetailData.php` (`?PositionData` → `?PositionLineData`)
- Delete: `tests/Unit/PortfolioView/DatasTest.php` (son cas « instrument detail » construit un `PositionData` ; ses cinq cas de jumelage n'ont plus d'objet ; le cas « price history » est repris par le test d'`AssetPage`, tâche 9)
- Tests inchangés à garder verts : `tests/Unit/PortfolioView/GetInstrumentDetailTest.php`, `app/Contexts/PortfolioView/Actions/GetClassCatalogTest.php`, `GetHoldingTrendsTest.php`

**Interfaces:**
- Consumes: `GetPortfolioPositions::__invoke(int $userId): array<int, PositionLineData>` (clé = assetId), `GetTransactionJournal::forAsset()`, `PriceRepositoryContract::{latestForAsset, latestClosesForAssets, closesForAssetsSince}`, modèles `Market\Models\{Instrument, SectorAllocation}`.
- Produces: signatures inchangées — `GetInstrumentDetail(int $userId, int $instrumentId): ?InstrumentDetailData`, `GetClassCatalog(int $userId, AssetClass $class): list<CatalogLineData>`, `GetHoldingTrends(int $userId, ?array $classes = null): list<HoldingTrendData>`. `InstrumentDetailData::$position` est un `?PositionLineData`, `$transactions` une `list<Portfolio\Datas\TransactionLineData>`.

- [ ] **Step 1: Supprimer `DatasTest` et lancer les tests existants**

```bash
git rm tests/Unit/PortfolioView/DatasTest.php
php artisan test --compact tests/Unit/PortfolioView/GetInstrumentDetailTest.php app/Contexts/PortfolioView/Actions/GetClassCatalogTest.php app/Contexts/PortfolioView/Actions/GetHoldingTrendsTest.php
```
Expected: vert avant modification ; ces tests utilisent la base, pas de faux port, et doivent rester verts après.

- [ ] **Step 2: Réécrire `GetInstrumentDetail`**

```php
<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\PortfolioView\Datas\InstrumentDetailData;
use App\Contexts\PortfolioView\Datas\SectorWeightData;

/**
 * La fiche d'un instrument : ses métadonnées, son dernier cours, la position du porteur si elle
 * est valorisée, son journal et ses secteurs. Lit Market par ses modèles et son dépôt de cours,
 * Portfolio par ses actions.
 */
class GetInstrumentDetail
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private GetTransactionJournal $journal,
    ) {}

    public function __invoke(int $userId, int $instrumentId): ?InstrumentDetailData
    {
        $instrument = Instrument::query()->find($instrumentId);

        if ($instrument === null) {
            return null;
        }

        $latest = $this->prices->latestForAsset($instrumentId);
        $position = ($this->positions)($userId)[$instrumentId] ?? null;

        if ($position !== null && $position->marketValue === null) {
            $position = null;
        }

        return new InstrumentDetailData(
            id: $instrument->id,
            name: (string) $instrument->name,
            ticker: $instrument->ticker,
            isin: $instrument->isin,
            type: $instrument->type,
            assetClass: $instrument->asset_class,
            lastPrice: $latest !== null ? (float) $latest->close : null,
            lastPriceDate: $latest !== null ? $latest->date->format('Y-m-d') : null,
            position: $position,
            transactions: $this->journal->forAsset($userId, $instrumentId),
            sectors: $this->sectorsOf($instrumentId),
        );
    }

    /** @return list<SectorWeightData> */
    private function sectorsOf(int $instrumentId): array
    {
        return SectorAllocation::query()
            ->where('asset_id', $instrumentId)
            ->orderByDesc('weight')
            ->get()
            ->map(fn (SectorAllocation $allocation): SectorWeightData => new SectorWeightData(
                label: $allocation->sector->getLabel(),
                weight: (float) $allocation->weight,
            ))
            ->values()
            ->all();
    }
}
```

Dans `InstrumentDetailData.php` : remplacer `public ?PositionData $position` par `public ?PositionLineData $position`, importer `App\Contexts\Portfolio\Datas\PositionLineData`, et mettre à jour le docblock `@param` de `$transactions` vers `list<\App\Contexts\Portfolio\Datas\TransactionLineData>`. `jsonSerialize()` ne change pas : `'position' => $this->position` sérialise désormais `PositionLineData` grâce à la tâche 3.

- [ ] **Step 3: Réécrire `GetClassCatalog`**

Constructeur et lectures :

```php
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\PortfolioView\Datas\CatalogLineData;
use App\Contexts\PortfolioView\Datas\InstrumentSummaryData;

class GetClassCatalog
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
    ) {}

    /** @return list<CatalogLineData> */
    public function __invoke(int $userId, AssetClass $class): array
    {
        $instruments = $this->instrumentsOf($class);
        $assetIds = array_map(
            fn (InstrumentSummaryData $instrument): int => $instrument->id,
            $instruments,
        );
        $prices = $assetIds === [] ? [] : $this->prices->latestClosesForAssets($assetIds);

        $quantities = [];
        foreach (($this->positions)($userId) as $position) {
            $quantities[$position->assetId] = $position->quantity;
        }

        return array_map(
            fn (InstrumentSummaryData $instrument): CatalogLineData => $this->toLine(
                $instrument,
                $prices[$instrument->id] ?? null,
                $quantities[$instrument->id] ?? null,
            ),
            $instruments,
        );
    }

    /** @return list<InstrumentSummaryData> */
    private function instrumentsOf(AssetClass $class): array
    {
        return Instrument::query()
            ->where('asset_class', $class->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Instrument $instrument): InstrumentSummaryData => new InstrumentSummaryData(
                id: $instrument->id,
                name: (string) $instrument->name,
                ticker: $instrument->ticker,
                isin: $instrument->isin,
                type: $instrument->type,
            ))
            ->values()
            ->all();
    }

    // toLine() inchangé.
}
```

Retirer l'import et l'usage de `HoldingSnapshotData`, `HoldingsPort`, `MarketDataPort`.

- [ ] **Step 4: Réécrire `GetHoldingTrends`**

```php
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\PortfolioView\Datas\HoldingTrendData;
use App\Contexts\PortfolioView\Services\SparklineReducer;
use Illuminate\Support\Carbon;

class GetHoldingTrends
{
    private const MAX_POINTS = 24;

    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private SparklineReducer $sparkline,
    ) {}

    /**
     * @param  list<AssetClass>|null  $classes
     * @return list<HoldingTrendData>
     */
    public function __invoke(int $userId, ?array $classes = null): array
    {
        $assetIds = array_values(array_map(
            fn (PositionLineData $position): int => $position->assetId,
            ($this->positions)($userId),
        ));

        if ($classes !== null) {
            $kept = array_flip($this->idsOfClasses($assetIds, $classes));
            $assetIds = array_values(array_filter($assetIds, fn (int $id): bool => isset($kept[$id])));
        }

        $closes = $this->prices->closesForAssetsSince($assetIds, Carbon::createFromTimestamp(0));

        return array_map(
            fn (int $assetId): HoldingTrendData => $this->toTrend($assetId, $closes[$assetId] ?? []),
            $assetIds,
        );
    }

    /**
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    private function idsOfClasses(array $assetIds, array $classes): array
    {
        if ($assetIds === [] || $classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    // toTrend() inchangé.
}
```

Vérifier que `PriceRepositoryContract::closesForAssetsSince([], ...)` rend `[]` sans requête (regarder `EloquentPriceRepository`) ; sinon garder la garde `$assetIds === [] ? [] : ...`.

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact tests/Unit/PortfolioView app/Contexts/PortfolioView`
Expected: tout vert. `GetInstrumentDetailTest` lit `$detail->position->marketValue`, `gain`, `gainPct` : mêmes noms sur `PositionLineData`. `GetHoldingTrendsTest` « reads the prices of every position without one query per instrument » et « keeps only the trends of the exposures it is given » couvrent le remplacement de `MarketData`.

- [ ] **Step 6: Commit**

```bash
git add -A app/Contexts/PortfolioView/Actions app/Contexts/PortfolioView/Datas/InstrumentDetailData.php tests/Unit/PortfolioView
git commit -m "refactor: les trois actions de PortfolioView lisent Portfolio et Market sans port"
```

---

### Task 7: `WalletPage` et `WalletController`

**Files:**
- Create: `app/Contexts/PortfolioView/Pages/WalletPage.php`
- Test: `app/Contexts/PortfolioView/Pages/WalletPageTest.php`
- Modify: `app/Contexts/PortfolioView/Http/WalletController.php`
- Doit rester vert : `tests/Feature/WalletPageTest.php` (7 cas, fixtures réelles)

**Interfaces:**
- Consumes: `PageProps`, `DeferredProp` (tâche 1), `GetAccountBreakdown(User, HoldingScope)` (3), `GetTransactionJournal(int, HoldingScope)` (2), `ClassBreakdown::of()` (4), `GetBasketAnalysis(User, HoldingScope)` (5), `GetPortfolioOverview(User, HoldingScope)`, `GetSectorBreakdown(User, HoldingScope)`, `BuildEvolutionSeries(int, ?int, ValuationGranularity, HoldingScope)`, `BuildPortfolioPerformances(int, HoldingScope)`.
- Produces: `WalletPage::for(int $userId, int $walletId): ?PageProps` — `null` si le porteur ou l'enveloppe est inconnu ou n'est pas à lui. Sync : `account`. Différées et groupes : `positions`→`positions`, `breakdown`→`repartition`, `evolution`→`evolution`, `performances`→`performances`, `basketAnalysis`→`analyse`, `sectorBreakdown`→`secteurs`, `transactions`→`transactions`. La tâche 10 ne l'utilise pas (l'enveloppe est hors snapshot).

- [ ] **Step 1: Écrire le test du composeur**

```php
<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Pages\WalletPage;

it('rend null pour un porteur inconnu, une enveloppe inconnue ou celle d\'un autre', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $etranger = Wallet::factory()->create(['name' => 'Ailleurs']);

    $page = app(WalletPage::class);

    expect($page->for(0, $wallet->id))->toBeNull()
        ->and($page->for($user->id, 999999))->toBeNull()
        ->and($page->for($user->id, $etranger->id))->toBeNull();
});

it('porte l\'en-tête en sync et une différée par section, chacune dans son groupe', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $page = app(WalletPage::class)->for($user->id, $wallet->id);

    expect(array_keys($page->sync))->toBe(['account'])
        ->and($page->sync['account']->walletId)->toBe($wallet->id)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'positions' => 'positions',
            'breakdown' => 'repartition',
            'evolution' => 'evolution',
            'performances' => 'performances',
            'basketAnalysis' => 'analyse',
            'sectorBreakdown' => 'secteurs',
            'transactions' => 'transactions',
        ]);
});

it('ne résout que ce que l\'enveloppe tient', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $autre = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $voisin = Instrument::factory()->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Price::factory()->create(['asset_id' => $voisin->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $autre->id, 'asset_id' => $voisin->id, 'quantity' => 10, 'avg_cost' => 50,
    ]);

    $props = app(WalletPage::class)->for($user->id, $wallet->id)->resolve();

    expect(array_column($props['positions'], 'assetId'))->toBe([$instrument->id])
        ->and(array_column($props['breakdown'], 'key'))->toBe(['equity'])
        ->and(array_column($props['transactions'], 'walletId'))->toBe([$wallet->id])
        ->and($props['basketAnalysis']->instruments[0]->label)->toBe($instrument->ticker);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/PortfolioView/Pages/WalletPageTest.php`
Expected: FAIL, classe introuvable.

- [ ] **Step 3: Écrire `WalletPage`**

```php
<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\PortfolioView\Actions\GetBasketAnalysis;
use App\Contexts\PortfolioView\Services\ClassBreakdown;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La page d'une enveloppe de détention : son en-tête fiscal en sync, tout le reste différé, un
 * groupe par section. Les positions sont les lignes du périmètre, la répartition leur somme par
 * classe ; le journal garde les versements et retraits de l'enveloppe (voir GetTransactionJournal).
 */
class WalletPage
{
    public function __construct(
        private GetAccountBreakdown $accounts,
        private GetPortfolioOverview $overview,
        private ClassBreakdown $classBreakdown,
        private BuildEvolutionSeries $evolution,
        private BuildPortfolioPerformances $performances,
        private GetBasketAnalysis $basketAnalysis,
        private GetSectorBreakdown $sectors,
        private GetTransactionJournal $journal,
    ) {}

    /** `null` quand le porteur ou l'enveloppe n'existe pas, ou qu'elle n'est pas à lui : le contrôleur répond 404. */
    public function for(int $userId, int $walletId): ?PageProps
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return null;
        }

        $scope = HoldingScope::ofWallet($walletId);
        $account = ($this->accounts)($user, $scope)[0] ?? null;

        if ($account === null) {
            return null;
        }

        return new PageProps(
            sync: ['account' => $account],
            deferred: [
                'positions' => new DeferredProp(
                    fn (): array => ($this->overview)($user, $scope)->holdings, 'positions',
                ),
                'breakdown' => new DeferredProp(
                    fn (): array => $this->classBreakdown->of(($this->overview)($user, $scope)->holdings), 'repartition',
                ),
                'evolution' => new DeferredProp(
                    fn () => ($this->evolution)($userId, null, ValuationGranularity::Week, $scope), 'evolution',
                ),
                'performances' => new DeferredProp(
                    fn (): array => ($this->performances)($userId, $scope), 'performances',
                ),
                'basketAnalysis' => new DeferredProp(
                    fn () => ($this->basketAnalysis)($user, $scope), 'analyse',
                ),
                'sectorBreakdown' => new DeferredProp(
                    fn (): array => ($this->sectors)($user, $scope), 'secteurs',
                ),
                'transactions' => new DeferredProp(
                    fn (): array => ($this->journal)($userId, $scope), 'transactions',
                ),
            ],
        );
    }
}
```

- [ ] **Step 4: Réduire `WalletController`**

```php
<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\PortfolioView\Pages\WalletPage;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention, `/enveloppes/{id}`. Une adresse par ligne de `wallets`,
 * pas par cas de `AccountType` : trois PEA ont trois anciennetés. 404 et non 403 sur l'enveloppe
 * d'un autre : le code ne doit pas dire qu'elle existe.
 */
class WalletController
{
    public function __construct(private WalletPage $page) {}

    public function __invoke(int $id): Response
    {
        $page = $this->page->for(auth()->id() ?? 0, $id) ?? abort(404);

        return $page->render('Wallet/Show');
    }
}
```

Reprendre les phrases utiles du docblock existant du contrôleur si elles ne figurent pas ci-dessus.

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Pages tests/Feature/WalletPageTest.php`
Expected: 3 + 7 passed. Le cas « sert chaque section en prop différée, un groupe par section » du test Feature vérifie exactement la table des groupes.

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/PortfolioView/Pages app/Contexts/PortfolioView/Http/WalletController.php
git commit -m "feat: WalletPage compose la page d'une enveloppe sans port"
```

---

### Task 8: `AssetClassPage` et `AssetClassController`

**Files:**
- Create: `app/Contexts/PortfolioView/Pages/AssetClassPage.php`
- Test: `app/Contexts/PortfolioView/Pages/AssetClassPageTest.php`
- Modify: `app/Contexts/PortfolioView/Http/AssetClassController.php`
- Modify: `app/Contexts/PortfolioView/Http/AssetClassControllerTest.php` (retirer les cas « par leurs seuls ports »)
- Doit rester vert : `tests/Feature/InstrumentsPageTest.php`

**Interfaces:**
- Consumes: tâches 1, 2, 5 ; `GetHoldingTrends(int, ?array)`, `GetPortfolioOverview`, `GetSectorBreakdown`, `BuildEvolutionSeries`, `BuildPortfolioPerformances`, `PortfolioOverviewData::empty()`, `BasketAnalysisData::empty()`.
- Produces: `AssetClassPage::for(int $userId, AssetClass $exposure): PageProps`. Sync : `assetClass` (`key, label, slug, hasSectors`), `overview`. Différées : `trends`→`tendances`, `evolutionSeries`→`evolution`, `performances`→`performances`, `basketAnalysis`→`analyse`, `transactions`→`transactions`, puis `sectorBreakdown`→`secteurs` seulement si `hasSectors()`. Sans porteur connu : `overview` vide, analyse vide, secteurs `[]`. La tâche 10 appelle `->resolve()`.

- [ ] **Step 1: Écrire le test du composeur**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Pages\AssetClassPage;

it('porte l\'exposition et l\'aperçu en sync, une différée par section dans son groupe', function () {
    ['user' => $user] = portfolioFixture();

    $page = app(AssetClassPage::class)->for($user->id, AssetClass::Equity);

    expect(array_keys($page->sync))->toBe(['assetClass', 'overview'])
        ->and($page->sync['assetClass'])->toBe([
            'key' => 'equity', 'label' => AssetClass::Equity->getLabel(), 'slug' => AssetClass::Equity->slug(), 'hasSectors' => true,
        ])
        ->and($page->sync['overview']->totalValue)->toBe(1000.0)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'trends' => 'tendances',
            'evolutionSeries' => 'evolution',
            'performances' => 'performances',
            'basketAnalysis' => 'analyse',
            'transactions' => 'transactions',
            'sectorBreakdown' => 'secteurs',
        ]);
});

it('retient les secteurs aux expositions qui n\'en ont pas', function () {
    ['user' => $user] = cryptoFixture();

    $page = app(AssetClassPage::class)->for($user->id, AssetClass::Crypto);

    expect($page->deferred)->not->toHaveKey('sectorBreakdown')
        ->and($page->sync['assetClass']['hasSectors'])->toBeFalse();
});

it('rend une page vide, résolue sans erreur, à un porteur inconnu', function () {
    User::factory()->create();

    $props = app(AssetClassPage::class)->for(0, AssetClass::Equity)->resolve();

    expect($props['overview']->totalValue)->toBe(0.0)
        ->and($props['overview']->holdings)->toBe([])
        ->and($props['trends'])->toBe([])
        ->and($props['transactions'])->toBe([])
        ->and($props['sectorBreakdown'])->toBe([])
        ->and($props['basketAnalysis']->instruments)->toBe([]);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/PortfolioView/Pages/AssetClassPageTest.php`
Expected: FAIL, classe introuvable.

- [ ] **Step 3: Écrire `AssetClassPage`**

```php
<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\PortfolioView\Actions\GetBasketAnalysis;
use App\Contexts\PortfolioView\Actions\GetHoldingTrends;
use App\Contexts\PortfolioView\Datas\BasketAnalysisData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La page liste d'une exposition (`/actions`, `/crypto`…) : l'aperçu en sync, chaque section
 * différée dans son groupe. Le bloc sectoriel montre les secteurs du portefeuille ENTIER, pas de la
 * seule exposition (comportement d'origine, voir `.ai/rules/portfolio-view.md`), et n'existe que
 * sur les expositions qui en ont. La page se rend vide, sans erreur, à un visiteur non connecté.
 */
class AssetClassPage
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetHoldingTrends $trends,
        private BuildEvolutionSeries $evolution,
        private BuildPortfolioPerformances $performances,
        private GetBasketAnalysis $basketAnalysis,
        private GetTransactionJournal $journal,
        private GetSectorBreakdown $sectors,
    ) {}

    public function for(int $userId, AssetClass $exposure): PageProps
    {
        $user = User::query()->find($userId);
        $scope = HoldingScope::ofClasses([$exposure]);

        $deferred = [
            'trends' => new DeferredProp(fn (): array => ($this->trends)($userId, [$exposure]), 'tendances'),
            'evolutionSeries' => new DeferredProp(
                fn () => ($this->evolution)($userId, null, ValuationGranularity::Week, $scope), 'evolution',
            ),
            'performances' => new DeferredProp(fn (): array => ($this->performances)($userId, $scope), 'performances'),
            'basketAnalysis' => new DeferredProp(
                fn () => $user === null ? BasketAnalysisData::empty() : ($this->basketAnalysis)($user, $scope), 'analyse',
            ),
            'transactions' => new DeferredProp(fn (): array => ($this->journal)($userId, $scope), 'transactions'),
        ];

        if ($exposure->hasSectors()) {
            $deferred['sectorBreakdown'] = new DeferredProp(
                fn (): array => $user === null ? [] : ($this->sectors)($user, HoldingScope::all()), 'secteurs',
            );
        }

        return new PageProps(
            sync: [
                'assetClass' => [
                    'key' => $exposure->value,
                    'label' => $exposure->getLabel(),
                    'slug' => $exposure->slug(),
                    'hasSectors' => $exposure->hasSectors(),
                ],
                'overview' => $user === null ? PortfolioOverviewData::empty() : ($this->overview)($user, $scope),
            ],
            deferred: $deferred,
        );
    }
}
```

- [ ] **Step 4: Réduire `AssetClassController`**

```php
<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Pages\AssetClassPage;
use Inertia\Response;

/** Une route par exposition, engendrée depuis l'enum dans `routes/web.php` ; `exposure` est un défaut de route. */
class AssetClassController
{
    public function __construct(private AssetClassPage $page) {}

    public function __invoke(): Response
    {
        $exposure = AssetClass::from((string) request()->route('exposure'));

        return $this->page->for(auth()->id() ?? 0, $exposure)->render('AssetClass/Index');
    }
}
```

- [ ] **Step 5: Nettoyer `AssetClassControllerTest`**

Supprimer, dans `app/Contexts/PortfolioView/Http/AssetClassControllerTest.php` :
- les fonctions `fakeValuationPort()` et `fakeOverviewPort()` (lignes ~33-97) et le docblock qui les précède ;
- le test `it('sert l'aperçu et l'évolution par leurs seuls ports', ...)` ;
- le test `it('sert la section secteur par son seul port', ...)` ;
- le test `it('sert les performances par leur seul port', ...)` ;
- le docblock « Même démonstration pour l'aperçu et l'évolution… » au-dessus du premier de ces tests.

Garder les sept autres cas. Pint retire les imports devenus inutiles (`no_unused_imports`) ; vérifier qu'aucun `use ...PortfolioView\Datas\...` ou `...Ports\...` ne subsiste.

- [ ] **Step 6: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Pages app/Contexts/PortfolioView/Http/AssetClassControllerTest.php tests/Feature/InstrumentsPageTest.php`
Expected: tout vert. `InstrumentsPageTest` « diffère les opérations de l'exposition, chaque ligne nommant son actif » lit `assetName` : porté par `TransactionLineData`.

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/PortfolioView/Pages app/Contexts/PortfolioView/Http/AssetClassController.php app/Contexts/PortfolioView/Http/AssetClassControllerTest.php
git commit -m "feat: AssetClassPage compose la page d'une exposition sans port"
```

---

### Task 9: `AssetPage` et `AssetController`

**Files:**
- Create: `app/Contexts/PortfolioView/Pages/AssetPage.php`
- Test: `app/Contexts/PortfolioView/Pages/AssetPageTest.php`
- Modify: `app/Contexts/PortfolioView/Http/AssetController.php`
- Modify: `app/Contexts/PortfolioView/Http/AssetControllerTest.php` (retirer le cas « par leurs seuls ports »)
- Doit rester vert : `tests/Feature/InstrumentDetailPageTest.php`

**Interfaces:**
- Consumes: `GetInstrumentDetail` (6), `GetInstrumentAnalysis` (5), `ChartStep::for()` (4), `Valuation\Actions\BuildAssetPerformances(int, int): array`, `BuildAssetValuationSeries(int, int, ValuationRange, ValuationGranularity): ValuationSeriesData`, `Valuation\Services\ValuationCalculator::windowAndAggregate(ValuationSeriesData, ?int, ValuationGranularity)`, `Income\Sources\Dividend\Actions\GetAssetDividendHistory(int, int): AssetDividendHistoryData`, `Income\Enums\IncomeSource::forAssetClass()`, `PriceRepositoryContract::forAssetSince()`, `PortfolioView\Services\PriceHistoryWindow::since()`, `PortfolioView\Datas\PriceHistoryData(labels, close)`.
- Produces: `AssetPage::for(int $userId, int $assetId): ?PageProps` — `null` sur instrument inconnu. Sync : `instrument`, `performances`, puis `dividends` si l'exposition porte un revenu. Différées, toutes dans le groupe `default` : `priceHistory`, `valuation`, `analysis`. La tâche 10 appelle `->resolve()`.

- [ ] **Step 1: Écrire le test du composeur**

```php
<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\PortfolioView\Pages\AssetPage;

it('rend null sur un instrument inconnu', function () {
    ['user' => $user] = portfolioFixture();

    expect(app(AssetPage::class)->for($user->id, 999999))->toBeNull();
});

it('porte la fiche, les performances et les dividendes en sync, trois différées par défaut', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $page = app(AssetPage::class)->for($user->id, $instrument->id);

    expect(array_keys($page->sync))->toBe(['instrument', 'performances', 'dividends'])
        ->and($page->sync['instrument']->id)->toBe($instrument->id)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'priceHistory' => 'default',
            'valuation' => 'default',
            'analysis' => 'default',
        ]);
});

it('retient les dividendes à la crypto', function () {
    ['user' => $user, 'crypto' => $crypto] = cryptoFixture();

    $page = app(AssetPage::class)->for($user->id, $crypto->id);

    expect($page->sync)->not->toHaveKey('dividends');
});

it('résout l\'historique de cours en tableaux parallèles, du plus ancien au plus récent', function () {
    ['user' => $user] = portfolioFixture();
    $asset = Instrument::factory()->create(['name' => 'Seul', 'ticker' => 'SEU']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-02', 'close' => 11]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 10]);

    $props = app(AssetPage::class)->for($user->id, $asset->id)->resolve();

    expect($props['priceHistory']->labels)->toBe(['2026-01-01', '2026-01-02'])
        ->and($props['priceHistory']->close)->toBe([10.0, 11.0])
        ->and($props['analysis'])->toBeNull();
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/PortfolioView/Pages/AssetPageTest.php`
Expected: FAIL, classe introuvable.

- [ ] **Step 3: Écrire `AssetPage`**

```php
<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Price;
use App\Contexts\PortfolioView\Actions\GetInstrumentAnalysis;
use App\Contexts\PortfolioView\Actions\GetInstrumentDetail;
use App\Contexts\PortfolioView\Datas\PriceHistoryData;
use App\Contexts\PortfolioView\Services\ChartStep;
use App\Contexts\PortfolioView\Services\PriceHistoryWindow;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Services\ValuationCalculator;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La fiche d'un instrument, `/asset/{id}`, une seule pour toutes les expositions. Les dividendes
 * n'apparaissent que si l'exposition porte une origine de revenu (`IncomeSource::forAssetClass`),
 * la même règle que le snapshot. La valorisation se lit au jour puis se ré-échantillonne selon
 * `ChartStep` : le pas est une décision de rendu, il ne traverse pas Valuation.
 */
class AssetPage
{
    public function __construct(
        private GetInstrumentDetail $detail,
        private BuildAssetPerformances $performances,
        private GetAssetDividendHistory $dividends,
        private PriceRepositoryContract $prices,
        private BuildAssetValuationSeries $valuationSeries,
        private ValuationCalculator $calculator,
        private ChartStep $chartStep,
        private GetInstrumentAnalysis $analysis,
    ) {}

    public function for(int $userId, int $assetId): ?PageProps
    {
        $detail = ($this->detail)($userId, $assetId);

        if ($detail === null) {
            return null;
        }

        $sync = [
            'instrument' => $detail,
            'performances' => ($this->performances)($userId, $assetId),
        ];

        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $sync['dividends'] = ($this->dividends)($userId, $assetId);
        }

        return new PageProps(
            sync: $sync,
            deferred: [
                'priceHistory' => new DeferredProp(fn (): PriceHistoryData => $this->priceHistory($assetId), 'default'),
                'valuation' => new DeferredProp(fn (): ValuationSeriesData => $this->valuation($userId, $assetId), 'default'),
                'analysis' => new DeferredProp(fn () => ($this->analysis)($userId, $assetId), 'default'),
            ],
        );
    }

    private function priceHistory(int $assetId): PriceHistoryData
    {
        $prices = $this->prices->forAssetSince($assetId, PriceHistoryWindow::since());

        return new PriceHistoryData(
            labels: $prices->map(fn (Price $price): string => $price->date->format('Y-m-d'))->values()->all(),
            close: $prices->map(fn (Price $price): float => (float) $price->close)->values()->all(),
        );
    }

    private function valuation(int $userId, int $assetId): ValuationSeriesData
    {
        $daily = ($this->valuationSeries)($userId, $assetId, ValuationRange::Max, ValuationGranularity::Day);

        return $this->calculator->windowAndAggregate($daily, null, $this->chartStep->for($daily->labels));
    }
}
```

`Inertia::defer()` sans groupe explicite pose `default` ; `DeferredProp` le nomme pour que `resolve()` et `render()` disent la même chose.

- [ ] **Step 4: Réduire `AssetController`**

```php
<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\PortfolioView\Pages\AssetPage;
use Inertia\Response;

/** La fiche d'un actif, partagée par toutes les expositions ; le fil d'Ariane se déduit de `assetClass`. */
class AssetController
{
    public function __construct(private AssetPage $page) {}

    public function __invoke(int $id): Response
    {
        $page = $this->page->for(auth()->id() ?? 0, $id) ?? abort(404);

        return $page->render('Asset/Show');
    }
}
```

- [ ] **Step 5: Nettoyer `AssetControllerTest`**

Supprimer le test `it('sert les performances, la valorisation et les détachements par leurs seuls ports', ...)` (lignes ~70 à la fin de son bloc) et son docblock. Garder les quatre autres.

- [ ] **Step 6: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Pages app/Contexts/PortfolioView/Http tests/Feature/InstrumentDetailPageTest.php tests/Unit/PortfolioView`
Expected: tout vert. Si `InstrumentDetailPageTest` « sends the whole valuation history, sampled week by week » échoue sur une clé, comparer `ValuationSeriesData::jsonSerialize()` à l'ancien `AssetValuationData` (`labels, valuations, invested, prices`) et corriger l'appel, pas la Data de Valuation.

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/PortfolioView/Pages app/Contexts/PortfolioView/Http/AssetController.php app/Contexts/PortfolioView/Http/AssetControllerTest.php
git commit -m "feat: AssetPage compose la fiche d'un instrument sans port"
```

---

### Task 10: `BuildPortfolioViewSnapshot` sur les composeurs, hash mis à jour

**Files:**
- Modify: `app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshot.php`
- Modify: `tests/Feature/SnapshotInvariantTest.php` (constante `SNAPSHOT_VERSION` et son en-tête)
- Doit rester vert : `app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshotTest.php`, `app/Shared/Pwa/Http/SnapshotControllerTest.php`

**Interfaces:**
- Consumes: `AssetClassPage::for()->resolve()`, `AssetPage::for()->resolve()`, `GetPortfolioPositions`.
- Produces: `BuildPortfolioViewSnapshot::__invoke(int $userId): array{classes: array<string, array>, assets: array<int, array>}` — inchangé pour `SnapshotController`. Chaque entrée de `classes` gagne la clé `assetClass` (la page la porte en sync) ; l'ordre des clés d'une fiche devient sync puis différé (`instrument, performances, [dividends], priceHistory, valuation, analysis`).

- [ ] **Step 1: Réécrire l'action**

```php
<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\PortfolioView\Pages\AssetClassPage;
use App\Contexts\PortfolioView\Pages\AssetPage;

/**
 * Le volet portefeuille de l'instantané hors-ligne : une page liste par exposition, une fiche par
 * position détenue, toutes sections résolues. Ce sont les composeurs des pages qui les rendent :
 * le blob et la page sont identiques par construction, plus par recopie.
 */
class BuildPortfolioViewSnapshot
{
    public function __construct(
        private GetPortfolioPositions $positions,
        private AssetClassPage $classPage,
        private AssetPage $assetPage,
    ) {}

    /** @return array{classes: array<string, array<string, mixed>>, assets: array<int, array<string, mixed>>} */
    public function __invoke(int $userId): array
    {
        $classes = [];
        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->classPage->for($userId, $exposure)->resolve();
        }

        $assets = [];
        foreach (($this->positions)($userId) as $position) {
            $page = $this->assetPage->for($userId, $position->assetId);

            if ($page !== null) {
                $assets[$position->assetId] = $page->resolve();
            }
        }

        return ['classes' => $classes, 'assets' => $assets];
    }
}
```

- [ ] **Step 2: Lancer les tests du snapshot**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshotTest.php app/Shared/Pwa/Http/SnapshotControllerTest.php`
Expected: tout vert, sauf éventuellement « ne relit pas les positions une fois par fiche construite » qui compte les requêtes sur `holdings_projection` et attend 3. Si la valeur diffère : passer `range(1, 10)` à `range(1, 20)` dans ce test ; si le compte ne bouge pas, il n'y a pas de N+1, mettre à jour l'attendu et le dire dans le message de commit. S'il bouge, une action non mémoïsée est appelée par fiche : la trouver (`GetPortfolioPositions`, `GetPortfolioOverview` sont `scoped`) avant de toucher au test.

- [ ] **Step 3: Mettre à jour le hash de référence**

Run: `php artisan test --compact tests/Feature/SnapshotInvariantTest.php`
Expected: FAIL, le hash calculé diffère de `SNAPSHOT_VERSION`. Copier le hash rendu dans la constante (`tests/Feature/SnapshotInvariantTest.php:137`) et ajouter à la fin de l'en-tête qui liste les modifications :

```
 * Modifié une vingtième fois : fin du jumelage. Les pages rendent les Datas de Portfolio,
 * Valuation et Income telles quelles, donc trois clés entrent dans le blob (`overview.netContributions`
 * sur chaque exposition, `instrument.position.assetId`, `instrument.transactions[].assetId` et
 * `assetName`) ; chaque exposition porte aussi `assetClass`, et l'ordre des clés d'une fiche suit
 * désormais sync puis différé. Aucun chiffre n'a bougé.
```

Relancer : vert. Puis lancer deux fois de suite pour vérifier la stabilité du hash.

- [ ] **Step 4: Commit**

```bash
git add app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshot.php app/Contexts/PortfolioView/Actions/BuildPortfolioViewSnapshotTest.php tests/Feature/SnapshotInvariantTest.php
git commit -m "refactor: le snapshot portefeuille résout les composeurs de page au lieu de recopier les contrôleurs"
```

---

### Task 11: Suppression des ports, adaptateurs, Datas jumelles et provider de PortfolioView

**Files:**
- Delete: `app/Contexts/PortfolioView/Ports/` (10 fichiers)
- Delete: `app/Contexts/PortfolioView/Infrastructure/` (tout : `BasketAnalysis`, `IncomeTotals`(+Test), `InstrumentAnalysis`, `MarketData`(+Test), `PortfolioAccounts`(+Test), `PortfolioHoldings`, `PortfolioSectors`(+Test), `PortfolioTotals`(+Test), `PortfolioTransactions`, `ValuationHistory`(+Test))
- Delete: 20 Datas dans `app/Contexts/PortfolioView/Datas/` : `AccountRowData`, `AnalysisData`, `AssetLineData`, `AssetValuationData`, `ClassTransactionLineData`, `ConcentrationData`, `ContributionLineData`, `DividendHistoryData`, `DividendLineData`, `DrawdownData`, `EvolutionData`, `HoldingRowData`, `HoldingSnapshotData`, `IncomeOverviewData`, `IncomeYearData`, `PerformanceLineData`, `PortfolioSummaryData`, `PositionData`, `SectorSliceData`, `TransactionLineData`
- Delete: `app/Contexts/PortfolioView/PortfolioViewProvider.php`
- Delete: `tests/Unit/PortfolioView/PortfolioHoldingsTest.php`, `PortfolioTransactionsTest.php`, `ProviderBindingTest.php`
- Modify: `app/Providers/AppServiceProvider.php` (retirer l'appel `PortfolioViewProvider::registers(...)` et ses 11 `use`)

**Interfaces:**
- Consumes: rien de nouveau. Après cette tâche, `grep -rn "PortfolioView\\\\Ports\|PortfolioView\\\\Infrastructure\|PortfolioViewProvider" app tests` doit être vide.

- [ ] **Step 1: Vérifier qu'aucun test de Portfolio ne dépend des cas métier de `PortfolioTotalsTest`**

`PortfolioTotalsTest` pince quatre comportements de Portfolio à travers l'adaptateur : le détachement théorique hors du gain réalisé (au total et à la position), le gain réalisé d'une exposition muette, les dividendes non comptés en supplément, l'investi mesuré aux apports nets. Vérifier qu'ils sont couverts chez le propriétaire :

```bash
grep -ln "détachement\|realizedGain\|netContributions" app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php app/Contexts/Portfolio/Actions/GetRealizedGainsTest.php app/Contexts/Portfolio/Actions/GetPortfolioPositionsTest.php
```

Si `netContributions` ou « détachement » n'apparaît dans aucun de ces fichiers, recopier le cas correspondant de `PortfolioTotalsTest` dans `GetPortfolioOverviewTest` en remplaçant `app(PortfolioOverviewPort::class)->overviewFor($userId, $scope)` par `app(GetPortfolioOverview::class)($user, $scope)` et `PortfolioSummaryData`/`HoldingRowData` par `PortfolioOverviewData`/`HoldingLineData`. Lancer `php artisan test --compact app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php`.

- [ ] **Step 2: Supprimer**

```bash
git rm -r app/Contexts/PortfolioView/Ports app/Contexts/PortfolioView/Infrastructure
git rm app/Contexts/PortfolioView/PortfolioViewProvider.php
cd app/Contexts/PortfolioView/Datas && git rm AccountRowData.php AnalysisData.php AssetLineData.php AssetValuationData.php ClassTransactionLineData.php ConcentrationData.php ContributionLineData.php DividendHistoryData.php DividendLineData.php DrawdownData.php EvolutionData.php HoldingRowData.php HoldingSnapshotData.php IncomeOverviewData.php IncomeYearData.php PerformanceLineData.php PortfolioSummaryData.php PositionData.php SectorSliceData.php TransactionLineData.php && cd -
git rm tests/Unit/PortfolioView/PortfolioHoldingsTest.php tests/Unit/PortfolioView/PortfolioTransactionsTest.php tests/Unit/PortfolioView/ProviderBindingTest.php
```

Restent dans `Datas/` : `AnalysisInstrumentData`, `BasketAnalysisData`, `CatalogLineData`, `ClassSliceData`, `HoldingTrendData`, `InstrumentAnalysisData` (+Test), `InstrumentDetailData`, `InstrumentMetaData`, `InstrumentSummaryData`, `PriceHistoryData`, `SectorWeightData`. `InstrumentMetaData` n'a plus de constructeur appelant : le supprimer aussi si `grep -rn InstrumentMetaData app resources` ne rend que sa propre définition.

- [ ] **Step 3: Nettoyer `AppServiceProvider`**

Retirer le bloc :

```php
        PortfolioViewProvider::registers(
            app: $this->app,
            marketData: MarketData::class,
            ...
            accounts: PortfolioViewAccounts::class,
        );
```

et les `use` correspondants (`BasketAnalysis`, `IncomeTotals`, `InstrumentAnalysis`, `MarketData`, `PortfolioAccounts as PortfolioViewAccounts`, `PortfolioHoldings`, `PortfolioSectors`, `PortfolioTotals`, `PortfolioTransactions`, `ValuationHistory`, `PortfolioViewProvider`).

- [ ] **Step 4: Vérifier**

```bash
grep -rn "PortfolioView\\\\Ports\|PortfolioView\\\\Infrastructure\|PortfolioViewProvider\|HoldingRowData\|PortfolioSummaryData\|ClassTransactionLineData\|AccountRowData\|SectorSliceData" app tests resources/js/lib/snapshotContract.ts
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact
```
Expected: grep vide ; phpstan zéro erreur ; suite complète verte (les tests Browser peuvent être exclus si l'environnement ne les lance pas : `php artisan test --compact --exclude-group=browser` ou selon la config du dépôt).

- [ ] **Step 5: Commit**

```bash
git add -A app/Contexts/PortfolioView app/Providers/AppServiceProvider.php tests/Unit/PortfolioView
git commit -m "refactor: PortfolioView perd ses ports, ses adaptateurs et ses vingt Datas jumelles"
```

---

### Task 12: `DashboardPage`, `DashboardController`, `BuildWealthSnapshot`

**Files:**
- Create: `app/Contexts/Wealth/Pages/DashboardPage.php`
- Test: `app/Contexts/Wealth/Pages/DashboardPageTest.php`
- Modify: `app/Contexts/Wealth/Http/DashboardController.php`
- Modify: `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php`
- Doit rester vert : `tests/Feature/DashboardPageTest.php`, `app/Contexts/Wealth/Actions/BuildWealthSnapshotTest.php`, `app/Shared/Pwa/Http/SnapshotControllerTest.php`

**Interfaces:**
- Consumes: `PageProps`, `GetTransactionJournal(int)`, `GetAccountBreakdown(User)`, `Wealth\Actions\{GetWealthOverview, BuildWealthSeries, GetWealthIncome, GetWealthSectors}` (toutes `(int $userId)`), `MarketSyncStatePort::current()`.
- Produces: `DashboardPage::for(int $userId): PageProps`. Sync : `overview`. Différées : `series`→`evolution`, `income`→`revenus`, `sectors`→`secteurs`, `transactions`→`transactions`, `accounts`→`enveloppes`. `accounts` rend `list<Portfolio\Datas\AccountLineData>` (même JSON que l'ancienne `WealthAccountData`), `transactions` une `list<TransactionLineData>`.

- [ ] **Step 1: Écrire le test du composeur**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\TransactionLineData;
use App\Contexts\Wealth\Pages\DashboardPage;

it('porte le résumé en sync et cinq différées, chacune dans son groupe', function () {
    ['user' => $user] = portfolioFixture();

    $page = app(DashboardPage::class)->for($user->id);

    expect(array_keys($page->sync))->toBe(['overview'])
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'series' => 'evolution',
            'income' => 'revenus',
            'sectors' => 'secteurs',
            'transactions' => 'transactions',
            'accounts' => 'enveloppes',
        ]);
});

it('résout les enveloppes et le journal depuis Portfolio', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $props = app(DashboardPage::class)->for($user->id)->resolve();

    expect($props['accounts'])->toHaveCount(1)
        ->and($props['accounts'][0])->toBeInstanceOf(AccountLineData::class)
        ->and($props['accounts'][0]->walletId)->toBe($wallet->id)
        ->and($props['transactions'][0])->toBeInstanceOf(TransactionLineData::class)
        ->and($props['transactions'][0]->assetName)->toBe('ACME');
});

it('se résout vide sans porteur connu', function () {
    User::factory()->create();

    $props = app(DashboardPage::class)->for(0)->resolve();

    expect($props['overview']->totalValue)->toBe(0.0)
        ->and($props['accounts'])->toBe([])
        ->and($props['transactions'])->toBe([]);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact app/Contexts/Wealth/Pages/DashboardPageTest.php`
Expected: FAIL, classe introuvable.

- [ ] **Step 3: Écrire `DashboardPage`**

```php
<?php

namespace App\Contexts\Wealth\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Actions\GetWealthSectors;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * Le tableau de bord : le résumé du patrimoine en sync, chaque section différée dans son groupe.
 * Les cartes d'enveloppes et le journal viennent de Portfolio directement : ce sont ses Datas que
 * le front lit. L'état de synchronisation (`sync`) n'est pas une donnée de page, le contrôleur
 * l'ajoute seul et le snapshot ne le porte pas.
 */
class DashboardPage
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
        private GetWealthSectors $sectors,
        private GetTransactionJournal $journal,
        private GetAccountBreakdown $accounts,
    ) {}

    public function for(int $userId): PageProps
    {
        $user = User::query()->find($userId);

        return new PageProps(
            sync: ['overview' => ($this->overview)($userId)],
            deferred: [
                'series' => new DeferredProp(fn () => ($this->series)($userId), 'evolution'),
                'income' => new DeferredProp(fn () => ($this->income)($userId), 'revenus'),
                'sectors' => new DeferredProp(fn (): array => ($this->sectors)($userId), 'secteurs'),
                'transactions' => new DeferredProp(fn (): array => ($this->journal)($userId), 'transactions'),
                'accounts' => new DeferredProp(
                    fn (): array => $user === null ? [] : ($this->accounts)($user), 'enveloppes',
                ),
            ],
        );
    }
}
```

Les quatre actions de Wealth prennent un `int` et rendent leur forme vide sur un porteur inconnu (`BuildWealthSnapshot(0)` est déjà testé ainsi) : seul `GetAccountBreakdown` a besoin de l'objet `User`.

- [ ] **Step 4: Réduire `DashboardController` et `BuildWealthSnapshot`**

```php
<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Market\Ports\MarketSyncStatePort;
use App\Contexts\Wealth\Pages\DashboardPage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(
        private DashboardPage $page,
        private MarketSyncStatePort $syncState,
    ) {}

    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'sync' => $this->syncState->current(),
            ...$this->page->for(auth()->id() ?? 0)->toInertiaProps(),
        ]);
    }
}
```

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Pages\DashboardPage;

/** Le volet tableau de bord de l'instantané hors-ligne : la page, toutes sections résolues. */
class BuildWealthSnapshot
{
    public function __construct(private DashboardPage $page) {}

    /** @return array<string, mixed> */
    public function __invoke(int $userId): array
    {
        return $this->page->for($userId)->resolve();
    }
}
```

- [ ] **Step 5: Vérifier le succès**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact app/Contexts/Wealth tests/Feature/DashboardPageTest.php app/Shared/Pwa tests/Feature/SnapshotInvariantTest.php`
Expected: tout vert. Le hash ne doit PAS bouger : `AccountLineData` et `WealthAccountData` rendent le même JSON, `TransactionLineData` et `WealthTransactionLineData` aussi, et l'ordre des clés du blob `dashboard` est inchangé. S'il bouge, comparer le JSON avant/après plutôt que mettre à jour la constante.

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Wealth/Pages app/Contexts/Wealth/Http/DashboardController.php app/Contexts/Wealth/Actions/BuildWealthSnapshot.php
git commit -m "feat: DashboardPage compose le tableau de bord, enveloppes et journal lus chez Portfolio"
```

---

### Task 13: Wealth sans ports internes : `CashClass` absorbe `PortfolioCash`, trois ports supprimés

**Files:**
- Modify: `app/Contexts/Wealth/Infrastructure/CashClass.php`
- Modify: `app/Contexts/Wealth/WealthProvider.php`
- Modify: `app/Providers/AppServiceProvider.php` (appel `WealthProvider::registers`)
- Delete: `app/Contexts/Wealth/Ports/AccountsPort.php`, `TransactionsPort.php`, `CashPort.php`
- Delete: `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php` (+Test), `PortfolioLedger.php` (+Test), `PortfolioCash.php`
- Delete: `app/Contexts/Wealth/Actions/GetWealthAccounts.php` (+Test), `GetWealthTransactions.php` (+Test)
- Delete: `app/Contexts/Wealth/Datas/WealthAccountData.php`, `WealthTransactionLineData.php`
- Doit rester vert : `app/Contexts/Wealth/Infrastructure/CashClassTest.php` (12 cas), `WealthInvariantTest`, `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Produces: `CashClass implements AssetClassPort`, construite par le conteneur avec `GetPortfolioOverview`, `GetCashMovements`, `CashLedger`, `BuildEvolutionSeries`, `SeriesAligner`, `InvestedCapital`, `PortfolioInvestedCapital`. `WealthProvider::registers(Application $app, array $extra): void`.

- [ ] **Step 1: Réécrire `CashClass`**

Remplacer le constructeur `private CashPort $cash` et les délégations par le corps de `PortfolioCash` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Services\CashLedger;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\AssetClassPort;
use App\Contexts\Wealth\Services\InvestedCapital;
use App\Contexts\Wealth\Services\SeriesAligner;

class CashClass implements AssetClassPort
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetCashMovements $movements,
        private CashLedger $ledger,
        private BuildEvolutionSeries $evolution,
        private SeriesAligner $aligner,
        private InvestedCapital $capital,
        private PortfolioInvestedCapital $allocation,
    ) {}

    public function key(): string
    {
        return 'cash';
    }

    public function label(): string
    {
        return 'Liquidités';
    }

    public function href(): ?string
    {
        return null;
    }

    public function color(): string
    {
        return 'cash';
    }

    public function incomeLabel(): ?string
    {
        return null;
    }

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        return new ClassSnapshotData(
            value: ($this->overview)($user)->cash,
            invested: $this->allocation->forCash($userId),
        );
    }

    /** @return list<ClassSectorData> */
    public function sectorSlicesFor(int $userId): array
    {
        return [new ClassSectorData(label: $this->label(), value: $this->snapshotFor($userId)->value)];
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        $movements = ($this->movements)($userId);

        if ($movements === []) {
            return ClassSeriesData::empty();
        }

        $labels = array_values(array_unique(array_map(
            fn (CashMovementData $movement): string => $movement->date,
            $movements,
        )));
        sort($labels);

        $costSeries = ($this->evolution)($userId, null, ValuationGranularity::Week);
        $costs = $this->aligner->onto(
            $labels,
            $costSeries->labels,
            $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->invested, $costSeries->perAsset),
                count($costSeries->labels),
            ),
        );

        $points = $this->ledger->timeline($movements, $labels);
        $values = [];
        $invested = [];
        foreach (array_keys($labels) as $index) {
            $values[] = $points[$index]['balance'];
            $invested[] = $this->capital->forCashSeries($points[$index]['netContributions'], $costs[$index], $points[$index]['balance']);
        }

        return new ClassSeriesData(labels: $labels, value: $values, invested: $invested);
    }

    public function monthlyIncomeFor(int $userId): float
    {
        return 0.0;
    }
}
```

Reprendre les docblocks de `PortfolioCash` (la formule de l'investi en cash, la raison du `Week`) et ceux de l'ancien `CashClass` (« aucune page à détailler », « aucune origine de revenu ») au-dessus des méthodes correspondantes.

- [ ] **Step 2: Réduire `WealthProvider`**

```php
    /** @param  list<class-string<AssetClassPort>>  $extra  Les classes qui ne sont pas un portefeuille, dans l'ordre d'affichage. */
    public static function registers(Application $app, array $extra): void
    {
        $app->scoped(
            AssetClassRegistry::class,
            // ... corps inchangé ...
        );
    }
```

Retirer les trois `bind` (`TransactionsPort`, `AccountsPort`, `CashPort`) et leurs `use`. Dans `AppServiceProvider`, l'appel devient :

```php
        WealthProvider::registers(
            app: $this->app,
            extra: [RealEstateClass::class, CashClass::class],
        );
```

et les `use` de `PortfolioAccounts`, `PortfolioLedger` (Wealth) partent.

- [ ] **Step 3: Supprimer**

```bash
git rm app/Contexts/Wealth/Ports/AccountsPort.php app/Contexts/Wealth/Ports/TransactionsPort.php app/Contexts/Wealth/Ports/CashPort.php
git rm app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php app/Contexts/Wealth/Infrastructure/PortfolioLedger.php app/Contexts/Wealth/Infrastructure/PortfolioLedgerTest.php app/Contexts/Wealth/Infrastructure/PortfolioCash.php
git rm app/Contexts/Wealth/Actions/GetWealthAccounts.php app/Contexts/Wealth/Actions/GetWealthAccountsTest.php app/Contexts/Wealth/Actions/GetWealthTransactions.php app/Contexts/Wealth/Actions/GetWealthTransactionsTest.php
git rm app/Contexts/Wealth/Datas/WealthAccountData.php app/Contexts/Wealth/Datas/WealthTransactionLineData.php
```

- [ ] **Step 4: Vérifier**

```bash
grep -rn "CashPort\|PortfolioCash\|WealthAccountData\|WealthTransactionLineData\|GetWealthAccounts\|GetWealthTransactions\|Wealth\\\\Ports\\\\AccountsPort\|Wealth\\\\Ports\\\\TransactionsPort" app tests
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact app/Contexts/Wealth tests/Feature/DashboardPageTest.php tests/Feature/SnapshotInvariantTest.php app/Shared
```
Expected: grep vide ; phpstan zéro erreur ; tout vert, hash inchangé. `CashClassTest` construit `app(CashClass::class)` : le conteneur résout les sept dépendances concrètes.

- [ ] **Step 5: Commit**

```bash
git add -A app/Contexts/Wealth app/Providers/AppServiceProvider.php
git commit -m "refactor: Wealth ne garde qu'AssetClassPort, CashClass absorbe PortfolioCash"
```

---

### Task 14: Providers rangés, `PriceProviderPort` retiré

**Files:**
- Modify: `app/Contexts/Portfolio/PortfolioProvider.php`
- Modify: `app/Contexts/Wealth/WealthProvider.php` (ajoute le `scoped` de `PortfolioInvestedCapital`)
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Contexts/Market/MarketProvider.php`, `app/Contexts/Market/MarketProviderTest.php`
- Modify: `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`, `YahooFinanceAdapterTest.php`
- Delete: `app/Contexts/Market/Ports/PriceProviderPort.php`, `app/Contexts/Market/Infrastructure/DatabaseAssetPriceAdapter.php`, `DatabaseAssetPriceAdapterTest.php`

**Interfaces:**
- Produces: `PortfolioProvider::registers(Application $app): void` posant les quatre `scoped` de Portfolio ; `MarketProvider::registers()` sans paramètre `$priceProvider`. `AppServiceProvider::register()` ne contient plus que des appels `registers()`.

- [ ] **Step 1: `PortfolioProvider`**

```php
<?php

namespace App\Contexts\Portfolio;

use App\Contexts\Portfolio\Actions\GetCashMovements;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Actions\GetRealizedGains;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Portfolio n'a pas de port à lier : il lit ses propres tables. Il mémoïse en revanche quatre
 * lectures par requête HTTP, une seule lecture du portefeuille servant toutes les expositions,
 * toutes les fiches et tous les recalculs d'une même requête.
 */
class PortfolioProvider extends ServiceProvider
{
    public static function registers(Application $app): void
    {
        /** Une lecture du portefeuille par requête : les classes d'actif la partagent. */
        $app->scoped(GetPortfolioOverview::class);

        /** Une lecture des positions par requête : PortfolioView et Income l'appellent une fois par position détenue. */
        $app->scoped(GetPortfolioPositions::class);

        /** Une lecture des ventes par requête : le gain réalisé se lit aux quatre expositions. */
        $app->scoped(GetRealizedGains::class);

        /** Une lecture des mouvements d'espèces par requête : RecomputeCashDeposits la relit à chaque transaction touchée. */
        $app->scoped(GetCashMovements::class);
    }
}
```

Dans `WealthProvider::registers()`, avant le `scoped` du registre :

```php
        /**
         * Une répartition des apports nets par requête : le reliquat qu'une exposition libère en
         * vendant se replace dans une autre, donc chaque classe a besoin de la photo globale et
         * la referait sinon cinq fois par tableau de bord.
         */
        $app->scoped(PortfolioInvestedCapital::class);
```

Dans `AppServiceProvider::register()` : retirer les cinq `$this->app->scoped(...)` et leurs docblocks, ajouter `PortfolioProvider::registers(app: $this->app);` après `ValuationProvider::registers(...)`, retirer les `use` des quatre actions et de `PortfolioInvestedCapital`, ajouter `use App\Contexts\Portfolio\PortfolioProvider;`.

- [ ] **Step 2: Retirer `PriceProviderPort`**

```bash
git rm app/Contexts/Market/Ports/PriceProviderPort.php app/Contexts/Market/Infrastructure/DatabaseAssetPriceAdapter.php app/Contexts/Market/Infrastructure/DatabaseAssetPriceAdapterTest.php
```

- `MarketProvider::registers()` : retirer le paramètre `string $priceProvider`, la ligne `$app->bind(PriceProviderPort::class, $priceProvider);` et le `use`.
- `AppServiceProvider` : retirer l'argument `priceProvider: DatabaseAssetPriceAdapter::class` et le `use`.
- `MarketProviderTest` : retirer l'assertion `->and(app(PriceProviderPort::class))->toBeInstanceOf(DatabaseAssetPriceAdapter::class)` et les deux `use`.
- `YahooFinanceAdapter` : retirer `PriceProviderPort` de la clause `implements` et supprimer les trois méthodes qu'aucun autre port ne déclare : `supportsPrices()`, `getCurrentPrice()`, `getPriceHistory()`. Vérifier avant : `grep -rn "getCurrentPrice\|getPriceHistory\|supportsPrices" app --include='*.php' | grep -v Test` ne doit rendre que `YahooFinanceAdapter.php` lui-même.
- `YahooFinanceAdapterTest` : supprimer les `it()` dont le seul sujet est l'une de ces trois méthodes (les appels sont aux lignes ~85, 89, 95, 107, 114, 121, 131, 140, 183) et, dans le test de `supportsPriceFeed`, la seule ligne `->and($this->adapter->supportsPrices($type))->toBe($this->adapter->supportsPriceFeed($type))`. Si une méthode privée de l'adaptateur n'est plus appelée après ces retraits, la supprimer aussi.

- [ ] **Step 3: Vérifier**

```bash
grep -rn "PriceProviderPort\|DatabaseAssetPriceAdapter\|getCurrentPrice\|getPriceHistory\|supportsPrices" app tests
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact
```
Expected: grep vide ; phpstan zéro erreur ; suite complète verte. `AppServiceProvider::register()` se lit désormais comme sept appels `registers()` et rien d'autre.

- [ ] **Step 4: Commit**

```bash
git add -A app/Contexts/Portfolio/PortfolioProvider.php app/Contexts/Wealth/WealthProvider.php app/Providers/AppServiceProvider.php app/Contexts/Market
git commit -m "refactor: chaque contexte lie ses propres scoped, PriceProviderPort mort retiré"
```

---

### Task 15: Règles `.ai/rules` et note au spec

**Files:**
- Modify: `.ai/rules/contexts.md` (section ajoutée)
- Rewrite: `.ai/rules/portfolio-view.md`
- Modify: `.ai/rules/wealth.md` (§1)
- Modify: `.ai/rules/infrastructure.md` (§1)
- Modify: `.ai/rules/tests.md` (§2)
- Modify: `docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md` (section Tests)

- [ ] **Step 1: `contexts.md`, nouvelle section en fin de fichier**

Si l'outil MCP `record-rule` est disponible, l'appeler avec `glob: app/Contexts/**`, `title: Un port est une frontière technique`, et la note ci-dessous ; sinon l'ajouter à la main à la fin de `.ai/rules/contexts.md` :

```markdown
## Un port est une frontière technique, jamais une politesse entre contextes
`Ports/` ne contient que ce qui a une vraie frontière derrière : HTTP et Python (Yahoo), cache (`SeriesCachePort`, `RealEstateCachePort`), état de synchronisation, dépôts Eloquent de Market, et les registres d'extension (`AssetClassPort`, `IncomeSourcePort`). Entre deux contextes du monolithe, on injecte l'action du voisin et on rend sa Data au front telle quelle — jamais d'interface, d'adaptateur ni de Data jumelle. PortfolioView et Wealth ont porté vingt-deux Datas recopiées à l'octet, trente méthodes d'adaptateur passe-plat et cent cinquante tests de recopie avant le chantier du 3 septembre 2026 : dix-neuf fichiers à ouvrir pour lire un chiffre, un hash d'instantané déplacé dix-neuf fois. Les ports de Valuation et Income vers Portfolio (`TransactionHistoryPort`, `PositionHistoryPort`, `DividendHistoryPort`) survivent pour l'instant, leurs adaptateurs ayant une logique propre : même doctrine, chantier suivant.
```

- [ ] **Step 2: `portfolio-view.md`, réécrit**

```markdown
---
paths:
  - 'app/Contexts/PortfolioView/**'
---

# Portfolio View

## PortfolioView compose sans port ; un composeur par page sert contrôleur et snapshot
Le contexte n'a ni `Ports/`, ni `Infrastructure/`, ni provider. Ses `Pages/` (`AssetClassPage`, `AssetPage`, `WalletPage`) injectent directement les actions de Portfolio, Valuation, Income et les contrats de Market, et rendent leurs Datas au front : `PortfolioOverviewData`, `HoldingLineData`, `AccountLineData`, `TransactionLineData`, `EvolutionSeriesData`, `PerformanceData`, `ValuationSeriesData`, `AllocationSliceData`, `AssetDividendHistoryData`. `Datas/` ne garde que les formes qu'aucun voisin n'a : la fiche (`InstrumentDetailData`), le catalogue, les tendances, les analyses, les tranches par classe, l'historique de cours.

Un composeur rend un `App\Shared\Inertia\PageProps` : les props sync, et les différées avec leur groupe. Le contrôleur fait `->render()`, `BuildPortfolioViewSnapshot` fait `->resolve()` — le blob hors-ligne et la page sont identiques par construction. Ajouter une prop à une page, c'est l'ajouter au composeur, une fois ; ne pas la recopier dans le snapshot. Le catalogue (`AssetClassCatalogController`) n'a que du sync et n'entre pas dans le snapshot : pas de composeur.

Les pages se rendent vides, sans erreur, à un visiteur non connecté (`userId` 0) : les actions de Portfolio qui prennent un `User` sont gardées par un `$user === null ? Xxx::empty() : ...` dans le composeur. `WalletPage` et `AssetPage` rendent `null` sur une ressource inconnue ou étrangère, et le contrôleur répond 404, jamais 403.

Ce que PortfolioView calcule lui-même vit dans `Actions/` (`GetBasketAnalysis`, `GetInstrumentAnalysis`, `GetHoldingTrends`, `GetClassCatalog`, `GetInstrumentDetail`) et `Services/` (purs : `ClassBreakdown`, `ChartStep`, `SparklineReducer`, les deux fenêtres). Une formule qui appartient à un voisin reste chez lui : `HoldingValuator`, `PositionAggregator`, `Drawdown`, `TransactionFlow` se demandent, ne se refont pas.

## PortfolioView sert toute page de positions valorisées, et ses lectures prennent un périmètre
Exposition (`/actions`…), fiche instrument (`/asset/{id}`) et enveloppe (`/enveloppes/{id}`) sont trois découpes du même portefeuille : elles vivent ici, avec les mêmes actions et les mêmes sections. Le périmètre passe par `Market\Datas\HoldingScope`, jamais par un `AssetClass` nu : `GetPortfolioOverview`, `GetSectorBreakdown`, `GetTransactionJournal`, `BuildEvolutionSeries`, `BuildPortfolioPerformances`, `GetBasketAnalysis`, `GetAccountBreakdown` le prennent en dernier paramètre. `GetBasketAnalysis` est la seule composition d'analyse : ne pas en écrire une seconde pour une nouvelle découpe.

Deux périmètres restent volontairement larges : le bloc sectoriel d'une **page d'exposition** montre les secteurs du portefeuille entier (`HoldingScope::all()`, comportement d'origine), et la position d'une fiche confond les enveloppes (`GetPortfolioPositions`).

Le journal d'une exposition ne montre aucune ligne sans actif, celui d'une enveloppe montre ses versements et retraits : c'est `GetTransactionJournal` qui porte l'asymétrie, pas la page.

Ni la profondeur d'historique ni le pas de valorisation ne se décident chez Valuation : `AssetPage` lit la série au jour et la ré-échantillonne selon `ChartStep`. Rebrancher un sélecteur de plage passerait par un enum de PortfolioView, pas par `ValuationRange`.
```

- [ ] **Step 3: `wealth.md`, §1**

Remplacer la première section par :

```markdown
## Wealth tient le patrimoine, pas la page d'une enveloppe
Le contexte sert le tableau de bord : `DashboardPage` compose résumé, classes, revenus, secteurs, journal et cartes repliées des enveloppes, et le contrôleur comme `BuildWealthSnapshot` la rendent. Journal et enveloppes viennent de Portfolio directement (`GetTransactionJournal`, `GetAccountBreakdown`) : pas de port vers Portfolio, pas de Data jumelle — `AccountsPort`, `TransactionsPort`, `CashPort`, `WealthAccountData` et `WealthTransactionLineData` ont été retirés. La page `/enveloppes/{id}` appartient à `PortfolioView`. Le seul port du contexte est `AssetClassPort`, le point d'extension des classes de patrimoine.
```

- [ ] **Step 4: `infrastructure.md`, §1**

Remplacer le premier paragraphe par :

```markdown
## La position par actif est calculée une seule fois, par `Portfolio`
`Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory` et les actions de `PortfolioView` (`GetInstrumentDetail`, `GetClassCatalog`, `GetHoldingTrends`, `GetInstrumentAnalysis`) ne recalculent pas la moyenne pondérée du `avg_cost` sur les enveloppes d'un actif : elles lisent `App\Contexts\Portfolio\Actions\GetPortfolioPositions`, qui la calcule via `Portfolio\Services\PositionAggregator` et la valorise via `Portfolio\Services\HoldingValuator`. Aucune classe de ces deux contextes ne doit requêter `Holding` directement — `grep -rn "Holding::query()" app/Contexts/PortfolioView app/Contexts/Income` doit rester vide (hors fixtures de test).
```

Garder la phrase « Une seule formule… » qui suit.

- [ ] **Step 5: `tests.md`, §2**

Remplacer « Depuis que les trois Datas jumelles d'opération (`PortfolioView\TransactionLineData`, `ClassTransactionLineData`, `Wealth\WealthTransactionLineData`) exposent `id` » par « Depuis que `Portfolio\Datas\TransactionLineData` expose `id` ». Le reste de la section est inchangé.

- [ ] **Step 6: Note au spec**

Dans `docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md`, section « Tests », paragraphe **Inchangés.**, remplacer la première phrase par : « Les tests de contrôleurs, sauf les cas « par leurs seuls ports » (trois dans `AssetClassControllerTest`, un dans `AssetControllerTest`) qui prouvaient l'isolation par port et partent avec lui. »

- [ ] **Step 7: Commit**

```bash
git add .ai/rules docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md
git commit -m "docs: la doctrine des ports après la fin du jumelage"
```

---

## Vérification finale

- [ ] `php artisan test --compact` : suite complète verte.
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G` : zéro erreur.
- [ ] `bun run typecheck` : vert (aucun type TS n'a été touché ; les clés JSON ajoutées sont ignorées).
- [ ] `find app/Contexts/PortfolioView -type d` ne liste que `Actions`, `Datas`, `Http`, `Pages`, `Services`.
- [ ] `ls app/Contexts/Wealth/Ports` ne liste que `AssetClassPort.php`.
- [ ] `grep -c 'registers(' app/Providers/AppServiceProvider.php` rend 7 et `grep -c 'scoped\|->bind' app/Providers/AppServiceProvider.php` rend 0.
- [ ] Ouvrir `/enveloppes/{id}`, `/actions`, `/asset/{id}`, `/` dans le navigateur (`bun run dev` ou `bun run build` si le front n'est pas servi) : mêmes écrans qu'avant.
