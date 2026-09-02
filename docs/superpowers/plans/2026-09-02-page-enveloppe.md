# Page d'une enveloppe de détention — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Servir `/enveloppes/{id}` — une page par wallet montrant son en-tête fiscal, ses positions, sa valeur dans le temps, sa répartition par classe d'actif et ses transactions.

**Architecture:** Le contexte `Wealth` sert la page : un contrôleur Inertia, une action mince par section, et des ports vers `Portfolio` (positions, transactions) et `Valuation` (série). `Valuation\BuildExposureSeries` gagne un filtre par enveloppe, ce qui suppose que `TransactionRecordData` porte son `walletId`. Le front réutilise `InstrumentsSection`, `SectorBreakdownList` et `TransactionsSection` grâce à une jumelle `WealthHoldingData` du même JSON que `HoldingLineData`.

**Tech Stack:** Laravel 12 / PHP 8.5, Inertia v3 + Vue 3 (`resources/js/pages`), Pest 5 (tests co-localisés dans `app/Contexts/**Test.php`), Vitest + happy-dom (tests `.test.ts` co-localisés dans `resources/js`), Tailwind 4, Bun.

**Spec:** `docs/superpowers/specs/2026-09-02-page-enveloppe-design.md`

## Global Constraints

- **Tests co-localisés.** Un test PHP vit à côté de la classe qu'il couvre (`app/Contexts/Wealth/Actions/GetWalletAccountTest.php`), jamais dans `tests/` — sauf test de route, qui va dans `tests/Feature/`. Un test de composant Vue vit à côté du `.vue` (`X.test.ts`).
- **Lancer les tests** avec `php artisan test --compact --filter=<nom>` (Pest) et `bun run test <chemin>` (Vitest). Ne jamais lancer la suite entière pendant une tâche.
- **Pint après toute modification PHP** : `vendor/bin/pint --dirty --format agent`.
- **Tout texte visible est en français**, accents compris.
- **Types explicites partout** : types de paramètres, types de retour, `list<T>` en PHPDoc, accolades même pour un corps d'une ligne.
- **PHPDoc plutôt que commentaires en ligne.** Les commentaires expliquent *pourquoi*, jamais *quoi* — c'est la voix du dépôt, voir n'importe quel fichier existant.
- **Jamais de nouvelle dépendance**, jamais de nouveau dossier racine.
- **Commit après chaque tâche**, message en français, préfixe `feat:` / `fix:` / `test:` / `refactor:`.
- **Le contexte `Wealth` ne connaît aucun type de `Portfolio` ni de `Valuation` hors de `Infrastructure/`** — c'est la règle de `.ai/rules/contexts.md`. Un `HoldingLineData` ou un `ValuationSeriesData` dans `Wealth\Actions` ou `Wealth\Http` est un échec de la tâche.
- **L'instantané hors-ligne est hors périmètre** : ne toucher ni `BuildMarketViewSnapshot`, ni `BuildWealthSnapshot`, ni `SnapshotController`. `tests/Feature/SnapshotInvariantTest.php` doit rester vert sans être modifié.

---

## File Structure

**Créés :**

| Fichier | Responsabilité |
| --- | --- |
| `app/Contexts/Wealth/Datas/WealthHoldingData.php` | Une position vue de l'enveloppe. Jumelle de `Portfolio\HoldingLineData`, même JSON clé pour clé. |
| `app/Contexts/Wealth/Datas/WealthHoldingDataTest.php` | Parité des clés des trois jumelles. |
| `app/Contexts/Wealth/Datas/WalletClassSliceData.php` | Une tranche de la répartition par classe d'une enveloppe. |
| `app/Contexts/Wealth/Ports/ValuationPort.php` | La série de valorisation d'une enveloppe. |
| `app/Contexts/Wealth/Infrastructure/WalletValuation.php` | Adaptateur : `BuildExposureSeries` filtrée par enveloppe → `ClassSeriesData`. |
| `app/Contexts/Wealth/Infrastructure/WalletValuationTest.php` | Test de l'adaptateur. |
| `app/Contexts/Wealth/Actions/GetWalletAccount.php` | L'en-tête d'une enveloppe, `null` si inconnue. |
| `app/Contexts/Wealth/Actions/GetWalletAccountTest.php` | |
| `app/Contexts/Wealth/Actions/GetWalletPositions.php` | Les positions d'une enveloppe. |
| `app/Contexts/Wealth/Actions/GetWalletPositionsTest.php` | |
| `app/Contexts/Wealth/Actions/GetWalletBreakdown.php` | Sa répartition par classe. |
| `app/Contexts/Wealth/Actions/GetWalletBreakdownTest.php` | |
| `app/Contexts/Wealth/Actions/GetWalletSeries.php` | Sa valeur dans le temps. |
| `app/Contexts/Wealth/Actions/GetWalletTransactions.php` | Son historique d'opérations. |
| `app/Contexts/Wealth/Actions/GetWalletTransactionsTest.php` | |
| `app/Contexts/Wealth/Http/WalletController.php` | La page : en-tête synchrone, quatre props différées, 404 sinon. |
| `tests/Feature/WalletPageTest.php` | Test de route : props, groupes différés, les deux 404. |
| `resources/js/pages/Wallet/Show.vue` | La page. |
| `resources/js/pages/Wallet/Show.test.ts` | Fil d'Ariane, absence de loupe. |
| `resources/js/components/wallet/WalletHeaderSection.vue` | L'en-tête fiscal déplié. |
| `resources/js/components/wallet/WalletHeaderSection.test.ts` | |
| `resources/js/components/wallet/WalletEvolutionSection.vue` | La courbe valeur / investi de l'enveloppe. |

**Modifiés :**

| Fichier | Modification |
| --- | --- |
| `app/Contexts/Valuation/Datas/TransactionRecordData.php` | `+ public int $walletId` |
| `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistory.php` | passe `walletId` |
| `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` | quatre constructions à compléter |
| `app/Contexts/Valuation/Infrastructure/MemoizedTransactionHistoryTest.php` | une construction à compléter |
| `app/Contexts/Valuation/Actions/BuildExposureSeries.php` | `+ ?int $walletId` et son nom de cache |
| `app/Contexts/Valuation/Actions/BuildExposureSeriesTest.php` | deux cas ajoutés |
| `app/Contexts/Wealth/Ports/AccountsPort.php` | trois méthodes |
| `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php` | leur implémentation |
| `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php` | leurs tests |
| `app/Contexts/Wealth/Ports/TransactionsPort.php` | `transactionsForWallet` |
| `app/Contexts/Wealth/Infrastructure/PortfolioLedger.php` | son implémentation |
| `app/Contexts/Wealth/Infrastructure/PortfolioLedgerTest.php` | son test |
| `app/Contexts/Wealth/WealthProvider.php` | liaison de `ValuationPort` |
| `routes/web.php` | la route |
| `resources/js/lib/wealth.ts` | `WalletClassSlice` |
| `resources/js/components/instruments/InstrumentsSection.vue` | `catalogHref` optionnelle |
| `resources/js/components/instruments/InstrumentsSection.test.ts` | cas sans catalogue |
| `resources/js/components/instruments/TransactionsSection.vue` | `section` en prop facultative |
| `resources/js/components/dashboard/WealthAccountsSection.vue` | les cartes deviennent des liens |
| `resources/js/components/dashboard/WealthAccountsSection.test.ts` | le lien |

---

### Task 1 : `walletId` dans l'historique de valorisation

**Files:**
- Modify: `app/Contexts/Valuation/Datas/TransactionRecordData.php`
- Modify: `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistory.php:38-48`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` (4 constructions)
- Modify: `app/Contexts/Valuation/Infrastructure/MemoizedTransactionHistoryTest.php:19`

**Interfaces:**
- Consomme : rien.
- Produit : `TransactionRecordData::$walletId` (`int`, non nullable), disponible pour Task 2.

- [ ] **Step 1 : Écrire le test qui échoue**

Ajouter à la fin de `app/Contexts/Valuation/Infrastructure/PortfolioTransactionHistoryTest.php` — **le fichier n'existe pas, le créer** avec ce contenu complet :

```php
<?php

use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Infrastructure\PortfolioTransactionHistory;

it('porte l\'enveloppe de chaque transaction', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $records = app(PortfolioTransactionHistory::class)->forUser($user->id);

    expect($records)->not->toBeEmpty()
        ->and($records[0]->walletId)->toBe($wallet->id);
});

