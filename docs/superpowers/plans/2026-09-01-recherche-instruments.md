# Recherche d'instruments — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre de chercher un instrument chez Yahoo, confirmer ses métadonnées et le créer, depuis le catalogue d'une exposition comme depuis le formulaire de transaction.

**Architecture:** Une méthode de recherche ajoutée à `InstrumentProviderPort` et à `YahooFinanceAdapter` (le script Python existe déjà). Deux routes dans `Market\Http` : une recherche JSON qui interroge la base avant Yahoo, et une création validée par FormRequest qui met en file un job de synchronisation sur cinq ans. Côté client, un panneau de recherche sans chrome de modale, monté dans un `Dialog` sur la page catalogue et à la place des champs dans le formulaire de transaction.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest, Inertia v3 + Vue 3, Vitest, Tailwind, reka-ui, yfinance via `PythonRunner`.

**Spec:** `docs/superpowers/specs/2026-09-01-recherche-instruments-design.md`

## Global Constraints

- **Tests co-localisés.** Un test PHP vit à côté de sa classe (`Foo.php` / `FooTest.php`), pas dans `tests/`. `tests/Pest.php` étend déjà `TestCase` + `RefreshDatabase` sur `../app/Contexts`. Un test de composant Vue vit à côté du `.vue`.
- **Toute écriture PHP finit par `vendor/bin/pint --dirty --format agent`.**
- **Tout texte visible est en français.** Les commentaires PHPDoc du dépôt sont en français ; les commentaires anglais existants ne se traduisent pas au passage.
- **Aucune migration.** Le schéma ne bouge pas : pas d'index unique sur `assets.ticker` (décision de la spec).
- **`$guarded = ['id']` sur `Instrument`** : on écrit un tableau littéral explicite, jamais `create($request->validated())`.
- **Commits fréquents**, un par tâche, message en français, préfixe `feat:` / `test:` selon le contenu.
- **Lancer le minimum de tests** : `php artisan test --compact --filter=<nom>` pour PHP, `bunx vitest run <chemin>` pour le front.
- `AuthenticateDefaultUser` connecte l'utilisateur unique sur toute requête web : `auth()->check()` est vrai dans les tests HTTP sans `actingAs`.

---

## Structure des fichiers

**Créés**
| Fichier | Responsabilité |
| --- | --- |
| `app/Contexts/Market/Datas/InstrumentSearchResultData.php` | Un résultat de recherche : plus pauvre qu'`InstrumentData` (type nullable, pas de secteurs) |
| `app/Contexts/Market/Datas/InstrumentInputData.php` | Le corps validé d'une création, en route vers l'action |
| `app/Contexts/Market/Jobs/SyncInstrumentJob.php` | Cinq ans d'historique pour un seul instrument |
| `app/Contexts/Market/Actions/CreateInstrument.php` | Écrit la ligne, met le job en file |
| `app/Contexts/Market/Http/InstrumentRequest.php` | Validation du corps de création |
| `app/Contexts/Market/Http/SearchInstrumentsController.php` | `GET /instruments/recherche` |
| `app/Contexts/Market/Http/StoreInstrumentController.php` | `POST /instruments` |
| `resources/js/components/instruments/InstrumentSearchPanel.vue` | Les deux étapes, sans chrome de modale |
| `resources/js/pages/AssetClass/Catalog.test.ts` | La page n'a pas encore de test |

**Modifiés**
| Fichier | Changement |
| --- | --- |
| `app/Contexts/Market/Ports/InstrumentProviderPort.php` | Ajout de `searchInstruments()` |
| `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php` | Implémentation + mapping `typeDisp` |
| `routes/web.php` | Deux routes |
| `resources/js/lib/swCache.ts` | `/instruments/recherche` en `passthrough` |
| `resources/js/pages/AssetClass/Catalog.vue` | Lien dans l'état vide + `Dialog` |
| `resources/js/components/transactions/TransactionForm.vue` | Lien sous « Actif » + bascule vers le panneau |

---

### Task 1 : la recherche chez Yahoo

**Files:**
- Create: `app/Contexts/Market/Datas/InstrumentSearchResultData.php`
- Modify: `app/Contexts/Market/Ports/InstrumentProviderPort.php`
- Modify: `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`
- Test: `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php` (existant, on ajoute)

**Interfaces:**
- Consumes: `App\Shared\Python\PythonRunner`, `YahooScript::Search`, `InstrumentType`.
- Produces: `InstrumentProviderPort::searchInstruments(string $query): array` rendant une `list<InstrumentSearchResultData>`. `InstrumentSearchResultData` porte `string $symbol`, `string $name`, `?string $exchange`, `?InstrumentType $type`, `?int $existingId = null`.

- [ ] **Step 1 : écrire la Data**

`app/Contexts/Market/Datas/InstrumentSearchResultData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\InstrumentType;

/**
 * Un résultat de recherche, plus pauvre qu'`InstrumentData` par nature : Yahoo peut rendre un type
 * que l'application ne sait pas traduire, et charger les secteurs de chaque ligne d'une liste
 * coûterait un aller-retour Python par ligne.
 *
 * `existingId` n'est posé que par la recherche locale : un instrument déjà en base s'affiche mais
 * ne se crée pas une seconde fois.
 */
readonly class InstrumentSearchResultData
{
    public function __construct(
        public string $symbol,
        public string $name,
        public ?string $exchange = null,
        public ?InstrumentType $type = null,
        public ?int $existingId = null,
    ) {}
}
```

- [ ] **Step 2 : écrire les tests qui échouent**

À ajouter à la fin de `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php` :

```php
it('traduit les types Yahoo en types d\'instrument', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => 'AAPL', 'name' => 'Apple Inc.', 'exchange' => 'NasdaqGS', 'type' => 'Equity'],
        ['symbol' => 'CW8.PA', 'name' => 'Amundi MSCI World', 'exchange' => 'Paris', 'type' => 'ETF'],
        ['symbol' => 'BTC-EUR', 'name' => 'Bitcoin EUR', 'exchange' => 'CCC', 'type' => 'Cryptocurrency'],
        ['symbol' => 'SI=F', 'name' => 'Silver', 'exchange' => 'NY Mercantile', 'type' => 'Future'],
    ]));

    $results = $this->adapter->searchInstruments('a');

    expect($results)->toHaveCount(4)
        ->and($results[0]->symbol)->toBe('AAPL')
        ->and($results[0]->name)->toBe('Apple Inc.')
        ->and($results[0]->exchange)->toBe('NasdaqGS')
        ->and($results[0]->type)->toBe(InstrumentType::Stock)
        ->and($results[1]->type)->toBe(InstrumentType::ETF)
        ->and($results[2]->type)->toBe(InstrumentType::Crypto)
        ->and($results[3]->type)->toBe(InstrumentType::Commodity);
});

it('garde un résultat dont le type Yahoo est inconnu, sans type', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => '^FCHI', 'name' => 'CAC 40', 'exchange' => 'Paris', 'type' => 'Index'],
    ]));

    $results = $this->adapter->searchInstruments('cac');

    /** Le résultat s'affiche quand même : c'est l'écran de confirmation qui tranchera. */
    expect($results)->toHaveCount(1)
        ->and($results[0]->type)->toBeNull();
});

it('écarte un résultat sans symbole', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'ok', data: [
        ['symbol' => '', 'name' => 'Sans symbole', 'exchange' => null, 'type' => 'Equity'],
        ['symbol' => 'MSFT', 'name' => 'Microsoft', 'exchange' => 'NasdaqGS', 'type' => 'Equity'],
    ]));

    expect($this->adapter->searchInstruments('m'))->toHaveCount(1);
});

it('rend une liste vide quand le script échoue', function () {
    $this->python->withResult(YahooScript::Search->path(), new PythonResult(status: 'error', error: 'boom'));

    expect($this->adapter->searchInstruments('aapl'))->toBe([]);
});

it('rend une liste vide quand le process Python casse', function () {
    expect(throwingAdapter()->searchInstruments('aapl'))->toBe([]);
});

it('passe la requête au script de recherche', function () {
    $this->adapter->searchInstruments('lvmh');

    expect($this->python->calls[0]['script'])->toBe(YahooScript::Search->path())
        ->and($this->python->calls[0]['input'])->toBe(['query' => 'lvmh']);
});
```

