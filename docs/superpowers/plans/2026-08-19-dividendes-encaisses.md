# Plan d'implémentation — Dividendes encaissés

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher le revenu de dividende déjà perçu par instrument et pour le portefeuille, calculé depuis les détachements Yahoo et les quantités détenues à l'ex-date, sans toucher au gain latent.

**Architecture:** Le contexte `Market` gagne un write-side dividendes calqué sur celui des prix (table, port de feed, script Python en lot, action de sync, commande planifiée). Un nouveau contexte `Income` agrège des reçus de revenu derrière un port `IncomeSourcePort` ; le dividende en est la première source, avec son calcul isolé dans un service sans base. Les vues (fiche instrument, tableau de bord) consomment `Income` par ses actions.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest 5 (tests colocalisés dans `app/Contexts`), Inertia v3 + Vue 3 + TypeScript, Vitest, ECharts (non utilisé ici), yfinance via `PythonRunner`, Tailwind v4.

**Spec:** `docs/superpowers/specs/2026-08-19-dividendes-encaisses-design.md`

## Global Constraints

- Tout texte visible par l'utilisateur est en **français** (libellés, états vides, en-têtes de tableau, aide de commande).
- Les tests PHP vivent **à côté des classes** dans `app/Contexts` (`pest()` les charge via `'../app/Contexts'`), sauf les tests de page (`tests/Feature`) et de navigateur (`tests/Browser`).
- `vendor/bin/pint --dirty --format agent` après toute modification PHP, avant chaque commit.
- Lancer le minimum de tests : `php artisan test --compact --filter=<nom>`.
- Aucune dépendance ajoutée (ni Composer ni npm).
- Le contexte `Income` ne référence **aucune** classe d'un autre contexte hors de son dossier `Infrastructure/` : les dépendances passent par ses propres ports. Même règle que `Valuation`.
- Le noyau d'`Income` (`Datas/`, `Ports/`, `Actions/`, `Enums/`) ne nomme jamais le dividende : tout ce qui est spécifique vit sous `Sources/Dividend/`.
- Montants **bruts**, supposés en euros : ni retenue à la source, ni conversion de devise.
- Aucune modification du contexte `Portfolio` : pas de nouveau `TransactionType`, pas de touche à `holdings_projection`.
- `decimal(12, 6)` pour un montant par action, `Y-m-d` pour une `ex_date`.
- Commits en français, préfixés `feat:` / `test:` / `refactor:` selon l'usage du dépôt.

## Écarts assumés par rapport à la spec

Deux choix pris à l'écriture du plan, plus simples que ce que la spec décrivait :

1. **La prop `dividends` de la fiche instrument n'est pas différée.** La visibilité de la section dépend de la donnée elle-même : différée, un instrument capitalisant afficherait un squelette avant de faire disparaître sa section. Le coût est de deux petites requêtes, sur une page qui en fait déjà autant pour ses performances.
2. **Le graphe annuel du tableau de bord est une liste de barres CSS**, pas un graphe ECharts. `bars.ts` (`largestOf`, `relativeBarWidth`) et le patron de `PerformanceBars.vue` couvrent le besoin sans ajouter de branche « axe catégoriel » à `chartFrame()`.
3. **`portfolioFixture()` n'est pas touchée.** Les deux tests de navigateur qui ont besoin d'un détachement en créent un sur place, en une ligne. Ajouter un paramètre à une fabrique partagée par toute la suite coûterait plus que ces deux lignes, et les tests d'ordre des sections dépendent du fait que la fixture reste sans dividende.

## Structure des fichiers

**Contexte `Market` (write-side dividendes)**
- `database/migrations/*_create_asset_dividends_table.php` — la table.
- `app/Contexts/Market/Models/Dividend.php` + `Factories/DividendFactory.php` — l'enregistrement.
- `app/Contexts/Market/Datas/DividendData.php`, `DividendRequestData.php`, `DividendSyncReportData.php` — les valeurs qui traversent les frontières.
- `app/Contexts/Market/Contracts/DividendRepositoryContract.php` + `Infrastructure/EloquentDividendRepository.php` — la persistance.
- `app/Contexts/Market/Ports/DividendFeedPort.php`, `DividendFeedException.php` — le contrat de récupération distante.
- `app/Contexts/Market/Infrastructure/Python/fetch_dividends_bulk.py` — la récupération yfinance.
- `app/Contexts/Market/Actions/SyncAssetDividends.php` + `Console/SyncDividendsCommand.php` — l'orchestration.

**Contexte `Income` — noyau, ignorant des dividendes**
- `Enums/IncomeSource.php`, `Datas/IncomeReceiptData.php`, `Datas/IncomeSummaryData.php`, `Datas/AnnualIncomeData.php`.
- `Ports/IncomeSourcePort.php`, `Infrastructure/IncomeSourceRegistry.php`.
- `Actions/GetIncomeSummary.php`, `Actions/GetAnnualIncome.php`, `IncomeProvider.php`.

**Contexte `Income` — source dividende**
- `Sources/Dividend/Services/DividendCalculator.php` — le calcul, sans base.
- `Sources/Dividend/Datas/` — `DividendRecordData`, `PositionRecordData`, `PositionSnapshotData`, `DividendReceiptData`, `AssetDividendHistoryData`.
- `Sources/Dividend/Ports/` — `DividendHistoryPort`, `PositionHistoryPort`.
- `Sources/Dividend/Infrastructure/` — `MarketDividendHistory`, `PortfolioPositionHistory`.
- `Sources/Dividend/DividendIncomeSource.php`, `Sources/Dividend/Actions/GetAssetDividendHistory.php`.

**Front**
- `resources/js/lib/income.ts` (+ `income.test.ts`) — types et calcul de largeur de barre.
- `resources/js/components/instrument/DividendsSection.vue`, `resources/js/components/dashboard/IncomeSection.vue`.

---

### Task 1: Table, modèle et fabrique du dividende

**Files:**
- Create: `database/migrations/<horodatage>_create_asset_dividends_table.php`
- Create: `app/Contexts/Market/Models/Dividend.php`
- Create: `app/Contexts/Market/Factories/DividendFactory.php`
- Test: `app/Contexts/Market/Models/DividendTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `App\Contexts\Market\Models\Dividend` — table `asset_dividends`, propriétés `asset_id` (int), `ex_date` (Carbon), `amount_per_share` (string décimal 6), relation `instrument()`. `DividendFactory` avec `asset_id` = `Instrument::factory()`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `app/Contexts/Market/Models/DividendTest.php` :

```php
<?php

use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\QueryException;

it('cast la date de détachement et garde six décimales sur le montant', function () {
    $dividend = Dividend::factory()->create([
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.123456,
    ])->fresh();

    expect($dividend->ex_date->format('Y-m-d'))->toBe('2026-03-05')
        ->and((float) $dividend->amount_per_share)->toBe(0.123456);
});

it('appartient à son instrument', function () {
    $instrument = Instrument::factory()->create();
    $dividend = Dividend::factory()->create(['asset_id' => $instrument->id]);

    expect($dividend->instrument->id)->toBe($instrument->id);
});

it('refuse deux détachements le même jour pour le même actif', function () {
    $instrument = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);

    expect(fn () => Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
    ]))->toThrow(QueryException::class);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DividendTest`
Expected: FAIL — `Class "App\Contexts\Market\Models\Dividend" not found`

- [ ] **Step 3: Créer la migration**

Run: `php artisan make:migration create_asset_dividends_table --no-interaction`

Remplacer le corps du fichier généré par :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Détachements de dividende par instrument, en montant par action. Six décimales et non
     * quatre comme les cours : un dividende trimestriel d'ETF se compte en centièmes de centime,
     * et l'arrondi dérive dès qu'on le multiplie par une position.
     */
    public function up(): void
    {
        Schema::create('asset_dividends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->date('ex_date');
            $table->decimal('amount_per_share', 12, 6);
            $table->timestamps();

            $table->unique(['asset_id', 'ex_date']);
            $table->index('ex_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_dividends');
    }
};
```

- [ ] **Step 4: Créer le modèle**

`app/Contexts/Market/Models/Dividend.php` :

```php
<?php

namespace App\Contexts\Market\Models;

use App\Contexts\Market\Factories\DividendFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $asset_id
 * @property-read Carbon $ex_date
 * @property-read string $amount_per_share
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(DividendFactory::class)]
class Dividend extends Model
{
    /** @use HasFactory<DividendFactory> */
    use HasFactory;

    protected $table = 'asset_dividends';

    /** @var list<string> */
    protected $fillable = ['asset_id', 'ex_date', 'amount_per_share'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ex_date' => 'date',
            'amount_per_share' => 'decimal:6',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class, 'asset_id');
    }
}
```

- [ ] **Step 5: Créer la fabrique**

`app/Contexts/Market/Factories/DividendFactory.php` :

```php
<?php

namespace App\Contexts\Market\Factories;

use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dividend>
 */
class DividendFactory extends Factory
{
    protected $model = Dividend::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'asset_id' => Instrument::factory(),
            'ex_date' => fake()->date(),
            'amount_per_share' => fake()->randomFloat(6, 0.01, 5),
        ];
    }
}
```

- [ ] **Step 6: Lancer le test pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=DividendTest`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Contexts/Market/Models/Dividend.php app/Contexts/Market/Models/DividendTest.php app/Contexts/Market/Factories/DividendFactory.php
git commit -m "feat: ajoute la table et le modèle des détachements de dividende"
```

---

### Task 2: Objets de valeur du sync dividendes

**Files:**
- Create: `app/Contexts/Market/Datas/DividendData.php`
- Create: `app/Contexts/Market/Datas/DividendRequestData.php`
- Create: `app/Contexts/Market/Datas/DividendSyncReportData.php`
- Test: `app/Contexts/Market/Datas/DividendDataTest.php`
- Test: `app/Contexts/Market/Datas/DividendSyncReportDataTest.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `DividendData(string $exDate, float $amountPerShare)` + `DividendData::fromArray(array{ex_date: string, amount_per_share: float})`.
  - `DividendRequestData(string $ticker, string $startDate, string $endDate)`.
  - `DividendSyncReportData(array $synced = [], array $failed = [], ?string $error = null)` avec `total(): int`, `syncedCount(): int`, `isTotalFailure(): bool`.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Datas/DividendDataTest.php` :

```php
<?php

use App\Contexts\Market\Datas\DividendData;

it('se construit depuis la charge utile du script', function () {
    $data = DividendData::fromArray(['ex_date' => '2026-03-05', 'amount_per_share' => 0.51]);

    expect($data->exDate)->toBe('2026-03-05')
        ->and($data->amountPerShare)->toBe(0.51);
});
```

`app/Contexts/Market/Datas/DividendSyncReportDataTest.php` :

```php
<?php

use App\Contexts\Market\Datas\DividendSyncReportData;

it('compte les tickers synchronisés et en échec', function () {
    $report = new DividendSyncReportData(synced: ['CW8.PA' => 2], failed: ['DEAD.PA']);

    expect($report->total())->toBe(2)
        ->and($report->syncedCount())->toBe(1)
        ->and($report->isTotalFailure())->toBeFalse();
});

it('ne signale un échec total que si rien n\'a été synchronisé', function () {
    expect((new DividendSyncReportData(failed: ['CW8.PA']))->isTotalFailure())->toBeTrue()
        ->and((new DividendSyncReportData)->isTotalFailure())->toBeFalse();
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="DividendData|DividendSyncReportData"`
Expected: FAIL — classes introuvables

- [ ] **Step 3: Écrire les trois objets de valeur**

`app/Contexts/Market/Datas/DividendData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

readonly class DividendData
{
    public function __construct(
        public string $exDate,
        public float $amountPerShare,
    ) {}

    /**
     * @param  array{ex_date: string, amount_per_share: float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            exDate: $data['ex_date'],
            amountPerShare: (float) $data['amount_per_share'],
        );
    }
}
```

`app/Contexts/Market/Datas/DividendRequestData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

/**
 * Demande de détachements pour un ticker sur une fenêtre de dates inclusive.
 *
 * Traduire la fenêtre dans la convention d'un fournisseur appartient à l'adaptateur.
 */
readonly class DividendRequestData
{
    public function __construct(
        public string $ticker,
        public string $startDate,
        public string $endDate,
    ) {}
}
```

`app/Contexts/Market/Datas/DividendSyncReportData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

readonly class DividendSyncReportData
{
    /**
     * @param  array<string, int>  $synced  nombre de détachements écrits, indexé par ticker
     * @param  array<int, string>  $failed  tickers pour lesquels le fournisseur est resté muet
     * @param  string|null  $error  erreur du fournisseur derrière un échec total, sinon null
     */
    public function __construct(
        public array $synced = [],
        public array $failed = [],
        public ?string $error = null,
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

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="DividendData|DividendSyncReportData"`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Contexts/Market/Datas
git commit -m "feat: ajoute les objets de valeur du sync des dividendes"
```

---

### Task 3: Persistance des dividendes

**Files:**
- Create: `app/Contexts/Market/Contracts/DividendRepositoryContract.php`
- Create: `app/Contexts/Market/Infrastructure/EloquentDividendRepository.php`
- Modify: `app/Contexts/Market/MarketProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `app/Contexts/Market/Infrastructure/EloquentDividendRepositoryTest.php`