it('porte l\'enveloppe d\'un versement, qui n\'a pourtant aucun actif', function () {
    ['user' => $user] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'date' => '2026-02-01',
        'amount' => 500,
    ]);

    $records = app(PortfolioTransactionHistory::class)->forUser($user->id);
    $deposit = array_values(array_filter(
        $records,
        fn ($record): bool => $record->assetId === null,
    ))[0];

    expect($deposit->walletId)->toBe($other->id);
});
```

- [ ] **Step 2 : Lancer le test, vérifier qu'il échoue**

```bash
php artisan test --compact --filter=PortfolioTransactionHistory
```

Attendu : ÉCHEC — `Unknown named parameter $walletId` n'apparaît pas encore ; l'erreur est `Undefined property: App\Contexts\Valuation\Datas\TransactionRecordData::$walletId`.

- [ ] **Step 3 : Ajouter la propriété**

Dans `TransactionRecordData::__construct`, insérer après `public ?int $assetId,` :

```php
        /**
         * L'enveloppe qui tient l'opération. Non nullable, versements compris : une transaction
         * appartient toujours à un compte, c'est ce qui permet à `BuildExposureSeries` de filtrer
         * une série par enveloppe sans laisser échapper le cash.
         */
        public int $walletId,
```

- [ ] **Step 4 : Renseigner la propriété dans l'adaptateur**

Dans `PortfolioTransactionHistory::forUser()`, dans le `new TransactionRecordData(`, après `assetId: ...,` :

```php
                    walletId: (int) $transaction->wallet_id,
```

- [ ] **Step 5 : Compléter les cinq constructions des tests voisins**

`ValuationCalculatorTest.php` — la fonction `tx()` gagne un paramètre et le passe :

```php
function tx(string $date, int $assetId, bool $isSell, float $qty, float $price, float $fees = 0.0, int $walletId = 1): TransactionRecordData
{
    $type = $isSell ? TransactionType::Sell : TransactionType::Buy;

    return new TransactionRecordData(
        date: Carbon::parse($date),
        assetId: $assetId,
        walletId: $walletId,
        type: $type,
        isSell: $isSell,
        quantity: $qty,
        unitPrice: $price,
        fees: $fees,
    );
}
```

`deposit()` du même fichier gagne `walletId: 1,` après `assetId: null,`. Les deux constructions inline
du même fichier (les `it('exposes the unit price…')` et `it('calculateDaily returns one point…')`)
gagnent `walletId: 1,` après `assetId: 1,`. Idem pour l'unique construction de
`MemoizedTransactionHistoryTest.php:19`.

- [ ] **Step 6 : Lancer les tests de Valuation, vérifier qu'ils passent**

```bash
php artisan test --compact --filter=Valuation
```

Attendu : PASS pour tout `app/Contexts/Valuation`.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation
git commit -m "feat: l'historique de valorisation porte l'enveloppe de chaque transaction"
```

---

### Task 2 : filtre par enveloppe dans `BuildExposureSeries`

**Files:**
- Modify: `app/Contexts/Valuation/Actions/BuildExposureSeries.php`
- Modify: `app/Contexts/Valuation/Actions/BuildExposureSeriesTest.php`

**Interfaces:**
- Consomme : `TransactionRecordData::$walletId` (Task 1).
- Produit : `BuildExposureSeries::__invoke(int $userId, ?array $classes = null, ?int $walletId = null): ValuationSeriesData`, consommée par Task 5.

- [ ] **Step 1 : Écrire les tests qui échouent**

Ajouter à la fin de `BuildExposureSeriesTest.php` :

```php
/**
 * Le filtre par enveloppe est plus franc que celui par classe : le cash étant tenu par wallet, un
 * versement sur le compte voisin n'a rien à faire dans la série de celui-ci.
 */
it('ne retient que les transactions de l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $mine = app(BuildExposureSeries::class)($user->id, null, $wallet->id);
    $theirs = app(BuildExposureSeries::class)($user->id, null, $other->id);
    $valuations = $mine->valuations;

    expect(end($valuations))->toBe(1000.0)
        ->and($theirs->labels)->toBe([]);
});

it('n\'attribue pas à une enveloppe le versement fait sur une autre', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $before = app(BuildExposureSeries::class)($user->id, null, $wallet->id);

    Transaction::factory()->deposit()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'date' => '2025-01-01',
        'amount' => 500,
    ]);

    $after = app(BuildExposureSeries::class)($user->id, null, $wallet->id);

    expect($after->labels)->toBe($before->labels)
        ->and($after->valuations)->toBe($before->valuations);
});

it('ne mêle pas deux enveloppes sous le même nom de cache', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    $whole = app(BuildExposureSeries::class)($user->id);
    $one = app(BuildExposureSeries::class)($user->id, null, $wallet->id);
    $none = app(BuildExposureSeries::class)($user->id, null, $other->id);

    expect($whole->valuations)->toBe($one->valuations)
        ->and($none->valuations)->toBe([]);
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=BuildExposureSeries
```

Attendu : ÉCHEC — `Too many arguments to function App\Contexts\Valuation\Actions\BuildExposureSeries::__invoke()`.

- [ ] **Step 3 : Implémenter le filtre**

Dans `BuildExposureSeries`, remplacer `__invoke()` et `build()` par :

```php
    /**
     * @param  ?list<AssetClass>  $classes  Null pour tout le portefeuille.
     * @param  ?int  $walletId  Null pour toutes les enveloppes.
     */
    public function __invoke(int $userId, ?array $classes = null, ?int $walletId = null): ValuationSeriesData
    {
        /**
         * Le filtre entre dans le nom retenu : la série agrège les transactions avant d'exister,
         * elle ne se découpe pas après coup. Un nom réutilisé servirait une classe à l'autre.
         */
        return $this->cache->remember(
            $this->cacheNameOf($classes, $walletId),
            $userId,
            fn (): ValuationSeriesData => $this->build($userId, $classes, $walletId),
        );
    }

    /** @param  ?list<AssetClass>  $classes */
    private function cacheNameOf(?array $classes, ?int $walletId): string
    {
        $name = $classes === null ? 'exposition' : 'exposition.'.$this->nameOf($classes);

        return $walletId === null ? $name : $name.'.enveloppe-'.$walletId;
    }

    /** @param  list<AssetClass>  $classes */
    private function nameOf(array $classes): string
    {
        return implode('-', array_map(fn (AssetClass $class): string => $class->value, $classes));
    }

    /** @param  ?list<AssetClass>  $classes */
    private function build(int $userId, ?array $classes, ?int $walletId): ValuationSeriesData
    {
        $transactions = $this->transactions->forUser($userId);

        /**
         * Le cash est global à l'utilisateur, pas à une exposition : un versement, un retrait ou
         * un dividende sans `asset_id` passe le filtre quelle que soit la classe demandée, sous
         * peine d'un cash construit sur les seuls achats et ventes de cette classe — négatif en
         * permanence, puisqu'il ne verrait jamais les versements qui les ont financés.
         */
        if ($classes !== null) {
            $kept = array_flip($this->directory->idsOfClasses($classes));
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $transaction->assetId === null || isset($kept[$transaction->assetId]),
            ));
        }

        /**
         * Le filtre par enveloppe, lui, ne fait aucune exception au cash : un versement appartient
         * au compte qui l'a reçu, et l'attribuer à ses voisins gonflerait leur apport d'un argent
         * qu'ils n'ont jamais vu. Les deux filtres ne se comportent donc pas pareil, à dessein.
         */
        if ($walletId !== null) {
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => $transaction->walletId === $walletId,
            ));
        }

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $assetIds = array_values(array_unique(array_filter(array_map(
            fn (TransactionRecordData $transaction): ?int => $transaction->assetId,
            $transactions,
        ), fn (?int $assetId): bool => $assetId !== null)));

        return $this->calculator->calculateDaily(
            $transactions,
            $this->prices->forAssetsSince($assetIds, $transactions[0]->date),
        );
    }
```

- [ ] **Step 4 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=BuildExposureSeries
```

Attendu : PASS, les quatre cas d'origine compris.

- [ ] **Step 5 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation
git commit -m "feat: BuildExposureSeries sait filtrer une série par enveloppe"
```

---

### Task 3 : la jumelle `WealthHoldingData`

**Files:**
- Create: `app/Contexts/Wealth/Datas/WealthHoldingData.php`
- Create: `app/Contexts/Wealth/Datas/WealthHoldingDataTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `WealthHoldingData` (14 propriétés, 17 clés JSON), consommée par Task 4.

- [ ] **Step 1 : Écrire le test de parité qui échoue**

`app/Contexts/Wealth/Datas/WealthHoldingDataTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Datas\HoldingRowData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Wealth\Datas\WealthHoldingData;

/**
 * Les trois jumelles doivent rendre le même JSON, clé pour clé et dans le même ordre : le front
 * lit `HoldingLine` sans savoir laquelle l'a produite, et l'instantané hors-ligne publie
 * `sha1(json_encode($body))`, qu'un ordre différent ferait retélécharger à tous les clients.
 */
it('rend exactement les clés de ses deux jumelles, dans le même ordre', function () {
    $arguments = [
        'assetId' => 1,
        'assetName' => 'ACME',
        'ticker' => 'ACM',
        'type' => InstrumentType::Stock,
        'assetClass' => AssetClass::Equity,
        'walletId' => 2,
        'walletName' => 'PEA',
        'accountType' => AccountType::Pea,
        'quantity' => 10.0,
        'avgCost' => 80.0,
        'lastPrice' => 100.0,
        'marketValue' => 1000.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
    ];

    $wealth = (new WealthHoldingData(...$arguments))->jsonSerialize();

    expect(array_keys($wealth))->toBe(array_keys((new HoldingLineData(...$arguments))->jsonSerialize()))
        ->and(array_keys($wealth))->toBe(array_keys((new HoldingRowData(...$arguments))->jsonSerialize()))
        ->and($wealth)->toBe((new HoldingRowData(...$arguments))->jsonSerialize());
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=WealthHoldingData
```

Attendu : ÉCHEC — `Class "App\Contexts\Wealth\Datas\WealthHoldingData" not found`.

- [ ] **Step 3 : Créer la Data**

`app/Contexts/Wealth/Datas/WealthHoldingData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Enums\AccountType;
use JsonSerializable;

/**
 * Une position tenue dans une enveloppe. Troisième jumelle de `Portfolio\HoldingLineData`, après
 * `MarketView\HoldingRowData`, et elle en reproduit le JSON clé pour clé : la page de l'enveloppe
 * réutilise `InstrumentsSection` et `InstrumentList`, qui lisent le type `HoldingLine` du front
 * sans savoir quel contexte l'a servi. `WealthHoldingDataTest` garde cette parité.
 *
 * Dix-sept clés pour quatorze propriétés : `typeLabel`, `assetClassLabel` et `accountTypeLabel` se
 * dérivent de leur enum au moment de sérialiser, jamais recopiés en chaînes à la construction —
 * sinon le libellé affiché cesserait de suivre l'enum le jour où celui-ci change.
 */
readonly class WealthHoldingData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $assetName,
        public ?string $ticker,
        public InstrumentType $type,
        public AssetClass $assetClass,
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
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
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'ticker' => $this->ticker,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'assetClass' => $this->assetClass->value,
            'assetClassLabel' => $this->assetClass->getLabel(),
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
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

- [ ] **Step 4 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=WealthHoldingData
```

Attendu : PASS.

- [ ] **Step 5 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth/Datas
git commit -m "feat: WealthHoldingData, jumelle de la ligne de position pour les enveloppes"
```

---

### Task 4 : `AccountsPort` sait servir une enveloppe

**Files:**
- Create: `app/Contexts/Wealth/Datas/WalletClassSliceData.php`
- Modify: `app/Contexts/Wealth/Ports/AccountsPort.php`
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php`
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php`

**Interfaces:**
- Consomme : `WealthHoldingData` (Task 3), `Portfolio\Actions\GetAccountBreakdown` et `GetPortfolioOverview` (existantes).
- Produit :
  - `AccountsPort::accountFor(int $userId, int $walletId): ?WealthAccountData`
  - `AccountsPort::positionsFor(int $userId, int $walletId): list<WealthHoldingData>`
  - `AccountsPort::breakdownFor(int $userId, int $walletId): list<WalletClassSliceData>`
  - `WalletClassSliceData(string $key, string $label, float $value, float $share)`

- [ ] **Step 1 : Écrire les tests qui échouent**

Ajouter à la fin de `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php` :

```php
it('rend l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $account = app(PortfolioAccounts::class)->accountFor($user->id, $wallet->id);

    expect($account?->walletId)->toBe($wallet->id)
        ->and($account?->marketValue)->toBe(1000.0);
});

it('ne rend rien pour une enveloppe inconnue ou tenue par un autre', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    $accounts = app(PortfolioAccounts::class);

    expect($accounts->accountFor($user->id, 999999))->toBeNull()
        ->and($accounts->accountFor($user->id, $foreign->id))->toBeNull();
});

it('ne rend que les positions de l\'enveloppe demandée', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 400]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 300,
    ]);

    $positions = app(PortfolioAccounts::class)->positionsFor($user->id, $wallet->id);

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id)
        ->and($positions[0]->walletId)->toBe($wallet->id);
});

it('ventile l\'enveloppe par classe d\'actif, la plus grosse part en tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $bitcoin = Instrument::factory()->ofType(InstrumentType::Crypto)->create(['name' => 'Bitcoin']);
    Price::factory()->create(['asset_id' => $bitcoin->id, 'date' => now(), 'close' => 250]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $bitcoin->id,
        'quantity' => 1,
        'avg_cost' => 250,
    ]);

    $slices = app(PortfolioAccounts::class)->breakdownFor($user->id, $wallet->id);

    expect($slices)->toHaveCount(2)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->label)->toBe('Actions')
        ->and($slices[0]->value)->toBe(1000.0)
        ->and($slices[0]->share)->toBe(80.0)
        ->and($slices[1]->key)->toBe('crypto')
        ->and($slices[1]->share)->toBe(20.0);
});

it('ne ventile rien pour une enveloppe sans position', function () {
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    expect(app(PortfolioAccounts::class)->breakdownFor($user->id, $empty->id))->toBe([]);
});
```

Compléter les `use` en tête du fichier de test avec ceux qui manquent : `App\Contexts\Market\Enums\InstrumentType`, `App\Contexts\Market\Models\Instrument`, `App\Contexts\Market\Models\Price`, `App\Contexts\Portfolio\Models\Holding`, `App\Contexts\Portfolio\Models\Wallet`.

> Le libellé `'Actions'` vient de `AssetClass::Equity->getLabel()`. Le vérifier avant d'écrire l'attente : `php artisan tinker --execute 'echo App\Contexts\Market\Enums\AssetClass::Equity->getLabel();'`. Si le libellé diffère, corriger l'attente du test, pas l'enum.

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=PortfolioAccounts
```

Attendu : ÉCHEC — `Call to undefined method …PortfolioAccounts::accountFor()`.

- [ ] **Step 3 : Créer `WalletClassSliceData`**

`app/Contexts/Wealth/Datas/WalletClassSliceData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Une classe d'actif dans une enveloppe : ce qu'elle y vaut, et la part qu'elle y pèse.
 *
 * Le libellé arrive rendu et la part déjà calculée : le front ne regroupe ni ne divise rien, c'est
 * la règle du dépôt — un pourcentage calculé côté écran divergerait de celui du serveur au premier
 * arrondi.
 */
readonly class WalletClassSliceData implements JsonSerializable
{
    /** @param float $share Part de l'enveloppe, en pourcentage. */
    public function __construct(
        public string $key,
        public string $label,
        public float $value,
        public float $share,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'share' => $this->share,
        ];
    }
}
```

- [ ] **Step 4 : Déclarer les trois méthodes au port**

Dans `app/Contexts/Wealth/Ports/AccountsPort.php`, ajouter à l'interface :

```php
    /** L'enveloppe demandée ; `null` quand elle n'existe pas ou qu'un autre porteur la tient. */
    public function accountFor(int $userId, int $walletId): ?WealthAccountData;

    /**
     * Les positions tenues dans l'enveloppe, toutes classes confondues.
     *
     * @return list<WealthHoldingData>
     */
    public function positionsFor(int $userId, int $walletId): array;

    /**
     * La ventilation de l'enveloppe par classe d'actif, la plus grosse part en tête.
     *
     * @return list<WalletClassSliceData>
     */
    public function breakdownFor(int $userId, int $walletId): array;
```

Et les `use` correspondants (`WealthHoldingData`, `WalletClassSliceData`).

- [ ] **Step 5 : Implémenter dans l'adaptateur**

Dans `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php` : injecter `GetPortfolioOverview` en second paramètre de constructeur — l'action est liée en `scoped` et mémoïsée par utilisateur, donc l'injecter ne relit rien — puis ajouter :

```php
    public function accountFor(int $userId, int $walletId): ?WealthAccountData
    {
        foreach ($this->accountsFor($userId) as $account) {
            if ($account->walletId === $walletId) {
                return $account;
            }
        }

        /**
         * Rien plutôt qu'une enveloppe vide : `GetAccountBreakdown` ne rend que les comptes du
         * porteur, si bien que l'enveloppe d'autrui et celle qui n'existe pas se confondent ici —
         * et la page doit répondre 404 aux deux, sans dire laquelle des deux elle a rencontré.
         */
        return null;
    }

    /** @return list<WealthHoldingData> */
    public function positionsFor(int $userId, int $walletId): array
    {
        return array_map(
            fn (HoldingLineData $line): WealthHoldingData => new WealthHoldingData(
                assetId: $line->assetId,
                assetName: $line->assetName,
                ticker: $line->ticker,
                type: $line->type,
                assetClass: $line->assetClass,
                walletId: $line->walletId,
                walletName: $line->walletName,
                accountType: $line->accountType,
                quantity: $line->quantity,
                avgCost: $line->avgCost,
                lastPrice: $line->lastPrice,
                marketValue: $line->marketValue,
                gain: $line->gain,
                gainPct: $line->gainPct,
            ),
            $this->linesOf($userId, $walletId),
        );
    }

    /** @return list<WalletClassSliceData> */
    public function breakdownFor(int $userId, int $walletId): array
    {
        $lines = $this->linesOf($userId, $walletId);

        /** @var array<string, float> $byClass */
        $byClass = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $value = $line->marketValue ?? 0.0;
            $byClass[$line->assetClass->value] = ($byClass[$line->assetClass->value] ?? 0.0) + $value;
            $total += $value;
        }

        /**
         * Une enveloppe sans valeur ne se ventile pas : diviser par zéro donnerait des parts
         * infinies, et une part de zéro pour cent sur chaque classe n'apprendrait rien.
         */
        if ($total <= 0.0) {
            return [];
        }

        $slices = array_map(
            fn (string $class, float $value): WalletClassSliceData => new WalletClassSliceData(
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
            fn (WalletClassSliceData $left, WalletClassSliceData $right): int => $right->value <=> $left->value,
        );

        return $slices;
    }

    /**
     * Les lignes de l'enveloppe, tirées de l'aperçu du portefeuille plutôt que d'une requête à
     * elles : l'action est liée en `scoped` et mémoïse ses lignes par utilisateur, une seconde
     * lecture paierait deux fois le même portefeuille.
     *
     * @return list<HoldingLineData>
     */
    private function linesOf(int $userId, int $walletId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            ($this->overview)($user)->holdings,
            fn (HoldingLineData $line): bool => $line->walletId === $walletId,
        ));
    }