- [ ] **Step 3 : lancer les tests, vérifier l'échec**

Run: `php artisan test --compact --filter=YahooFinanceAdapter`
Expected: FAIL — `Call to undefined method ...::searchInstruments()`

- [ ] **Step 4 : ajouter la méthode au port**

Dans `app/Contexts/Market/Ports/InstrumentProviderPort.php`, sous `findBySymbol()` :

```php
    /**
     * Cherche des instruments par nom ou par symbole, sans type imposé.
     *
     * Distincte de `findBySymbol()`, qui résout un symbole déjà connu : ici le type est
     * précisément ce qu'on cherche à apprendre, et les N résultats sont le matériau d'un choix.
     *
     * @return list<InstrumentSearchResultData>
     */
    public function searchInstruments(string $query): array;
```

Ajouter l'import `use App\Contexts\Market\Datas\InstrumentSearchResultData;`.

- [ ] **Step 5 : implémenter dans l'adaptateur**

Dans `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`, juste après `findBySymbol()` :

```php
    /** @return list<InstrumentSearchResultData> */
    public function searchInstruments(string $query): array
    {
        try {
            $search = $this->python->run(YahooScript::Search->path(), ['query' => $query]);

            if (! $search->ok() || empty($search->data)) {
                return [];
            }

            $results = [];

            foreach ($search->data as $hit) {
                $symbol = (string) ($hit['symbol'] ?? '');

                if ($symbol === '') {
                    continue;
                }

                $results[] = new InstrumentSearchResultData(
                    symbol: $symbol,
                    name: (string) ($hit['name'] ?? $symbol),
                    exchange: $hit['exchange'] ?? null,
                    type: $this->typeFromYahoo($hit['type'] ?? null),
                );
            }

            return $results;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Le `typeDisp` de Yahoo vers l'enum du domaine. Un libellé inconnu — un indice, un warrant —
     * rend `null` plutôt que d'inventer un type : c'est l'écran de confirmation qui tranchera.
     */
    private function typeFromYahoo(?string $typeDisp): ?InstrumentType
    {
        return match (strtolower((string) $typeDisp)) {
            'equity', 'stock' => InstrumentType::Stock,
            'etf' => InstrumentType::ETF,
            'cryptocurrency', 'crypto' => InstrumentType::Crypto,
            'future', 'futures' => InstrumentType::Commodity,
            default => null,
        };
    }
```

Ajouter l'import `use App\Contexts\Market\Datas\InstrumentSearchResultData;`.

- [ ] **Step 6 : lancer les tests, vérifier le succès**

Run: `php artisan test --compact --filter=YahooFinanceAdapter`
Expected: PASS (les tests existants de l'adaptateur compris)

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market
git commit -m "feat: le fournisseur de marché sait chercher un instrument par nom"
```

---

### Task 2 : la route de recherche

**Files:**
- Create: `app/Contexts/Market/Http/SearchInstrumentsController.php`
- Create: `app/Contexts/Market/Http/SearchInstrumentsControllerTest.php`
- Modify: `routes/web.php`
- Modify: `resources/js/lib/swCache.ts`
- Test: `resources/js/lib/swCache.test.ts` (existant, on ajoute)

**Interfaces:**
- Consumes: `InstrumentProviderPort::searchInstruments()` et `InstrumentSearchResultData` (Task 1).
- Produces: `GET /instruments/recherche?q=` nommée `instruments.search`, rendant un tableau JSON d'objets `{symbol, name, exchange, type, typeLabel, existingId}` — `type` est la valeur de l'enum ou `null`, `typeLabel` son libellé français ou `null`, `existingId` l'identifiant en base ou `null`.

- [ ] **Step 1 : écrire le test qui échoue**

`app/Contexts/Market/Http/SearchInstrumentsControllerTest.php` :

```php
<?php

use App\Contexts\Market\Datas\InstrumentSearchResultData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\InstrumentProviderPort;

/** Un fournisseur qui rend ce qu'on lui dit, et retient ce qu'on lui a demandé. */
function fakeProvider(array $results = []): object
{
    $provider = new class($results) implements InstrumentProviderPort
    {
        public array $queries = [];

        public function __construct(private array $results) {}

        public function findBySymbol(string $symbol, InstrumentType $type): ?\App\Contexts\Market\Datas\InstrumentData
        {
            return null;
        }

        public function supportsInstruments(InstrumentType $type): bool
        {
            return true;
        }

        public function searchInstruments(string $query): array
        {
            $this->queries[] = $query;

            return $this->results;
        }
    };

    app()->instance(InstrumentProviderPort::class, $provider);

    return $provider;
}

it('rend une liste vide sans appeler le fournisseur quand la requête est vide', function () {
    $provider = fakeProvider([new InstrumentSearchResultData(symbol: 'AAPL', name: 'Apple')]);

    $this->getJson('/instruments/recherche?q=')->assertOk()->assertExactJson([]);

    expect($provider->queries)->toBe([]);
});

it('marque d\'un identifiant les instruments déjà en base', function () {
    $instrument = Instrument::factory()->create(['name' => 'Apple Inc.', 'ticker' => 'AAPL']);
    fakeProvider();

    $this->getJson('/instruments/recherche?q=appl')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.symbol', 'AAPL')
        ->assertJsonPath('0.existingId', $instrument->id)
        ->assertJsonPath('0.typeLabel', 'Action');
});

it('cherche aussi par ticker', function () {
    Instrument::factory()->create(['name' => 'Amundi MSCI World', 'ticker' => 'CW8.PA']);
    fakeProvider();

    $this->getJson('/instruments/recherche?q=cw8')->assertOk()->assertJsonCount(1);
});

it('complète la base par le fournisseur, sans doubler un symbole connu', function () {
    Instrument::factory()->create(['name' => 'Apple Inc.', 'ticker' => 'AAPL']);

    fakeProvider([
        new InstrumentSearchResultData(symbol: 'AAPL', name: 'Apple Inc.', exchange: 'NasdaqGS', type: InstrumentType::Stock),
        new InstrumentSearchResultData(symbol: 'APLE', name: 'Apple Hospitality REIT', exchange: 'NYSE', type: InstrumentType::Stock),
    ]);

    $response = $this->getJson('/instruments/recherche?q=apple')->assertOk()->assertJsonCount(2);

    /** La ligne de la base d'abord, marquée ; le résultat neuf ensuite, sans identifiant. */
    $response->assertJsonPath('0.existingId', Instrument::query()->first()->id)
        ->assertJsonPath('1.symbol', 'APLE')
        ->assertJsonPath('1.existingId', null)
        ->assertJsonPath('1.exchange', 'NYSE');
});

it('rend un type nul quand le fournisseur n\'a pas su le traduire', function () {
    fakeProvider([new InstrumentSearchResultData(symbol: '^FCHI', name: 'CAC 40', exchange: 'Paris', type: null)]);

    $this->getJson('/instruments/recherche?q=cac')
        ->assertOk()
        ->assertJsonPath('0.type', null)
        ->assertJsonPath('0.typeLabel', null);
});
```

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `php artisan test --compact --filter=SearchInstrumentsController`
Expected: FAIL — la route n'existe pas, `bootstrap/app.php` renvoie vers l'accueil (302 au lieu de 200)

- [ ] **Step 3 : écrire le contrôleur**

`app/Contexts/Market/Http/SearchInstrumentsController.php` :

```php
<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Datas\InstrumentSearchResultData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\InstrumentProviderPort;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La recherche d'un instrument, base d'abord puis fournisseur.
 *
 * L'ordre n'est pas une optimisation : `assets.ticker` n'a pas d'index unique, et voir la ligne
 * déjà connue avant de cliquer est la seule chose qui empêche d'en créer un double. Un résultat
 * marqué d'un `existingId` mène à sa fiche, il ne se crée pas.
 *
 * JSON et non Inertia, comme `/transactions/options` : la frappe interroge cette route à chaque
 * mot, une visite Inertia rechargerait la page à chaque lettre.
 */
class SearchInstrumentsController
{
    private const LOCAL_LIMIT = 10;

    public function __construct(private InstrumentProviderPort $provider) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        /** Rien à chercher : pas de requête SQL, et surtout pas de process Python. */
        if ($query === '') {
            return response()->json([]);
        }

        $local = Instrument::query()
            ->where(function (Builder $builder) use ($query): void {
                $builder->where('name', 'like', '%'.$query.'%')
                    ->orWhere('ticker', 'like', '%'.$query.'%');
            })
            ->orderBy('name')
            ->limit(self::LOCAL_LIMIT)
            ->get();

        $results = $local->map(fn (Instrument $instrument): InstrumentSearchResultData => new InstrumentSearchResultData(
            symbol: $instrument->ticker ?? '',
            name: $instrument->name ?? '',
            exchange: null,
            type: $instrument->type,
            existingId: $instrument->id,
        ))->all();

        $known = $local->pluck('ticker')
            ->filter()
            ->map(fn (string $ticker): string => strtolower($ticker))
            ->all();

        foreach ($this->provider->searchInstruments($query) as $hit) {
            if (in_array(strtolower($hit->symbol), $known, true)) {
                continue;
            }

            $results[] = $hit;
        }

        return response()->json(array_map(
            fn (InstrumentSearchResultData $result): array => [
                'symbol' => $result->symbol,
                'name' => $result->name,
                'exchange' => $result->exchange,
                'type' => $result->type?->value,
                'typeLabel' => $result->type?->getLabel(),
                'existingId' => $result->existingId,
            ],
            $results,
        ));
    }
}
```

- [ ] **Step 4 : déclarer la route**

Dans `routes/web.php`, après le bloc des transactions et avant la boucle des expositions :

```php
/**
 * La recherche d'un instrument : JSON, jamais mise en cache par le worker. Déclarée avant
 * `/asset/{id}` par habitude du dépôt — un segment littéral ne se lit jamais comme un identifiant.
 */
