# AssetView Domain Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construire le domaine `AssetView` — couche read-only qui expose prix, secteurs et métadonnées d'assets via Ports/Adapters/DTOs, avec tests co-localisés TDD.

**Architecture:** Ports dans `AssetView/Ports/`, Adapters Eloquent dans `AssetView/Infrastructure/Adapters/` qui consomment les interfaces du domaine Asset, DTOs en `readonly class` dans `AssetView/DTOs/`. Tests co-localisés dans `app/Domains/AssetView/Tests/` (pas dans `tests/`).

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Eloquent, SQLite in-memory pour tests.

---

## Contexte critique

- `HasInfos` trait (`App\Infrastructure\Eloquent\Traits\HasInfos`) est **supprimé du disque** (staged pour deletion) mais référencé par les 6 modèles Asset → **doit être recréé en Task 1** sinon autoloading échoue
- `AssetPrice.close` est casté `decimal:4` → retourne une **string** `"123.4500"` depuis Eloquent → toujours caster avec `(float)` dans les DTOs
- `infos()` (pas `info()`) est le nom de la relation définie par `HasInfos`
- `AssetSectorFactory::definition()` est vide → passer tous les champs manuellement dans les tests
- `EloquentAssetRepository` référence `Portfolio\Wallet` supprimé — OK car PHP charge les classes lazily; `findAll()` n'utilise pas `Wallet`

---

## Fichiers modifiés / créés

| Fichier | Action |
|---|---|
| `phpunit.xml` | Modifier — ajouter `<directory>app/Domains</directory>` |
| `tests/Pest.php` | Modifier — ajouter `.in('../app/Domains')` |
| `app/Infrastructure/Eloquent/Traits/HasInfos.php` | **Recréer** — trait supprimé du disque |
| `app/Domains/Asset/Contracts/AssetRepositoryInterface.php` | Modifier — ajouter `findAll()` |
| `app/Domains/Asset/Infrastructure/Eloquent/EloquentAssetRepository.php` | Modifier — implémenter `findAll()` |
| `app/Domains/AssetView/Ports/AssetPriceViewPort.php` | Créer |
| `app/Domains/AssetView/Ports/AssetSectorViewPort.php` | Créer |
| `app/Domains/AssetView/Ports/AssetMetaViewPort.php` | Créer |
| `app/Domains/AssetView/DTOs/PriceHistoryDTO.php` | Créer |
| `app/Domains/AssetView/DTOs/SectorWeightDTO.php` | Créer |
| `app/Domains/AssetView/DTOs/AssetMetaDTO.php` | Créer |
| `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetPriceViewAdapter.php` | Créer |
| `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetSectorViewAdapter.php` | Créer |
| `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetMetaViewAdapter.php` | Créer |
| `app/Domains/AssetView/Tests/Unit/PriceHistoryDTOTest.php` | Créer |
| `app/Domains/AssetView/Tests/Unit/SectorWeightDTOTest.php` | Créer |
| `app/Domains/AssetView/Tests/Unit/AssetMetaDTOTest.php` | Créer |
| `app/Domains/AssetView/Tests/Feature/EloquentAssetPriceViewAdapterTest.php` | Créer |
| `app/Domains/AssetView/Tests/Feature/EloquentAssetSectorViewAdapterTest.php` | Créer |
| `app/Domains/AssetView/Tests/Feature/EloquentAssetMetaViewAdapterTest.php` | Créer |
| `app/Providers/AppServiceProvider.php` | Modifier — bindings AssetView |

---

## Task 1: Configuration tests co-localisés

**Files:**
- Modify: `phpunit.xml`
- Modify: `tests/Pest.php`

- [ ] **Step 1.1: Ajouter le directory dans `phpunit.xml`**

```xml
<!-- phpunit.xml — remplacer le bloc <testsuites> -->
<testsuites>
    <testsuite name="Tests">
        <directory>tests</directory>
        <directory>app/Domains</directory>
    </testsuite>
</testsuites>
```