```

Ajouter les `use` manquants : `App\Contexts\Market\Enums\AssetClass`, `App\Contexts\Portfolio\Actions\GetPortfolioOverview`, `App\Contexts\Wealth\Datas\WalletClassSliceData`, `App\Contexts\Wealth\Datas\WealthHoldingData`.

- [ ] **Step 6 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=PortfolioAccounts
```

Attendu : PASS, les cas d'origine du fichier compris.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth
git commit -m "feat: AccountsPort sert une enveloppe, ses positions et sa ventilation"
```

---

### Task 5 : `TransactionsPort` sait servir le journal d'une enveloppe

**Files:**
- Modify: `app/Contexts/Wealth/Ports/TransactionsPort.php`
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioLedger.php`
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioLedgerTest.php`

**Interfaces:**
- Consomme : `WealthTransactionLineData` (existante).
- Produit : `TransactionsPort::transactionsForWallet(int $userId, int $walletId): list<WealthTransactionLineData>`.

- [ ] **Step 1 : Écrire le test qui échoue**

Ajouter à la fin de `app/Contexts/Wealth/Infrastructure/PortfolioLedgerTest.php` :

```php
it('ne rend que les opérations de l\'enveloppe demandée, la plus récente en tête', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $other = Wallet::factory()->for($user)->create(['name' => 'Second compte']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 5,
        'unit_price' => 90,
        'date' => '2026-03-01',
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $other->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'unit_price' => 95,
        'date' => '2026-04-01',
    ]);

    $lines = app(PortfolioLedger::class)->transactionsForWallet($user->id, $wallet->id);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->date)->toBe('2026-03-01')
        ->and(array_unique(array_map(fn ($line): int => $line->walletId, $lines)))->toBe([$wallet->id]);
});