Route::get('/instruments/recherche', SearchInstrumentsController::class)->name('instruments.search');
```

Ajouter `use App\Contexts\Market\Http\SearchInstrumentsController;` aux imports.

- [ ] **Step 5 : lancer le test, vérifier le succès**

Run: `php artisan test --compact --filter=SearchInstrumentsController`
Expected: PASS

- [ ] **Step 6 : écrire le test du service worker**

Dans `resources/js/lib/swCache.test.ts`, à côté du cas `/transactions/options` (autour de la ligne 66) :

```typescript
    it('laisse passer la recherche d\'instruments sans la mettre en cache', () => {
        expect(
            classifyRequest(
                { method: 'GET', url: 'https://argent.test/instruments/recherche?q=apple', mode: 'cors', inertia: false, partialData: null },
                'https://argent.test',
            ),
        ).toBe('passthrough');
    });
```

Adapter la forme de l'appel à celle des cas voisins du fichier si elle diffère (helper local, `describe` englobant).

- [ ] **Step 7 : lancer le test, vérifier l'échec**

Run: `bunx vitest run resources/js/lib/swCache.test.ts`
Expected: FAIL — reçu `'other'` au lieu de `'passthrough'`

- [ ] **Step 8 : modifier `swCache.ts`**

```typescript
const TRANSACTION_OPTIONS_PATH = '/transactions/options';
const INSTRUMENT_SEARCH_PATH = '/instruments/recherche';
```

et dans `classifyRequest` :

```typescript
    if (
        url.pathname === SNAPSHOT_PATH
        || url.pathname === TRANSACTION_OPTIONS_PATH
        || url.pathname === INSTRUMENT_SEARCH_PATH
    ) {
        return 'passthrough';
    }
```

- [ ] **Step 9 : lancer les tests, vérifier le succès**

Run: `bunx vitest run resources/js/lib/swCache.test.ts`
Expected: PASS

- [ ] **Step 10 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market routes/web.php resources/js/lib/swCache.ts resources/js/lib/swCache.test.ts
git commit -m "feat: une route cherche un instrument, en base puis chez Yahoo"
```

---

### Task 3 : le job de synchronisation d'un instrument

**Files:**
- Create: `app/Contexts/Market/Jobs/SyncInstrumentJob.php`
- Create: `app/Contexts/Market/Jobs/SyncInstrumentJobTest.php`

**Interfaces:**
- Consumes: `SyncAssetPrices::__invoke(?int $assetId = null, ?string $since = null)`, `SyncAssetSectors::__invoke(?int $assetId = null)`, `SyncAssetDividends::__invoke(?int $assetId = null, ?string $since = null)`.
- Produces: `SyncInstrumentJob::__construct(public int $instrumentId)`, mis en file par `CreateInstrument` (Task 4).

- [ ] **Step 1 : écrire le test qui échoue**

`app/Contexts/Market/Jobs/SyncInstrumentJobTest.php` :

```php
<?php

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Actions\SyncAssetSectors;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Datas\PriceSyncReportData;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use Illuminate\Support\Carbon;

it('synchronise cinq ans du seul instrument créé', function () {
    Carbon::setTestNow('2026-09-01');

    $calls = [];

    $prices = Mockery::mock(SyncAssetPrices::class);
    $prices->shouldReceive('__invoke')
        ->once()
        ->with(42, '2021-09-01')
        ->andReturnUsing(function () use (&$calls): PriceSyncReportData {
            $calls[] = 'prices';

            return new PriceSyncReportData;
        });

    $sectors = Mockery::mock(SyncAssetSectors::class);
    $sectors->shouldReceive('__invoke')
        ->once()
        ->with(42)
        ->andReturnUsing(function () use (&$calls): array {
            $calls[] = 'sectors';

            return [];
        });

    $dividends = Mockery::mock(SyncAssetDividends::class);
    $dividends->shouldReceive('__invoke')
        ->once()
        ->with(42, '2021-09-01')
        ->andReturnUsing(function () use (&$calls): DividendSyncReportData {
            $calls[] = 'dividends';

            return new DividendSyncReportData;
        });

    (new SyncInstrumentJob(42))->handle($prices, $sectors, $dividends);

    /** Les cours d'abord, comme dans `SyncMarketData` : c'est eux qui font vivre la fiche. */
    expect($calls)->toBe(['prices', 'sectors', 'dividends']);
});
```

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `php artisan test --compact --filter=SyncInstrumentJob`
Expected: FAIL — `Class "App\Contexts\Market\Jobs\SyncInstrumentJob" not found`

- [ ] **Step 3 : écrire le job**

`app/Contexts/Market/Jobs/SyncInstrumentJob.php` :

