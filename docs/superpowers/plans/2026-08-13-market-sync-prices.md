# Commande de rafraîchissement des prix — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer `php artisan market:sync-prices`, qui récupère les prix quotidiens des instruments chez Yahoo Finance et les écrit dans `asset_prices`, en reprenant au dernier prix connu de chaque actif.

**Architecture:** Le contexte Market gagne son write-side prix (`PriceRepositoryContract::upsertForAsset`) et un port de fetch distant dédié (`PriceFeedPort`), implémenté par `YahooFinanceAdapter` via le script `fetch_prices_bulk.py` en un seul process Python. `PriceProviderPort`, bindé sur le read-side base, n'est pas touché. L'action `SyncAssetPrices` orchestre : sélection des instruments, calcul de la plage par actif, fetch groupé, upsert. La commande ne fait que présenter le rapport et choisir le code de sortie.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 4, Mockery, SQLite en mémoire pour les tests, yfinance via `app/Shared/Python`.

**Spec:** `docs/superpowers/specs/2026-08-13-market-sync-prices-design.md`

## Global Constraints

- **Tests colocalisés** : le test d'un fichier vit à côté de lui, en `<Nom>Test.php`. `phpunit.xml:8-12` inclut `tests`, `app/Contexts` et `app/Shared` comme suites.
- `tests/Pest.php:14-16` applique `RefreshDatabase` à tout `app/Contexts` : la base est disponible dans chaque test de ce plan.
- **Toute sortie utilisateur en français**, accents compris (`échec`, `synchronisés`, `Aucun instrument à synchroniser.`). Code, noms de classes et de méthodes en anglais.
- **Types de retour explicites** partout, promotion de propriétés dans les constructeurs, accolades même pour les corps d'une ligne.
- **PHPDoc plutôt que commentaires inline.** Formes de tableaux documentées (`array<int, PriceData>`).
- `vendor/bin/pint --dirty --format agent` avant chaque commit.
- Tests : `php artisan test --compact --filter=<nom>`. Jamais de suite complète pendant une tâche.
- Un commit par tâche, message en français, préfixe `feat:` / `fix:` / `refactor:` / `docs:`.
- Branche de travail : `vendredi-soir`. Ne pas committer sur `main`.
- `InstrumentFactory::definition()` met un `ticker` **aléatoirement nul** (`fake()->optional()`) : chaque test qui a besoin d'un ticker doit le passer explicitement.
- `Price` a un cast `decimal:4` sur `open/high/low/close` : les valeurs relues sont des **chaînes**, à caster en `(float)` avant comparaison.

---

### Task 1: Corriger la borne de fin exclusive de yfinance

`PriceProviderPort::getPriceHistory($assetId, $startDate, $endDate)` traite `endDate` comme inclusif — c'est ce que fait `DatabaseAssetPriceAdapter::getPriceHistory()` (`date <= $endDate`, ligne 28). `YahooFinanceAdapter` passe la valeur brute au script, or `yf.Ticker::history(start, end)` **exclut** `end`. La clôture du dernier jour demandé est donc systématiquement perdue, dans `getPriceHistory()` comme dans `getCurrentPrice()`.

**Files:**
- Modify: `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php:31-83`
- Test: `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `YahooFinanceAdapter::window(string $ticker, string $startDate, string $endDate): array{ticker: string, start_date: string, end_date: string}` — méthode privée, réutilisée par la Task 4 pour construire les entrées du script bulk.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php` :

```php
it('includes the requested end date in the price history window', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getPriceHistory($instrument->id, '2026-01-01', '2026-01-31');

    expect($this->python->calls[0]['input'])->toBe([
        'ticker' => 'AAPL',
        'start_date' => '2026-01-01',
        'end_date' => '2026-02-01',
    ]);
});

it('includes today in the default price history window', function () {
    $this->travelTo('2026-08-13 10:00:00');
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getPriceHistory($instrument->id);

    expect($this->python->calls[0]['input']['start_date'])->toBe('2025-08-13')
        ->and($this->python->calls[0]['input']['end_date'])->toBe('2026-08-14');
});

it('includes today when fetching the current price', function () {
    $this->travelTo('2026-08-13 10:00:00');
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->adapter->getCurrentPrice($instrument->id);

    expect($this->python->calls[0]['input']['end_date'])->toBe('2026-08-14');
});
```

`$this->python` et `$this->adapter` viennent du `beforeEach` déjà en place (lignes 14-17). `FakePythonRunner::$calls` enregistre `['script' => …, 'input' => …]` à chaque appel.

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=YahooFinanceAdapter`
Expected: FAIL — les trois nouveaux tests attendent `2026-02-01` / `2026-08-14` et reçoivent `2026-01-31` / `2026-08-13`. Les tests existants passent.

- [ ] **Step 3: Extraire la construction de la fenêtre et décaler la borne**

Dans `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`, ajouter l'import :

```php
use Illuminate\Support\Carbon;
```

Ajouter la méthode privée en fin de classe :

```php
/**
 * Build the script parameters for an inclusive date window.
 *
 * yfinance excludes its `end` bound, the port contract includes it.
 *
 * @return array{ticker: string, start_date: string, end_date: string}
 */