it('ne rend rien pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    expect(app(PortfolioLedger::class)->transactionsForWallet($user->id, $foreign->id))->toBe([]);
});
```

Compléter les `use` du fichier avec `App\Contexts\Portfolio\Models\Transaction` et `App\Contexts\Portfolio\Models\Wallet` s'ils manquent.

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=PortfolioLedger
```

Attendu : ÉCHEC — `Call to undefined method …PortfolioLedger::transactionsForWallet()`.

- [ ] **Step 3 : Déclarer au port**

Dans `TransactionsPort` :

```php
    /**
     * Les opérations d'une enveloppe, la plus récente en tête. Scopée en base et non filtrée en
     * mémoire : le journal d'un compte n'a pas à charger l'historique entier du porteur.
     *
     * @return list<WealthTransactionLineData>
     */
    public function transactionsForWallet(int $userId, int $walletId): array;
```

- [ ] **Step 4 : Implémenter dans l'adaptateur**

Dans `PortfolioLedger`, extraire le corps existant de `transactionsFor()` dans une méthode privée qui accepte une enveloppe facultative, puis exposer les deux entrées :

```php
    /** @return list<WealthTransactionLineData> */
    public function transactionsFor(int $userId): array
    {
        return $this->read($userId, null);
    }

    /** @return list<WealthTransactionLineData> */
    public function transactionsForWallet(int $userId, int $walletId): array
    {
        return $this->read($userId, $walletId);
    }

    /**
     * La jointure nomme l'actif en une requête : une ligne par opération, chacune chargeant son
     * actif rouvrirait un N+1 sur tout l'historique.
     *
     * `leftJoin` et non `join` : un versement ou un retrait n'a pas d'`asset_id`, et le tableau de
     * bord doit quand même les afficher — c'est le seul des trois journaux à le faire, les deux
     * jumelles de `MarketView` restant scopées à un actif ou une exposition.
     *
     * `$walletId` non nul ajoute le seul filtre qui distingue le journal d'une enveloppe de celui
     * du patrimoine ; le `user_id` reste posé dans les deux cas, sans quoi l'identifiant d'une
     * enveloppe d'autrui suffirait à lire son historique.
     *
     * @return list<WealthTransactionLineData>
     */
    private function read(int $userId, ?int $walletId): array
    {
        return Transaction::query()
            ->leftJoin('assets', 'assets.id', '=', 'transactions.asset_id')
            ->where('transactions.user_id', $userId)
            ->when($walletId !== null, fn ($query) => $query->where('transactions.wallet_id', $walletId))
            ->orderByDesc('transactions.date')
            ->orderByDesc('transactions.id')
            ->select('transactions.*', 'assets.name as asset_name')
            ->get()
            // … corps de map() inchangé, repris tel quel de transactionsFor()
            ->values()
            ->all();
    }
```

Le `map()` existant se déplace tel quel dans `read()`, sans une ligne de changement. Typer la
fermeture du `when()` : `fn (Builder $query): Builder => …`, avec
`use Illuminate\Database\Eloquent\Builder;`.

- [ ] **Step 5 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=PortfolioLedger
```

Attendu : PASS. Puis vérifier que le tableau de bord n'a pas bougé :

```bash
php artisan test --compact --filter=GetWealthTransactions
```

- [ ] **Step 6 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth
git commit -m "feat: le journal d'opérations se scope à une enveloppe"
```

---

### Task 6 : `ValuationPort` et la série d'une enveloppe

**Files:**
- Create: `app/Contexts/Wealth/Ports/ValuationPort.php`
- Create: `app/Contexts/Wealth/Infrastructure/WalletValuation.php`
- Create: `app/Contexts/Wealth/Infrastructure/WalletValuationTest.php`
- Modify: `app/Contexts/Wealth/WealthProvider.php`

**Interfaces:**
- Consomme : `BuildExposureSeries::__invoke($userId, $classes, $walletId)` (Task 2), `ClassSeriesData` (existante).
- Produit : `Wealth\Ports\ValuationPort::seriesForWallet(int $userId, int $walletId): ClassSeriesData`, liée à `WalletValuation` dans le conteneur.

- [ ] **Step 1 : Écrire le test qui échoue**

`app/Contexts/Wealth/Infrastructure/WalletValuationTest.php` :

```php
<?php

use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Ports\ValuationPort;

it('rend la série de l\'enveloppe, valeur et investi alignés sur ses labels', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $wallet->id);
    $value = $series->value;

    expect($series->labels)->not->toBeEmpty()
        ->and($value)->toHaveCount(count($series->labels))
        ->and($series->invested)->toHaveCount(count($series->labels))
        ->and(end($value))->toBe(1000.0);
});

it('rend une série vide pour une enveloppe sans opération', function () {
    ['user' => $user] = portfolioFixture();
    $empty = Wallet::factory()->for($user)->create(['name' => 'Compte vide']);

    $series = app(ValuationPort::class)->seriesForWallet($user->id, $empty->id);

    expect($series->labels)->toBe([])
        ->and($series->value)->toBe([])
        ->and($series->invested)->toBe([]);
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=WalletValuation
```

Attendu : ÉCHEC — `Target interface [App\Contexts\Wealth\Ports\ValuationPort] is not instantiable`.

- [ ] **Step 3 : Créer le port**

`app/Contexts/Wealth/Ports/ValuationPort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;

/**
 * La valeur d'une enveloppe dans le temps. Une enveloppe à la fois : le patrimoine entier se lit
 * depuis le tableau de bord, pas d'ici.
 *
 * `Wealth` ne connaît pas `Valuation` : ce port cache `BuildExposureSeries` et son filtre par
 * enveloppe, comme `AccountsPort` cache `GetAccountBreakdown`.
 */
interface ValuationPort
{
    public function seriesForWallet(int $userId, int $walletId): ClassSeriesData;
}
```

- [ ] **Step 4 : Créer l'adaptateur**