```php
<?php

namespace App\Contexts\Market\Jobs;

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Actions\SyncAssetPrices;
use App\Contexts\Market\Actions\SyncAssetSectors;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * L'historique d'un instrument fraîchement créé : cinq ans, la profondeur d'`InstrumentCatalogSeeder`.
 *
 * Le job ne filtre rien lui-même — chacune des trois actions teste déjà le `supportsX()` de son
 * port avant d'ouvrir un process Python. Une crypto n'a pas de ventilation sectorielle, et c'est
 * `SyncAssetSectors` qui le sait.
 *
 * Pas de port d'état : `MarketSyncStatePort` raconte la synchronisation totale, celle du bouton du
 * tableau de bord. Y publier l'arrivée d'un seul instrument ferait clignoter le bouton pour un
 * travail qui ne le concerne pas.
 */
class SyncInstrumentJob implements ShouldQueue
{
    use Queueable;

    /** Une seule tentative, comme `SyncMarketDataJob` et le worker de développement. */
    public int $tries = 1;

    /** Trois passes Python sur un seul instrument : loin des quinze minutes du lot complet. */
    public int $timeout = 300;

    private const YEARS_OF_HISTORY = 5;

    public function __construct(public int $instrumentId) {}

    public function handle(
        SyncAssetPrices $prices,
        SyncAssetSectors $sectors,
        SyncAssetDividends $dividends,
    ): void {
        $since = today()->subYears(self::YEARS_OF_HISTORY)->format('Y-m-d');

        ($prices)($this->instrumentId, $since);
        ($sectors)($this->instrumentId);
        ($dividends)($this->instrumentId, $since);
    }
}
```

- [ ] **Step 4 : lancer le test, vérifier le succès**

Run: `php artisan test --compact --filter=SyncInstrumentJob`
Expected: PASS

- [ ] **Step 5 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Jobs
git commit -m "feat: un job remplit cinq ans d'historique pour un seul instrument"
```

---

### Task 4 : la création d'un instrument

**Files:**
- Create: `app/Contexts/Market/Datas/InstrumentInputData.php`
- Create: `app/Contexts/Market/Actions/CreateInstrument.php`
- Create: `app/Contexts/Market/Http/InstrumentRequest.php`
- Create: `app/Contexts/Market/Http/StoreInstrumentController.php`
- Create: `app/Contexts/Market/Http/StoreInstrumentControllerTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `SyncInstrumentJob` (Task 3), `InstrumentType`, `AssetClass`.
- Produces: `POST /instruments` nommée `instruments.store`, rendant `201` et `{id, name, ticker, isin, type, typeLabel, assetClass, assetClassLabel, assetClassSlug}`. `CreateInstrument::__invoke(InstrumentInputData $input): Instrument`.

- [ ] **Step 1 : écrire le test qui échoue**

`app/Contexts/Market/Http/StoreInstrumentControllerTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Support\Facades\Bus;

function instrumentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'NVIDIA Corp.',
        'ticker' => 'NVDA',
        'isin' => null,
        'type' => InstrumentType::Stock->value,
        'assetClass' => AssetClass::Equity->value,
    ], $overrides);
}

it('crée l\'instrument et met sa synchronisation en file', function () {
    Bus::fake();

    $response = $this->postJson('/instruments', instrumentPayload())->assertCreated();

    $instrument = Instrument::query()->firstOrFail();

    expect($instrument->name)->toBe('NVIDIA Corp.')
        ->and($instrument->ticker)->toBe('NVDA')
        ->and($instrument->isin)->toBeNull()
        ->and($instrument->type)->toBe(InstrumentType::Stock)
        ->and($instrument->asset_class)->toBe(AssetClass::Equity);

    $response->assertJsonPath('id', $instrument->id)
        ->assertJsonPath('typeLabel', 'Action')
        ->assertJsonPath('assetClassLabel', AssetClass::Equity->getLabel())
        ->assertJsonPath('assetClassSlug', AssetClass::Equity->slug());

    Bus::assertDispatched(
        SyncInstrumentJob::class,
        fn (SyncInstrumentJob $job): bool => $job->instrumentId === $instrument->id,
    );
});

it('garde l\'exposition envoyée plutôt que le défaut du type', function () {
    Bus::fake();

    /** Un ETF obligataire : le défaut du type dirait « Actions », l'utilisateur a dit autrement. */
    $this->postJson('/instruments', instrumentPayload([
        'name' => 'iShares Core Global Aggregate Bond',
        'ticker' => 'AGGH.L',
        'type' => InstrumentType::ETF->value,
        'assetClass' => AssetClass::Bond->value,
    ]))->assertCreated();

    expect(Instrument::query()->firstOrFail()->asset_class)->toBe(AssetClass::Bond);
});

it('refuse un corps incomplet ou un enum inconnu', function (array $payload, string $field) {
    Bus::fake();

    $this->postJson('/instruments', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(Instrument::query()->count())->toBe(0);
    Bus::assertNotDispatched(SyncInstrumentJob::class);
})->with([
    'sans nom' => [instrumentPayload(['name' => '']), 'name'],
    'sans ticker' => [instrumentPayload(['ticker' => '']), 'ticker'],
    'type inconnu' => [instrumentPayload(['type' => 'warrant']), 'type'],
    'exposition inconnue' => [instrumentPayload(['assetClass' => 'nft']), 'assetClass'],
]);

it('accepte un ISIN et le stocke', function () {
    Bus::fake();

    $this->postJson('/instruments', instrumentPayload(['isin' => 'US67066G1040']))->assertCreated();

    expect(Instrument::query()->firstOrFail()->isin)->toBe('US67066G1040');
});
```

> Le jeu de données appelle `instrumentPayload()` au chargement du fichier, avant tout `beforeEach` — la fonction ne touche ni la base ni le conteneur, c'est sans danger.

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `php artisan test --compact --filter=StoreInstrumentController`
Expected: FAIL — la route n'existe pas (302 vers l'accueil au lieu de 201)

- [ ] **Step 3 : écrire la Data d'entrée**

`app/Contexts/Market/Datas/InstrumentInputData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;

/**
 * Le corps validé d'une création, en route vers l'action. Les clés arrivent en camelCase comme
 * tout le JSON du dépôt ; c'est ici qu'elles rencontrent les enums du domaine.
 */
readonly class InstrumentInputData
{
    public function __construct(
        public string $name,
        public string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public AssetClass $assetClass,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated): self
    {
        return new self(
            name: (string) $validated['name'],
            ticker: (string) $validated['ticker'],
            isin: $validated['isin'] ?? null,
            type: InstrumentType::from((string) $validated['type']),
            assetClass: AssetClass::from((string) $validated['assetClass']),
        );
    }
}
```

- [ ] **Step 4 : écrire le FormRequest**

`app/Contexts/Market/Http/InstrumentRequest.php` :

```php
<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le corps d'une création d'instrument. La validation vit dans le `Http/` du contexte propriétaire
 * de la table `assets`, comme `Portfolio\Http\TransactionRequest` pour les transactions.
 */
class InstrumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** Les pages de lecture savent s'afficher vides ; une création n'a personne pour la porter. */
        return auth()->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            /**
             * Requis, là où la colonne est nullable : un instrument sans ticker ne serait
             * synchronisable par aucune des trois actions, qui l'écartent toutes explicitement.
             */
            'ticker' => ['required', 'string', 'max:255'],

            'isin' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstrumentType::class)],

            /**
             * Envoyée et non déduite : l'utilisateur a vu la valeur sur l'écran de confirmation.
             * `AssetClass::defaultForType()` reste le défaut d'une création sans exposition, posé
             * par le hook `creating` du modèle — ce chemin-ci ne l'emprunte pas.
             */
            'assetClass' => ['required', Rule::enum(AssetClass::class)],
        ];
    }
}
```

- [ ] **Step 5 : écrire l'action**

`app/Contexts/Market/Actions/CreateInstrument.php` :

```php
<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Datas\InstrumentInputData;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use App\Contexts\Market\Models\Instrument;

/**
 * La première écriture d'instrument de l'application : jusqu'ici le catalogue ne venait que des
 * seeders.
 */
class CreateInstrument
{
    public function __invoke(InstrumentInputData $input): Instrument
    {
        /** Tableau littéral et non `$request->validated()` : `Instrument` est gardé par `['id']`. */
        $instrument = Instrument::query()->create([
            'name' => $input->name,
            'ticker' => $input->ticker,
            'isin' => $input->isin,
            'type' => $input->type,
            'asset_class' => $input->assetClass,
        ]);

        /** La fiche est atteignable tout de suite, vide, le temps que le job passe. */
        SyncInstrumentJob::dispatch($instrument->id);

        return $instrument;
    }
}
```