**Interfaces:**
- Consumes: `Dividend` (Task 1), `DividendData` (Task 2).
- Produces: `DividendRepositoryContract` avec
  - `latestForAsset(int $assetId): ?Dividend`
  - `forAssets(array $assetIds): array` → `list<array{assetId: int, exDate: string, amountPerShare: float}>`, trié par actif puis date croissante
  - `namesFor(array $assetIds): array<int, string>`
  - `upsertForAsset(int $assetId, array $dividends): int`
  Liaison conteneur : `MarketProvider::registers(..., dividendRepository: EloquentDividendRepository::class)`.

- [ ] **Step 1: Écrire le test qui échoue**

`app/Contexts/Market/Infrastructure/EloquentDividendRepositoryTest.php` :

```php
<?php

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;

it('rend le dernier détachement connu d\'un actif', function () {
    $instrument = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-06-04']);

    $latest = app(DividendRepositoryContract::class)->latestForAsset($instrument->id);

    expect($latest->ex_date->format('Y-m-d'))->toBe('2026-06-04');
});

it('écrase le montant d\'un détachement déjà stocké', function () {
    $instrument = Instrument::factory()->create();
    $repository = app(DividendRepositoryContract::class);

    $repository->upsertForAsset($instrument->id, [new DividendData('2026-03-05', 0.51)]);
    $written = $repository->upsertForAsset($instrument->id, [new DividendData('2026-03-05', 0.62)]);

    expect($written)->toBe(1)
        ->and(Dividend::query()->where('asset_id', $instrument->id)->count())->toBe(1)
        ->and((float) Dividend::query()->where('asset_id', $instrument->id)->value('amount_per_share'))->toBe(0.62);
});

it('n\'écrit rien et rend zéro sans détachement', function () {
    $instrument = Instrument::factory()->create();

    expect(app(DividendRepositoryContract::class)->upsertForAsset($instrument->id, []))->toBe(0)
        ->and(Dividend::query()->count())->toBe(0);
});

it('rend les détachements de plusieurs actifs, triés par date croissante', function () {
    $first = Instrument::factory()->create();
    $second = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $first->id, 'ex_date' => '2026-06-04', 'amount_per_share' => 0.62]);
    Dividend::factory()->create(['asset_id' => $first->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.51]);
    Dividend::factory()->create(['asset_id' => $second->id, 'ex_date' => '2026-04-02', 'amount_per_share' => 1.25]);

    $rows = app(DividendRepositoryContract::class)->forAssets([$first->id, $second->id]);

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(['assetId' => $first->id, 'exDate' => '2026-03-05', 'amountPerShare' => 0.51])
        ->and($rows[1]['exDate'])->toBe('2026-06-04')
        ->and($rows[2]['assetId'])->toBe($second->id);
});

it('rend un tableau vide sans actif demandé', function () {
    expect(app(DividendRepositoryContract::class)->forAssets([]))->toBe([])
        ->and(app(DividendRepositoryContract::class)->namesFor([]))->toBe([]);
});

it('rend le nom des instruments demandés, indexé par identifiant', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    expect(app(DividendRepositoryContract::class)->namesFor([$instrument->id]))
        ->toBe([$instrument->id => 'Amundi MSCI World']);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=EloquentDividendRepositoryTest`
Expected: FAIL — `Target interface [App\Contexts\Market\Contracts\DividendRepositoryContract] does not exist.`

- [ ] **Step 3: Écrire le contrat**

`app/Contexts/Market/Contracts/DividendRepositoryContract.php` :

```php
<?php

namespace App\Contexts\Market\Contracts;

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;

interface DividendRepositoryContract
{
    public function latestForAsset(int $assetId): ?Dividend;

    /**
     * Détachements de plusieurs actifs, triés par actif puis date croissante.
     *
     * Rend des tableaux et non des modèles, pour la même raison que `closesForAssetsSince()` :
     * l'appelant lit trois colonnes et n'a que faire d'une hydratation.
     *
     * @param  array<int>  $assetIds
     * @return list<array{assetId: int, exDate: string, amountPerShare: float}>
     */
    public function forAssets(array $assetIds): array;

    /**
     * Nom des instruments demandés, pour étiqueter un revenu sans exposer le modèle.
     *
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;

    /**
     * Insère ou met à jour les détachements d'un actif.
     *
     * Appariement sur `(asset_id, ex_date)`, montant écrasé : le fournisseur révise ses valeurs.
     *
     * @param  array<int, DividendData>  $dividends
     * @return int nombre de lignes soumises à la base
     */
    public function upsertForAsset(int $assetId, array $dividends): int;
}
```

- [ ] **Step 4: Écrire l'implémentation**

`app/Contexts/Market/Infrastructure/EloquentDividendRepository.php` :

```php
<?php

namespace App\Contexts\Market\Infrastructure;

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Support\Carbon;

class EloquentDividendRepository implements DividendRepositoryContract
{
    public function latestForAsset(int $assetId): ?Dividend
    {
        return Dividend::query()
            ->where('asset_id', $assetId)
            ->orderByDesc('ex_date')
            ->first();
    }

    /**
     * @param  array<int>  $assetIds
     * @return list<array{assetId: int, exDate: string, amountPerShare: float}>
     */
    public function forAssets(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Dividend::query()
            ->whereIn('asset_id', $assetIds)
            ->orderBy('asset_id')
            ->orderBy('ex_date')
            ->toBase()
            ->get(['asset_id', 'ex_date', 'amount_per_share'])
            ->map(fn (object $row): array => [
                'assetId' => (int) $row->asset_id,
                'exDate' => Carbon::parse($row->ex_date)->format('Y-m-d'),
                'amountPerShare' => (float) $row->amount_per_share,
            ])
            ->all();
    }

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereNotNull('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<int, DividendData>  $dividends
     */
    public function upsertForAsset(int $assetId, array $dividends): int
    {
        if ($dividends === []) {
            return 0;
        }

        $rows = array_map(fn (DividendData $dividend): array => [
            'asset_id' => $assetId,
            'ex_date' => $dividend->exDate,
            'amount_per_share' => $dividend->amountPerShare,
        ], $dividends);

        Dividend::query()->upsert($rows, ['asset_id', 'ex_date'], ['amount_per_share']);

        return count($rows);
    }
}
```

- [ ] **Step 5: Câbler le conteneur**

Dans `app/Contexts/Market/MarketProvider.php`, ajouter le paramètre et la liaison :

```php
    /**
     * @param  class-string<DividendRepositoryContract>  $dividendRepository
     */
    public static function registers(
        // ... paramètres existants, inchangés
        string $dividendRepository,
    ): void {
        // ... liaisons existantes, inchangées
        $app->bind(DividendRepositoryContract::class, $dividendRepository);
    }
```

Dans `app/Providers/AppServiceProvider.php`, ajouter l'argument nommé à l'appel existant :

```php
        MarketProvider::registers(
            // ... arguments existants, inchangés
            dividendRepository: EloquentDividendRepository::class,
        );
```

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="EloquentDividendRepositoryTest|MarketProviderTest"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Market/Contracts app/Contexts/Market/Infrastructure app/Contexts/Market/MarketProvider.php app/Providers/AppServiceProvider.php
git commit -m "feat: persiste les détachements de dividende"
```

---

### Task 4: Script yfinance des détachements

**Files:**
- Create: `app/Contexts/Market/Infrastructure/Python/fetch_dividends_bulk.py`
- Modify: `app/Contexts/Market/Infrastructure/Python/YahooScript.php`
- Modify: `tests/Fixtures/python/yfinance.py`
- Test: `app/Contexts/Market/Infrastructure/Python/YahooScriptTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `YahooScript::DividendsBulk` (valeur `'fetch_dividends_bulk.py'`). Le script lit sur stdin `{"tickers": [{"ticker": string, "start_date": "Y-m-d", "end_date": "Y-m-d"}]}` et écrit `{"status": "ok", "data": {"<ticker>": [{"ex_date": "Y-m-d", "amount_per_share": float}]}}`. `end_date` est **exclusive**, comme chez yfinance. Un ticker sans détachement est absent de `data`.

- [ ] **Step 1: Écrire les tests qui échouent**

Dans `app/Contexts/Market/Infrastructure/Python/YahooScriptTest.php`, renommer le fonction d'aide `runPriceScript` en `runYahooScript` (et ses 4 appels existants), puis ajouter :

```php
it('rend les détachements d\'un ticker sur la fenêtre demandée', function () {
    $output = runYahooScript(YahooScript::DividendsBulk, [
        'tickers' => [
            ['ticker' => 'CW8.PA', 'start_date' => '2026-01-01', 'end_date' => '2026-07-01'],
        ],
    ]);

    $payload = json_decode($output, true);

    expect($payload)->not->toBeNull(json_last_error_msg())
        ->and($payload['status'])->toBe('ok')
        ->and($payload['data']['CW8.PA'])->toBe([
            ['ex_date' => '2026-03-05', 'amount_per_share' => 0.51],
        ]);
});

it('écarte un montant non fini plutôt que de perdre le lot', function () {
    // Le stub place un NaN au 2026-06-04 : sérialisé tel quel, il rendrait tout le lot
    // indécodable pour un décodeur JSON strict.
    $output = runYahooScript(YahooScript::DividendsBulk, [
        'tickers' => [
            ['ticker' => 'CW8.PA', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01'],
        ],
    ]);

    $payload = json_decode($output, true);

    expect($payload)->not->toBeNull(json_last_error_msg())
        ->and(collect($payload['data']['CW8.PA'])->pluck('ex_date')->all())
        ->toBe(['2026-03-05', '2026-09-03']);
});

it('omet un ticker sans détachement sur la fenêtre', function () {
    $output = runYahooScript(YahooScript::DividendsBulk, [
        'tickers' => [
            ['ticker' => 'CW8.PA', 'start_date' => '2020-01-01', 'end_date' => '2020-12-31'],
        ],
    ]);

    expect(json_decode($output, true)['data'])->toBe([]);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=YahooScriptTest`
Expected: FAIL — `Undefined constant App\Contexts\Market\Infrastructure\Python\YahooScript::DividendsBulk`

- [ ] **Step 3: Ajouter le cas d'enum**

Dans `app/Contexts/Market/Infrastructure/Python/YahooScript.php` :

```php
    case DividendsBulk = 'fetch_dividends_bulk.py';
```

- [ ] **Step 4: Donner des dividendes au stub yfinance**

Dans `tests/Fixtures/python/yfinance.py`, ajouter à la classe `Ticker` :

```python
    @property
    def dividends(self) -> pd.Series:
        """Trois détachements dont un montant absent, comme Yahoo en publie sur une opération
        sur titre incomplète : le script doit l'écarter sans perdre les deux autres."""
        return pd.Series(
            [0.51, np.nan, 0.62],
            index=pd.to_datetime(["2026-03-05", "2026-06-04", "2026-09-03"]),
        )
```

- [ ] **Step 5: Écrire le script**

`app/Contexts/Market/Infrastructure/Python/fetch_dividends_bulk.py` :

```python
from __future__ import annotations

import json
import math
import sys

import yfinance as yf


def main() -> None:
    params = json.loads(sys.stdin.read())
    tickers_info = params.get("tickers", [])

    all_data: dict[str, list[dict]] = {}

    for info in tickers_info:
        ticker = info["ticker"]

        # `Ticker.dividends` n'a pas d'équivalent groupé chez yfinance : la boucle reste dans
        # un seul process, ce qui est le but du lot — un boot d'interpréteur et un import
        # yfinance pour tout le catalogue au lieu de N.
        rows = _series_to_list(yf.Ticker(ticker).dividends, info["start_date"], info["end_date"])

        if rows:
            all_data[ticker] = rows

    print(json.dumps({"status": "ok", "data": all_data}, allow_nan=False))


def _series_to_list(series, start: str, end: str) -> list[dict]:
    """Détachements de la fenêtre [start, end), bornes alignées sur la convention yfinance."""
    data = []

    for date, value in series.items():
        day = date.strftime("%Y-%m-%d")

        if day < start or day >= end:
            continue

        # Yahoo publie parfois une opération sur titre sans montant. Ce NaN se sérialiserait en
        # littéral `NaN` : du JSON invalide qui coûterait le lot entier pour une ligne.
        amount = _as_float(value)

        if amount is None or amount <= 0:
            continue

        data.append({"ex_date": day, "amount_per_share": round(amount, 6)})

    return data


def _as_float(value) -> float | None:
    """Lit une cellule en flottant, en signalant None si elle est absente ou inutilisable."""
    try:
        number = float(value)
    except (TypeError, ValueError):
        return None

    return None if math.isnan(number) else number


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e)}))
        sys.exit(1)
```

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=YahooScriptTest`
Expected: PASS (7 tests : 4 existants renommés + 3 nouveaux)

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Market/Infrastructure/Python tests/Fixtures/python/yfinance.py
git commit -m "feat: récupère les détachements de dividende depuis yfinance"
```

---

### Task 5: Port et adaptateur du feed dividendes

**Files:**
- Create: `app/Contexts/Market/Ports/DividendFeedPort.php`
- Create: `app/Contexts/Market/Ports/DividendFeedException.php`
- Modify: `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`
- Modify: `app/Contexts/Market/MarketProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php`