`app/Contexts/Wealth/Infrastructure/WalletValuation.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Valuation\Actions\BuildExposureSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\ValuationPort;

/**
 * L'action est injectée, jamais construite : sa série est retenue sous un nom de cache qui porte
 * l'enveloppe, et le contrôleur de la page la redemande à l'identique quand une prop différée
 * revient.
 *
 * Aucun calcul ici, seulement une traduction : `ValuationSeriesData::$valuations` devient
 * `ClassSeriesData::$value`, le nom que le patrimoine emploie pour ses séries.
 */
class WalletValuation implements ValuationPort
{
    public function __construct(private BuildExposureSeries $series) {}

    public function seriesForWallet(int $userId, int $walletId): ClassSeriesData
    {
        $series = ($this->series)($userId, null, $walletId);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $series->valuations,
            invested: $series->invested,
        );
    }
}
```

- [ ] **Step 5 : Lier le port dans `WealthProvider::registers()`**

Après la liaison de `CashPort`, ajouter :

```php
        /**
         * Interne à la page d'une enveloppe, comme `CashPort` l'est à `CashClass` : aucun appelant
         * hors de `Wealth` ne le consomme, donc pas de paramètre dédié — le fixer ici suffit.
         */
        $app->bind(ValuationPort::class, WalletValuation::class);
```

Avec les `use` : `App\Contexts\Wealth\Infrastructure\WalletValuation`, `App\Contexts\Wealth\Ports\ValuationPort`.

- [ ] **Step 6 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=WalletValuation
```

Attendu : PASS.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth
git commit -m "feat: la valeur d'une enveloppe dans le temps, derrière son port"
```

---

### Task 7 : les cinq actions de lecture

**Files:**
- Create: `app/Contexts/Wealth/Actions/GetWalletAccount.php` + `GetWalletAccountTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWalletPositions.php` + `GetWalletPositionsTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWalletBreakdown.php` + `GetWalletBreakdownTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWalletSeries.php`
- Create: `app/Contexts/Wealth/Actions/GetWalletTransactions.php` + `GetWalletTransactionsTest.php`

**Interfaces:**
- Consomme : les trois méthodes d'`AccountsPort` (Task 4), `TransactionsPort::transactionsForWallet` (Task 5), `ValuationPort::seriesForWallet` (Task 6).
- Produit, toutes en `__invoke(int $userId, int $walletId)` :
  - `GetWalletAccount` → `?WealthAccountData`
  - `GetWalletPositions` → `list<WealthHoldingData>`
  - `GetWalletBreakdown` → `list<WalletClassSliceData>`
  - `GetWalletSeries` → `ClassSeriesData`
  - `GetWalletTransactions` → `list<WealthTransactionLineData>`

- [ ] **Step 1 : Écrire les tests qui échouent**

`app/Contexts/Wealth/Actions/GetWalletAccountTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWalletAccount;

it('rend l\'enveloppe du porteur', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    expect(app(GetWalletAccount::class)($user->id, $wallet->id)?->walletId)->toBe($wallet->id);
});

it('ne rend rien pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    expect(app(GetWalletAccount::class)($user->id, $foreign->id))->toBeNull();
});
```

`GetWalletPositionsTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWalletPositions;

it('rend les positions de l\'enveloppe', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $positions = app(GetWalletPositions::class)($user->id, $wallet->id);

    expect($positions)->toHaveCount(1)
        ->and($positions[0]->assetId)->toBe($instrument->id);
});
```

`GetWalletBreakdownTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWalletBreakdown;

it('ventile l\'enveloppe par classe d\'actif', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $slices = app(GetWalletBreakdown::class)($user->id, $wallet->id);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->key)->toBe('equity')
        ->and($slices[0]->share)->toBe(100.0);
});
```

`GetWalletTransactionsTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWalletTransactions;

it('rend les opérations de l\'enveloppe', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $lines = app(GetWalletTransactions::class)($user->id, $wallet->id);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->walletId)->toBe($wallet->id);
});
```

`GetWalletSeries` n'a pas de test à elle : `WalletValuationTest` couvre déjà la série, et l'action
ne fait que déléguer sans rien décider — un test ici ne vérifierait que le conteneur.

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=GetWallet
```

Attendu : ÉCHEC — `Class "App\Contexts\Wealth\Actions\GetWalletAccount" not found`.

- [ ] **Step 3 : Écrire les cinq actions**

`GetWalletAccount.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * L'en-tête d'une enveloppe : ce qu'elle vaut, et les règles qu'elle déclare. `null` quand elle
 * n'existe pas ou qu'un autre porteur la tient — la page en fait un 404, sans dire lequel des deux
 * cas elle a rencontré.
 */
class GetWalletAccount
{
    public function __construct(private AccountsPort $accounts) {}

    public function __invoke(int $userId, int $walletId): ?WealthAccountData
    {
        return $this->accounts->accountFor($userId, $walletId);
    }
}
```

`GetWalletPositions.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthHoldingData;
use App\Contexts\Wealth\Ports\AccountsPort;

/** Les positions tenues dans une enveloppe, toutes classes confondues. */
class GetWalletPositions
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WealthHoldingData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->accounts->positionsFor($userId, $walletId);
    }
}
```

`GetWalletBreakdown.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WalletClassSliceData;
use App\Contexts\Wealth\Ports\AccountsPort;

/** Ce que l'enveloppe expose, classe par classe. Vide quand elle ne vaut rien. */
class GetWalletBreakdown
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WalletClassSliceData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->accounts->breakdownFor($userId, $walletId);
    }
}
```

`GetWalletSeries.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\ValuationPort;

/** La valeur d'une enveloppe dans le temps, comparée à ce qui y a été mis. */
class GetWalletSeries
{
    public function __construct(private ValuationPort $valuation) {}

    public function __invoke(int $userId, int $walletId): ClassSeriesData
    {
        return $this->valuation->seriesForWallet($userId, $walletId);
    }
}
```

`GetWalletTransactions.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthTransactionLineData;
use App\Contexts\Wealth\Ports\TransactionsPort;

/** L'historique d'une enveloppe, la plus récente en tête. */
class GetWalletTransactions
{
    public function __construct(private TransactionsPort $transactions) {}

    /** @return list<WealthTransactionLineData> */
    public function __invoke(int $userId, int $walletId): array
    {
        return $this->transactions->transactionsForWallet($userId, $walletId);
    }
}
```

- [ ] **Step 4 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=GetWallet
```

Attendu : PASS.

- [ ] **Step 5 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth/Actions
git commit -m "feat: les cinq lectures d'une enveloppe, une action par section"
```

---

### Task 8 : route, contrôleur, page nue

**Files:**
- Create: `app/Contexts/Wealth/Http/WalletController.php`
- Create: `tests/Feature/WalletPageTest.php`
- Create: `resources/js/pages/Wallet/Show.vue`
- Modify: `routes/web.php`
- Modify: `resources/js/lib/wealth.ts`

**Interfaces:**
- Consomme : les cinq actions (Task 7).
- Produit : la route nommée `wallets.show`, la page Inertia `Wallet/Show` et ses props `account` (synchrone), `positions`, `breakdown`, `evolution`, `transactions` (différées, groupes `positions`, `repartition`, `evolution`, `transactions`). Types front `WalletClassSlice` dans `lib/wealth.ts`.

- [ ] **Step 1 : Écrire le test de route qui échoue**

`tests/Feature/WalletPageTest.php` :

```php
<?php

use App\Contexts\Portfolio\Models\Wallet;

it('sert la page de l\'enveloppe avec son en-tête', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Wallet/Show')
            ->where('account.walletId', $wallet->id)
            ->where('account.marketValue', 1000.0)
        );
});

it('sert les positions, la ventilation, la série et le journal en props différées', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $response = $this->actingAs($user)->get("/enveloppes/{$wallet->id}");

    $deferred = $response->viewData('page')['deferredProps'];

    expect($deferred)->toHaveKeys(['positions', 'repartition', 'evolution', 'transactions'])
        ->and($deferred['positions'])->toBe(['positions'])
        ->and($deferred['repartition'])->toBe(['breakdown'])
        ->and($deferred['evolution'])->toBe(['evolution'])
        ->and($deferred['transactions'])->toBe(['transactions']);
});

it('résout les positions et le journal quand leur groupe est demandé', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user)
        ->get("/enveloppes/{$wallet->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'Wallet/Show',
            'X-Inertia-Partial-Data' => 'positions,transactions',
        ])
        ->assertOk()
        ->assertJsonPath('props.positions.0.assetId', $instrument->id)
        ->assertJsonPath('props.transactions.0.walletId', $wallet->id);
});

it('répond 404 pour une enveloppe inconnue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)->get('/enveloppes/999999')->assertNotFound();
});

/** 404 et non 403 : le code ne doit pas révéler que l'enveloppe existe. */
it('répond 404 pour l\'enveloppe d\'un autre porteur', function () {
    ['user' => $user] = portfolioFixture();
    ['wallet' => $foreign] = portfolioFixture();

    $this->actingAs($user)->get("/enveloppes/{$foreign->id}")->assertNotFound();
});