private function window(string $ticker, string $startDate, string $endDate): array
{
    return [
        'ticker' => $ticker,
        'start_date' => $startDate,
        'end_date' => Carbon::parse($endDate)->addDay()->format('Y-m-d'),
    ];
}
```

Remplacer l'appel dans `getCurrentPrice()` :

```php
$result = $this->python->run(YahooScript::Prices->path(), $this->window(
    $asset->ticker,
    now()->subYear()->format('Y-m-d'),
    now()->format('Y-m-d'),
));
```

Et dans `getPriceHistory()`, après les deux `??=` :

```php
$result = $this->python->run(
    YahooScript::Prices->path(),
    $this->window($asset->ticker, $startDate, $endDate),
);
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=YahooFinanceAdapter`
Expected: PASS, tous les tests du fichier.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php
git commit -m "fix: inclut la borne de fin dans les fenetres de prix Yahoo"
```

---

### Task 2: Write-side des prix — `upsertForAsset`

`PriceRepositoryContract` est en lecture seule ; rien n'écrit dans `asset_prices`. On ajoute l'écriture idempotente. `unique(['asset_id', 'date'])` existe déjà (`database/migrations/2026_02_21_000944_create_security_prices_table.php:24`, colonne renommée par `2026_05_09_150000_*`) : **aucune migration**.

**Files:**
- Modify: `app/Contexts/Market/Contracts/PriceRepositoryContract.php`
- Modify: `app/Contexts/Market/Infrastructure/EloquentPriceRepository.php`
- Test: `app/Contexts/Market/Infrastructure/EloquentPriceRepositoryTest.php`

**Interfaces:**
- Consumes: `PriceData` (existant : `app/Contexts/Market/Datas/PriceData.php`, champs `date, close, open, high, low, volume`).
- Produces: `PriceRepositoryContract::upsertForAsset(int $assetId, array $prices): int` — utilisée par la Task 5.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à `app/Contexts/Market/Infrastructure/EloquentPriceRepositoryTest.php` — et l'import `use App\Contexts\Market\Datas\PriceData;` en tête du fichier :

```php
it('inserts the given prices', function () {
    $written = $this->repository->upsertForAsset($this->instrument->id, [
        new PriceData(date: '2026-01-01', close: 10.0, open: 9.0, high: 11.0, low: 8.0, volume: 100),
        new PriceData(date: '2026-01-02', close: 12.0),
    ]);

    expect($written)->toBe(2)
        ->and(Price::query()->where('asset_id', $this->instrument->id)->count())->toBe(2);
});

it('overwrites an existing price on the same date', function () {
    Price::factory()->create([
        'asset_id' => $this->instrument->id,
        'date' => '2026-01-01',
        'close' => 10.0,
    ]);

    $this->repository->upsertForAsset($this->instrument->id, [
        new PriceData(date: '2026-01-01', close: 42.5),
    ]);

    expect(Price::query()->where('asset_id', $this->instrument->id)->count())->toBe(1)
        ->and((float) $this->repository->latestForAsset($this->instrument->id)->close)->toBe(42.5);
});

it('writes nothing for an empty list', function () {
    expect($this->repository->upsertForAsset($this->instrument->id, []))->toBe(0)
        ->and(Price::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=EloquentPriceRepository`
Expected: FAIL avec `Call to undefined method … ::upsertForAsset()`.

- [ ] **Step 3: Déclarer puis implémenter la méthode**

Dans `app/Contexts/Market/Contracts/PriceRepositoryContract.php`, ajouter l'import `use App\Contexts\Market\Datas\PriceData;` et la méthode :

```php
/**
 * Insert or update the daily prices of an asset.
 *
 * Rows are matched on (asset_id, date) and existing values are overwritten:
 * the provider revises past closes.
 *
 * @param  array<int, PriceData>  $prices
 * @return int number of rows written
 */
public function upsertForAsset(int $assetId, array $prices): int;
```

Dans `app/Contexts/Market/Infrastructure/EloquentPriceRepository.php`, ajouter l'import `use App\Contexts\Market\Datas\PriceData;` et la méthode :