- [ ] **Step 1.2: Configurer la base test case dans `tests/Pest.php`**

Ajouter après les deux blocs `pest()` existants :

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('../app/Domains');
```

- [ ] **Step 1.3: Vérifier la découverte**

```bash
php artisan test --compact --filter=nonexistent 2>&1 | head -5
```

Expected: no errors about directory not found, just "No tests ran".

- [ ] **Step 1.4: Commit**

```bash
git add phpunit.xml tests/Pest.php
git commit -m "test: configure co-located test discovery for app/Domains"
```

---

## Task 2: Recréer le trait HasInfos

`app/Infrastructure/Eloquent/Traits/HasInfos.php` est supprimé du disque. Six modèles (`Stock`, `ETF`, `Crypto`, `Bond`, `RealEstate`, `Savings`) l'utilisent — sans ce fichier, toute factory qui crée ces modèles échoue.

**Files:**
- Create: `app/Infrastructure/Eloquent/Traits/HasInfos.php`

- [ ] **Step 2.1: Recréer le trait**

```php
<?php

namespace App\Infrastructure\Eloquent\Traits;

use Illuminate\Database\Eloquent\Relations\HasOne;

trait HasInfos
{
    abstract protected function infosModel(): string;

    public function infos(): HasOne
    {
        return $this->hasOne(
            $this->infosModel(),
            'asset_id',
            $this->getKeyName()
        );
    }
}
```

- [ ] **Step 2.2: Vérifier que les tests Asset existants passent toujours**

```bash
php artisan test --compact tests/Domains/Asset/Unit/Models/StockTest.php
```

Expected: `1 passed`

- [ ] **Step 2.3: Commit**

```bash
git add app/Infrastructure/Eloquent/Traits/HasInfos.php
git commit -m "fix: restore HasInfos trait deleted during architectural reset"
```

---

## Task 3: Étendre AssetRepositoryInterface avec findAll()

`EloquentAssetMetaViewAdapter` a besoin de lister tous les assets. `AssetRepositoryInterface` n'a pas de `findAll()`.

**Files:**
- Modify: `app/Domains/Asset/Contracts/AssetRepositoryInterface.php`
- Modify: `app/Domains/Asset/Infrastructure/Eloquent/EloquentAssetRepository.php`
- Modify (test existant): `tests/Domains/Asset/Feature/Repositories/AssetRepositoryTest.php`

- [ ] **Step 3.1: Écrire le test dans le fichier existant**

Ouvrir `tests/Domains/Asset/Feature/Repositories/AssetRepositoryTest.php` et ajouter :

```php
it('finds all assets', function (): void {
    Stock::factory()->count(3)->create();

    $result = app(AssetRepositoryInterface::class)->findAll();

    expect($result)->toHaveCount(3);
});
```

Imports nécessaires en tête du fichier (ajouter si absents) :
```php
use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Models\Assets\Stock;
```

- [ ] **Step 3.2: Vérifier que le test échoue**

```bash
php artisan test --compact --filter="finds all assets"
```

Expected: FAIL — `Call to undefined method ... findAll()`

- [ ] **Step 3.3: Ajouter `findAll()` à l'interface**

`app/Domains/Asset/Contracts/AssetRepositoryInterface.php` — ajouter la méthode :

```php
use Illuminate\Database\Eloquent\Collection;

/** @return Collection<int, Asset> */
public function findAll(): Collection;
```

Fichier complet après modification :

```php
<?php

namespace App\Domains\Asset\Contracts;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;
use Illuminate\Database\Eloquent\Collection;

interface AssetRepositoryInterface
{
    public function findById(int $id): ?Asset;

    /** @return Collection<int, Asset> */
    public function forWallet(int $walletId): Collection;

    /** @return array<int> */
    public function getIdsForWallet(int $walletId): array;

    /** @return Collection<int, Asset> */
    public function findByType(AssetType $type): Collection;