it('renvoie vers l\'accueil quand l\'identifiant n\'est pas numérique', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)->get('/enveloppes/pea')->assertRedirect('/');
});
```

> Le nom exact de la clé des props différées dans `viewData('page')` dépend de la version
> d'Inertia. Avant d'écrire ce test, vérifier la forme réelle sur une page existante :
> `php artisan test --compact --filter=AssetClassPage` puis, si besoin,
> `grep -rn "deferredProps" tests app vendor/inertiajs | head`. Si la clé diffère, adapter le test
> — pas la production. Si aucun test existant n'inspecte les groupes différés, remplacer ce
> deuxième cas par une assertion `assertInertia(fn ($page) => $page->missing('positions'))`, qui
> vérifie la même chose de l'extérieur : la prop n'est pas dans la réponse initiale.

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
php artisan test --compact --filter=WalletPage
```

Attendu : ÉCHEC — 302 vers `/` (aucune route ne matche), pas 200.

- [ ] **Step 3 : Écrire le contrôleur**

`app/Contexts/Wealth/Http/WalletController.php` :

```php
<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Wealth\Actions\GetWalletAccount;
use App\Contexts\Wealth\Actions\GetWalletBreakdown;
use App\Contexts\Wealth\Actions\GetWalletPositions;
use App\Contexts\Wealth\Actions\GetWalletSeries;
use App\Contexts\Wealth\Actions\GetWalletTransactions;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention. Calquée sur `MarketView\Http\AssetClassController` :
 * l'en-tête synchrone pour qu'il ne saute pas à l'arrivée, chaque autre section derrière son propre
 * groupe différé, qui ne part qu'au dépli pour les sections repliées.
 *
 * L'instantané hors-ligne ne porte pas cette page : ses sections différées afficheront leur état
 * « indisponible hors-ligne », c'est assumé.
 */
class WalletController
{
    public function __construct(
        private GetWalletAccount $getAccount,
        private GetWalletPositions $getPositions,
        private GetWalletBreakdown $getBreakdown,
        private GetWalletSeries $getSeries,
        private GetWalletTransactions $getTransactions,
    ) {}

    public function __invoke(int $id): Response
    {
        $userId = auth()->id() ?? 0;

        $account = ($this->getAccount)($userId, $id);

        /**
         * 404 et non page vide : l'enveloppe inconnue et celle d'un autre porteur se confondent
         * ici, et le code ne doit pas révéler laquelle des deux a été demandée.
         */
        if ($account === null) {
            abort(404);
        }

        return Inertia::render('Wallet/Show', [
            'account' => $account,
            'positions' => Inertia::defer(fn (): array => ($this->getPositions)($userId, $id), 'positions'),
            'breakdown' => Inertia::defer(fn (): array => ($this->getBreakdown)($userId, $id), 'repartition'),
            'evolution' => Inertia::defer(fn () => ($this->getSeries)($userId, $id), 'evolution'),
            /** Repliée à l'arrivée : l'historique du compte ne se charge que pour qui le déplie. */
            'transactions' => Inertia::defer(fn (): array => ($this->getTransactions)($userId, $id), 'transactions'),
        ]);
    }
}
```

- [ ] **Step 4 : Déclarer la route**

Dans `routes/web.php`, après la boucle des expositions et avant `Route::get('/asset/{id}', …)` :

```php
/**
 * La page d'une enveloppe de détention. Une adresse par ligne de `wallets` et non par cas de
 * `AccountType` : trois PEA distincts ont trois anciennetés et trois comptes espèces, un seul
 * chiffre ne saurait les dire.
 */
Route::get('/enveloppes/{id}', WalletController::class)
    ->whereNumber('id')
    ->name('wallets.show');
```

Avec l'import `use App\Contexts\Wealth\Http\WalletController;`.

- [ ] **Step 5 : Ajouter les types front**

Dans `resources/js/lib/wealth.ts`, à la suite de `WealthAccount` :

```ts
/** Une classe d'actif dans une enveloppe : ce qu'elle y vaut, et la part qu'elle y pèse. */
export interface WalletClassSlice {
    key: string;
    label: string;
    value: number;
    /** Part de l'enveloppe, en pourcentage. */
    share: number;
}
```

- [ ] **Step 6 : Créer la page nue**

`resources/js/pages/Wallet/Show.vue` — les sections propres arrivent en Task 9 ; cette étape ne pose
que la coquille qui fait passer le test de route :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import TransactionDialog from '@/components/transactions/TransactionDialog.vue';
import type { HoldingLine } from '@/lib/portfolio';
import type { ClassSeries, WalletClassSlice, WealthAccount, WealthTransactionLine } from '@/lib/wealth';

const props = defineProps<{
    account: WealthAccount;
    positions?: HoldingLine[];
    breakdown?: WalletClassSlice[];
    evolution?: ClassSeries;
    transactions?: WealthTransactionLine[];
}>();

/** Le courtier titre la page quand il est connu ; le nom du portefeuille sinon. */
const title = (): string => `${props.account.broker ?? props.account.walletName} (${props.account.accountTypeLabel})`;
</script>

<template>
    <Head :title="title()" />

    <AppPage />

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title() }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
```

> `ClassSeries` doit exister dans `lib/wealth.ts` (`{ labels: string[]; value: number[]; invested: number[] }`).
> Vérifier par `grep -n "ClassSeries" resources/js/lib/wealth.ts` ; si le type n'y est pas, l'ajouter
> avec le même commentaire de rôle que ses voisins.

- [ ] **Step 7 : Lancer, vérifier que ça passe**

```bash
php artisan test --compact --filter=WalletPage
bun run build
```

Attendu : PASS, et le build passe (la page est référencée par `Inertia::render`, une page absente le
casserait).

- [ ] **Step 8 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth routes/web.php resources/js
git commit -m "feat: /enveloppes/{id} sert la page d'une enveloppe"
```

---

### Task 9 : l'en-tête fiscal

**Files:**
- Create: `resources/js/components/wallet/WalletHeaderSection.vue`
- Create: `resources/js/components/wallet/WalletHeaderSection.test.ts`
- Modify: `resources/js/pages/Wallet/Show.vue`

**Interfaces:**
- Consomme : `WealthAccount` (`lib/wealth.ts`), `HeroFigures`, `GainPill`, `eur`/`pct` (`lib/format`).
- Produit : `WalletHeaderSection` avec la prop `account: WealthAccount`, et les crochets de test
  `[data-wallet-regime]`, `[data-wallet-cash]`, `[data-wallet-age]`, `[data-wallet-alert]`.

- [ ] **Step 1 : Écrire le test qui échoue**

`resources/js/components/wallet/WalletHeaderSection.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import type { WealthAccount } from '@/lib/wealth';
import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';

const account = (overrides: Partial<WealthAccount> = {}): WealthAccount => ({
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    ageInYears: 7,
    maturityYears: 5,
    taxRegimeLabel: 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
    ineligibleAssetNames: [],
    broker: 'IBKR',
    cashBalance: 150,
    ...overrides,
});

function mountHeader(value: WealthAccount): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(WalletHeaderSection, { account: value }).mount(host);

    return host;
}

const text = (host: HTMLElement, selector: string): string | undefined =>
    host.querySelector(selector)?.textContent?.replace(/\s+/g, ' ').trim();

describe('en-tête de la page d\'une enveloppe', () => {
    it('affiche le régime fiscal et le solde d\'espèces', () => {
        const host = mountHeader(account());

        expect(text(host, '[data-wallet-regime]')).toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %');
        expect(text(host, '[data-wallet-cash]')).toContain('150');
    });

    it('dit le seuil franchi quand l\'enveloppe a l\'âge requis', () => {
        expect(text(mountHeader(account()), '[data-wallet-age]'))
            .toBe('Ouverte depuis 7 ans · Seuil de 5 ans franchi');
    });

    it('compte les années restantes quand le seuil n\'est pas atteint', () => {
        expect(text(mountHeader(account({ ageInYears: 3 })), '[data-wallet-age]'))
            .toBe('Ouverte depuis 3 ans · Seuil de 5 ans dans 2 ans');
    });

    /** Une enveloppe « 0 an » mentirait : sans date d'ouverture, la ligne disparaît. */
    it('taît l\'ancienneté quand la date d\'ouverture est inconnue', () => {
        expect(mountHeader(account({ ageInYears: null })).querySelector('[data-wallet-age]')).toBeNull();
    });

    it('accorde le singulier de l\'année', () => {
        expect(text(mountHeader(account({ ageInYears: 1, maturityYears: null })), '[data-wallet-age]'))
            .toBe('Ouverte depuis 1 an');
    });

    it('alerte sur les actifs que l\'enveloppe n\'admet pas', () => {
        const host = mountHeader(account({ ineligibleAssetNames: ['Bitcoin', 'Ethereum'] }));

        expect(text(host, '[data-wallet-alert]')).toBe('Non éligible à cette enveloppe : Bitcoin, Ethereum');
    });

    it('n\'alerte pas quand tout est éligible', () => {
        expect(mountHeader(account()).querySelector('[data-wallet-alert]')).toBeNull();
    });
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
bun run test resources/js/components/wallet/WalletHeaderSection.test.ts
```