```php
public function upsertForAsset(int $assetId, array $prices): int
{
    if ($prices === []) {
        return 0;
    }

    $rows = array_map(fn (PriceData $price): array => [
        'asset_id' => $assetId,
        'date' => $price->date,
        'open' => $price->open,
        'high' => $price->high,
        'low' => $price->low,
        'close' => $price->close,
        'volume' => $price->volume,
    ], $prices);

    Price::query()->upsert($rows, ['asset_id', 'date'], ['open', 'high', 'low', 'close', 'volume']);

    return count($rows);
}
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=EloquentPriceRepository`
Expected: PASS. Si SQLite refuse l'upsert, vérifier que l'index unique existe : `php artisan db:table asset_prices`.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Contracts/PriceRepositoryContract.php app/Contexts/Market/Infrastructure/EloquentPriceRepository.php app/Contexts/Market/Infrastructure/EloquentPriceRepositoryTest.php
git commit -m "feat: ouvre le write-side des prix avec un upsert par actif"
```

---

### Task 3: Objets de valeur `PriceRequestData` et `PriceSyncReportData`

Deux DTO : la requête envoyée au port de fetch, et le rapport que l'action rend à la commande.

**Files:**
- Create: `app/Contexts/Market/Datas/PriceRequestData.php`
- Create: `app/Contexts/Market/Datas/PriceRequestDataTest.php`
- Create: `app/Contexts/Market/Datas/PriceSyncReportData.php`
- Create: `app/Contexts/Market/Datas/PriceSyncReportDataTest.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `new PriceRequestData(string $ticker, string $startDate, string $endDate)` — propriétés publiques `ticker`, `startDate`, `endDate`, dates en `'Y-m-d'`, **bornes inclusives**.
  - `new PriceSyncReportData(array $synced = [], array $failed = [])` — `$synced` est `array<string, int>` (nombre de prix écrits, par ticker), `$failed` est `array<int, string>` (tickers en échec). Méthodes `total(): int`, `syncedCount(): int`, `isTotalFailure(): bool`.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Datas/PriceRequestDataTest.php` :

```php
<?php

use App\Contexts\Market\Datas\PriceRequestData;

it('holds a ticker and an inclusive date window', function () {
    $request = new PriceRequestData(
        ticker: 'PE500.PA',
        startDate: '2026-08-11',
        endDate: '2026-08-13',
    );

    expect($request->ticker)->toBe('PE500.PA')
        ->and($request->startDate)->toBe('2026-08-11')
        ->and($request->endDate)->toBe('2026-08-13');
});
```

`app/Contexts/Market/Datas/PriceSyncReportDataTest.php` :

```php
<?php

use App\Contexts\Market\Datas\PriceSyncReportData;