    /** @return Collection<int, Asset> */
    public function findAll(): Collection;

    public function save(Asset $asset): void;
}
```

- [ ] **Step 3.4: Implémenter dans EloquentAssetRepository**

Ajouter la méthode à `app/Domains/Asset/Infrastructure/Eloquent/EloquentAssetRepository.php` :

```php
public function findAll(): Collection
{
    return Asset::query()->get();
}
```

- [ ] **Step 3.5: Vérifier que le test passe**

```bash
php artisan test --compact --filter="finds all assets"
```

Expected: `1 passed`

- [ ] **Step 3.6: Commit**

```bash
git add app/Domains/Asset/Contracts/AssetRepositoryInterface.php \
        app/Domains/Asset/Infrastructure/Eloquent/EloquentAssetRepository.php \
        tests/Domains/Asset/Feature/Repositories/AssetRepositoryTest.php
git commit -m "feat: add findAll() to AssetRepositoryInterface"
```

---

## Task 4: PriceHistoryDTO (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Unit/PriceHistoryDTOTest.php`
- Create: `app/Domains/AssetView/DTOs/PriceHistoryDTO.php`

- [ ] **Step 4.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Models\AssetPrice;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\PriceHistoryDTO;

it('maps all fields from AssetPrice model', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'date' => '2026-01-15',
        'close' => 123.45,
        'open' => 120.00,
        'high' => 125.00,
        'low' => 119.00,
        'volume' => 50000,
    ]);

    $dto = PriceHistoryDTO::fromModel($price);

    expect($dto->date)->toBe('2026-01-15')
        ->and($dto->close)->toBe(123.45)
        ->and($dto->open)->toBe(120.0)
        ->and($dto->high)->toBe(125.0)
        ->and($dto->low)->toBe(119.0)
        ->and($dto->volume)->toBe(50000);
});

it('handles nullable open/high/low fields', function (): void {
    $stock = Stock::factory()->create();
    $price = AssetPrice::factory()->create([
        'asset_id' => $stock->id,
        'open' => null,
        'high' => null,
        'low' => null,
    ]);

    $dto = PriceHistoryDTO::fromModel($price);

    expect($dto->open)->toBeNull()
        ->and($dto->high)->toBeNull()
        ->and($dto->low)->toBeNull();
});
```

- [ ] **Step 4.2: Vérifier que le test échoue**

```bash
php artisan test --compact --filter="PriceHistoryDTO"
```

Expected: FAIL — `Class "App\Domains\AssetView\DTOs\PriceHistoryDTO" not found`

- [ ] **Step 4.3: Créer le DTO**

```php
<?php

namespace App\Domains\AssetView\DTOs;

use App\Domains\Asset\Models\AssetPrice;

readonly class PriceHistoryDTO
{
    public function __construct(
        public string $date,
        public float $close,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?int $volume = null,
    ) {}

    public static function fromModel(AssetPrice $model): self
    {
        return new self(
            date: $model->date->format('Y-m-d'),
            close: (float) $model->close,
            open: $model->open !== null ? (float) $model->open : null,
            high: $model->high !== null ? (float) $model->high : null,
            low: $model->low !== null ? (float) $model->low : null,
            volume: $model->volume,
        );
    }
}
```

- [ ] **Step 4.4: Vérifier que les tests passent**

```bash
php artisan test --compact --filter="PriceHistoryDTO"
```

Expected: `2 passed`

- [ ] **Step 4.5: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4.6: Commit**

```bash
git add app/Domains/AssetView/DTOs/PriceHistoryDTO.php \
        app/Domains/AssetView/Tests/Unit/PriceHistoryDTOTest.php
git commit -m "feat: add PriceHistoryDTO with fromModel() factory"
```

---

## Task 5: SectorWeightDTO (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Unit/SectorWeightDTOTest.php`
- Create: `app/Domains/AssetView/DTOs/SectorWeightDTO.php`

- [ ] **Step 5.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\AssetSector;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\SectorWeightDTO;