Attendu : ÉCHEC — le module `@/components/wallet/WalletHeaderSection.vue` n'existe pas.

- [ ] **Step 3 : Écrire le composant**

`resources/js/components/wallet/WalletHeaderSection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import HeroFigures from '@/components/HeroFigures.vue';
import { eur, pct } from '@/lib/format';
import type { HeroMetaEntry } from '@/lib/instrument';
import type { WealthAccount } from '@/lib/wealth';

const props = defineProps<{ account: WealthAccount }>();

/** Accord du singulier : « 1 an », jamais « 1 ans ». Même règle que la carte du tableau de bord. */
const years = (n: number): string => (n === 1 ? '1 an' : `${n} ans`);

const maturity = computed<string | null>(() => {
    const { ageInYears, maturityYears } = props.account;

    if (maturityYears === null || ageInYears === null) {
        return null;
    }

    return ageInYears >= maturityYears
        ? `Seuil de ${years(maturityYears)} franchi`
        : `Seuil de ${years(maturityYears)} dans ${years(maturityYears - ageInYears)}`;
});

/** L'ancienneté ne s'affiche pas sans date d'ouverture : un compte « 0 an » mentirait. */
const age = computed<string | null>(() => {
    if (props.account.ageInYears === null) {
        return null;
    }

    const opened = `Ouverte depuis ${years(props.account.ageInYears)}`;

    return maturity.value === null ? opened : `${opened} · ${maturity.value}`;
});

const entries = computed<HeroMetaEntry[]>(() => [
    { label: 'Espèces', value: eur(props.account.cashBalance, 0) },
]);
</script>

<template>
    <section data-section="wallet-header" class="flex shrink-0 flex-col gap-1.5 px-6">
        <HeroFigures
            :value="props.account.marketValue"
            :gain="props.account.gain"
            :gain-label="pct(props.account.gainPct)"
            :entries="entries"
        >
            <template #beneath-value>
                <span data-wallet-cash class="sr-only">{{ eur(props.account.cashBalance, 0) }}</span>
            </template>
        </HeroFigures>

        <p data-wallet-regime class="text-xs text-muted-foreground">{{ props.account.taxRegimeLabel }}</p>

        <p v-if="age" data-wallet-age class="text-xs text-subtle-foreground">{{ age }}</p>

        <p
            v-if="props.account.ineligibleAssetNames.length"
            data-wallet-alert
            class="text-xs font-semibold text-loss"
        >
            Non éligible à cette enveloppe : {{ props.account.ineligibleAssetNames.join(', ') }}
        </p>
    </section>
</template>
```

> Le `data-wallet-cash` en `sr-only` dans le slot est un doublon du repère « Espèces » rendu par
> `HeroMetaList`, dont le test ne peut pas cibler la ligne. Préférer, si c'est faisable en une
> ligne : supprimer ce slot et cibler `[data-hero-meta]` dans le test. Choisir l'une des deux
> formes, pas les deux — un doublon visible à l'écran serait un défaut.

- [ ] **Step 4 : Lancer, vérifier que ça passe**

```bash
bun run test resources/js/components/wallet/WalletHeaderSection.test.ts
```

Attendu : PASS, huit cas.

- [ ] **Step 5 : Brancher l'en-tête dans la page**

Dans `resources/js/pages/Wallet/Show.vue`, remplacer `<AppPage />` par :

```vue
    <AppPage>
        <WalletHeaderSection :account="props.account" />
    </AppPage>
```

et ajouter l'import `import WalletHeaderSection from '@/components/wallet/WalletHeaderSection.vue';`.

- [ ] **Step 6 : Commit**

```bash
bun run build
git add resources/js
git commit -m "feat: l'en-tête fiscal de la page d'une enveloppe"
```

---

### Task 10 : positions, ventilation, courbe, journal

**Files:**
- Create: `resources/js/components/wallet/WalletEvolutionSection.vue`
- Modify: `resources/js/components/instruments/InstrumentsSection.vue`
- Modify: `resources/js/components/instruments/InstrumentsSection.test.ts`
- Modify: `resources/js/components/instruments/TransactionsSection.vue`
- Modify: `resources/js/pages/Wallet/Show.vue`
- Create: `resources/js/pages/Wallet/Show.test.ts`

**Interfaces:**
- Consomme : `WalletClassSlice`, `ClassSeries`, `HoldingLine`, `WealthTransactionLine`, les props différées de Task 8, `WalletHeaderSection` de Task 9.
- Produit : la page complète, `InstrumentsSection` avec `catalogHref` facultative, `TransactionsSection` avec `section` facultative.

- [ ] **Step 1 : Écrire les tests qui échouent**

Ajouter à `resources/js/components/instruments/InstrumentsSection.test.ts`, dans le `describe`
existant, et adapter `mountSection` pour accepter des props :

```ts
function mountSection(props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(InstrumentsSection, { holdings, trends: [], catalogHref: '/actions/catalogue', ...props }).mount(host);

    return host;
}

/** Une enveloppe n'a pas de catalogue : sans adresse, la loupe n'a nulle part à mener. */
it('n\'affiche aucune loupe quand aucun catalogue n\'est donné', () => {
    expect(mountSection({ catalogHref: undefined }).querySelector('[data-catalog-link]')).toBeNull();
});
```

Créer `resources/js/pages/Wallet/Show.test.ts` :

```ts
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';
import type { WealthAccount } from '@/lib/wealth';

/**
 * La page monte cinq sections dont deux dépendent d'Inertia : `Head` et `Deferred` n'ont rien à
 * rendre ici, `Link` se réduit à son ancrage, `usePage` sert les props rescapées.
 */
vi.mock('@inertiajs/vue3', () => ({
    Head: { setup: () => () => null },
    Deferred: { setup: () => () => null },
    usePage: () => ({ rescuedProps: [] }),
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
}));

const { default: Show } = await import('@/pages/Wallet/Show.vue');

const account: WealthAccount = {
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    ageInYears: 7,
    maturityYears: 5,
    taxRegimeLabel: 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
    ineligibleAssetNames: [],
    broker: 'IBKR',
    cashBalance: 150,
};

function mountPage(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(Show, { account, positions: [], breakdown: [], transactions: [] }).mount(host);

    return host;
}

describe('page d\'une enveloppe', () => {
    it('nomme l\'enveloppe dans son fil d\'Ariane, courtier puis type', () => {
        const labels = [...mountPage().querySelectorAll('[data-bottom-bar] a, [data-bottom-bar] span')]
            .map((node) => node.textContent?.trim())
            .filter(Boolean);

        expect(labels).toContain('Tableau de bord');
        expect(labels).toContain('IBKR (PEA)');
    });

    it('n\'offre pas de catalogue depuis une enveloppe', () => {
        expect(mountPage().querySelector('[data-catalog-link]')).toBeNull();
    });

    it('monte les cinq sections de la page', () => {
        const host = mountPage();

        for (const section of ['wallet-header', 'wallet-evolution', 'instruments', 'wallet-breakdown', 'class-transactions']) {
            expect(host.querySelector(`[data-section="${section}"]`), section).not.toBeNull();
        }
    });
});
```

> Si `TransactionDialog` ou `AsyncBaseChart` refusent de monter dans happy-dom, les remplacer par
> `vi.mock` avec un composant vide — le même procédé que le mock d'Inertia ci-dessus. Ne pas
> modifier les composants de production pour satisfaire le test.

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
bun run test resources/js/pages/Wallet/Show.test.ts resources/js/components/instruments/InstrumentsSection.test.ts
```

Attendu : ÉCHEC — la loupe est toujours rendue sans `catalogHref`, et les sections `wallet-evolution`
et `wallet-breakdown` n'existent pas.

- [ ] **Step 3 : Rendre `catalogHref` facultative**

Dans `InstrumentsSection.vue`, remplacer la déclaration de props par :

```ts
const props = defineProps<{
    holdings: HoldingLine[];
    trends?: CatalogTrend[] | null;
    /**
     * Le catalogue de la poche : la section ne montre que les positions, la loupe mène au reste.
     * Absente sur la page d'une enveloppe, qui n'a pas de catalogue — la loupe disparaît alors.
     */
    catalogHref?: string;
}>();
```

et poser la garde sur le lien :

```vue
        <template #aside>
            <Link
                v-if="props.catalogHref"
                :href="props.catalogHref"