it('is empty by default', function () {
    $report = new PriceSyncReportData;

    expect($report->total())->toBe(0)
        ->and($report->syncedCount())->toBe(0)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('counts synced and failed tickers', function () {
    $report = new PriceSyncReportData(
        synced: ['AAPL' => 253, 'PE500.PA' => 2],
        failed: ['DEAD.PA'],
    );

    expect($report->total())->toBe(3)
        ->and($report->syncedCount())->toBe(2)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('counts a ticker with zero new prices as synced', function () {
    $report = new PriceSyncReportData(synced: ['DEAD.PA' => 0]);

    expect($report->syncedCount())->toBe(1)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('is a total failure when nothing was synced', function () {
    $report = new PriceSyncReportData(failed: ['AAPL', 'PE500.PA']);

    expect($report->total())->toBe(2)
        ->and($report->isTotalFailure())->toBeTrue();
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="PriceRequestData|PriceSyncReportData"`
Expected: FAIL avec `Class "App\Contexts\Market\Datas\PriceRequestData" not found`.

- [ ] **Step 3: Écrire les deux DTO**

`app/Contexts/Market/Datas/PriceRequestData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

/**
 * A price fetch request for one ticker over an inclusive date window.
 *
 * Translating the window to a provider's own convention belongs to the adapter.
 */
readonly class PriceRequestData
{
    public function __construct(
        public string $ticker,
        public string $startDate,
        public string $endDate,
    ) {}
}
```

`app/Contexts/Market/Datas/PriceSyncReportData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

readonly class PriceSyncReportData
{
    /**
     * @param  array<string, int>  $synced  number of prices written, keyed by ticker
     * @param  array<int, string>  $failed  tickers the provider could not be reached for
     */
    public function __construct(
        public array $synced = [],
        public array $failed = [],
    ) {}

    public function total(): int
    {
        return count($this->synced) + count($this->failed);
    }

    public function syncedCount(): int
    {
        return count($this->synced);
    }

    public function isTotalFailure(): bool
    {
        return $this->total() > 0 && $this->synced === [];
    }
}
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter="PriceRequestData|PriceSyncReportData"`
Expected: PASS, 5 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Datas/PriceRequestData.php app/Contexts/Market/Datas/PriceRequestDataTest.php app/Contexts/Market/Datas/PriceSyncReportData.php app/Contexts/Market/Datas/PriceSyncReportDataTest.php
git commit -m "feat: ajoute les objets de valeur de requete et de rapport de prix"
```

---

### Task 4: Port de fetch distant `PriceFeedPort` et son adaptateur Yahoo

`PriceProviderPort` est bindé sur `DatabaseAssetPriceAdapter` (lecture base, `AppServiceProvider.php:38`). Un sync a besoin d'un accès distant : nouveau port, orienté ticker comme `SectorProviderPort`. Le fetch est groupé — un seul boot Python pour tout le catalogue — via `fetch_prices_bulk.py`, présent sur disque et jamais branché.

**Files:**
- Create: `app/Contexts/Market/Ports/PriceFeedPort.php`
- Create: `app/Contexts/Market/Ports/PriceFeedException.php`
- Modify: `app/Contexts/Market/Infrastructure/Python/YahooScript.php`
- Modify: `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`
- Modify: `app/Contexts/Market/MarketProvider.php`
- Modify: `app/Providers/AppServiceProvider.php:33-40`
- Test: `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php`
- Test: `app/Contexts/Market/MarketProviderTest.php`

**Interfaces:**
- Consumes: `PriceRequestData` (Task 3) ; `YahooFinanceAdapter::window()` (Task 1) ; `PriceData::fromArray()` (existant).
- Produces:
  - `PriceFeedPort::fetchPrices(array $requests): array` — prend `array<int, PriceRequestData>`, rend `array<string, array<int, PriceData>>` indexé par ticker, lève `PriceFeedException` si le fetch entier échoue. Un ticker sans barre sur la plage est **absent** du retour.
  - `PriceFeedPort::supports(InstrumentType $type): bool`.
  - `PriceFeedException::fetchFailed(string $error): self`.
  - Binding conteneur : `app(PriceFeedPort::class)` rend `YahooFinanceAdapter`.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php` les imports `use App\Contexts\Market\Datas\PriceData;`, `use App\Contexts\Market\Datas\PriceRequestData;`, `use App\Contexts\Market\Ports\PriceFeedException;` puis :

```php
it('sends one bulk entry per request with an inclusive end date', function () {
    $this->adapter->fetchPrices([
        new PriceRequestData('PE500.PA', '2026-08-11', '2026-08-13'),
        new PriceRequestData('AAPL', '2025-08-13', '2026-08-13'),
    ]);

    expect($this->python->calls[0]['script'])->toBe(YahooScript::PricesBulk->path())
        ->and($this->python->calls[0]['input']['tickers'])->toBe([
            ['ticker' => 'PE500.PA', 'start_date' => '2026-08-11', 'end_date' => '2026-08-14'],
            ['ticker' => 'AAPL', 'start_date' => '2025-08-13', 'end_date' => '2026-08-14'],
        ]);
});

it('maps the bulk payload to PriceData keyed by ticker', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', [
        'AAPL' => [
            ['date' => '2026-08-12', 'open' => 1.0, 'high' => 2.0, 'low' => 0.5, 'close' => 1.5, 'volume' => 10],
            ['date' => '2026-08-13', 'open' => 1.5, 'high' => 2.5, 'low' => 1.0, 'close' => 2.0, 'volume' => 20],
        ],
    ]));

    $prices = $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);

    expect($prices)->toHaveKey('AAPL')
        ->and($prices['AAPL'])->toHaveCount(2)
        ->and($prices['AAPL'][0])->toBeInstanceOf(PriceData::class)
        ->and($prices['AAPL'][0]->date)->toBe('2026-08-12')
        ->and($prices['AAPL'][0]->close)->toBe(1.5)
        ->and($prices['AAPL'][0]->volume)->toBe(10);
});

it('omits tickers absent from the bulk payload', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('ok', []));

    expect($this->adapter->fetchPrices([new PriceRequestData('DEAD.PA', '2026-08-11', '2026-08-13')]))
        ->toBe([]);
});

it('never runs the script without requests', function () {
    expect($this->adapter->fetchPrices([]))->toBe([])
        ->and($this->python->calls)->toBe([]);
});

it('throws when the bulk script returns an error envelope', function () {
    $this->python->withResult(YahooScript::PricesBulk->path(), new PythonResult('error', error: 'boom'));

    $this->adapter->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
})->throws(PriceFeedException::class);

it('throws when the runner itself fails', function () {
    throwingAdapter()->fetchPrices([new PriceRequestData('AAPL', '2026-08-11', '2026-08-13')]);
})->throws(PriceFeedException::class);
```

`throwingAdapter()` est le helper déjà défini dans ce fichier (lignes 21-30) : son runner lève `PythonProcessException`.

Dans `app/Contexts/Market/MarketProviderTest.php`, ajouter l'import `use App\Contexts\Market\Ports\PriceFeedPort;` et une ligne à la chaîne d'attentes existante :

```php
        ->and(app(PriceFeedPort::class))->toBeInstanceOf(YahooFinanceAdapter::class);
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="YahooFinanceAdapter|MarketProvider"`
Expected: FAIL avec `Undefined constant App\Contexts\Market\Infrastructure\Python\YahooScript::PricesBulk` et `Target [App\Contexts\Market\Ports\PriceFeedPort] is not instantiable`.

- [ ] **Step 3: Créer le port, l'exception, le script et l'implémentation**

`app/Contexts/Market/Ports/PriceFeedPort.php` :

```php
<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Enums\InstrumentType;

interface PriceFeedPort
{
    /**
     * Check if the feed covers this instrument type.
     */
    public function supports(InstrumentType $type): bool;

    /**
     * Fetch daily prices for several tickers at once.
     *
     * A ticker with no bar over its window is absent from the result: the feed
     * cannot tell a closed market from a dead ticker.
     *
     * @param  array<int, PriceRequestData>  $requests
     * @return array<string, array<int, PriceData>> prices keyed by ticker
     *
     * @throws PriceFeedException when the whole fetch fails
     */
    public function fetchPrices(array $requests): array;
}
```

`app/Contexts/Market/Ports/PriceFeedException.php` :

```php
<?php

namespace App\Contexts\Market\Ports;

use RuntimeException;

class PriceFeedException extends RuntimeException
{
    public static function fetchFailed(string $error): self
    {
        return new self("Price feed fetch failed: {$error}");
    }
}
```

Dans `app/Contexts/Market/Infrastructure/Python/YahooScript.php`, ajouter le cas :

```php
    case PricesBulk = 'fetch_prices_bulk.py';
```

Dans `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php` : ajouter `PriceFeedPort` à la liste `implements`, les imports `use App\Contexts\Market\Datas\PriceRequestData;`, `use App\Contexts\Market\Ports\PriceFeedException;`, `use App\Shared\Python\PythonProcessException;`, puis la méthode :

```php
public function fetchPrices(array $requests): array
{
    if ($requests === []) {
        return [];
    }

    try {
        $result = $this->python->run(YahooScript::PricesBulk->path(), [
            'tickers' => array_map(
                fn (PriceRequestData $request): array => $this->window(
                    $request->ticker,
                    $request->startDate,
                    $request->endDate,
                ),
                $requests,
            ),
        ]);
    } catch (PythonProcessException $exception) {
        throw PriceFeedException::fetchFailed($exception->getMessage());
    }

    if (! $result->ok()) {
        throw PriceFeedException::fetchFailed($result->error ?? 'unknown error');
    }

    $prices = [];

    foreach ($result->data ?? [] as $ticker => $rows) {
        $prices[$ticker] = array_map(
            fn (array $row): PriceData => PriceData::fromArray($row),
            $rows,
        );
    }

    return $prices;
}
```

L'import `use App\Contexts\Market\Datas\PriceData;` est peut-être déjà là ; sinon l'ajouter. `supports()` est commun aux quatre ports de cet adaptateur et reste inchangé (`Stock` et `ETF`).

Le script Python n'est **pas** modifié : il lit déjà `{"tickers": [{ticker, start_date, end_date}, …]}` et regroupe les tickers de même plage dans un `yf.download` unique.

Dans `app/Contexts/Market/MarketProvider.php`, ajouter le paramètre et son binding — les appelants utilisent des arguments nommés, la position est libre :

```php
    /**
     * @param  class-string<PriceFeedPort>  $priceFeed
     */
```

```php
        string $priceFeed,
```

```php
        $app->bind(PriceFeedPort::class, $priceFeed);
```

avec l'import `use App\Contexts\Market\Ports\PriceFeedPort;`.

Dans `app/Providers/AppServiceProvider.php`, ajouter l'argument à l'appel existant :

```php
            priceProvider: DatabaseAssetPriceAdapter::class,
            priceFeed: YahooFinanceAdapter::class,
            sectorProvider: YahooFinanceAdapter::class,
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter="YahooFinanceAdapter|MarketProvider"`
Expected: PASS, tous les tests des deux fichiers.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Ports/PriceFeedPort.php app/Contexts/Market/Ports/PriceFeedException.php app/Contexts/Market/Infrastructure/Python/YahooScript.php app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php app/Contexts/Market/MarketProvider.php app/Contexts/Market/MarketProviderTest.php app/Providers/AppServiceProvider.php
git commit -m "feat: branche un port de recuperation groupee des prix sur Yahoo"
```

---

### Task 5: Action `SyncAssetPrices`

Orchestration : sélection des instruments, plage par actif, fetch groupé, upsert, rapport. Structure calquée sur `app/Contexts/Market/Actions/SyncAssetSectors.php`.

**Files:**
- Create: `app/Contexts/Market/Actions/SyncAssetPrices.php`
- Create: `app/Contexts/Market/Actions/SyncAssetPricesTest.php`

**Interfaces:**
- Consumes: `InstrumentRepositoryContract::findAll()` / `findById()` (existants) ; `PriceRepositoryContract::latestForAsset()` (existant) et `upsertForAsset()` (Task 2) ; `PriceFeedPort::supports()` / `fetchPrices()` et `PriceFeedException` (Task 4) ; `PriceRequestData` et `PriceSyncReportData` (Task 3).
- Produces: `SyncAssetPrices::__invoke(?int $assetId = null, ?string $since = null): PriceSyncReportData` — utilisée par la Task 6.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Actions/SyncAssetPricesTest.php` :

```php
<?php

use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;

/**
 * Bind a feed that supports Stock and ETF, records the requests it receives
 * in $captured, and returns the given prices keyed by ticker.
 *
 * @param  array<string, array<int, PriceData>>  $prices
 * @param  array<int, mixed>  $captured
 */
function fakeFeed(array $prices, array &$captured = []): void
{
    test()->mock(PriceFeedPort::class, function ($mock) use ($prices, &$captured) {
        $mock->shouldReceive('supports')
            ->andReturnUsing(fn (InstrumentType $type): bool => in_array($type, [InstrumentType::Stock, InstrumentType::ETF]));
        $mock->shouldReceive('fetchPrices')
            ->andReturnUsing(function (array $requests) use ($prices, &$captured) {
                $captured = $requests;

                return $prices;
            });
    });
}

it('resumes from the last stored price of each asset', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-08-11']);
    $this->travelTo('2026-08-13 10:00:00');
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL')
        ->and($captured[0]->startDate)->toBe('2026-08-11')
        ->and($captured[0]->endDate)->toBe('2026-08-13');
});