- [ ] **Step 6 : écrire le contrôleur**

`app/Contexts/Market/Http/StoreInstrumentController.php` :

```php
<?php

namespace App\Contexts\Market\Http;

use App\Contexts\Market\Actions\CreateInstrument;
use App\Contexts\Market\Datas\InstrumentInputData;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Http\JsonResponse;

/**
 * JSON et non redirection, seule dérogation à la règle des routes d'écriture.
 *
 * La raison est le formulaire de transaction : le panneau de recherche s'y ouvre par-dessus une
 * saisie en cours, qui ne doit ni être perdue ni rejouée, et le formulaire a besoin de l'`id`
 * fraîchement créé pour le sélectionner. Une redirection ne le lui donnerait qu'au prix d'un flash
 * ou d'un rechargement complet des options.
 *
 * Le prix payé est côté client : les erreurs reviennent en 422 et se remappent à la main.
 */
class StoreInstrumentController
{
    public function __construct(private CreateInstrument $create) {}

    public function __invoke(InstrumentRequest $request): JsonResponse
    {
        $instrument = ($this->create)(InstrumentInputData::fromValidated($request->validated()));

        return response()->json($this->payload($instrument), 201);
    }

    /** @return array<string, mixed> */
    private function payload(Instrument $instrument): array
    {
        return [
            'id' => $instrument->id,
            'name' => $instrument->name,
            'ticker' => $instrument->ticker,
            'isin' => $instrument->isin,
            'type' => $instrument->type->value,
            'typeLabel' => $instrument->type->getLabel(),
            'assetClass' => $instrument->asset_class->value,
            'assetClassLabel' => $instrument->asset_class->getLabel(),
            /** Le client s'en sert pour aller au catalogue d'une autre exposition que la sienne. */
            'assetClassSlug' => $instrument->asset_class->slug(),
        ];
    }
}
```

- [ ] **Step 7 : déclarer la route**

Dans `routes/web.php`, juste sous la route de recherche :

```php
Route::post('/instruments', StoreInstrumentController::class)->name('instruments.store');
```

Ajouter `use App\Contexts\Market\Http\StoreInstrumentController;`.

- [ ] **Step 8 : lancer les tests, vérifier le succès**

Run: `php artisan test --compact --filter=StoreInstrumentController`
Expected: PASS

- [ ] **Step 9 : lancer les tests du contexte Market en entier**

Run: `php artisan test --compact app/Contexts/Market`
Expected: PASS — rien de cassé côté synchronisation

- [ ] **Step 10 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market routes/web.php
git commit -m "feat: une route crée un instrument et met son historique en file"
```

---

### Task 5 : le panneau de recherche

**Files:**
- Create: `resources/js/components/instruments/InstrumentSearchPanel.vue`
- Create: `resources/js/components/instruments/InstrumentSearchPanel.test.ts`

**Interfaces:**
- Consumes: `GET /instruments/recherche` (Task 2), `POST /instruments` (Task 4).
- Produces: un composant dont les props sont `{ initialTerm?: string; exposure?: string | null }` et qui émet `created` avec `{ id: number; name: string; ticker: string | null; assetClass: string; assetClassSlug: string }`, `cancel` sans charge utile, et `open` avec `{ id: number }` quand l'utilisateur choisit un instrument déjà en base.

- [ ] **Step 1 : écrire le test qui échoue**

`resources/js/components/instruments/InstrumentSearchPanel.test.ts` :

```typescript
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';

const { default: InstrumentSearchPanel } = await import('@/components/instruments/InstrumentSearchPanel.vue');

type Result = {
    symbol: string;
    name: string;
    exchange: string | null;
    type: string | null;
    typeLabel: string | null;
    existingId: number | null;
};

const yahooHit = (overrides: Partial<Result> = {}): Result => ({
    symbol: 'NVDA',
    name: 'NVIDIA Corp.',
    exchange: 'NasdaqGS',
    type: 'stock',
    typeLabel: 'Action',
    existingId: null,
    ...overrides,
});

/** Une réponse JSON minimale, comme `fetch` la rend. */
const jsonResponse = (body: unknown, status = 200): Response =>
    ({ ok: status < 400, status, json: async () => body }) as Response;

function mountPanel(props: Record<string, unknown> = {}): { host: HTMLElement; events: Record<string, unknown[]> } {
    const host = document.createElement('div');
    document.body.append(host);

    const events: Record<string, unknown[]> = { created: [], cancel: [], open: [] };

    createApp(InstrumentSearchPanel, {
        ...props,
        onCreated: (payload: unknown): void => void events.created.push(payload),
        onCancel: (): void => void events.cancel.push(null),
        onOpen: (payload: unknown): void => void events.open.push(payload),
    }).mount(host);

    return { host, events };
}

const type = async (host: HTMLElement, term: string): Promise<void> => {
    const input = host.querySelector<HTMLInputElement>('[data-instrument-search-input]')!;
    input.value = term;
    input.dispatchEvent(new Event('input'));
    await nextTick();
};

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('panneau de recherche d\'instruments', () => {
    it('ne cherche qu\'une fois la frappe retombée', async () => {
        const fetchMock = vi.fn(async () => jsonResponse([yahooHit()]));
        vi.stubGlobal('fetch', fetchMock);

        const { host } = mountPanel();

        await type(host, 'n');
        await type(host, 'nv');
        await type(host, 'nvd');

        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toContain('/instruments/recherche?q=nvd');
        expect(host.querySelectorAll('[data-instrument-result]')).toHaveLength(1);
    });

    it('mène à la fiche d\'un instrument déjà en base, sans le recréer', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit({ existingId: 12 })])));

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        const row = host.querySelector<HTMLElement>('[data-instrument-result]')!;
        expect(row.querySelector('[data-instrument-existing]')).not.toBeNull();

        row.querySelector('button')!.click();
        await nextTick();

        expect(events.open).toEqual([{ id: 12 }]);
        /** Pas d'étape 2 : il n'y a rien à confirmer pour un instrument connu. */
        expect(host.querySelector('[data-instrument-confirm]')).toBeNull();
    });

    it('passe à la confirmation, pré-remplie du résultat', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();

        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        expect(host.querySelector<HTMLInputElement>('[data-instrument-name]')?.value).toBe('NVIDIA Corp.');
        expect(host.querySelector<HTMLInputElement>('[data-instrument-ticker]')?.value).toBe('NVDA');
        expect(host.querySelector<HTMLSelectElement>('[data-instrument-type]')?.value).toBe('stock');
    });

    it('pré-remplit l\'exposition de la page quand elle est donnée', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel({ exposure: 'crypto' });

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        expect(host.querySelector<HTMLSelectElement>('[data-instrument-asset-class]')?.value).toBe('crypto');
    });

    it('revient à l\'étape 1 en gardant le terme cherché', async () => {
        vi.stubGlobal('fetch', vi.fn(async () => jsonResponse([yahooHit()])));

        const { host } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLElement>('[data-instrument-back]')!.click();
        await nextTick();

        expect(host.querySelector<HTMLInputElement>('[data-instrument-search-input]')?.value).toBe('nvda');
    });

    it('émet l\'instrument créé', async () => {
        const fetchMock = vi.fn(async (url: unknown, init?: RequestInit) =>
            init?.method === 'POST'
                ? jsonResponse({
                      id: 31,
                      name: 'NVIDIA Corp.',
                      ticker: 'NVDA',
                      assetClass: 'equity',
                      assetClassSlug: 'actions',
                  }, 201)
                : jsonResponse([yahooHit()]),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(events.created).toEqual([
            { id: 31, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' },
        ]);
    });

    it('affiche les erreurs de validation du serveur', async () => {
        const fetchMock = vi.fn(async (url: unknown, init?: RequestInit) =>
            init?.method === 'POST'
                ? jsonResponse({ message: 'invalide', errors: { ticker: ['Le ticker est obligatoire.'] } }, 422)
                : jsonResponse([yahooHit()]),
        );
        vi.stubGlobal('fetch', fetchMock);

        const { host, events } = mountPanel();

        await type(host, 'nvda');
        await vi.advanceTimersByTimeAsync(300);
        await nextTick();
        host.querySelector<HTMLElement>('[data-instrument-result] button')!.click();
        await nextTick();

        host.querySelector<HTMLFormElement>('[data-instrument-confirm]')!.dispatchEvent(new Event('submit'));
        await vi.advanceTimersByTimeAsync(0);
        await nextTick();

        expect(host.querySelector('[data-instrument-error]')?.textContent).toContain('Le ticker est obligatoire.');
        expect(events.created).toEqual([]);
    });
});
```

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `bunx vitest run resources/js/components/instruments/InstrumentSearchPanel.test.ts`
Expected: FAIL — le fichier `.vue` n'existe pas

- [ ] **Step 3 : écrire le composant**

`resources/js/components/instruments/InstrumentSearchPanel.vue` :

```vue
<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';