it('maps sector and weight from AssetSector model', function (): void {
    $stock = Stock::factory()->create();
    $sector = AssetSector::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Technology->value,
        'weight' => '0.450000',
    ]);

    $dto = SectorWeightDTO::fromModel($sector);

    expect($dto->sector)->toBe(Sector::Technology)
        ->and($dto->weight)->toBe(0.45);
});
```

- [ ] **Step 5.2: Vérifier que le test échoue**

```bash
php artisan test --compact --filter="SectorWeightDTO"
```

Expected: FAIL — `Class "App\Domains\AssetView\DTOs\SectorWeightDTO" not found`

- [ ] **Step 5.3: Créer le DTO**

```php
<?php

namespace App\Domains\AssetView\DTOs;

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\AssetSector;

readonly class SectorWeightDTO
{
    public function __construct(
        public Sector $sector,
        public float $weight,
    ) {}

    public static function fromModel(AssetSector $model): self
    {
        return new self(
            sector: $model->sector,
            weight: (float) $model->weight,
        );
    }
}
```

- [ ] **Step 5.4: Vérifier que le test passe**

```bash
php artisan test --compact --filter="SectorWeightDTO"
```

Expected: `1 passed`

- [ ] **Step 5.5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/AssetView/DTOs/SectorWeightDTO.php \
        app/Domains/AssetView/Tests/Unit/SectorWeightDTOTest.php
git commit -m "feat: add SectorWeightDTO with fromModel() factory"
```

---

## Task 6: AssetMetaDTO (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Unit/AssetMetaDTOTest.php`
- Create: `app/Domains/AssetView/DTOs/AssetMetaDTO.php`

Note: `->infos` (pas `->info`) est la relation définie par `HasInfos`. Certains types d'asset n'ont pas de ticker/isin (`Savings` n'a aucun champ optionnel, `RealEstate` a uniquement isin).

- [ ] **Step 6.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\Asset\Models\Assets\Savings;
use App\Domains\AssetView\DTOs\AssetMetaDTO;

it('maps id, name, type, ticker and isin from Stock model', function (): void {
    $stock = Stock::factory()
        ->withInfos(fn (StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'AAPL',
            'isin' => 'US0378331005',
        ]))
        ->create(['name' => 'Apple Inc']);

    $dto = AssetMetaDTO::fromModel($stock);

    expect($dto->id)->toBe($stock->id)
        ->and($dto->name)->toBe('Apple Inc')
        ->and($dto->type)->toBe(AssetType::Stock)
        ->and($dto->ticker)->toBe('AAPL')
        ->and($dto->isin)->toBe('US0378331005');
});

it('returns null ticker and isin when asset has no infos', function (): void {
    $savings = Savings::factory()->create(['name' => 'Livret A']);

    $dto = AssetMetaDTO::fromModel($savings);

    expect($dto->ticker)->toBeNull()
        ->and($dto->isin)->toBeNull();
});
```

- [ ] **Step 6.2: Vérifier que le test échoue**

```bash
php artisan test --compact --filter="AssetMetaDTO"
```

Expected: FAIL — `Class "App\Domains\AssetView\DTOs\AssetMetaDTO" not found`

- [ ] **Step 6.3: Créer le DTO**

```php
<?php

namespace App\Domains\AssetView\DTOs;

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Assets\Asset;

readonly class AssetMetaDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public AssetType $type,
        public ?string $ticker = null,
        public ?string $isin = null,
    ) {}

    public static function fromModel(Asset $asset): self
    {
        $infos = $asset->relationLoaded('infos') ? $asset->infos : $asset->infos()->first();

        return new self(
            id: $asset->id,
            name: $asset->name,
            type: $asset->type,
            ticker: $infos?->ticker ?? null,
            isin: $infos?->isin ?? null,
        );
    }
}
```

- [ ] **Step 6.4: Vérifier que les tests passent**

```bash
php artisan test --compact --filter="AssetMetaDTO"
```

Expected: `2 passed`

- [ ] **Step 6.5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/AssetView/DTOs/AssetMetaDTO.php \
        app/Domains/AssetView/Tests/Unit/AssetMetaDTOTest.php
git commit -m "feat: add AssetMetaDTO with fromModel() factory"
```