it('falls back to a twelve month window without any stored price', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $this->travelTo('2026-08-13 10:00:00');
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured[0]->startDate)->toBe('2025-08-13');
});

it('lets an explicit since date win over the stored history', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    Price::factory()->create(['asset_id' => $instrument->id, 'date' => '2026-08-11']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)(null, '2020-01-01');

    expect($captured[0]->startDate)->toBe('2020-01-01');
});

it('skips instruments without a ticker and unsupported types', function () {
    Instrument::factory()->create(['ticker' => null]);
    Instrument::factory()->ofType(InstrumentType::Crypto)->create(['ticker' => 'BTC-EUR']);
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL');
});

it('writes the fetched prices and reports the count per ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'AAPL']);
    fakeFeed(['AAPL' => [
        new PriceData(date: '2026-08-12', close: 10.0),
        new PriceData(date: '2026-08-13', close: 11.0),
    ]]);

    $report = app(SyncAssetPrices::class)();

    expect($report->synced)->toBe(['AAPL' => 2])
        ->and($report->failed)->toBe([])
        ->and(Price::query()->where('asset_id', $instrument->id)->count())->toBe(2);
});

it('reports zero prices for a ticker absent from the payload', function () {
    Instrument::factory()->create(['ticker' => 'DEAD.PA']);
    fakeFeed([]);

    $report = app(SyncAssetPrices::class)();

    expect($report->synced)->toBe(['DEAD.PA' => 0])
        ->and($report->isTotalFailure())->toBeFalse();
});