/**
 * La recherche d'un instrument, en deux étapes, sans chrome de modale.
 *
 * Sans chrome pour une raison précise : la page catalogue l'enveloppe dans un `Dialog`, mais le
 * formulaire de transaction l'affiche **à la place** de ses champs, dans le `DialogContent` déjà
 * ouvert. Deux dialogues reka-ui superposés donneraient deux verrous de défilement, deux pièges de
 * focus et un `Échap` ambigu — c'est déjà la raison pour laquelle la confirmation de suppression
 * de `TransactionDialog` est un volet et non un second dialogue.
 */
type SearchResult = {
    symbol: string;
    name: string;
    exchange: string | null;
    type: string | null;
    typeLabel: string | null;
    existingId: number | null;
};

export type CreatedInstrument = {
    id: number;
    name: string;
    ticker: string | null;
    assetClass: string;
    assetClassSlug: string;
};

const props = withDefaults(defineProps<{ initialTerm?: string; exposure?: string | null }>(), {
    initialTerm: '',
    exposure: null,
});

const emit = defineEmits<{
    created: [instrument: CreatedInstrument];
    cancel: [];
    open: [payload: { id: number }];
}>();

/** Les deux enums, dupliqués côté client comme partout ailleurs dans les formulaires. */
const TYPES: { value: string; label: string }[] = [
    { value: 'stock', label: 'Action' },
    { value: 'etf', label: 'ETF' },
    { value: 'crypto', label: 'Cryptomonnaie' },
    { value: 'bond', label: 'Obligation' },
    { value: 'commodity', label: 'Matière première' },
];

/** Les quatre cas d'`AssetClass`, dans leur ordre — qui est un contrat côté PHP. */
const ASSET_CLASSES: { value: string; label: string }[] = [
    { value: 'equity', label: 'Actions' },
    { value: 'bond', label: 'Obligations' },
    { value: 'commodity', label: 'Matières premières' },
    { value: 'crypto', label: 'Crypto' },
];

/** L'exposition par défaut d'un type, jumelle d'`AssetClass::defaultForType()`. */
const defaultClassFor = (type: string): string =>
    ({ stock: 'equity', etf: 'equity', bond: 'bond', crypto: 'crypto', commodity: 'commodity' })[type] ?? 'equity';

const term: Ref<string> = ref(props.initialTerm);
const results: Ref<SearchResult[]> = ref([]);
const searching: Ref<boolean> = ref(false);
const failed: Ref<boolean> = ref(false);
const chosen: Ref<SearchResult | null> = ref(null);
const submitting: Ref<boolean> = ref(false);
const errors: Ref<Record<string, string>> = ref({});

const draft = ref({ name: '', ticker: '', isin: '', type: 'stock', assetClass: 'equity' });

let timer: ReturnType<typeof setTimeout> | null = null;
let inFlight: AbortController | null = null;