**Interfaces:**
- Consumes: `DividendData`, `DividendRequestData` (Task 2), `YahooScript::DividendsBulk` (Task 4).
- Produces: `DividendFeedPort::supportsDividendFeed(InstrumentType $type): bool` et `fetchDividends(array $requests): array<string, array<int, DividendData>>`, `DividendFeedException::fetchFailed(string $reason, ?Throwable $previous = null): self` avec propriété publique `reason`. Liaison : `MarketProvider::registers(..., dividendFeed: YahooFinanceAdapter::class)`.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à `app/Contexts/Market/Infrastructure/YahooFinanceAdapterTest.php` :

```php
it('ne couvre les dividendes que pour les actions et les ETF', function () {
    $adapter = app(YahooFinanceAdapter::class);

    expect($adapter->supportsDividendFeed(InstrumentType::Stock))->toBeTrue()
        ->and($adapter->supportsDividendFeed(InstrumentType::ETF))->toBeTrue()
        ->and($adapter->supportsDividendFeed(InstrumentType::Crypto))->toBeFalse()
        ->and($adapter->supportsDividendFeed(InstrumentType::Commodity))->toBeFalse()
        ->and($adapter->supportsDividendFeed(InstrumentType::Bond))->toBeFalse();
});

it('décode les détachements du script et décale la borne haute d\'un jour', function () {
    $runner = (new FakePythonRunner)->withResult(
        YahooScript::DividendsBulk->path(),
        new PythonResult(status: 'ok', data: ['CW8.PA' => [
            ['ex_date' => '2026-03-05', 'amount_per_share' => 0.51],
        ]]),
    );

    $dividends = (new YahooFinanceAdapter($runner))->fetchDividends([
        new DividendRequestData('CW8.PA', '2026-01-01', '2026-06-30'),
    ]);

    expect($dividends['CW8.PA'][0])->toBeInstanceOf(DividendData::class)
        ->and($dividends['CW8.PA'][0]->exDate)->toBe('2026-03-05')
        ->and($dividends['CW8.PA'][0]->amountPerShare)->toBe(0.51)
        ->and($runner->calls[0]['input']['tickers'][0]['end_date'])->toBe('2026-07-01');
});

it('n\'appelle pas le script sans demande', function () {
    $runner = new FakePythonRunner;

    expect((new YahooFinanceAdapter($runner))->fetchDividends([]))->toBe([])
        ->and($runner->calls)->toBe([]);
});

it('lève une exception de feed quand le script échoue', function () {
    $runner = (new FakePythonRunner)->withResult(
        YahooScript::DividendsBulk->path(),
        new PythonResult(status: 'error', error: 'yfinance rate limited'),
    );

    expect(fn () => (new YahooFinanceAdapter($runner))->fetchDividends([
        new DividendRequestData('CW8.PA', '2026-01-01', '2026-06-30'),
    ]))->toThrow(DividendFeedException::class, 'yfinance rate limited');
});
```

Ajouter les `use` manquants en tête du fichier de test : `App\Contexts\Market\Datas\DividendData`, `App\Contexts\Market\Datas\DividendRequestData`, `App\Contexts\Market\Ports\DividendFeedException`, `App\Contexts\Market\Infrastructure\Python\YahooScript`, `App\Shared\Python\FakePythonRunner`, `App\Shared\Python\PythonResult` (ne pas dupliquer ceux déjà présents).

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=YahooFinanceAdapterTest`
Expected: FAIL — `Call to undefined method ...::supportsDividendFeed()`

- [ ] **Step 3: Écrire le port et son exception**

`app/Contexts/Market/Ports/DividendFeedPort.php` :

```php
<?php

namespace App\Contexts\Market\Ports;

use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Enums\InstrumentType;

interface DividendFeedPort
{
    /**
     * Dit si le flux couvre les détachements de ce type d'instrument.
     */
    public function supportsDividendFeed(InstrumentType $type): bool;

    /**
     * Récupère les détachements de plusieurs tickers d'un coup.
     *
     * Un ticker sans détachement sur sa fenêtre est absent du résultat : le flux ne distingue
     * pas un instrument capitalisant d'un ticker mort.
     *
     * @param  array<int, DividendRequestData>  $requests
     * @return array<string, array<int, DividendData>> détachements indexés par ticker
     *
     * @throws DividendFeedException quand la récupération échoue en totalité
     */
    public function fetchDividends(array $requests): array;
}
```

`app/Contexts/Market/Ports/DividendFeedException.php` :

```php
<?php

namespace App\Contexts\Market\Ports;

use RuntimeException;
use Throwable;

class DividendFeedException extends RuntimeException
{
    /**
     * @param  string  $reason  l'erreur du fournisseur seule, rapportable telle quelle à l'opérateur
     */
    private function __construct(string $message, public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    /**
     * L'échec sous-jacent est chaîné pour que rien de la cause ne soit perdu.
     */
    public static function fetchFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self("Dividend feed fetch failed: {$reason}", $reason, $previous);
    }
}
```

- [ ] **Step 4: Implémenter le port dans l'adaptateur**

Dans `app/Contexts/Market/Infrastructure/YahooFinanceAdapter.php`, ajouter `DividendFeedPort` à la liste des interfaces implémentées, puis :

```php
    /**
     * Yahoo ne publie de détachement que pour une entreprise ou un fonds qui en détient.
     */
    public function supportsDividendFeed(InstrumentType $type): bool
    {
        return in_array($type, [InstrumentType::Stock, InstrumentType::ETF]);
    }

    public function fetchDividends(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        try {
            $result = $this->python->run(
                YahooScript::DividendsBulk->path(),
                [
                    'tickers' => array_map(
                        fn (DividendRequestData $request): array => $this->window(
                            $request->ticker,
                            $request->startDate,
                            $request->endDate,
                        ),
                        $requests,
                    ),
                ],
                self::BULK_TIMEOUT_SECONDS,
            );

            if (! $result->ok()) {
                throw DividendFeedException::fetchFailed($result->error ?? 'unknown error');
            }

            $dividends = [];

            foreach ($result->data ?? [] as $ticker => $rows) {
                $dividends[(string) $ticker] = array_map(
                    fn (array $row): DividendData => DividendData::fromArray($row),
                    $rows,
                );
            }

            return $dividends;
        } catch (DividendFeedException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw DividendFeedException::fetchFailed($exception->getMessage(), $exception);
        }
    }
```

- [ ] **Step 5: Câbler le port**

Dans `MarketProvider::registers()`, ajouter le paramètre `string $dividendFeed` (annoté `@param class-string<DividendFeedPort> $dividendFeed`) et `$app->bind(DividendFeedPort::class, $dividendFeed);`. Dans `AppServiceProvider`, ajouter `dividendFeed: YahooFinanceAdapter::class,` à l'appel.

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="YahooFinanceAdapterTest|MarketProviderTest"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Market app/Providers/AppServiceProvider.php
git commit -m "feat: expose un port de récupération des dividendes"
```

---

### Task 6: Action de synchronisation

**Files:**
- Create: `app/Contexts/Market/Actions/SyncAssetDividends.php`
- Test: `app/Contexts/Market/Actions/SyncAssetDividendsTest.php`

**Interfaces:**
- Consumes: `InstrumentRepositoryContract`, `DividendFeedPort` (Task 5), `DividendRepositoryContract` (Task 3), `DividendSyncReportData` (Task 2).
- Produces: `SyncAssetDividends::__invoke(?int $assetId = null, ?string $since = null): DividendSyncReportData`. Fenêtre par défaut : dernier `ex_date` stocké, sinon `now()->subMonths(60)`. Borne haute : `now()`.

- [ ] **Step 1: Écrire le test qui échoue**

`app/Contexts/Market/Actions/SyncAssetDividendsTest.php` :

```php
<?php

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
use App\Contexts\Market\Ports\DividendFeedPort;
use Illuminate\Support\Facades\Log;

/**
 * Lie un flux qui couvre les actions et les ETF, enregistre les demandes reçues dans $captured
 * et rend les détachements donnés, indexés par ticker.
 *
 * @param  array<string, array<int, DividendData>>  $dividends
 * @param  array<int, mixed>  $captured
 */
function fakeDividendFeed(array $dividends, array &$captured = []): void
{
    test()->mock(DividendFeedPort::class, function ($mock) use ($dividends, &$captured) {
        $mock->shouldReceive('supportsDividendFeed')
            ->andReturnUsing(fn (InstrumentType $type): bool => in_array($type, [InstrumentType::Stock, InstrumentType::ETF]));
        $mock->shouldReceive('fetchDividends')
            ->andReturnUsing(function (array $requests) use ($dividends, &$captured) {
                $captured = $requests;

                return $dividends;
            });
    });
}

it('reprend au dernier détachement connu de chaque actif', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    $this->travelTo('2026-08-19 10:00:00');
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA')
        ->and($captured[0]->startDate)->toBe('2026-03-05')
        ->and($captured[0]->endDate)->toBe('2026-08-19');
});

it('retombe sur une fenêtre de soixante mois sans détachement stocké', function () {
    Instrument::factory()->create(['ticker' => 'CW8.PA']);
    $this->travelTo('2026-08-19 10:00:00');
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured[0]->startDate)->toBe('2021-08-19');
});

it('laisse une date de début explicite gagner sur l\'historique stocké', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)(null, '2015-01-01');

    expect($captured[0]->startDate)->toBe('2015-01-01');
});

it('écarte les instruments sans ticker et les types sans dividende', function () {
    Instrument::factory()->create(['ticker' => null]);
    Instrument::factory()->ofType(InstrumentType::Crypto)->create(['ticker' => 'BTC-EUR']);
    Instrument::factory()->ofType(InstrumentType::Bond)->create(['ticker' => 'OAT.PA']);
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['ticker' => 'CW8.PA']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)();

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA');
});

it('écrit les détachements récupérés et rapporte leur compte par ticker', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    fakeDividendFeed(['CW8.PA' => [
        new DividendData('2026-03-05', 0.51),
        new DividendData('2026-06-04', 0.62),
    ]]);

    $report = app(SyncAssetDividends::class)();

    expect($report->synced)->toBe(['CW8.PA' => 2])
        ->and($report->failed)->toBe([])
        ->and(Dividend::query()->where('asset_id', $instrument->id)->count())->toBe(2);
});

it('rapporte zéro détachement pour un ticker absent de la charge utile', function () {
    Instrument::factory()->create(['ticker' => 'ACC.PA']);
    fakeDividendFeed([]);

    $report = app(SyncAssetDividends::class)();

    expect($report->synced)->toBe(['ACC.PA' => 0])
        ->and($report->isTotalFailure())->toBeFalse();
});

it('marque tous les tickers en échec et journalise quand le flux lève', function () {
    Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    Log::spy();
    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->andThrow(DividendFeedException::fetchFailed('yfinance rate limited'));
    });

    $report = app(SyncAssetDividends::class)();

    expect($report->failed)->toBe(['CW8.PA', 'PE500.PA'])
        ->and($report->synced)->toBe([])
        ->and($report->error)->toBe('yfinance rate limited')
        ->and($report->isTotalFailure())->toBeTrue();

    Log::shouldHaveReceived('error')->once();
});

it('restreint la synchronisation à l\'actif demandé', function () {
    $first = Instrument::factory()->create(['ticker' => 'CW8.PA']);
    Instrument::factory()->create(['ticker' => 'PE500.PA']);
    $captured = [];
    fakeDividendFeed([], $captured);

    app(SyncAssetDividends::class)($first->id);

    expect($captured)->toHaveCount(1)
        ->and($captured[0]->ticker)->toBe('CW8.PA');
});

it('n\'appelle jamais le flux quand aucun instrument n\'est éligible', function () {
    Instrument::factory()->create(['ticker' => null]);
    $this->mock(DividendFeedPort::class, function ($mock) {
        $mock->shouldReceive('supportsDividendFeed')->andReturn(true);
        $mock->shouldReceive('fetchDividends')->never();
    });

    expect(app(SyncAssetDividends::class)()->total())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=SyncAssetDividendsTest`
Expected: FAIL — `Class "App\Contexts\Market\Actions\SyncAssetDividends" not found`

- [ ] **Step 3: Écrire l'action**

`app/Contexts/Market/Actions/SyncAssetDividends.php` :