---

## Task 7: Créer les 3 Ports (interfaces)

Pas de tests unitaires sur les interfaces elles-mêmes — elles sont testées à travers les adapters.

**Files:**
- Create: `app/Domains/AssetView/Ports/AssetPriceViewPort.php`
- Create: `app/Domains/AssetView/Ports/AssetSectorViewPort.php`
- Create: `app/Domains/AssetView/Ports/AssetMetaViewPort.php`

- [ ] **Step 7.1: Créer `AssetPriceViewPort`**

```php
<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface AssetPriceViewPort
{
    /** @return Collection<int, PriceHistoryDTO> */
    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection;

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO;
}
```

- [ ] **Step 7.2: Créer `AssetSectorViewPort`**

```php
<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\DTOs\SectorWeightDTO;
use Illuminate\Support\Collection;

interface AssetSectorViewPort
{
    /** @return Collection<int, SectorWeightDTO> */
    public function getSectorWeights(int $assetId): Collection;
}
```

- [ ] **Step 7.3: Créer `AssetMetaViewPort`**

```php
<?php

namespace App\Domains\AssetView\Ports;

use App\Domains\AssetView\DTOs\AssetMetaDTO;
use Illuminate\Support\Collection;

interface AssetMetaViewPort
{
    public function getMeta(int $assetId): ?AssetMetaDTO;

    /** @return Collection<int, AssetMetaDTO> */
    public function getAllAssets(): Collection;
}
```

- [ ] **Step 7.4: Commit**

```bash
git add app/Domains/AssetView/Ports/
git commit -m "feat: add AssetView port interfaces"
```

---

## Task 8: EloquentAssetPriceViewAdapter (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Feature/EloquentAssetPriceViewAdapterTest.php`
- Create: `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetPriceViewAdapter.php`

- [ ] **Step 8.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Factories\AssetPriceFactory;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetPriceViewAdapter;
use Carbon\Carbon;

it('returns price history as PriceHistoryDTOs', function (): void {
    $stock = Stock::factory()
        ->withPrices(fn (AssetPriceFactory $f) => $f->state(['date' => '2026-01-10', 'close' => 100.0]))
        ->withPrices(fn (AssetPriceFactory $f) => $f->state(['date' => '2026-02-10', 'close' => 110.0]))
        ->withPrices(fn (AssetPriceFactory $f) => $f->state(['date' => '2026-12-10', 'close' => 999.0]))
        ->create();

    $adapter = app(EloquentAssetPriceViewAdapter::class);
    $result = $adapter->getPriceHistory(
        $stock->id,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-06-01'),
    );

    expect($result)->toHaveCount(2)
        ->and($result->first())->toBeInstanceOf(PriceHistoryDTO::class)
        ->and($result->first()->close)->toBe(100.0)
        ->and($result->last()->close)->toBe(110.0);
});

it('returns empty collection when no prices', function (): void {
    $stock = Stock::factory()->create();

    $result = app(EloquentAssetPriceViewAdapter::class)
        ->getPriceHistory($stock->id, Carbon::parse('2026-01-01'), Carbon::now());

    expect($result)->toBeEmpty();
});

it('returns latest price as PriceHistoryDTO', function (): void {
    $stock = Stock::factory()
        ->withPrices(fn (AssetPriceFactory $f) => $f->state(['date' => '2026-05-01', 'close' => 50.0]))
        ->withPrices(fn (AssetPriceFactory $f) => $f->state(['date' => '2026-05-22', 'close' => 99.0]))
        ->create();

    $dto = app(EloquentAssetPriceViewAdapter::class)->getLatestPrice($stock->id);

    expect($dto)->not->toBeNull()
        ->and($dto)->toBeInstanceOf(PriceHistoryDTO::class)
        ->and($dto->date)->toBe('2026-05-22')
        ->and($dto->close)->toBe(99.0);
});