const search = async (value: string): Promise<void> => {
    inFlight?.abort();

    if (value.trim() === '') {
        results.value = [];

        return;
    }

    const controller = new AbortController();
    inFlight = controller;
    searching.value = true;
    failed.value = false;

    try {
        const response = await fetch(`/instruments/recherche?q=${encodeURIComponent(value.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            failed.value = true;

            return;
        }

        results.value = (await response.json()) as SearchResult[];
    } catch {
        /** Une requête annulée par la frappe suivante n'est pas un échec à montrer. */
        if (!controller.signal.aborted) {
            failed.value = true;
        }
    } finally {
        if (inFlight === controller) {
            searching.value = false;
        }
    }
};

/** Débouncé : la frappe ne doit pas ouvrir un process Python par lettre. */
watch(term, (value: string): void => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    timer = setTimeout((): void => void search(value), 300);
});

onBeforeUnmount((): void => {
    if (timer !== null) {
        clearTimeout(timer);
    }

    inFlight?.abort();
});

const choose = (result: SearchResult): void => {
    if (result.existingId !== null) {
        emit('open', { id: result.existingId });

        return;
    }

    const type = result.type ?? 'stock';

    chosen.value = result;
    errors.value = {};
    draft.value = {
        name: result.name,
        ticker: result.symbol,
        isin: '',
        type,
        /** L'exposition de la page prime : un instrument créé ailleurs disparaîtrait au retour. */
        assetClass: props.exposure ?? defaultClassFor(type),
    };
};

const back = (): void => {
    chosen.value = null;
    errors.value = {};
};

const submit = async (): Promise<void> => {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};

    try {
        const response = await fetch('/instruments', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                name: draft.value.name,
                ticker: draft.value.ticker,
                isin: draft.value.isin === '' ? null : draft.value.isin,
                type: draft.value.type,
                assetClass: draft.value.assetClass,
            }),
        });

        if (response.status === 422) {
            const body = (await response.json()) as { errors?: Record<string, string[]> };

            errors.value = Object.fromEntries(
                Object.entries(body.errors ?? {}).map(([field, messages]): [string, string] => [field, messages[0]]),
            );

            return;
        }

        if (!response.ok) {
            errors.value = { global: "L'instrument n'a pas pu être créé." };

            return;
        }

        emit('created', (await response.json()) as CreatedInstrument);
    } catch {
        errors.value = { global: "L'instrument n'a pas pu être créé." };
    } finally {
        submitting.value = false;
    }
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <template v-if="chosen === null">
            <input
                v-model="term"
                data-instrument-search-input
                type="search"
                aria-label="Chercher un instrument"
                placeholder="Nom ou ticker"
                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            />

            <p v-if="searching" data-instrument-searching class="text-sm text-muted-foreground">Recherche…</p>

            <p v-else-if="failed" data-instrument-failed class="text-sm text-destructive">
                La recherche n'a rien pu ramener. Réessaie dans un instant.
            </p>

            <ul v-else-if="results.length" class="flex flex-col">
                <li v-for="result in results" :key="result.symbol" data-instrument-result class="border-b border-separator last:border-b-0">
                    <button type="button" class="flex w-full items-center gap-3 px-3 py-3 text-left hover:bg-muted" @click="choose(result)">
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="truncate font-semibold">
                                {{ result.name }}
                                <span class="text-muted-foreground">({{ result.symbol }})</span>
                            </span>
                            <span class="flex items-center gap-2 text-xs text-muted-foreground">
                                <span v-if="result.typeLabel">{{ result.typeLabel }}</span>
                                <span v-if="result.exchange">{{ result.exchange }}</span>
                                <span v-if="result.existingId !== null" data-instrument-existing class="font-semibold text-subtle-foreground">
                                    Déjà suivi
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <p v-else-if="term.trim() !== ''" data-instrument-none class="py-6 text-center text-sm text-muted-foreground">
                Aucun instrument ne porte ce nom.
            </p>

            <button type="button" data-instrument-cancel class="self-start text-sm text-muted-foreground" @click="emit('cancel')">
                Annuler
            </button>
        </template>

        <form v-else data-instrument-confirm class="flex flex-col gap-4" @submit.prevent="submit()">
            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Nom
                <input v-model="draft.name" data-instrument-name type="text" class="rounded-md border border-input bg-background px-3 py-2 font-normal" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Ticker
                <input v-model="draft.ticker" data-instrument-ticker type="text" class="rounded-md border border-input bg-background px-3 py-2 font-normal" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                ISIN
                <input v-model="draft.isin" data-instrument-isin type="text" class="rounded-md border border-input bg-background px-3 py-2 font-normal" />
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Type
                <select v-model="draft.type" data-instrument-type class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in TYPES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm font-medium">
                Exposition
                <select v-model="draft.assetClass" data-instrument-asset-class class="rounded-md border border-input bg-background px-3 py-2 font-normal">
                    <option v-for="option in ASSET_CLASSES" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <p v-if="Object.keys(errors).length" data-instrument-error class="text-xs text-destructive">
                {{ Object.values(errors).join(' ') }}
            </p>

            <div class="flex items-center gap-3">
                <button type="submit" :disabled="submitting" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                    Ajouter
                </button>
                <button type="button" data-instrument-back class="text-sm text-muted-foreground" @click="back()">Retour</button>
            </div>
        </form>
    </div>
</template>
```

> Vérifier les classes utilitaires contre un composant voisin (`resources/js/components/ui/input/Input.vue`, `ui/native-select`) et remplacer les `input`/`select` nus par ces composants si leurs props le permettent sans casser les sélecteurs `data-*` des tests. La forme compte plus que la copie exacte des classes.

- [ ] **Step 4 : lancer les tests, vérifier le succès**

Run: `bunx vitest run resources/js/components/instruments/InstrumentSearchPanel.test.ts`
Expected: PASS (7 cas)

- [ ] **Step 5 : commit**

```bash
git add resources/js/components/instruments
git commit -m "feat: un panneau cherche un instrument et confirme ses métadonnées"
```

---

### Task 6 : l'ajout depuis le catalogue d'une exposition

**Files:**
- Modify: `resources/js/pages/AssetClass/Catalog.vue`
- Create: `resources/js/pages/AssetClass/Catalog.test.ts`

**Interfaces:**
- Consumes: `InstrumentSearchPanel` et son événement `created` (Task 5), les props de page `assetClass: { key, label, slug }` et `catalog: CatalogLine[]`.
- Produces: rien pour les tâches suivantes.

- [ ] **Step 1 : écrire le test qui échoue**

`resources/js/pages/AssetClass/Catalog.test.ts` :

```typescript
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, nextTick, type VNode } from 'vue';
import type { CatalogLine } from '@/lib/catalog';

const reload = vi.fn();
const visit = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: { setup: () => () => null },
    Link: {
        props: { href: { type: String, required: true } },
        setup: (props: { href: string }, { slots, attrs }: { slots: Record<string, () => VNode[]>; attrs: Record<string, unknown> }) =>
            () => h('a', { ...attrs, href: props.href }, slots.default?.()),
    },
    router: { reload: (...args: unknown[]) => reload(...args), visit: (...args: unknown[]) => visit(...args) },
}));

const { default: Catalog } = await import('@/pages/AssetClass/Catalog.vue');

const line = (overrides: Partial<CatalogLine> = {}): CatalogLine => ({
    id: 7,
    name: 'Apple',
    ticker: 'AAPL',
    isin: 'US0378331005',
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 200,
    held: false,
    quantity: null,
    marketValue: null,
    ...overrides,
});

function mountCatalog(): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(Catalog, {
        assetClass: { key: 'equity', label: 'Actions', slug: 'actions' },
        catalog: [line()],
    }).mount(host);

    return host;
}

const filter = async (host: HTMLElement, term: string): Promise<void> => {
    const input = host.querySelector<HTMLInputElement>('[data-catalog-search]')!;
    input.value = term;
    input.dispatchEvent(new Event('input'));
    await nextTick();
};

describe('catalogue d\'une exposition', () => {
    it('propose de chercher chez Yahoo quand le filtre ne trouve rien', async () => {
        const host = mountCatalog();

        expect(host.querySelector('[data-catalog-yahoo]')).toBeNull();

        await filter(host, 'nvidia');

        expect(host.querySelector('[data-catalog-yahoo]')?.textContent).toContain('nvidia');
    });

    it('ne propose rien tant que le filtre est vide', async () => {
        const host = mountCatalog();

        await filter(host, '');

        expect(host.querySelector('[data-catalog-yahoo]')).toBeNull();
    });
});
```

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `bunx vitest run resources/js/pages/AssetClass/Catalog.test.ts`
Expected: FAIL — `[data-catalog-yahoo]` introuvable

- [ ] **Step 3 : modifier la page**

Dans `resources/js/pages/AssetClass/Catalog.vue`, remplacer le bloc `<script setup>` par :

```typescript
import { computed, ref } from 'vue';
import type { Ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import CatalogList from '@/components/instruments/CatalogList.vue';
import InstrumentSearchPanel, { type CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { filterCatalog, type CatalogLine } from '@/lib/catalog';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    catalog: CatalogLine[];
}>();

/** La recherche ne quitte pas la page : le catalogue d'une poche tient en mémoire. */
const term = ref<string>('');

const lines = computed<CatalogLine[]>(() => filterCatalog(props.catalog, term.value));

/**
 * L'ajout s'accroche à l'état vide du filtre plutôt qu'à un second champ : c'est là que le manque
 * se constate, et l'écran ne montre jamais deux recherches à la fois.
 */
const adding: Ref<boolean> = ref(false);

const onCreated = (instrument: CreatedInstrument): void => {
    adding.value = false;

    /**
     * La page ne vaut que pour une exposition. Un instrument rangé ailleurs n'apparaîtrait pas au
     * rechargement, et la création aurait l'air ratée : on va alors à son catalogue.
     */
    if (instrument.assetClass !== props.assetClass.key) {
        router.visit(`/${instrument.assetClassSlug}/catalogue`);

        return;
    }

    term.value = '';
    router.reload({ only: ['catalog'] });
};
</script>
```

Puis, dans le template, remplacer le bloc `<div class="-mx-3">` par :

```vue
            <div class="-mx-3">
                <CatalogList
                    :lines="lines"
                    :empty-label="term.trim() === '' ? 'Aucun instrument dans cette classe.' : 'Aucun instrument ne correspond à cette recherche.'"
                />
            </div>

            <button
                v-if="term.trim() !== '' && lines.length === 0"
                type="button"
                data-catalog-yahoo
                class="self-start text-sm font-semibold text-primary"
                @click="adding = true"
            >
                Chercher « {{ term.trim() }} » chez Yahoo
            </button>
        </section>

        <Dialog v-model:open="adding">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Ajouter un instrument</DialogTitle>
                </DialogHeader>

                <InstrumentSearchPanel
                    :initial-term="term.trim()"
                    :exposure="props.assetClass.key"
                    @created="onCreated"
                    @cancel="adding = false"
                    @open="(payload) => router.visit(`/asset/${payload.id}`)"
                />
            </DialogContent>
        </Dialog>
```

en veillant à ne pas laisser deux `</section>` : le `</section>` d'origine est déplacé au-dessus du `<Dialog>`, il ne s'en ajoute pas un second.

- [ ] **Step 4 : lancer le test, vérifier le succès**

Run: `bunx vitest run resources/js/pages/AssetClass/Catalog.test.ts`
Expected: PASS

- [ ] **Step 5 : vérifier dans le navigateur**

```bash
bun run build
```

Ouvrir `https://argent.test/actions/catalogue`, taper un nom absent, cliquer le lien, chercher `NVDA`, confirmer. La ligne doit apparaître sans cours, et `php artisan queue:work --once` doit la remplir.

- [ ] **Step 6 : commit**

```bash
git add resources/js/pages/AssetClass
git commit -m "feat: le catalogue d'une exposition propose de chercher un instrument chez Yahoo"
```

---

### Task 7 : l'ajout depuis le formulaire de transaction

**Files:**
- Modify: `resources/js/components/transactions/TransactionForm.vue`
- Test: `resources/js/components/transactions/TransactionForm.test.ts` (existant, on ajoute)

**Interfaces:**
- Consumes: `InstrumentSearchPanel` et son événement `created` (Task 5), la route `/transactions/options` déjà lue par le formulaire.
- Produces: rien.

- [ ] **Step 1 : écrire le test qui échoue**

À ajouter à `resources/js/components/transactions/TransactionForm.test.ts`, en suivant les helpers de montage et de doubles `fetch` déjà présents en tête de fichier (les réutiliser plutôt que d'en écrire d'autres) :

```typescript
    it('bascule vers la recherche d\'instrument et sélectionne celui qui vient d\'être créé', async () => {
        /**
         * Le panneau est un composant à part, testé chez lui : ici on ne vérifie que la bascule et
         * ce que le formulaire fait de l'instrument créé.
         */
        const host = await mountForm({ type: 'buy' });

        host.querySelector<HTMLElement>('[data-transaction-add-instrument]')!.click();
        await nextTick();

        /** Les champs cèdent la place, ils ne s'empilent pas sous un second dialogue. */
        expect(host.querySelector('[data-instrument-search-input]')).not.toBeNull();
        expect(host.querySelector('#transaction-date')).toBeNull();

        await emitCreated(host, { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' });

        expect(host.querySelector('[data-instrument-search-input]')).toBeNull();
        expect(currentAssetId(host)).toBe('99');
    });
```

`mountForm`, `emitCreated` et `currentAssetId` sont à écrire au niveau du fichier s'ils n'existent pas :
- `mountForm` : monter `TransactionForm` avec les doubles déjà en place dans ce fichier.
- `emitCreated` : simuler l'émission `created` du panneau. Le plus simple est de mocker `@/components/instruments/InstrumentSearchPanel.vue` en tête de fichier par un stub qui expose un bouton :

```typescript
vi.mock('@/components/instruments/InstrumentSearchPanel.vue', () => ({
    default: {
        emits: ['created', 'cancel', 'open'],
        setup: (_props: unknown, { emit }: { emit: (event: string, payload?: unknown) => void }) => () =>
            h('div', [
                h('input', { 'data-instrument-search-input': '' }),
                h('button', {
                    'data-stub-create': '',
                    onClick: () =>
                        emit('created', { id: 99, name: 'NVIDIA Corp.', ticker: 'NVDA', assetClass: 'equity', assetClassSlug: 'actions' }),
                }),
            ]),
    },
}));
```

`emitCreated` se réduit alors à cliquer `[data-stub-create]` puis `await nextTick()`, et le double `fetch` de `/transactions/options` doit rendre l'instrument 99 au second appel.

- [ ] **Step 2 : lancer le test, vérifier l'échec**

Run: `bunx vitest run resources/js/components/transactions/TransactionForm.test.ts`
Expected: FAIL — `[data-transaction-add-instrument]` introuvable

- [ ] **Step 3 : modifier le formulaire**

Dans le `<script setup>` de `resources/js/components/transactions/TransactionForm.vue`, ajouter aux imports :

```typescript
import InstrumentSearchPanel, { type CreatedInstrument } from '@/components/instruments/InstrumentSearchPanel.vue';
```

et, près des autres `ref` :

```typescript
/**
 * Le panneau remplace les champs, il ne s'ouvre pas par-dessus : un second `Dialog` sous celui de
 * `TransactionDialog` donnerait deux verrous de défilement et un `Échap` ambigu. Le formulaire
 * n'est pas démonté, seulement masqué — la saisie en cours survit.
 */
const searchingInstrument: Ref<boolean> = ref(false);

/** Les options rechargées de la même route qu'à l'ouverture : c'est elle qui porte le catalogue. */
const reloadOptions = async (): Promise<void> => {
    try {
        const response = await fetch('/transactions/options', { headers: { Accept: 'application/json' } });

        if (response.ok) {
            options.value = (await response.json()) as FormOptions;
        }
    } catch {
        optionsFailed.value = true;
    }
};

const onInstrumentCreated = async (instrument: CreatedInstrument): Promise<void> => {
    searchingInstrument.value = false;

    await reloadOptions();

    form.assetId = String(instrument.id);
    prefillUnitPrice();
};
```

Dans le template, envelopper le contenu existant du formulaire pour qu'il cède la place :

```vue
    <InstrumentSearchPanel
        v-if="searchingInstrument"
        @created="onInstrumentCreated"
        @cancel="searchingInstrument = false"
        @open="searchingInstrument = false"
    />

    <template v-else>
        <!-- tout le contenu actuel du formulaire, inchangé -->
    </template>
```

et, sous le `FormField` de l'actif (dans la branche `v-else` du sélecteur, pas dans le cas verrouillé) :

```vue
            <button
                type="button"
                data-transaction-add-instrument
                class="self-start text-sm text-muted-foreground underline"
                @click="searchingInstrument = true"
            >
                L'actif n'est pas dans la liste ?
            </button>
```

Ne pas ajouter ce bouton quand `dialog.lockedAssetName !== null` : l'actif est imposé par la page, en changer n'a pas de sens.

- [ ] **Step 4 : lancer le test, vérifier le succès**

Run: `bunx vitest run resources/js/components/transactions/TransactionForm.test.ts`
Expected: PASS — le nouveau cas et les cas existants

- [ ] **Step 5 : lancer toute la suite**

Run: `bunx vitest run` puis `php artisan test --compact`
Expected: PASS des deux côtés

- [ ] **Step 6 : vérifier dans le navigateur**

```bash
bun run build
```

Ouvrir une saisie d'achat, remplir la date et l'enveloppe, cliquer « L'actif n'est pas dans la liste ? », créer un instrument : au retour, la date et l'enveloppe doivent être **toujours là**, et l'actif sélectionné.

- [ ] **Step 7 : commit**

```bash
git add resources/js/components/transactions
git commit -m "feat: une saisie peut créer l'actif qui lui manque sans se perdre"
```

---

## Auto-revue

**Couverture de la spec**

| Section de la spec | Tâche |
| --- | --- |
| Port et adaptateur, mapping `typeDisp` | Task 1 |
| `GET /instruments/recherche`, base d'abord | Task 2 |
| Service worker `passthrough` | Task 2 |
| `POST /instruments` en JSON, `InstrumentRequest`, `CreateInstrument` | Task 4 |
| Job cinq ans, sans refiltrer les `supportsX()` | Task 3 |
| Panneau deux étapes, sans chrome de modale | Task 5 |
| État vide du catalogue, piège de l'exposition | Task 6 |
| Formulaire de transaction, refetch des options | Task 7 |

**Hors périmètre, conforme à la spec** : suppression de `findBySymbol()`, index unique sur `assets.ticker`, suppression d'instrument, obligations.

**Cohérence des types** : `InstrumentSearchResultData` (Task 1) est consommée telle quelle par le contrôleur de recherche (Task 2), qui l'aplatit en `{symbol, name, exchange, type, typeLabel, existingId}` — la forme exacte que `SearchResult` déclare côté client (Task 5). `CreatedInstrument` (Task 5) reprend clé pour clé la charge de `StoreInstrumentController::payload()` (Task 4), aux champs près qui ne servent pas au client (`isin`, `typeLabel`, `assetClassLabel` restent servis, simplement inutilisés). `SyncInstrumentJob::$instrumentId` (Task 3) est le nom lu par le test de dispatch de la Task 4.