```

- [ ] **Step 4 : Rendre la clé de section de `TransactionsSection` facultative**

Dans `TransactionsSection.vue` :

```ts
const props = withDefaults(
    defineProps<{
        transactions?: NamedTransactionLine[] | null;
        /**
         * Ce que nomme `data-section` : l'état du pli est un `ref` local, rien n'est partagé entre
         * pages — c'est la lecture du DOM et les tests qui veulent savoir de quelle page il s'agit.
         */
        section?: string;
        emptyLabel?: string;
    }>(),
    { transactions: null, section: 'class-transactions', emptyLabel: 'Aucune transaction sur cette classe.' },
);
```

puis passer `:section="props.section"` à `CollapsibleSection` et `:empty-label="props.emptyLabel"` à
`TransactionYearList`. Vérifier d'abord la forme réelle du fichier : s'il n'a pas de `withDefaults`,
en ajouter un sans changer les valeurs actuelles, qui sont le défaut.

- [ ] **Step 5 : Écrire la section d'évolution**

`resources/js/components/wallet/WalletEvolutionSection.vue` :

```vue
<script setup lang="ts">
import ValueVsInvestedChart from '@/components/ValueVsInvestedChart.vue';
import type { ClassSeries } from '@/lib/wealth';

const props = defineProps<{ series?: ClassSeries | null }>();
</script>

<template>
    <section data-section="wallet-evolution" class="flex shrink-0 flex-col gap-4">
        <!--
            `ValueVsInvestedChart` et non `EvolutionSection` : celle-ci veut un détail par
            instrument que la série d'une enveloppe ne produit pas — l'enveloppe se lit en bloc.
        -->
        <ValueVsInvestedChart
            defer-key="evolution"
            :loaded="props.series !== null && props.series !== undefined"
            :labels="props.series?.labels ?? []"
            :value="props.series?.value ?? []"
            :invested="props.series?.invested ?? []"
            description="Valeur de l'enveloppe dans le temps, comparée au montant investi."
        />
    </section>
</template>
```

- [ ] **Step 6 : Composer la page**

`resources/js/pages/Wallet/Show.vue`, corps final du template :

```vue
<template>
    <Head :title="title()" />

    <AppPage>
        <WalletHeaderSection :account="props.account" />

        <!-- La courbe suit immédiatement la valeur qu'elle raconte ; le reste vient ensuite. -->
        <WalletEvolutionSection :series="props.evolution" />

        <InstrumentsSection :holdings="props.positions ?? []" />

        <CollapsibleSection section="wallet-breakdown" title="Répartition">
            <SectorBreakdownList :rows="breakdownRows" />
        </CollapsibleSection>

        <TransactionsSection
            :transactions="props.transactions"
            section="class-transactions"
            empty-label="Aucune transaction sur cette enveloppe."
        />
    </AppPage>

    <AppBottomBar :items="[{ label: 'Tableau de bord', href: '/' }, { label: title() }]" />

    <!-- Frère d'`AppPage` : dedans, il ajouterait un écart fantôme au `gap-6` du conteneur. -->
    <TransactionDialog />
</template>
```

et, dans le `<script setup>`, la conversion vers la forme que `SectorBreakdownList` consomme :

```ts
/**
 * `SectorBreakdownList` lit des `SectorBreakdownRow` : la ventilation d'une enveloppe s'y coule
 * sans composant neuf, seuls les noms de champs changent. Les parts viennent du serveur, rien
 * n'est recalculé ici.
 */
const breakdownRows = computed<SectorBreakdownRow[]>(() =>
    (props.breakdown ?? []).map((slice) => ({
        label: slice.label,
        share: slice.share,
        amount: slice.value,
    })),
);
```

Imports à ajouter : `computed` de `vue`, `CollapsibleSection`, `SectorBreakdownList`,
`InstrumentsSection`, `TransactionsSection`, `WalletEvolutionSection`, et le type
`SectorBreakdownRow` de `@/lib/sector`.

- [ ] **Step 7 : Lancer les tests front, vérifier qu'ils passent**

```bash
bun run test resources/js/pages/Wallet/Show.test.ts resources/js/components/instruments
bun run build
```

Attendu : PASS, et un build propre.

- [ ] **Step 8 : Commit**

```bash
git add resources/js
git commit -m "feat: positions, ventilation, courbe et journal sur la page d'une enveloppe"
```

---

### Task 11 : les cartes du tableau de bord mènent à leur page

**Files:**
- Modify: `resources/js/components/dashboard/WealthAccountsSection.vue`
- Modify: `resources/js/components/dashboard/WealthAccountsSection.test.ts`

**Interfaces:**
- Consomme : la route `wallets.show` (Task 8).
- Produit : rien que d'autres tâches consomment.

- [ ] **Step 1 : Écrire le test qui échoue**

Ajouter au `describe` de `WealthAccountsSection.test.ts` — et compléter le `vi.mock` du haut du
fichier avec un `Link`, sur le modèle de celui d'`InstrumentsSection.test.ts` :

```ts
it('mène à la page de l\'enveloppe', async () => {
    const host = await mountSection([account({ walletId: 42 })]);
    const link = host.querySelector<HTMLAnchorElement>('[data-account-card] a, a[data-account-card]');

    expect(link?.getAttribute('href')).toBe('/enveloppes/42');
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

```bash
bun run test resources/js/components/dashboard/WealthAccountsSection.test.ts
```

Attendu : ÉCHEC — `expected null to be '/enveloppes/42'`.

- [ ] **Step 3 : Transformer la carte en lien**

Dans `WealthAccountsSection.vue`, remplacer le `<li>` porteur de `data-account-card` par un `<li>`
contenant un `<Link>` qui porte l'attribut et les classes existantes :

```vue
                <li v-for="account in rows" :key="account.walletId">
                    <Link
                        :href="`/enveloppes/${account.walletId}`"
                        prefetch
                        data-account-card
                        class="flex flex-col gap-1.5 rounded-md border border-separator px-3 py-3 transition-colors hover:border-muted-foreground"
                    >
```

en fermant par `</Link></li>`, et ajouter `Link` à l'import `@inertiajs/vue3` du composant.

- [ ] **Step 4 : Lancer, vérifier que ça passe**

```bash
bun run test resources/js/components/dashboard/WealthAccountsSection.test.ts
bun run build
```

Attendu : PASS, les cas d'origine compris.

- [ ] **Step 5 : Commit**

```bash
git add resources/js
git commit -m "feat: les enveloppes du tableau de bord mènent à leur page"
```

---

### Task 12 : vérification d'ensemble

**Files:** aucun — sauf correctif rendu nécessaire par un échec.

**Interfaces:**
- Consomme : tout ce qui précède.
- Produit : la preuve que la suite entière est verte.

- [ ] **Step 1 : Lancer toute la suite PHP**

```bash
php artisan test --compact
```

Attendu : PASS. `tests/Feature/SnapshotInvariantTest.php` en particulier doit passer **sans avoir
été modifié** : la page ne touche pas au blob hors-ligne. S'il échoue, la cause est une Data
sérialisée qui a bougé — la corriger, ne pas régénérer le hash.

- [ ] **Step 2 : Lancer toute la suite front**

```bash
bun run test
```

Attendu : PASS.

- [ ] **Step 3 : Vérifier la page dans l'application**

```bash
php artisan tinker --execute 'echo App\Contexts\Portfolio\Models\Wallet::query()->value("id");'
```

Ouvrir `https://argent.test/enveloppes/<cet id>` et vérifier de visu : l'en-tête chiffré, la courbe,
les positions, la ventilation, le pli des transactions.

- [ ] **Step 4 : Enregistrer la règle durable**

Enregistrer, via l'outil MCP `record-rule` de Boost, la règle qui n'était nulle part avant cette
tranche :

- `glob` : `app/Contexts/Valuation/Actions/**`
- `title` : « Le filtre par enveloppe de BuildExposureSeries ne fait pas d'exception au cash »
- `note` : le filtre par classe laisse passer tout mouvement sans `asset_id`, sous peine d'un cash
  bâti sur les seuls achats de la classe ; le filtre par enveloppe, lui, écarte franchement le cash
  des autres comptes, puisque le cash est tenu par wallet. Les deux ne se comportent pas pareil, à
  dessein — ne pas aligner l'un sur l'autre. Chaque filtre entre dans le nom de cache.

- [ ] **Step 5 : Commit s'il reste quoi que ce soit**

```bash
git status --short
```

Si le répertoire est propre, la tranche est terminée.

---

## Self-review

**Couverture du spec :** page par wallet (Task 8), en-tête fiscal (Task 9), positions (Task 4 + 10),
ventilation (Task 4 + 10), évolution (Tasks 1, 2, 6, 10), transactions (Tasks 5, 10), les deux 404
(Task 8), les trois retouches de composants existants (Tasks 10, 11), jumelle et parité (Task 3),
hors-ligne explicitement non touché (contraintes globales + Task 12). Aucun élément du spec sans
tâche.

**Points où l'exécutant doit vérifier avant d'écrire** — signalés en bloc-citation dans les tâches
concernées, parce que la forme exacte dépend du code en place : le libellé de `AssetClass::Equity`
(Task 4), la clé des props différées dans `viewData('page')` (Task 8), l'existence du type
`ClassSeries` côté front (Task 8), la présence d'un `withDefaults` dans `TransactionsSection`
(Task 10), le montage de `TransactionDialog`/`AsyncBaseChart` sous happy-dom (Task 10), et la forme
finale du repère « Espèces » de l'en-tête (Task 9).