it('returns null as latest price when no prices exist', function (): void {
    $stock = Stock::factory()->create();

    expect(app(EloquentAssetPriceViewAdapter::class)->getLatestPrice($stock->id))->toBeNull();
});
```

- [ ] **Step 8.2: Vérifier que les tests échouent**

```bash
php artisan test --compact --filter="EloquentAssetPriceViewAdapter"
```

Expected: FAIL — `Class "App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetPriceViewAdapter" not found`

- [ ] **Step 8.3: Créer l'adapter**

```php
<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Contracts\AssetPriceRepositoryInterface;
use App\Domains\Asset\Models\AssetPrice;
use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EloquentAssetPriceViewAdapter implements AssetPriceViewPort
{
    public function __construct(
        private readonly AssetPriceRepositoryInterface $prices,
    ) {}

    /** @return Collection<int, PriceHistoryDTO> */
    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection
    {
        return $this->prices->forAssetSince($assetId, $from)
            ->filter(fn (AssetPrice $p) => $p->date->lte($to))
            ->map(PriceHistoryDTO::fromModel(...))
            ->values();
    }

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO
    {
        $price = $this->prices->latestForAsset($assetId);

        return $price ? PriceHistoryDTO::fromModel($price) : null;
    }
}
```

- [ ] **Step 8.4: Vérifier que les tests passent**

```bash
php artisan test --compact --filter="EloquentAssetPriceViewAdapter"
```

Expected: `4 passed`

- [ ] **Step 8.5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetPriceViewAdapter.php \
        app/Domains/AssetView/Tests/Feature/EloquentAssetPriceViewAdapterTest.php
git commit -m "feat: add EloquentAssetPriceViewAdapter"
```

---

## Task 9: EloquentAssetSectorViewAdapter (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Feature/EloquentAssetSectorViewAdapterTest.php`
- Create: `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetSectorViewAdapter.php`

Note: Pas de repository pour `AssetSector` → query Eloquent directe. Exception délibérée.

- [ ] **Step 9.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Enums\Sector;
use App\Domains\Asset\Models\AssetSector;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\AssetView\DTOs\SectorWeightDTO;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetSectorViewAdapter;

it('returns sector weights as SectorWeightDTOs', function (): void {
    $stock = Stock::factory()->create();
    AssetSector::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Technology->value,
        'weight' => '0.600000',
    ]);
    AssetSector::factory()->create([
        'asset_id' => $stock->id,
        'sector' => Sector::Healthcare->value,
        'weight' => '0.400000',
    ]);

    $result = app(EloquentAssetSectorViewAdapter::class)->getSectorWeights($stock->id);

    expect($result)->toHaveCount(2)
        ->and($result->first())->toBeInstanceOf(SectorWeightDTO::class)
        ->and($result->first()->sector)->toBe(Sector::Technology)
        ->and($result->first()->weight)->toBe(0.6);
});

it('returns empty collection when asset has no sectors', function (): void {
    $stock = Stock::factory()->create();

    $result = app(EloquentAssetSectorViewAdapter::class)->getSectorWeights($stock->id);

    expect($result)->toBeEmpty();
});

it('does not return sectors from other assets', function (): void {
    $stock1 = Stock::factory()->create();
    $stock2 = Stock::factory()->create();
    AssetSector::factory()->create([
        'asset_id' => $stock1->id,
        'sector' => Sector::Technology->value,
        'weight' => '1.000000',
    ]);

    $result = app(EloquentAssetSectorViewAdapter::class)->getSectorWeights($stock2->id);

    expect($result)->toBeEmpty();
});
```

- [ ] **Step 9.2: Vérifier que les tests échouent**

```bash
php artisan test --compact --filter="EloquentAssetSectorViewAdapter"
```

Expected: FAIL — `Class "App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetSectorViewAdapter" not found`

- [ ] **Step 9.3: Créer l'adapter**

```php
<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Models\AssetSector;
use App\Domains\AssetView\DTOs\SectorWeightDTO;
use App\Domains\AssetView\Ports\AssetSectorViewPort;
use Illuminate\Support\Collection;