```php
<?php

namespace App\Contexts\Market\Actions;

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Datas\DividendRequestData;
use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Ports\DividendFeedException;
use App\Contexts\Market\Ports\DividendFeedPort;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncAssetDividends
{
    /**
     * Cinq ans et non un an comme les cours : l'historique perçu doit couvrir la vie des
     * positions, pas la dernière année.
     */
    private const FALLBACK_MONTHS = 60;

    public function __construct(
        private InstrumentRepositoryContract $instruments,
        private DividendFeedPort $feed,
        private DividendRepositoryContract $dividends,
    ) {}

    /**
     * Récupère et persiste les détachements de chaque instrument distribuant.
     *
     * Sans $since, chaque instrument reprend à son dernier détachement stocké.
     */
    public function __invoke(?int $assetId = null, ?string $since = null): DividendSyncReportData
    {
        $instruments = $this->instrumentsToSync($assetId)->filter(
            fn (Instrument $instrument): bool => $instrument->ticker !== null
                && $this->feed->supportsDividendFeed($instrument->type),
        );

        if ($instruments->isEmpty()) {
            return new DividendSyncReportData;
        }

        $requests = $instruments->map(fn (Instrument $instrument): DividendRequestData => new DividendRequestData(
            ticker: $instrument->ticker,
            startDate: $this->startDateFor($instrument, $since),
            endDate: now()->format('Y-m-d'),
        ))->values()->all();

        try {
            $fetched = $this->feed->fetchDividends($requests);
        } catch (DividendFeedException $exception) {
            $tickers = $instruments->pluck('ticker')->values()->all();

            Log::error('Dividend sync failed for every asset.', [
                'error' => $exception->getMessage(),
                'tickers' => $tickers,
            ]);

            return new DividendSyncReportData(failed: $tickers, error: $exception->reason);
        }

        $synced = [];

        foreach ($instruments as $instrument) {
            $synced[$instrument->ticker] = $this->dividends->upsertForAsset(
                $instrument->id,
                $fetched[$instrument->ticker] ?? [],
            );
        }

        return new DividendSyncReportData(synced: $synced);
    }

    private function startDateFor(Instrument $instrument, ?string $since): string
    {
        if ($since !== null) {
            return $since;
        }

        $latest = $this->dividends->latestForAsset($instrument->id);

        return $latest !== null
            ? $latest->ex_date->format('Y-m-d')
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

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=SyncAssetDividendsTest`
Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Contexts/Market/Actions
git commit -m "feat: synchronise les détachements de dividende par instrument"
```

---

### Task 7: Commande et planification

**Files:**
- Create: `app/Contexts/Market/Console/SyncDividendsCommand.php`
- Modify: `bootstrap/app.php`
- Test: `app/Contexts/Market/Console/SyncDividendsCommandTest.php`
- Test: `tests/Feature/ScheduleTest.php`

**Interfaces:**
- Consumes: `SyncAssetDividends` (Task 6), `InstrumentRepositoryContract`.
- Produces: commande `market:sync-dividends` avec `--asset=` et `--since=`, planifiée le samedi à 23h45, sortie ajoutée à `storage/logs/market-sync-dividends.log`.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Console/SyncDividendsCommandTest.php` :

```php
<?php

use App\Contexts\Market\Datas\DividendSyncReportData;
use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Models\Instrument;

it('refuse une date de début mal formée', function () {
    $this->artisan('market:sync-dividends', ['--since' => '19/08/2026'])
        ->expectsOutputToContain('Format attendu : AAAA-MM-JJ.')
        ->assertFailed();
});

it('refuse un identifiant d\'actif inconnu', function () {
    $this->artisan('market:sync-dividends', ['--asset' => '404'])
        ->expectsOutputToContain('Aucun instrument ne porte l\'identifiant « 404 ».')
        ->assertFailed();
});

it('prévient quand aucun instrument n\'est à synchroniser', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData);

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('Aucun instrument à synchroniser.')
        ->assertSuccessful();
});

it('détaille le rapport par ticker', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData(synced: ['CW8.PA' => 2], failed: ['DEAD.PA']));

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('CW8.PA : 2 détachements')
        ->expectsOutputToContain('DEAD.PA : échec')
        ->expectsOutputToContain('2 instruments, 1 synchronisé, 1 échec')
        ->assertSuccessful();
});

it('échoue quand la récupération échoue en totalité', function () {
    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->andReturn(new DividendSyncReportData(failed: ['CW8.PA'], error: 'yfinance rate limited'));

    $this->artisan('market:sync-dividends')
        ->expectsOutputToContain('yfinance rate limited')
        ->assertFailed();
});

it('passe l\'actif demandé à l\'action', function () {
    $instrument = Instrument::factory()->create(['ticker' => 'CW8.PA']);

    $this->mock(SyncAssetDividends::class)
        ->shouldReceive('__invoke')
        ->once()
        ->with($instrument->id, null)
        ->andReturn(new DividendSyncReportData(synced: ['CW8.PA' => 1]));

    $this->artisan('market:sync-dividends', ['--asset' => (string) $instrument->id])->assertSuccessful();
});
```

Ajouter à `tests/Feature/ScheduleTest.php` :

```php
it('schedules the dividend sync every saturday at 23:45', function () {
    expect(scheduledEventsFor('market:sync-dividends')->pluck('expression')->unique()->values()->all())
        ->toBe(['45 23 * * 6']);
});

it('appends the dividend sync output to its own log file', function () {
    $outputs = scheduledEventsFor('market:sync-dividends')
        ->map(fn (Event $event): array => [$event->output, $event->shouldAppendOutput])
        ->unique()
        ->values()
        ->all();

    expect($outputs)->toBe([[storage_path('logs/market-sync-dividends.log'), true]]);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="SyncDividendsCommandTest|ScheduleTest"`
Expected: FAIL — `The command "market:sync-dividends" does not exist.`

- [ ] **Step 3: Écrire la commande**

`app/Contexts/Market/Console/SyncDividendsCommand.php` :

```php
<?php

namespace App\Contexts\Market\Console;

use App\Contexts\Market\Actions\SyncAssetDividends;
use App\Contexts\Market\Contracts\InstrumentRepositoryContract;
use App\Contexts\Market\Datas\DividendSyncReportData;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncDividendsCommand extends Command
{
    protected $signature = 'market:sync-dividends
                            {--asset= : Identifiant d\'un seul actif à synchroniser}
                            {--since= : Date de début forcée (Y-m-d), sinon reprise au dernier détachement connu}';

    protected $description = 'Récupère les détachements de dividende des instruments auprès du fournisseur de marché';

    public function handle(SyncAssetDividends $syncAssetDividends, InstrumentRepositoryContract $instruments): int
    {
        $since = $this->optionOrNull('since');

        if ($since !== null && ! Carbon::hasFormat($since, 'Y-m-d')) {
            $this->error("Date de début invalide : « {$since} ». Format attendu : AAAA-MM-JJ.");

            return self::FAILURE;
        }

        $asset = $this->optionOrNull('asset');

        if ($asset !== null && $instruments->findById((int) $asset) === null) {
            $this->error("Aucun instrument ne porte l'identifiant « {$asset} ».");

            return self::FAILURE;
        }

        $report = $syncAssetDividends($asset !== null ? (int) $asset : null, $since);

        if ($report->total() === 0) {
            $this->warn('Aucun instrument à synchroniser.');

            return self::SUCCESS;
        }

        $this->printReport($report);

        return $report->isTotalFailure() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lit une option en traitant une valeur vide comme absente.
     *
     * `--asset=` veut dire « tous les actifs », et `--since=` « reprendre au dernier
     * détachement connu ».
     */
    private function optionOrNull(string $name): ?string
    {
        $value = $this->option($name);

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function printReport(DividendSyncReportData $report): void
    {
        foreach ($report->synced as $ticker => $count) {
            $this->line("{$ticker} : {$count} détachement".$this->plural($count));
        }

        foreach ($report->failed as $ticker) {
            $this->error("{$ticker} : échec");
        }

        if ($report->error !== null) {
            $this->error("Échec de la récupération auprès du fournisseur : {$report->error}");
        }

        $this->newLine();
        $this->line($this->summary($report->total(), $report->syncedCount(), count($report->failed)));
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

- [ ] **Step 4: Enregistrer et planifier la commande**

Dans `bootstrap/app.php` : ajouter `use App\Contexts\Market\Console\SyncDividendsCommand;`, `SyncDividendsCommand::class,` dans `withCommands()`, et dans `withSchedule()` :

```php
        /**
         * Hebdomadaire et non quotidien : un détachement est un évènement rare, et le
         * rattrapage repart toujours du dernier `ex_date` connu. L'horaire suit celui des
         * cours pour ne pas croiser deux process Python.
         */
        $schedule->command('market:sync-dividends')
            ->weeklyOn(6, '23:45')
            ->appendOutputTo(storage_path('logs/market-sync-dividends.log'));