it('marks every ticker as failed when the feed throws', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $report = app(SyncAssetPrices::class)();

    expect($report->failed)->toBe(['AAPL', 'PE500.PA'])
        ->and($report->synced)->toBe([])
        ->and($report->isTotalFailure())->toBeTrue();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $captured = [];
    fakeFeed([], $captured);

    app(SyncAssetPrices::class)($first->id);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('AAPL');
});

it('never calls the feed when no instrument is eligible', function () {
    Instrument::factory()->create(['ticker' => null]);
    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->never();
    });

    $report = app(SyncAssetPrices::class)();

    expect($report->total())->toBe(0);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=SyncAssetPrices`
Expected: FAIL avec `Class "App\Contexts\Market\Actions\SyncAssetPrices" not found`.

- [ ] **Step 3: Écrire l'action**

`app/Contexts/Market/Actions/SyncAssetPrices.php` :

```php
<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\PriceRequestData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;
use Illuminate\Support\Collection;

class SyncAssetPrices
{
    private const FALLBACK_MONTHS = 12;

    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private PriceFeedPort $feed,
        private PriceRepositoryContract $prices,
    ) {}

    /**
     * Fetch and persist the daily prices of every supported asset.
     *
     * Without $since, each asset resumes at its last stored price.
     */
    public function __invoke(?int $assetId = null, ?string $since = null): PriceSyncReportData
    {
        $instruments = $this->instrumentsToSync($assetId)->filter(
            fn (Instrument $instrument): bool => $instrument->ticker !== null
                && $this->feed->supports($instrument->type),
        );

        if ($instruments->isEmpty()) {
            return new PriceSyncReportData;
        }

        $requests = $instruments->map(fn (Instrument $instrument): PriceRequestData => new PriceRequestData(
            ticker: $instrument->ticker,
            startDate: $this->startDateFor($instrument, $since),
            endDate: now()->format('Y-m-d'),
        ))->values()->all();

        try {
            $fetched = $this->feed->fetchPrices($requests);
        } catch (PriceFeedException) {
            return new PriceSyncReportData(failed: $instruments->pluck('ticker')->values()->all());
        }

        $synced = [];

        foreach ($instruments as $instrument) {
            $synced[$instrument->ticker] = $this->prices->upsertForAsset(
                $instrument->id,
                $fetched[$instrument->ticker] ?? [],
            );
        }

        return new PriceSyncReportData(synced: $synced);
    }

    private function startDateFor(Instrument $instrument, ?string $since): string
    {
        if ($since !== null) {
            return $since;
        }

        $latest = $this->prices->latestForAsset($instrument->id);

        return $latest !== null
            ? $latest->date->format('Y-m-d')
            : now()->subMonths(self::FALLBACK_MONTHS)->format('Y-m-d');
    }

    /**
     * @return Collection<int, Instrument>
     */
    private function instrumentsToSync(?int $assetId): Collection
    {
        if ($assetId === null) {
            return $this->instruments->findAll();
        }

        $instrument = $this->instruments->findById($assetId);

        return $instrument !== null ? collect([$instrument]) : collect();
    }
}
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=SyncAssetPrices`
Expected: PASS, 9 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Actions/SyncAssetPrices.php app/Contexts/Market/Actions/SyncAssetPricesTest.php
git commit -m "feat: ajoute l'action de synchronisation des prix des instruments"
```

---

### Task 6: Commande `market:sync-prices`

Présentation et code de sortie. Les commandes de `app/Contexts` ne sont pas auto-découvertes (Laravel 12 ne scanne que `app/Console/Commands`) : l'enregistrement dans `bootstrap/app.php` fait partie de cette tâche, sinon `$this->artisan('market:sync-prices')` échoue.