class EloquentAssetSectorViewAdapter implements AssetSectorViewPort
{
    /** @return Collection<int, SectorWeightDTO> */
    public function getSectorWeights(int $assetId): Collection
    {
        return AssetSector::query()
            ->where('asset_id', $assetId)
            ->get()
            ->map(SectorWeightDTO::fromModel(...));
    }
}
```

- [ ] **Step 9.4: Vérifier que les tests passent**

```bash
php artisan test --compact --filter="EloquentAssetSectorViewAdapter"
```

Expected: `3 passed`

- [ ] **Step 9.5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetSectorViewAdapter.php \
        app/Domains/AssetView/Tests/Feature/EloquentAssetSectorViewAdapterTest.php
git commit -m "feat: add EloquentAssetSectorViewAdapter"
```

---

## Task 10: EloquentAssetMetaViewAdapter (TDD)

**Files:**
- Create: `app/Domains/AssetView/Tests/Feature/EloquentAssetMetaViewAdapterTest.php`
- Create: `app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetMetaViewAdapter.php`

- [ ] **Step 10.1: Écrire le test**

```php
<?php

use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Factories\AssetInfos\StockAssetInfoFactory;
use App\Domains\Asset\Models\Assets\Stock;
use App\Domains\Asset\Models\Assets\Savings;
use App\Domains\AssetView\DTOs\AssetMetaDTO;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetMetaViewAdapter;

it('returns asset meta by id', function (): void {
    $stock = Stock::factory()
        ->withInfos(fn (StockAssetInfoFactory $f) => $f->state([
            'ticker' => 'MSFT',
            'isin' => 'US5949181045',
        ]))
        ->create(['name' => 'Microsoft']);

    $dto = app(EloquentAssetMetaViewAdapter::class)->getMeta($stock->id);

    expect($dto)->not->toBeNull()
        ->and($dto)->toBeInstanceOf(AssetMetaDTO::class)
        ->and($dto->name)->toBe('Microsoft')
        ->and($dto->type)->toBe(AssetType::Stock)
        ->and($dto->ticker)->toBe('MSFT')
        ->and($dto->isin)->toBe('US5949181045');
});

it('returns null when asset not found', function (): void {
    $dto = app(EloquentAssetMetaViewAdapter::class)->getMeta(99999);

    expect($dto)->toBeNull();
});

it('returns all assets as AssetMetaDTOs', function (): void {
    Stock::factory()->count(2)->create();
    Savings::factory()->create();

    $result = app(EloquentAssetMetaViewAdapter::class)->getAllAssets();

    expect($result)->toHaveCount(3)
        ->and($result->first())->toBeInstanceOf(AssetMetaDTO::class);
});
```

- [ ] **Step 10.2: Vérifier que les tests échouent**

```bash
php artisan test --compact --filter="EloquentAssetMetaViewAdapter"
```

Expected: FAIL — `Class "App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetMetaViewAdapter" not found`

- [ ] **Step 10.3: Créer l'adapter**

```php
<?php

namespace App\Domains\AssetView\Infrastructure\Adapters;

use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Models\Assets\Asset;
use App\Domains\AssetView\DTOs\AssetMetaDTO;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use Illuminate\Support\Collection;

class EloquentAssetMetaViewAdapter implements AssetMetaViewPort
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function getMeta(int $assetId): ?AssetMetaDTO
    {
        $asset = $this->assets->findById($assetId);

        return $asset ? AssetMetaDTO::fromModel($asset) : null;
    }

    /** @return Collection<int, AssetMetaDTO> */
    public function getAllAssets(): Collection
    {
        return $this->assets->findAll()
            ->map(AssetMetaDTO::fromModel(...));
    }
}
```