```

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="SyncDividendsCommandTest|ScheduleTest"`
Expected: PASS (6 + 5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Market/Console bootstrap/app.php tests/Feature/ScheduleTest.php
git commit -m "feat: planifie la commande de synchronisation des dividendes"
```

---

### Task 8: Noyau du contexte Income

**Files:**
- Create: `app/Contexts/Income/Enums/IncomeSource.php`
- Create: `app/Contexts/Income/Datas/IncomeReceiptData.php`
- Create: `app/Contexts/Income/Datas/IncomeSummaryData.php`
- Create: `app/Contexts/Income/Datas/AnnualIncomeData.php`
- Create: `app/Contexts/Income/Ports/IncomeSourcePort.php`
- Create: `app/Contexts/Income/Infrastructure/IncomeSourceRegistry.php`
- Create: `app/Contexts/Income/IncomeProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `app/Contexts/Income/Infrastructure/IncomeSourceRegistryTest.php`
- Test: `app/Contexts/Income/Enums/IncomeSourceTest.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `IncomeSource: string` avec `case Dividend = 'dividend'`, `getLabel(): string` (« Dividendes »), `values(): list<string>`.
  - `IncomeReceiptData(IncomeSource $source, Carbon $date, float $amount, ?int $assetId = null, ?string $label = null)`.
  - `IncomeSummaryData(float $totalReceived, float $last12Months, array $bySource)` + `empty()` + `jsonSerialize()`.
  - `AnnualIncomeData(int $year, float $total, array $bySource)` + `jsonSerialize()`.
  - `IncomeSourcePort::source(): IncomeSource` et `receiptsFor(int $userId): list<IncomeReceiptData>`.
  - `IncomeSourceRegistry::receiptsFor(int $userId): list<IncomeReceiptData>` — concaténation de toutes les sources enregistrées.
  - `IncomeProvider::registers(Application $app, array $sources): void`.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Income/Enums/IncomeSourceTest.php` :

```php
<?php

use App\Contexts\Income\Enums\IncomeSource;

it('étiquette chaque source en français', function () {
    expect(IncomeSource::Dividend->getLabel())->toBe('Dividendes');
});

it('liste ses valeurs', function () {
    expect(IncomeSource::values())->toBe(['dividend']);
});
```

`app/Contexts/Income/Infrastructure/IncomeSourceRegistryTest.php` :

```php
<?php

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Ports\IncomeSourcePort;
use Illuminate\Support\Carbon;

/**
 * Une source de revenu factice, pour prouver que l'agrégation ne connaît aucune source par son
 * nom : la même mécanique doit servir un dividende comme un loyer.
 */
function fakeIncomeSource(float ...$amounts): IncomeSourcePort
{
    return new class($amounts) implements IncomeSourcePort
    {
        /** @param list<float> $amounts */
        public function __construct(private array $amounts) {}

        public function source(): IncomeSource
        {
            return IncomeSource::Dividend;
        }

        /** @return list<IncomeReceiptData> */
        public function receiptsFor(int $userId): array
        {
            return array_map(fn (float $amount): IncomeReceiptData => new IncomeReceiptData(
                source: IncomeSource::Dividend,
                date: Carbon::parse('2026-03-05'),
                amount: $amount,
            ), $this->amounts);
        }
    };
}

it('concatène les reçus de toutes les sources', function () {
    $registry = new IncomeSourceRegistry([fakeIncomeSource(10.0, 20.0), fakeIncomeSource(5.0)]);

    expect(collect($registry->receiptsFor(1))->pluck('amount')->all())->toBe([10.0, 20.0, 5.0]);
});

it('rend un tableau vide sans aucune source', function () {
    expect((new IncomeSourceRegistry([]))->receiptsFor(1))->toBe([]);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="IncomeSourceTest|IncomeSourceRegistryTest"`
Expected: FAIL — classes introuvables

- [ ] **Step 3: Écrire l'enum et les objets de valeur**

`app/Contexts/Income/Enums/IncomeSource.php` :

```php
<?php

namespace App\Contexts\Income\Enums;

/**
 * Origine d'un revenu perçu. Le noyau du contexte n'en connaît aucune en particulier : ajouter
 * un loyer, c'est ajouter un cas ici et une source qui le produit.
 */
enum IncomeSource: string
{
    case Dividend = 'dividend';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Dividend => 'Dividendes',
        };
    }
}
```

`app/Contexts/Income/Datas/IncomeReceiptData.php` :

```php
<?php

namespace App\Contexts\Income\Datas;

use App\Contexts\Income\Enums\IncomeSource;
use Illuminate\Support\Carbon;

/**
 * Un revenu perçu, quelle que soit son origine. `assetId` reste nul pour un revenu qui ne porte
 * sur aucun instrument — un loyer, par exemple —, `label` portant alors seul son identité.
 */
readonly class IncomeReceiptData
{
    public function __construct(
        public IncomeSource $source,
        public Carbon $date,
        public float $amount,
        public ?int $assetId = null,
        public ?string $label = null,
    ) {}
}
```

`app/Contexts/Income/Datas/IncomeSummaryData.php` :

```php
<?php

namespace App\Contexts\Income\Datas;

use JsonSerializable;

readonly class IncomeSummaryData implements JsonSerializable
{
    /**
     * @param  array<string, float>  $bySource  montant perçu, indexé par valeur de `IncomeSource`
     */
    public function __construct(
        public float $totalReceived,
        public float $last12Months,
        public array $bySource,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'bySource' => $this->bySource,
        ];
    }
}
```

`app/Contexts/Income/Datas/AnnualIncomeData.php` :

```php
<?php

namespace App\Contexts\Income\Datas;

use JsonSerializable;

readonly class AnnualIncomeData implements JsonSerializable
{
    /**
     * @param  array<string, float>  $bySource  montant perçu cette année-là, par source
     */
    public function __construct(
        public int $year,
        public float $total,
        public array $bySource,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'year' => $this->year,
            'total' => $this->total,
            'bySource' => $this->bySource,
        ];
    }
}
```

- [ ] **Step 4: Écrire le port et le registre**

`app/Contexts/Income/Ports/IncomeSourcePort.php` :

```php
<?php

namespace App\Contexts\Income\Ports;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;

/**
 * Une origine de revenu. Le port ne dit pas si les reçus sont calculés ou lus en base : le
 * dividende les calcule depuis les positions, un loyer les lira.
 */
interface IncomeSourcePort
{
    public function source(): IncomeSource;

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array;
}
```

`app/Contexts/Income/Infrastructure/IncomeSourceRegistry.php` :

```php
<?php

namespace App\Contexts\Income\Infrastructure;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Ports\IncomeSourcePort;

class IncomeSourceRegistry
{
    /**
     * @param  iterable<IncomeSourcePort>  $sources
     */
    public function __construct(private iterable $sources) {}

    /**
     * Tous les revenus perçus par l'utilisateur, toutes origines confondues.
     *
     * @return list<IncomeReceiptData>
     */
    public function receiptsFor(int $userId): array
    {
        $receipts = [];

        foreach ($this->sources as $source) {
            foreach ($source->receiptsFor($userId) as $receipt) {
                $receipts[] = $receipt;
            }
        }

        return $receipts;
    }
}
```

- [ ] **Step 5: Écrire le provider et le câbler**

`app/Contexts/Income/IncomeProvider.php` :

```php
<?php

namespace App\Contexts\Income;

use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Ports\IncomeSourcePort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class IncomeProvider extends ServiceProvider
{
    /**
     * @param  list<class-string<IncomeSourcePort>>  $sources  origines de revenu à agréger
     */
    public static function registers(Application $app, array $sources): void
    {
        $app->tag($sources, 'income.sources');

        $app->bind(
            IncomeSourceRegistry::class,
            fn (Application $app): IncomeSourceRegistry => new IncomeSourceRegistry($app->tagged('income.sources')),
        );
    }
}
```

Dans `app/Providers/AppServiceProvider.php`, après `InstrumentViewProvider::registers(...)` :

```php
        IncomeProvider::registers(
            app: $this->app,
            sources: [],
        );
```

Le tableau reste vide jusqu'à la Task 10, qui y ajoute `DividendIncomeSource::class`.

- [ ] **Step 6: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="IncomeSourceTest|IncomeSourceRegistryTest"`
Expected: PASS (4 tests)

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Income app/Providers/AppServiceProvider.php
git commit -m "feat: ouvre un contexte Income agrégeant des revenus multi-sources"
```

---

### Task 9: Calcul des dividendes perçus

**Files:**
- Create: `app/Contexts/Income/Sources/Dividend/Datas/DividendRecordData.php`
- Create: `app/Contexts/Income/Sources/Dividend/Datas/PositionRecordData.php`
- Create: `app/Contexts/Income/Sources/Dividend/Datas/DividendReceiptData.php`
- Create: `app/Contexts/Income/Sources/Dividend/Services/DividendCalculator.php`
- Test: `app/Contexts/Income/Sources/Dividend/Services/DividendCalculatorTest.php`

**Interfaces:**
- Consumes: rien (aucune base, aucun conteneur).
- Produces:
  - `DividendRecordData(int $assetId, Carbon $exDate, float $amountPerShare)`.
  - `PositionRecordData(int $assetId, Carbon $date, bool $isSell, float $quantity)`.
  - `DividendReceiptData(int $assetId, string $exDate, float $quantity, float $amountPerShare, float $amount)` + `jsonSerialize()`.
  - `DividendCalculator::receipts(array $transactions, array $dividends): list<DividendReceiptData>` — trié par ex-date décroissante.

- [ ] **Step 1: Écrire le test qui échoue**

`app/Contexts/Income/Sources/Dividend/Services/DividendCalculatorTest.php` :

```php
<?php

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use Illuminate\Support\Carbon;

function boughtOn(string $date, float $quantity, int $assetId = 1): PositionRecordData
{
    return new PositionRecordData($assetId, Carbon::parse($date), false, $quantity);
}

function soldOn(string $date, float $quantity, int $assetId = 1): PositionRecordData
{
    return new PositionRecordData($assetId, Carbon::parse($date), true, $quantity);
}

function detachment(string $date, float $amountPerShare, int $assetId = 1): DividendRecordData
{
    return new DividendRecordData($assetId, Carbon::parse($date), $amountPerShare);
}

it('multiplie le détachement par la quantité détenue à l\'ex-date', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->quantity)->toBe(10.0)
        ->and($receipts[0]->amountPerShare)->toBe(0.5)
        ->and($receipts[0]->amount)->toBe(5.0)
        ->and($receipts[0]->exDate)->toBe('2026-03-05');
});

it('exclut un achat passé le jour même du détachement', function () {
    // Détenir le titre le jour de l'ex-date ne donne pas droit au dividende : il faut le
    // détenir la veille.
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-03-05', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toBe([]);
});

it('retient la quantité restante après une vente partielle', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 4.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts[0]->quantity)->toBe(6.0)
        ->and($receipts[0]->amount)->toBe(3.0);
});

it('ignore un détachement sur une position soldée', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 10.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts)->toBe([]);
});

it('reprend le versement après un rachat', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), soldOn('2026-02-01', 10.0), boughtOn('2026-04-01', 3.0)],
        [detachment('2026-03-05', 0.5), detachment('2026-06-04', 1.0)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->exDate)->toBe('2026-06-04')
        ->and($receipts[0]->amount)->toBe(3.0);
});

it('agrège les enveloppes : la source ne voit qu\'une quantité par actif', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0), boughtOn('2026-01-15', 5.0)],
        [detachment('2026-03-05', 0.5)],
    );

    expect($receipts[0]->quantity)->toBe(15.0)
        ->and($receipts[0]->amount)->toBe(7.5);
});

it('ne mélange pas les actifs', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 10.0, assetId: 1), boughtOn('2026-01-10', 100.0, assetId: 2)],
        [detachment('2026-03-05', 0.5, assetId: 2)],
    );

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->assetId)->toBe(2)
        ->and($receipts[0]->amount)->toBe(50.0);
});

it('ignore un détachement sur un actif jamais acheté', function () {
    expect((new DividendCalculator)->receipts([], [detachment('2026-03-05', 0.5)]))->toBe([]);
});

it('rend un tableau vide sans détachement', function () {
    expect((new DividendCalculator)->receipts([boughtOn('2026-01-10', 10.0)], []))->toBe([]);
});

it('trie les reçus du plus récent au plus ancien', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-01', 10.0)],
        [detachment('2026-03-05', 0.5), detachment('2026-09-03', 0.6), detachment('2026-06-04', 0.4)],
    );

    expect(array_map(fn ($receipt): string => $receipt->exDate, $receipts))
        ->toBe(['2026-09-03', '2026-06-04', '2026-03-05']);
});

it('arrondit le montant perçu au centime', function () {
    $receipts = (new DividendCalculator)->receipts(
        [boughtOn('2026-01-10', 3.0)],
        [detachment('2026-03-05', 0.123456)],
    );

    expect($receipts[0]->amount)->toBe(0.37);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DividendCalculatorTest`
Expected: FAIL — `Class "App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData" not found`

- [ ] **Step 3: Écrire les trois objets de valeur**

`app/Contexts/Income/Sources/Dividend/Datas/DividendRecordData.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use Illuminate\Support\Carbon;

/** Un détachement tel que le contexte Marché le publie, avant tout croisement avec une position. */
readonly class DividendRecordData
{
    public function __construct(
        public int $assetId,
        public Carbon $exDate,
        public float $amountPerShare,
    ) {}
}
```

`app/Contexts/Income/Sources/Dividend/Datas/PositionRecordData.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use Illuminate\Support\Carbon;

/** Un mouvement de position, réduit à ce dont le calcul a besoin : un sens et une quantité. */
readonly class PositionRecordData
{
    public function __construct(
        public int $assetId,
        public Carbon $date,
        public bool $isSell,
        public float $quantity,
    ) {}
}
```

`app/Contexts/Income/Sources/Dividend/Datas/DividendReceiptData.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use JsonSerializable;

/**
 * Détail d'un dividende perçu. Seule la fiche instrument le consomme : le noyau du contexte
 * Income n'en voit que la forme générique, `IncomeReceiptData`.
 */
readonly class DividendReceiptData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $exDate,
        public float $quantity,
        public float $amountPerShare,
        public float $amount,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'exDate' => $this->exDate,
            'quantity' => $this->quantity,
            'amountPerShare' => $this->amountPerShare,
            'amount' => $this->amount,
        ];
    }
}
```

- [ ] **Step 4: Écrire le calcul**

`app/Contexts/Income/Sources/Dividend/Services/DividendCalculator.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Services;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use Illuminate\Support\Carbon;

class DividendCalculator
{
    /**
     * Croise les détachements avec les quantités détenues pour rendre les dividendes perçus,
     * du plus récent au plus ancien.
     *
     * @param  list<PositionRecordData>  $transactions  tous actifs confondus, ordre indifférent
     * @param  list<DividendRecordData>  $dividends
     * @return list<DividendReceiptData>
     */
    public function receipts(array $transactions, array $dividends): array
    {
        /** @var array<int, list<PositionRecordData>> $movements */
        $movements = [];

        foreach ($transactions as $transaction) {
            $movements[$transaction->assetId][] = $transaction;
        }

        $receipts = [];

        foreach ($dividends as $dividend) {
            $quantity = $this->quantityBefore($movements[$dividend->assetId] ?? [], $dividend->exDate);

            if ($quantity <= 0.0) {
                continue;
            }

            $receipts[] = new DividendReceiptData(
                assetId: $dividend->assetId,
                exDate: $dividend->exDate->format('Y-m-d'),
                quantity: $quantity,
                amountPerShare: $dividend->amountPerShare,
                amount: round($quantity * $dividend->amountPerShare, 2),
            );
        }

        usort($receipts, fn (DividendReceiptData $a, DividendReceiptData $b): int => $b->exDate <=> $a->exDate);

        return $receipts;
    }

    /**
     * Quantité détenue la veille du détachement.
     *
     * La comparaison exclut l'ex-date : un achat passé ce jour-là ne donne pas droit au
     * dividende, seul un titre détenu avant le détachement le perçoit.
     *
     * @param  list<PositionRecordData>  $movements
     */
    private function quantityBefore(array $movements, Carbon $exDate): float
    {
        $quantity = 0.0;

        foreach ($movements as $movement) {
            if (! $movement->date->lt($exDate)) {
                continue;
            }

            $quantity += $movement->isSell ? -$movement->quantity : $movement->quantity;
        }

        return $quantity;
    }
}
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=DividendCalculatorTest`
Expected: PASS (11 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Income/Sources
git commit -m "feat: calcule les dividendes perçus depuis les quantités détenues à l'ex-date"
```

---

### Task 10: Source dividende branchée sur Marché et Portefeuille

**Files:**
- Create: `app/Contexts/Income/Sources/Dividend/Ports/DividendHistoryPort.php`
- Create: `app/Contexts/Income/Sources/Dividend/Ports/PositionHistoryPort.php`
- Create: `app/Contexts/Income/Sources/Dividend/Datas/PositionSnapshotData.php`
- Create: `app/Contexts/Income/Sources/Dividend/Infrastructure/MarketDividendHistory.php`
- Create: `app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php`
- Create: `app/Contexts/Income/Sources/Dividend/DividendIncomeSource.php`
- Modify: `app/Contexts/Income/IncomeProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `app/Contexts/Income/Sources/Dividend/Infrastructure/MarketDividendHistoryTest.php`
- Test: `app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistoryTest.php`
- Test: `app/Contexts/Income/Sources/Dividend/DividendIncomeSourceTest.php`

**Interfaces:**
- Consumes: `DividendRepositoryContract` (Task 3), `DividendCalculator` + Datas (Task 9), `IncomeSourcePort` + `IncomeReceiptData` (Task 8).
- Produces:
  - `DividendHistoryPort::forAssets(array $assetIds): list<DividendRecordData>` et `namesFor(array $assetIds): array<int, string>`.
  - `PositionHistoryPort::transactionsFor(int $userId): list<PositionRecordData>`, `assetIdsFor(int $userId): list<int>`, `positionFor(int $userId, int $assetId): ?PositionSnapshotData`.
  - `PositionSnapshotData(float $quantity, ?float $avgCost)`.
  - `DividendIncomeSource implements IncomeSourcePort`, lié au tag `income.sources`.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Income/Sources/Dividend/Infrastructure/MarketDividendHistoryTest.php` :

```php
<?php

use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;

it('traduit les détachements du contexte Marché en enregistrements de calcul', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.51,
    ]);

    $records = app(DividendHistoryPort::class)->forAssets([$instrument->id]);

    expect($records)->toHaveCount(1)
        ->and($records[0]->assetId)->toBe($instrument->id)
        ->and($records[0]->exDate->format('Y-m-d'))->toBe('2026-03-05')
        ->and($records[0]->amountPerShare)->toBe(0.51);
});