**Files:**
- Create: `app/Contexts/Market/Console/SyncPricesCommand.php`
- Create: `app/Contexts/Market/Console/SyncPricesCommandTest.php`
- Modify: `bootstrap/app.php:17-21`

**Interfaces:**
- Consumes: `SyncAssetPrices::__invoke()` et `PriceSyncReportData` (Tasks 3 et 5).
- Produces: la commande Artisan `market:sync-prices {--asset=} {--since=}`, utilisée par la Task 7.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Console/SyncPricesCommandTest.php` :

```php
<?php

use App\Contexts\Market\Datas\PriceData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Ports\PriceFeedException;
use App\Contexts\Market\Ports\PriceFeedPort;

it('reports the number of prices per ticker and a summary', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    Instrument::factory()->create(['ticker' => 'DEAD.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn(['AAPL' => [
            new PriceData(date: '2026-08-12', close: 10.0),
            new PriceData(date: '2026-08-13', close: 11.0),
        ]]);
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : 2 prix')
        ->expectsOutputToContain('DEAD.PA : 0 prix')
        ->expectsOutputToContain('2 instruments, 2 synchronisés, 0 échec')
        ->assertSuccessful();
});

it('warns when no instrument is eligible', function () {
    Instrument::factory()->create(['ticker' => null]);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->never();
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('Aucun instrument à synchroniser.')
        ->assertSuccessful();
});

it('fails when the feed is unreachable', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andThrow(PriceFeedException::fetchFailed('boom'));
    });

    $this->artisan('market:sync-prices')
        ->expectsOutputToContain('AAPL : échec')
        ->expectsOutputToContain('1 instrument, 0 synchronisé, 1 échec')
        ->assertFailed();
});

it('restricts the sync to the given asset', function () {
    $first = Instrument::factory()->create(['ticker' => 'AAPL']);
    $second = Instrument::factory()->create(['ticker' => 'PE500.PA']);

    $this->mock(PriceFeedPort::class, function ($mock) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturn([
            'AAPL' => [new PriceData(date: '2026-08-13', close: 10.0)],
            'PE500.PA' => [new PriceData(date: '2026-08-13', close: 20.0)],
        ]);
    });

    $this->artisan('market:sync-prices', ['--asset' => $first->id])->assertSuccessful();

    expect(Price::query()->where('asset_id', $first->id)->count())->toBe(1)
        ->and(Price::query()->where('asset_id', $second->id)->count())->toBe(0);
});

it('forwards the since option to the feed window', function () {
    Instrument::factory()->create(['ticker' => 'AAPL']);
    $captured = [];

    $this->mock(PriceFeedPort::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('supports')->andReturn(true);
        $mock->shouldReceive('fetchPrices')->andReturnUsing(function (array $requests) use (&$captured) {
            $captured = $requests;

            return [];
        });
    });

    $this->artisan('market:sync-prices', ['--since' => '2020-01-01'])->assertSuccessful();

    expect($captured[0]->startDate)->toBe('2020-01-01');
});
```

Note : un rapport mêlant `synced` et `failed` est inatteignable — le fetch est groupé, donc l'échec est global. `PriceSyncReportData` sait le représenter et son test unitaire (Task 3) couvre ce cas ; la commande, elle, n'a que les deux extrêmes à afficher.

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=SyncPricesCommand`
Expected: FAIL avec `The command "market:sync-prices" does not exist.`

- [ ] **Step 3: Écrire la commande et l'enregistrer**

`app/Contexts/Market/Console/SyncPricesCommand.php` :

```php
<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetPrices;
use Illuminate\Console\Command;

class SyncPricesCommand extends Command
{
    protected $signature = 'market:sync-prices
                            {--asset= : Identifiant d\'un seul actif à synchroniser}
                            {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier prix connu}';

    protected $description = 'Récupère les prix quotidiens des instruments auprès du fournisseur de marché';

    public function handle(SyncAssetPrices $syncAssetPrices): int
    {
        $assetId = $this->option('asset');

        $report = $syncAssetPrices(
            $assetId !== null ? (int) $assetId : null,
            $this->option('since'),
        );

        if ($report->total() === 0) {
            $this->warn('Aucun instrument à synchroniser.');

            return self::SUCCESS;
        }

        foreach ($report->synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} prix");
        }

        foreach ($report->failed as $ticker) {
            $this->line("{$ticker} : échec");
        }

        $this->newLine();
        $this->line($this->summary($report->total(), $report->syncedCount(), count($report->failed)));

        return $report->isTotalFailure() ? self::FAILURE : self::SUCCESS;
    }

    private function summary(int $total, int $synced, int $failed): string
    {
        return sprintf(
            '%d instrument%s, %d synchronisé%s, %d échec%s',
            $total, $this->plural($total),
            $synced, $this->plural($synced),
            $failed, $this->plural($failed),
        );
    }

    private function plural(int $count): string
    {
        return $count > 1 ? 's' : '';
    }
}
```

Dans `bootstrap/app.php`, ajouter l'import `use App\Contexts\Market\Console\SyncPricesCommand;` et la commande :