- [ ] **Step 10.4: Vérifier que les tests passent**

```bash
php artisan test --compact --filter="EloquentAssetMetaViewAdapter"
```

Expected: `3 passed`

- [ ] **Step 10.5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domains/AssetView/Infrastructure/Adapters/EloquentAssetMetaViewAdapter.php \
        app/Domains/AssetView/Tests/Feature/EloquentAssetMetaViewAdapterTest.php
git commit -m "feat: add EloquentAssetMetaViewAdapter"
```

---

## Task 11: Bindings AppServiceProvider

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 11.1: Ajouter les bindings AssetView**

Dans `app/Providers/AppServiceProvider.php`, ajouter dans la méthode `register()` après les bindings Asset existants :

```php
// Imports à ajouter en haut du fichier
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetMetaViewAdapter;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetPriceViewAdapter;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetSectorViewAdapter;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use App\Domains\AssetView\Ports\AssetSectorViewPort;
```

Dans `register()` :
```php
// AssetView ports → Adapters
$this->app->bind(AssetPriceViewPort::class, EloquentAssetPriceViewAdapter::class);
$this->app->bind(AssetSectorViewPort::class, EloquentAssetSectorViewAdapter::class);
$this->app->bind(AssetMetaViewPort::class, EloquentAssetMetaViewAdapter::class);
```

- [ ] **Step 11.2: Vérifier la résolution via les ports**

Ajouter un test rapide dans `EloquentAssetPriceViewAdapterTest.php` pour valider la résolution via le port :

```php
it('resolves from container via AssetPriceViewPort', function (): void {
    $adapter = app(\App\Domains\AssetView\Ports\AssetPriceViewPort::class);
    expect($adapter)->toBeInstanceOf(EloquentAssetPriceViewAdapter::class);
});
```

- [ ] **Step 11.3: Lancer les tests de l'adapter**

```bash
php artisan test --compact --filter="EloquentAssetPriceViewAdapter"
```

Expected: `5 passed`

- [ ] **Step 11.4: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/AppServiceProvider.php \
        app/Domains/AssetView/Tests/Feature/EloquentAssetPriceViewAdapterTest.php
git commit -m "feat: bind AssetView ports to Eloquent adapters in AppServiceProvider"
```

---

## Task 12: Validation finale

- [ ] **Step 12.1: Lancer tous les tests**

```bash
php artisan test --compact
```

Expected: tous les tests passent. En cas d'échec, identifier et corriger.

- [ ] **Step 12.2: Vérifier que les tests co-localisés sont bien découverts**

```bash
php artisan test --compact app/Domains/AssetView/Tests/
```

Expected: `14 passed` (2 DTOTest Unit + 1 SectorWeight + 4+1 Price adapter + 3 Sector adapter + 3+1 Meta adapter)

- [ ] **Step 12.3: Commit final si modifications Pint**

```bash
vendor/bin/pint --dirty --format agent
git add -p
git commit -m "style: apply Pint formatting to AssetView domain"
```

---

## Vérification spec vs plan

| Spec requirement | Task couvrant |
|---|---|
| Ports AssetPriceViewPort | Task 7 |
| Ports AssetSectorViewPort | Task 7 |
| Ports AssetMetaViewPort | Task 7 |
| Adapter EloquentAssetPriceViewAdapter | Task 8 |
| Adapter EloquentAssetSectorViewAdapter | Task 9 |
| Adapter EloquentAssetMetaViewAdapter | Task 10 |
| DTO PriceHistoryDTO fromModel() | Task 4 |
| DTO SectorWeightDTO fromModel() | Task 5 |
| DTO AssetMetaDTO fromModel() | Task 6 |
| Bindings AppServiceProvider | Task 11 |
| Tests co-localisés | Task 1 |
| findAll() sur AssetRepositoryInterface | Task 3 |
| Recréer HasInfos (prérequis) | Task 2 |