it('rend le nom des instruments demandés', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    expect(app(DividendHistoryPort::class)->namesFor([$instrument->id]))
        ->toBe([$instrument->id => 'Amundi MSCI World']);
});
```

`app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistoryTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('rend les mouvements de l\'utilisateur, sens compris', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Transaction::factory()->sell()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-02-01', 'quantity' => 4, 'unit_price' => 90,
    ]);

    $records = app(PositionHistoryPort::class)->transactionsFor($user->id);

    expect($records)->toHaveCount(2)
        ->and($records[0]->isSell)->toBeFalse()
        ->and($records[0]->quantity)->toBe(10.0)
        ->and($records[1]->isSell)->toBeTrue()
        ->and($records[1]->quantity)->toBe(4.0);
});

it('ignore les transactions d\'un autre utilisateur et celles sans actif', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    Transaction::factory()->buy()->create(['user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other), 'asset_id' => Instrument::factory()]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => null]);

    expect(app(PositionHistoryPort::class)->transactionsFor($user->id))->toBe([])
        ->and(app(PositionHistoryPort::class)->assetIdsFor($user->id))->toBe([]);
});

it('liste les actifs mouvementés une seule fois', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->count(2)->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
    ]);

    expect(app(PositionHistoryPort::class)->assetIdsFor($user->id))->toBe([$instrument->id]);
});

it('agrège la position courante de toutes les enveloppes, prix de revient pondéré', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => Wallet::factory()->for($user), 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 100,
    ]);

    $position = app(PositionHistoryPort::class)->positionFor($user->id, $instrument->id);

    expect($position->quantity)->toBe(20.0)
        ->and($position->avgCost)->toBe(90.0);
});

it('rend null sans position sur l\'actif', function () {
    $user = User::factory()->create();

    expect(app(PositionHistoryPort::class)->positionFor($user->id, 404))->toBeNull();
});
```

`app/Contexts/Income/Sources/Dividend/DividendIncomeSourceTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Sources\Dividend\DividendIncomeSource;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('rend un reçu générique par dividende perçu, étiqueté du nom de l\'instrument', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    $receipts = app(DividendIncomeSource::class)->receiptsFor($user->id);

    expect(app(DividendIncomeSource::class)->source())->toBe(IncomeSource::Dividend)
        ->and($receipts)->toHaveCount(1)
        ->and($receipts[0]->amount)->toBe(5.0)
        ->and($receipts[0]->assetId)->toBe($instrument->id)
        ->and($receipts[0]->label)->toBe('Amundi MSCI World')
        ->and($receipts[0]->date->format('Y-m-d'))->toBe('2026-03-05');
});

it('ne lit aucun dividende sans mouvement de position', function () {
    $user = User::factory()->create();
    Dividend::factory()->create(['ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    expect(app(DividendIncomeSource::class)->receiptsFor($user->id))->toBe([]);
});

it('est enregistrée dans le registre des sources de revenu', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    expect(app(IncomeSourceRegistry::class)->receiptsFor($user->id))->toHaveCount(1);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="MarketDividendHistoryTest|PortfolioPositionHistoryTest|DividendIncomeSourceTest"`
Expected: FAIL — ports et classes introuvables

- [ ] **Step 3: Écrire les ports et l'instantané de position**

`app/Contexts/Income/Sources/Dividend/Ports/DividendHistoryPort.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Ports;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;

/**
 * Ce que la source dividende attend du marché. `namesFor()` y vit aussi : le nom de l'instrument
 * n'est lu que pour étiqueter un reçu, et lui donner son propre port n'ajouterait qu'un fichier.
 */
interface DividendHistoryPort
{
    /**
     * @param  array<int>  $assetIds
     * @return list<DividendRecordData>
     */
    public function forAssets(array $assetIds): array;

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array;
}
```

`app/Contexts/Income/Sources/Dividend/Ports/PositionHistoryPort.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Ports;

use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;

interface PositionHistoryPort
{
    /**
     * Tous les mouvements de position de l'utilisateur, ordre chronologique.
     *
     * @return list<PositionRecordData>
     */
    public function transactionsFor(int $userId): array;

    /**
     * Actifs que l'utilisateur a mouvementés au moins une fois : le périmètre à interroger côté
     * marché, une position soldée ayant pu percevoir un dividende avant sa vente.
     *
     * @return list<int>
     */
    public function assetIdsFor(int $userId): array;

    /** Position courante, toutes enveloppes confondues. */
    public function positionFor(int $userId, int $assetId): ?PositionSnapshotData;
}
```

`app/Contexts/Income/Sources/Dividend/Datas/PositionSnapshotData.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

/** Position courante sur un actif, réduite à ce qu'un rendement sur coût demande. */
readonly class PositionSnapshotData
{
    public function __construct(
        public float $quantity,
        public ?float $avgCost,
    ) {}
}
```

- [ ] **Step 4: Écrire les deux adaptateurs**

`app/Contexts/Income/Sources/Dividend/Infrastructure/MarketDividendHistory.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Infrastructure;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Market\Contracts\DividendRepositoryContract;
use Illuminate\Support\Carbon;

class MarketDividendHistory implements DividendHistoryPort
{
    public function __construct(private DividendRepositoryContract $dividends) {}

    /**
     * @param  array<int>  $assetIds
     * @return list<DividendRecordData>
     */
    public function forAssets(array $assetIds): array
    {
        return array_map(
            fn (array $row): DividendRecordData => new DividendRecordData(
                assetId: $row['assetId'],
                exDate: Carbon::parse($row['exDate']),
                amountPerShare: $row['amountPerShare'],
            ),
            $this->dividends->forAssets($assetIds),
        );
    }

    /**
     * @param  array<int>  $assetIds
     * @return array<int, string>
     */
    public function namesFor(array $assetIds): array
    {
        return $this->dividends->namesFor($assetIds);
    }
}
```

`app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Infrastructure;

use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;

class PortfolioPositionHistory implements PositionHistoryPort
{
    /** @return list<PositionRecordData> */
    public function transactionsFor(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->orderBy('date')
            ->get()
            ->map(fn (Transaction $transaction): PositionRecordData => new PositionRecordData(
                assetId: (int) $transaction->asset_id,
                date: $transaction->date,
                isSell: $transaction->type === TransactionType::Sell,
                quantity: (float) $transaction->quantity,
            ))
            ->values()
            ->all();
    }

    /** @return list<int> */
    public function assetIdsFor(int $userId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('asset_id')
            ->distinct()
            ->pluck('asset_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function positionFor(int $userId, int $assetId): ?PositionSnapshotData
    {
        $rows = Holding::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $quantity = (float) $rows->sum(fn (Holding $holding): float => (float) $holding->quantity);

        /** Le prix de revient d'un actif tenu dans deux enveloppes est la moyenne pondérée des leurs. */
        $withCost = $rows->filter(fn (Holding $holding): bool => $holding->avg_cost !== null);
        $qtyWithCost = (float) $withCost->sum(fn (Holding $holding): float => (float) $holding->quantity);

        $avgCost = $qtyWithCost > 0.0
            ? (float) $withCost->sum(fn (Holding $holding): float => (float) $holding->quantity * (float) $holding->avg_cost) / $qtyWithCost
            : null;

        return new PositionSnapshotData(quantity: $quantity, avgCost: $avgCost);
    }
}
```

- [ ] **Step 5: Écrire la source**

`app/Contexts/Income/Sources/Dividend/DividendIncomeSource.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use Illuminate\Support\Carbon;

class DividendIncomeSource implements IncomeSourcePort
{
    public function __construct(
        private DividendHistoryPort $dividends,
        private PositionHistoryPort $positions,
        private DividendCalculator $calculator,
    ) {}

    public function source(): IncomeSource
    {
        return IncomeSource::Dividend;
    }

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array
    {
        $assetIds = $this->positions->assetIdsFor($userId);

        if ($assetIds === []) {
            return [];
        }

        $receipts = $this->calculator->receipts(
            $this->positions->transactionsFor($userId),
            $this->dividends->forAssets($assetIds),
        );

        $names = $this->dividends->namesFor($assetIds);

        return array_map(fn (DividendReceiptData $receipt): IncomeReceiptData => new IncomeReceiptData(
            source: IncomeSource::Dividend,
            date: Carbon::parse($receipt->exDate),
            amount: $receipt->amount,
            assetId: $receipt->assetId,
            label: $names[$receipt->assetId] ?? null,
        ), $receipts);
    }
}
```

- [ ] **Step 6: Câbler ports et source**

Dans `app/Contexts/Income/IncomeProvider.php`, étendre `registers()` :

```php
    /**
     * @param  list<class-string<IncomeSourcePort>>  $sources  origines de revenu à agréger
     * @param  class-string<DividendHistoryPort>  $dividendHistory
     * @param  class-string<PositionHistoryPort>  $positionHistory
     */
    public static function registers(
        Application $app,
        array $sources,
        string $dividendHistory,
        string $positionHistory,
    ): void {
        $app->bind(DividendHistoryPort::class, $dividendHistory);
        $app->bind(PositionHistoryPort::class, $positionHistory);

        $app->tag($sources, 'income.sources');

        $app->bind(
            IncomeSourceRegistry::class,
            fn (Application $app): IncomeSourceRegistry => new IncomeSourceRegistry($app->tagged('income.sources')),
        );
    }
```

Dans `app/Providers/AppServiceProvider.php` :

```php
        IncomeProvider::registers(
            app: $this->app,
            sources: [DividendIncomeSource::class],
            dividendHistory: MarketDividendHistory::class,
            positionHistory: PortfolioPositionHistory::class,
        );
```

- [ ] **Step 7: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="MarketDividendHistoryTest|PortfolioPositionHistoryTest|DividendIncomeSourceTest|IncomeSourceRegistryTest"`
Expected: PASS (12 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Contexts/Income app/Providers/AppServiceProvider.php
git commit -m "feat: branche la source dividende sur le marché et le portefeuille"
```

---

### Task 11: Agrégats de revenu du portefeuille

**Files:**
- Create: `app/Contexts/Income/Actions/GetIncomeSummary.php`
- Create: `app/Contexts/Income/Actions/GetAnnualIncome.php`
- Test: `app/Contexts/Income/Actions/GetIncomeSummaryTest.php`
- Test: `app/Contexts/Income/Actions/GetAnnualIncomeTest.php`

**Interfaces:**
- Consumes: `IncomeSourceRegistry` (Task 8), `IncomeSummaryData`, `AnnualIncomeData`.
- Produces: `GetIncomeSummary::__invoke(int $userId): IncomeSummaryData`, `GetAnnualIncome::__invoke(int $userId): list<AnnualIncomeData>` (années croissantes).

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Income/Actions/GetIncomeSummaryTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * Un utilisateur détenant 10 titres depuis 2024, deux détachements : un dans les douze derniers
 * mois, un plus ancien.
 *
 * @return array{user: User, instrument: Instrument}
 */
function dividendFixture(): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2024-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);

    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    return ['user' => $user, 'instrument' => $instrument];
}

it('totalise le perçu, les douze derniers mois et la ventilation par source', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $summary = app(GetIncomeSummary::class)($user->id);

    expect($summary->totalReceived)->toBe(13.0)
        ->and($summary->last12Months)->toBe(8.0)
        ->and($summary->bySource)->toBe(['dividend' => 13.0]);
});

it('rend un résumé vide sans aucun revenu', function () {
    $user = User::factory()->create();

    $summary = app(GetIncomeSummary::class)($user->id);

    expect($summary->totalReceived)->toBe(0.0)
        ->and($summary->last12Months)->toBe(0.0)
        ->and($summary->bySource)->toBe([]);
});

it('ne compte pas le revenu d\'un autre utilisateur', function () {
    $this->travelTo('2026-08-19 10:00:00');
    dividendFixture();
    $other = User::factory()->create();

    expect(app(GetIncomeSummary::class)($other->id)->totalReceived)->toBe(0.0);
});
```

`app/Contexts/Income/Actions/GetAnnualIncomeTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;

it('groupe le revenu par année, de la plus ancienne à la plus récente', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $years = app(GetAnnualIncome::class)($user->id);

    expect($years)->toHaveCount(2)
        ->and($years[0]->year)->toBe(2025)
        ->and($years[0]->total)->toBe(5.0)
        ->and($years[0]->bySource)->toBe(['dividend' => 5.0])
        ->and($years[1]->year)->toBe(2026)
        ->and($years[1]->total)->toBe(8.0);
});

it('rend un tableau vide sans revenu', function () {
    expect(app(GetAnnualIncome::class)(User::factory()->create()->id))->toBe([]);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="GetIncomeSummaryTest|GetAnnualIncomeTest"`
Expected: FAIL — `Class "App\Contexts\Income\Actions\GetIncomeSummary" not found`

- [ ] **Step 3: Écrire les deux actions**

`app/Contexts/Income/Actions/GetIncomeSummary.php` :

```php
<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Datas\IncomeSummaryData;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use Illuminate\Support\Carbon;

class GetIncomeSummary
{
    public function __construct(private IncomeSourceRegistry $sources) {}

    /**
     * Revenu perçu par l'utilisateur, toutes origines confondues.
     *
     * Le total ne se mêle jamais au gain latent : les cours stockés sont déjà ajustés des
     * dividendes, et les additionner compterait deux fois une partie du même rendement.
     */
    public function __invoke(int $userId): IncomeSummaryData
    {
        $receipts = $this->sources->receiptsFor($userId);

        if ($receipts === []) {
            return IncomeSummaryData::empty();
        }

        $since = Carbon::now()->subYear();
        $total = 0.0;
        $last12Months = 0.0;
        $bySource = [];

        foreach ($receipts as $receipt) {
            $total += $receipt->amount;
            $key = $receipt->source->value;
            $bySource[$key] = ($bySource[$key] ?? 0.0) + $receipt->amount;

            if ($receipt->date->gte($since)) {
                $last12Months += $receipt->amount;
            }
        }

        return new IncomeSummaryData(
            totalReceived: round($total, 2),
            last12Months: round($last12Months, 2),
            bySource: array_map(fn (float $amount): float => round($amount, 2), $bySource),
        );
    }
}
```

`app/Contexts/Income/Actions/GetAnnualIncome.php` :

```php
<?php

namespace App\Contexts\Income\Actions;

use App\Contexts\Income\Datas\AnnualIncomeData;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;

class GetAnnualIncome
{
    public function __construct(private IncomeSourceRegistry $sources) {}

    /**
     * Revenu par année civile, de la plus ancienne à la plus récente.
     *
     * La ventilation par source est produite dès maintenant, alors qu'une seule origine existe :
     * elle ne coûte rien et évite de refaire l'affichage quand une deuxième arrive.
     *
     * @return list<AnnualIncomeData>
     */
    public function __invoke(int $userId): array
    {
        /** @var array<int, array<string, float>> $bySourcePerYear */
        $bySourcePerYear = [];

        foreach ($this->sources->receiptsFor($userId) as $receipt) {
            $year = (int) $receipt->date->format('Y');
            $key = $receipt->source->value;
            $bySourcePerYear[$year][$key] = ($bySourcePerYear[$year][$key] ?? 0.0) + $receipt->amount;
        }

        ksort($bySourcePerYear);

        $years = [];

        foreach ($bySourcePerYear as $year => $bySource) {
            $bySource = array_map(fn (float $amount): float => round($amount, 2), $bySource);

            $years[] = new AnnualIncomeData(
                year: $year,
                total: round(array_sum($bySource), 2),
                bySource: $bySource,
            );
        }

        return $years;
    }
}
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter="GetIncomeSummaryTest|GetAnnualIncomeTest"`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Contexts/Income/Actions
git commit -m "feat: agrège le revenu perçu du portefeuille et son historique annuel"
```

---

### Task 12: Historique de dividende par instrument

**Files:**
- Create: `app/Contexts/Income/Sources/Dividend/Datas/AssetDividendHistoryData.php`
- Create: `app/Contexts/Income/Sources/Dividend/Actions/GetAssetDividendHistory.php`
- Test: `app/Contexts/Income/Sources/Dividend/Actions/GetAssetDividendHistoryTest.php`

**Interfaces:**
- Consumes: `DividendHistoryPort`, `PositionHistoryPort`, `DividendCalculator` (Tasks 9-10).
- Produces: `GetAssetDividendHistory::__invoke(int $userId, int $assetId): AssetDividendHistoryData`, avec `receipts` (list<DividendReceiptData>, plus récent d'abord), `totalReceived`, `last12Months`, `yieldOnCost` (pourcentage ou null). Sérialisé en JSON pour Inertia.

- [ ] **Step 1: Écrire le test qui échoue**

`app/Contexts/Income/Sources/Dividend/Actions/GetAssetDividendHistoryTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

/**
 * 10 titres à 80 € payés en 2024, deux détachements dont un dans les douze derniers mois.
 *
 * @return array{user: User, instrument: Instrument}
 */
function heldWithDividends(): array
{
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    /**
     * La position n'est pas insérée à la main : `TransactionObserver` la projette depuis cet achat
     * via `ProjectHolding`, qui fait un `updateOrCreate` sur `(asset_id, wallet_id)`. Insérer un
     * `Holding` après la transaction violerait cette clé primaire composite — 10 titres à 80 €
     * projettent exactement `quantity = 10, avg_cost = 80`.
     */
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2024-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);

    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    return ['user' => $user, 'instrument' => $instrument];
}

it('détaille les détachements perçus, du plus récent au plus ancien', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->receipts)->toHaveCount(2)
        ->and($history->receipts[0]->exDate)->toBe('2026-03-05')
        ->and($history->receipts[0]->quantity)->toBe(10.0)
        ->and($history->receipts[0]->amount)->toBe(8.0)
        ->and($history->totalReceived)->toBe(13.0)
        ->and($history->last12Months)->toBe(8.0);
});

it('rapporte le perçu de douze mois au coût de la position', function () {
    // 8 € perçus sur un coût de 800 € : 1 %.
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    expect(app(GetAssetDividendHistory::class)($user->id, $instrument->id)->yieldOnCost)->toBe(1.0);
});

it('laisse le rendement nul quand la position est soldée', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();
    Holding::query()->where('asset_id', $instrument->id)->delete();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->yieldOnCost)->toBeNull()
        ->and($history->totalReceived)->toBe(13.0);
});

it('rend un historique vide sur un instrument capitalisant', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();

    $history = app(GetAssetDividendHistory::class)($user->id, $instrument->id);

    expect($history->receipts)->toBe([])
        ->and($history->totalReceived)->toBe(0.0)
        ->and($history->yieldOnCost)->toBeNull();
});