```php
    ->withCommands([
        SyncPricesCommand::class,
        SyncSectorsCommand::class,
    ])
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=SyncPricesCommand`
Expected: PASS, 5 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Console/SyncPricesCommand.php app/Contexts/Market/Console/SyncPricesCommandTest.php bootstrap/app.php
git commit -m "feat: ajoute la commande market:sync-prices"
```

---

### Task 7: Remettre le sync quotidien en service

`bootstrap/app.php:22-29` porte un TODO de quatre lignes et un `$schedule->command('securities:fetch-prices')` commenté depuis la refonte DDD. La commande existe désormais : on la planifie et on met le suivi de refonte à jour.

**Files:**
- Modify: `bootstrap/app.php:22-29`
- Create: `tests/Feature/ScheduleTest.php`
- Modify: `docs/refactor-status.md`

**Interfaces:**
- Consumes: la commande `market:sync-prices` (Task 6).
- Produces: rien pour la suite du plan.

- [ ] **Step 1: Écrire le test qui échoue**

`tests/Feature/ScheduleTest.php` :

```php
<?php

it('schedules the price sync every day at 23:30', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('30 23 * * *')
        ->expectsOutputToContain('market:sync-prices')
        ->assertSuccessful();
});

it('keeps the weekly sector sync scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('0 0 * * 0')
        ->expectsOutputToContain('market:sync-sectors')
        ->assertSuccessful();
});
```

L'assertion passe par `schedule:list` et non par `app(Schedule::class)->events()` : `withSchedule()` s'enregistre via `Artisan::starting()` (`vendor/laravel/framework/src/Illuminate/Foundation/Configuration/ApplicationBuilder.php:364`), donc le conteneur ne rend les événements que si la console a démarré. `$this->artisan()` la démarre, quel que soit le runner. Format de sortie vérifié : `0 0 * * 0  php artisan market:sync-sectors`.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=Schedule`
Expected: FAIL — le premier test ne trouve ni `30 23 * * *` ni `market:sync-prices` dans la sortie. Le second passe déjà.

- [ ] **Step 3: Planifier la commande**

Dans `bootstrap/app.php`, remplacer tout le corps de `withSchedule` — les quatre lignes de TODO et la ligne commentée disparaissent :

```php
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('market:sync-prices')->dailyAt('23:30');
        $schedule->command('market:sync-sectors')->weekly();
    })
```

23h30 : après la clôture d'Euronext (17h30 CET) comme celle des places US (22h CET). Un run manqué se rattrape au suivant, puisque chaque actif reprend à son dernier prix stocké.

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=Schedule`
Expected: PASS, 2 tests.

- [ ] **Step 5: Mettre à jour le suivi de refonte**

Dans `docs/refactor-status.md`, tableau **Market** (§2), remplacer la ligne :

```markdown
| Couche tâche planifiée (commande `securities:*`) | aucune commande Artisan | A faire |
```

par :

```markdown
| Couche tâche planifiée | `Console/SyncPricesCommand.php` (`market:sync-prices`), `Console/SyncSectorsCommand.php` (`market:sync-sectors`) (+ tests) | Fait |
```

Ajouter à ce même tableau, sous la ligne des repositories :

```markdown
| Application — Write-side prix | `PriceRepositoryContract::upsertForAsset`, `Ports/PriceFeedPort.php`, `Actions/SyncAssetPrices.php` (+ tests) | Fait |
```

Dans le tableau **Cablage applicatif**, remplacer la ligne :

```markdown
| `bootstrap/app.php` — scheduler (`securities:fetch-prices`, `securities:fetch-sectors`) | Neutralise (refs mortes commentees, sync en pause) |
```

par :

```markdown
| `bootstrap/app.php` — scheduler (`market:sync-prices` quotidien, `market:sync-sectors` hebdomadaire) | Fait |
```

- [ ] **Step 6: Vérifier l'ensemble avant de committer**

```bash
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
php artisan test --compact
```

Expected: Pint sans reformatage restant, PHPStan sans erreur (niveau 2, `phpstan.neon`), suite complète verte.

- [ ] **Step 7: Committer**

```bash
git add bootstrap/app.php tests/Feature/ScheduleTest.php docs/refactor-status.md
git commit -m "feat: replanifie le sync quotidien des prix du marche"
```

---

## Vérification finale

Après la Task 7, un essai réel contre Yahoo, hors suite de tests (nécessite `.venv` et `yfinance`, installés par `composer post-install-cmd`) :

```bash
php artisan tinker --execute="echo App\Contexts\Market\Models\Instrument::query()->whereNotNull('ticker')->value('id');"
php artisan market:sync-prices --asset=<l'id affiché ci-dessus>
php artisan tinker --execute="echo App\Contexts\Market\Models\Price::query()->count();"
php artisan market:sync-prices
php artisan market:sync-prices
php artisan tinker --execute="echo App\Contexts\Market\Models\Price::query()->count();"
```

Attendu : une ligne par ticker, un résumé, code de sortie 0. Les deux derniers runs se suivent immédiatement : le second doit afficher le même nombre de prix par ticker **sans que le total en base augmente** — c'est la preuve que l'upsert est idempotent.