it('ignore les détachements des autres actifs', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = heldWithDividends();
    $other = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $other->id, 'ex_date' => '2026-04-02', 'amount_per_share' => 9.0]);

    expect(app(GetAssetDividendHistory::class)($user->id, $other->id)->receipts)->toBe([]);
});

it('se sérialise pour la page', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = heldWithDividends();

    $payload = json_decode(json_encode(app(GetAssetDividendHistory::class)($user->id, $instrument->id)), true);

    // `toEqual` et non `toBe` sur les montants : `json_encode()` sérialise un flottant à fraction
    // nulle sans son « .0 », que `json_decode` redonne en entier. La valeur est ce qui compte —
    // JavaScript n'a de toute façon qu'un seul type numérique.
    expect($payload['receipts'][0]['exDate'])->toBe('2026-03-05')
        ->and($payload['receipts'][0]['amount'])->toEqual(8.0)
        ->and($payload['totalReceived'])->toEqual(13.0)
        ->and($payload['yieldOnCost'])->toEqual(1.0);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=GetAssetDividendHistoryTest`
Expected: FAIL — `Class "App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory" not found`

- [ ] **Step 3: Écrire l'objet de valeur**

`app/Contexts/Income/Sources/Dividend/Datas/AssetDividendHistoryData.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

use JsonSerializable;

readonly class AssetDividendHistoryData implements JsonSerializable
{
    /**
     * @param  list<DividendReceiptData>  $receipts  du plus récent au plus ancien
     * @param  float|null  $yieldOnCost  perçu sur douze mois rapporté au coût de la position, en
     *                                   pourcentage ; nul sans position ou sans prix de revient
     */
    public function __construct(
        public array $receipts,
        public float $totalReceived,
        public float $last12Months,
        public ?float $yieldOnCost,
    ) {}

    public static function empty(): self
    {
        return new self([], 0.0, 0.0, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'receipts' => array_map(fn (DividendReceiptData $receipt): array => $receipt->jsonSerialize(), $this->receipts),
            'totalReceived' => $this->totalReceived,
            'last12Months' => $this->last12Months,
            'yieldOnCost' => $this->yieldOnCost,
        ];
    }
}
```

- [ ] **Step 4: Écrire l'action**

`app/Contexts/Income/Sources/Dividend/Actions/GetAssetDividendHistory.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Dividend\Actions;

use App\Contexts\Income\Sources\Dividend\Datas\AssetDividendHistoryData;
use App\Contexts\Income\Sources\Dividend\Datas\DividendReceiptData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Ports\DividendHistoryPort;
use App\Contexts\Income\Sources\Dividend\Ports\PositionHistoryPort;
use App\Contexts\Income\Sources\Dividend\Services\DividendCalculator;
use Illuminate\Support\Carbon;

class GetAssetDividendHistory
{
    public function __construct(
        private DividendHistoryPort $dividends,
        private PositionHistoryPort $positions,
        private DividendCalculator $calculator,
    ) {}

    /**
     * Dividendes perçus sur un seul instrument, avec son rendement sur coût.
     *
     * Vit dans le dossier de la source dividende et non dans le noyau : « par instrument » n'a
     * pas de sens pour un revenu qui ne porte sur aucun titre.
     */
    public function __invoke(int $userId, int $assetId): AssetDividendHistoryData
    {
        $movements = array_values(array_filter(
            $this->positions->transactionsFor($userId),
            fn (PositionRecordData $movement): bool => $movement->assetId === $assetId,
        ));

        $receipts = $this->calculator->receipts($movements, $this->dividends->forAssets([$assetId]));

        if ($receipts === []) {
            return AssetDividendHistoryData::empty();
        }

        $since = Carbon::now()->subYear();
        $total = 0.0;
        $last12Months = 0.0;

        foreach ($receipts as $receipt) {
            $total += $receipt->amount;

            if (Carbon::parse($receipt->exDate)->gte($since)) {
                $last12Months += $receipt->amount;
            }
        }

        return new AssetDividendHistoryData(
            receipts: $receipts,
            totalReceived: round($total, 2),
            last12Months: round($last12Months, 2),
            yieldOnCost: $this->yieldOnCost($userId, $assetId, $last12Months),
        );
    }

    /** Perçu sur douze mois rapporté au coût de la position courante, en pourcentage. */
    private function yieldOnCost(int $userId, int $assetId, float $last12Months): ?float
    {
        $position = $this->positions->positionFor($userId, $assetId);

        if ($position === null || $position->avgCost === null) {
            return null;
        }

        $cost = $position->avgCost * $position->quantity;

        return $cost > 0.0 ? round($last12Months / $cost * 100, 2) : null;
    }
}
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=GetAssetDividendHistoryTest`
Expected: PASS (6 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Contexts/Income/Sources/Dividend
git commit -m "feat: détaille les dividendes perçus sur un instrument"
```

---

### Task 13: Section dividendes de la fiche instrument

**Files:**
- Create: `resources/js/lib/income.ts`
- Create: `resources/js/lib/income.test.ts`
- Create: `resources/js/components/instrument/DividendsSection.vue`
- Modify: `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`
- Modify: `resources/js/Pages/Instruments/Show.vue`
- Test: `tests/Feature/InstrumentDetailPageTest.php`
- Test: `tests/Browser/InstrumentDetailTest.php`

**Interfaces:**
- Consumes: `GetAssetDividendHistory` (Task 12).
- Produces:
  - prop Inertia `dividends` sur `Instruments/Show`, non différée, forme `{ receipts: [{ assetId, exDate, quantity, amountPerShare, amount }], totalReceived, last12Months, yieldOnCost }` ;
  - `resources/js/lib/income.ts` exportant les interfaces `DividendReceipt`, `AssetDividendHistory`, `IncomeSummary`, `AnnualIncome`, `AnnualIncomeBar` et la fonction `annualIncomeBars(rows: AnnualIncome[]): AnnualIncomeBar[]` ;
  - section `data-section="dividends"`, lignes `data-dividend-row`, absente quand `receipts` est vide.

- [ ] **Step 1: Écrire le test de page qui échoue**

Ajouter à `tests/Feature/InstrumentDetailPageTest.php` :

```php
it('expose les dividendes perçus sur la fiche', function () {
    $this->travelTo('2026-08-19 10:00:00');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    $this->actingAs($user)
        ->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('dividends.receipts', 1)
            ->where('dividends.receipts.0.exDate', '2026-03-05')
            /**
             * Clôture et cast, comme `instrument.position.marketValue` ailleurs dans ce fichier :
             * les props traversent `json_encode()`, qui sérialise un flottant à fraction nulle sans
             * son « .0 », et `where()` compare strictement.
             */
            ->where('dividends.receipts.0.amount', fn ($v) => (float) $v === 5.0)
            ->where('dividends.totalReceived', fn ($v) => (float) $v === 5.0)
            ->where('dividends.yieldOnCost', fn ($v) => (float) $v === 0.63)
        );
});

it('rend un historique de dividendes vide sur un capitalisant', function () {
    $asset = Instrument::factory()->create(['name' => 'ACC']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('dividends.receipts', 0));
});
```

Ajouter `use App\Contexts\Market\Models\Dividend;` en tête du fichier.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=InstrumentDetailPageTest`
Expected: FAIL — prop `dividends` absente

- [ ] **Step 3: Passer la prop depuis le contrôleur**

Dans `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`, ajouter au tableau de `Inertia::render` :

```php
            /**
             * Non différée : la visibilité de la section dépend de la donnée elle-même, et un
             * squelette qui disparaît sur chaque instrument capitalisant coûterait plus qu'il ne
             * rapporte. Deux petites requêtes, sur une page qui en fait déjà autant.
             */
            'dividends' => app(GetAssetDividendHistory::class)($userId, $id),
```

avec `use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;`.

- [ ] **Step 4: Lancer le test de page pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=InstrumentDetailPageTest`
Expected: PASS

- [ ] **Step 5: Écrire le test JS qui échoue**

`resources/js/lib/income.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { annualIncomeBars, type AnnualIncome } from './income';

const year = (year: number, total: number): AnnualIncome => ({ year, total, bySource: { dividend: total } });

describe('annualIncomeBars', () => {
    it('mesure chaque barre contre la plus grande année', () => {
        const bars = annualIncomeBars([year(2025, 50), year(2026, 100)]);

        expect(bars.map((bar) => bar.barWidth)).toEqual(['50%', '100%']);
    });

    it('rend une barre nulle quand aucune année ne porte de revenu', () => {
        expect(annualIncomeBars([year(2026, 0)])[0].barWidth).toBe('0%');
    });

    it('ne rend rien sans année', () => {
        expect(annualIncomeBars([])).toEqual([]);
    });
});
```

- [ ] **Step 6: Lancer le test JS pour vérifier qu'il échoue**

Run: `bun run test:js -- income`
Expected: FAIL — `Failed to resolve import "./income"`

- [ ] **Step 7: Écrire le module TypeScript**

`resources/js/lib/income.ts` :

```ts
import { largestOf, relativeBarWidth } from '@/lib/bars';

export interface DividendReceipt {
    assetId: number;
    exDate: string;
    quantity: number;
    amountPerShare: number;
    amount: number;
}

export interface AssetDividendHistory {
    receipts: DividendReceipt[];
    totalReceived: number;
    last12Months: number;
    /** Perçu sur douze mois rapporté au coût de la position, en pourcentage. */
    yieldOnCost: number | null;
}

/** Montants indexés par origine de revenu : `dividend` aujourd'hui, un loyer demain. */
export type IncomeBySource = Record<string, number>;

export interface IncomeSummary {
    totalReceived: number;
    last12Months: number;
    bySource: IncomeBySource;
}

export interface AnnualIncome {
    year: number;
    total: number;
    bySource: IncomeBySource;
}

export interface AnnualIncomeBar {
    year: number;
    total: number;
    barWidth: string;
}

/** Une barre par année, mesurée contre la meilleure année et non contre leur somme. */
export const annualIncomeBars = (rows: AnnualIncome[]): AnnualIncomeBar[] => {
    const largest = largestOf(rows.map((row) => row.total));

    return rows.map((row) => ({
        year: row.year,
        total: row.total,
        barWidth: relativeBarWidth(row.total, largest),
    }));
};
```

- [ ] **Step 8: Lancer le test JS pour vérifier qu'il passe**

Run: `bun run test:js -- income && bun run typecheck`
Expected: PASS (3 tests), typecheck sans erreur

- [ ] **Step 9: Écrire la section Vue**

`resources/js/components/instrument/DividendsSection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur, frDate, pct } from '@/lib/format';
import type { AssetDividendHistory } from '@/lib/income';

const props = defineProps<{ dividends: AssetDividendHistory }>();

const heading = computed<string>(() => `Dividendes (${props.dividends.receipts.length})`);

/** Un montant par action se lit au millième : 0,51 € et 0,515 € ne sont pas le même dividende. */
const perShare = (value: number): string => eur(value, 3);
</script>

<template>
    <section data-section="dividends" class="flex flex-col gap-6 px-6">
        <div class="flex flex-col gap-1.5">
            <h2 class="text-[17px] leading-none font-bold">{{ heading }}</h2>
            <p class="text-sm text-muted-foreground">
                <span data-dividend-total>{{ eur(props.dividends.totalReceived) }}</span> perçus,
                dont <span data-dividend-last12>{{ eur(props.dividends.last12Months) }}</span> sur douze mois
                <template v-if="props.dividends.yieldOnCost !== null">
                    · <span data-dividend-yield>{{ pct(props.dividends.yieldOnCost) }}</span> du prix de revient
                </template>
            </p>
        </div>

        <div class="min-w-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Détachement</TableHead>
                        <TableHead class="text-right">Par action</TableHead>
                        <TableHead class="text-right">Quantité</TableHead>
                        <TableHead class="text-right">Perçu</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="receipt in props.dividends.receipts"
                        :key="receipt.exDate"
                        data-dividend-row
                    >
                        <TableCell>{{ frDate(receipt.exDate) }}</TableCell>
                        <TableCell class="text-right">{{ perShare(receipt.amountPerShare) }}</TableCell>
                        <TableCell class="text-right">{{ receipt.quantity }}</TableCell>
                        <TableCell class="text-right font-semibold">{{ eur(receipt.amount) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </section>
</template>
```

- [ ] **Step 10: Monter la section dans la page**

Dans `resources/js/Pages/Instruments/Show.vue` : importer `DividendsSection` et `AssetDividendHistory`, ajouter `dividends: AssetDividendHistory;` aux props, et insérer entre `SectorsSection` et `TransactionsSection` :

```vue
        <DividendsSection
            v-if="props.dividends.receipts.length"
            :dividends="props.dividends"
        />
```

- [ ] **Step 11: Écrire le test de navigateur**

Ajouter à `tests/Browser/InstrumentDetailTest.php` :

```php
it('affiche les dividendes perçus quand l\'instrument en verse', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.5,
    ]);

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('Dividendes (1)')
        ->assertScript("document.querySelectorAll('[data-dividend-row]').length", 1)
        // `includes` et non une égalité : `Intl` sépare le montant du symbole par une espace
        // insécable étroite, invisible dans le source du test mais fatale à une comparaison stricte.
        ->assertScript("document.querySelector('[data-dividend-total]').textContent.includes('5,00')", true)
        ->assertNoJavaScriptErrors();
});

it('n\'affiche aucune section dividendes sur un instrument capitalisant', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertDontSee('Dividendes')
        ->assertScript("document.querySelectorAll('[data-section=\"dividends\"]').length", 0)
        ->assertNoJavaScriptErrors();
});
```

Ajouter `use App\Contexts\Market\Models\Dividend;` en tête du fichier. Le test d'ordre des sections déjà présent (`'hero|valuation|performance|sectors|transactions'`) reste vrai : `portfolioFixture()` ne crée aucun dividende.

- [ ] **Step 12: Lancer tous les tests touchés**

Run: `bun run build && php artisan test --compact --filter="InstrumentDetailPageTest|InstrumentDetailTest"`
Expected: PASS

- [ ] **Step 13: Commit**

```bash
git add resources/js app/Contexts/InstrumentView tests/Feature/InstrumentDetailPageTest.php tests/Browser/InstrumentDetailTest.php
git commit -m "feat: affiche les dividendes perçus sur la fiche instrument"
```

---

### Task 14: Section Revenus du tableau de bord

**Files:**
- Create: `resources/js/components/dashboard/IncomeSection.vue`
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Feature/DashboardPageTest.php`
- Test: `tests/Browser/DashboardTest.php`

**Interfaces:**
- Consumes: `GetIncomeSummary`, `GetAnnualIncome` (Task 11), `annualIncomeBars` et types de `resources/js/lib/income.ts` (Task 13).
- Produces: props différées `income` et `annualIncome` sur `Dashboard`, groupe `revenus` ; section `data-section="income"` avec lignes `data-income-year`.

- [ ] **Step 1: Écrire le test de page qui échoue**

Ajouter à `tests/Feature/DashboardPageTest.php` :

```php
it('diffère le revenu perçu et son historique annuel dans le groupe revenus', function () {
    $this->travelTo('2026-08-19 10:00:00');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2025-01-01', 'quantity' => 10, 'unit_price' => 80]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2025-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $asset->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.8]);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->missing('income')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                /** Clôture et cast : voir la note de `InstrumentDetailPageTest` sur `json_encode()`. */
                ->where('income.totalReceived', fn ($v) => (float) $v === 13.0)
                ->where('income.last12Months', fn ($v) => (float) $v === 8.0)
                ->where('income.bySource.dividend', fn ($v) => (float) $v === 13.0)
                ->has('annualIncome', 2)
                ->where('annualIncome.0.year', 2025)
                ->where('annualIncome.1.total', fn ($v) => (float) $v === 8.0)
            )
        );
});
```

Ajouter `use App\Contexts\Market\Models\Dividend;` si absent. Aligner le nom des classes importées sur celles déjà utilisées dans ce fichier.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL — props `income` / `annualIncome` absentes

- [ ] **Step 3: Passer les props différées**

Dans `app/Contexts/Portfolio/Http/DashboardController.php`, ajouter au tableau de `Inertia::render` :

```php
            /** Un seul groupe pour les deux : la section les affiche ensemble. */
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetIncomeSummary::class)($user->id)
                : IncomeSummaryData::empty(), 'revenus'),
            'annualIncome' => Inertia::defer(fn () => $user !== null
                ? app(GetAnnualIncome::class)($user->id)
                : [], 'revenus'),
```

avec les `use` correspondants (`App\Contexts\Income\Actions\GetAnnualIncome`, `App\Contexts\Income\Actions\GetIncomeSummary`, `App\Contexts\Income\Datas\IncomeSummaryData`).

- [ ] **Step 4: Lancer le test de page pour vérifier qu'il passe**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=DashboardPageTest`
Expected: PASS

- [ ] **Step 5: Écrire la section Vue**

`resources/js/components/dashboard/IncomeSection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import { annualIncomeBars, type AnnualIncome, type AnnualIncomeBar, type IncomeSummary } from '@/lib/income';

const props = defineProps<{ income?: IncomeSummary; annualIncome?: AnnualIncome[] }>();

const bars = computed<AnnualIncomeBar[]>(() => annualIncomeBars(props.annualIncome ?? []));

const hasIncome = computed<boolean>(() => (props.income?.totalReceived ?? 0) > 0);
</script>

<template>
    <!--
        Titre « Revenus » et non « Dividendes » : c'est cette section qui accueillera les autres
        origines de revenu, et la renommer plus tard déplacerait un repère déjà acquis.
    -->
    <section data-section="income" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Revenus</h2>

        <Deferred data="income">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <template v-if="hasIncome">
                <p class="text-sm text-muted-foreground">
                    <span data-income-total class="font-semibold text-foreground">
                        {{ eur(props.income?.totalReceived ?? 0) }}
                    </span>
                    perçus, dont
                    <span data-income-last12>{{ eur(props.income?.last12Months ?? 0) }}</span>
                    sur douze mois
                </p>

                <ul class="flex min-h-0 grow flex-col gap-3">
                    <li
                        v-for="bar in bars"
                        :key="bar.year"
                        data-income-year
                        class="flex max-h-12 grow items-center gap-3 text-sm md:max-h-none md:grow-0"
                    >
                        <span class="w-12 shrink-0 font-semibold tabular-nums">{{ bar.year }}</span>

                        <span class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-separator">
                            <span
                                data-income-bar
                                class="block h-full rounded-full bg-sector-bar"
                                :style="{ width: bar.barWidth }"
                            ></span>
                        </span>

                        <span class="w-24 shrink-0 text-right font-semibold tabular-nums">
                            {{ eur(bar.total, 0) }}
                        </span>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu perçu pour l'instant.
            </p>
        </Deferred>
    </section>
</template>
```

- [ ] **Step 6: Monter la section dans la page**

Dans `resources/js/Pages/Dashboard.vue` : importer `IncomeSection` et les types `AnnualIncome`, `IncomeSummary`, ajouter `income?: IncomeSummary; annualIncome?: AnnualIncome[];` aux props, et insérer après `PerformancesSection` :

```vue
        <IncomeSection v-if="overview.holdings.length" :income="income" :annual-income="annualIncome" />
```

- [ ] **Step 7: Écrire le test de navigateur**

Ajouter à `tests/Browser/DashboardTest.php` :

```php
it('affiche le revenu perçu et son historique annuel', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();
    Dividend::factory()->create([
        'asset_id' => $instrument->id,
        'ex_date' => '2026-03-05',
        'amount_per_share' => 0.5,
    ]);

    $this->actingAs($user);

    visit('/')
        ->assertSee('Revenus')
        ->assertScript("document.querySelectorAll('[data-income-year]').length", 1)
        ->assertScript("document.querySelector('[data-income-total]').textContent.includes('5,00')", true)
        ->assertNoJavaScriptErrors();
});

it('annonce l\'absence de revenu quand aucun instrument n\'en verse', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Aucun revenu perçu pour l\'instant.')
        ->assertNoJavaScriptErrors();
});
```

Ajouter `use App\Contexts\Market\Models\Dividend;` en tête du fichier.

- [ ] **Step 8: Lancer la suite complète**

Run: `bun run build && bun run typecheck && bun run test:js && php artisan test --compact`
Expected: PASS — aucune régression

- [ ] **Step 9: Commit**

```bash
git add resources/js app/Contexts/Portfolio/Http tests/Feature/DashboardPageTest.php tests/Browser/DashboardTest.php
git commit -m "feat: ajoute la section Revenus au tableau de bord"
```

---

## Vérification finale

- [ ] `php artisan test --compact` — toute la suite au vert.
- [ ] `bun run typecheck && bun run test:js` — front au vert.
- [ ] `vendor/bin/pint --dirty --format agent` — plus rien à corriger.
- [ ] `php artisan market:sync-dividends --asset=<un ETF distribuant>` sur la base locale, puis vérifier que la fiche affiche la section et que le tableau de bord compte le revenu.
- [ ] `php artisan schedule:list` — `market:sync-dividends` présent le samedi à 23h45.
