# AssetView Domain — Design Spec

**Date:** 2026-05-23  
**Scope:** Infrastructure seulement (ports, adapters, DTOs) — pas de Filament pour l'instant

---

## Contexte

Le projet a subi un reset architectural (`checkpoint` commit) pour uniformiser les patterns ports/adapters et STI/CTI sur l'ensemble des domaines. Le domaine Asset est reconstruit proprement. AssetView est le prochain domaine à construire : c'est la couche **read-only** qui expose les données d'asset à l'UI (Filament) et aux futurs domaines.

**Problème résolu :** Dans l'ancienne architecture, les widgets Filament lisaient directement les modèles Eloquent et les services du domaine Asset, mélangeant les préoccupations write/read. AssetView sépare clairement la lecture (view-models, DTOs) de l'écriture (repositories, models).

---

## Architecture

```
app/Domains/AssetView/
├── Ports/
│   ├── AssetPriceViewPort.php
│   ├── AssetSectorViewPort.php
│   └── AssetMetaViewPort.php
├── Infrastructure/
│   └── Adapters/
│       ├── EloquentAssetPriceViewAdapter.php
│       ├── EloquentAssetSectorViewAdapter.php
│       └── EloquentAssetMetaViewAdapter.php
└── DTOs/
    ├── PriceHistoryDTO.php
    ├── SectorWeightDTO.php
    └── AssetMetaDTO.php
```

**Règle de dépendance :**
- AssetView dépend de `Asset\Contracts` (repositories interfaces), jamais des modèles Eloquent directement
- AssetView ne dépend pas de Portfolio ni d'aucun autre domaine
- Les Adapters consomment `AssetPriceRepositoryInterface` et `AssetRepositoryInterface`

---

## DTOs

Tous en `readonly class` avec `fromModel()` statique. Pattern identique aux ValueObjects du domaine Asset.

### `PriceHistoryDTO`
```php
readonly class PriceHistoryDTO {
    public function __construct(
        public string $date,
        public float $close,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
        public ?int $volume = null,
    ) {}

    public static function fromModel(AssetPrice $model): self;
}
```

### `SectorWeightDTO`
```php
readonly class SectorWeightDTO {
    public function __construct(
        public Sector $sector,
        public float $weight,
    ) {}

    public static function fromModel(AssetSector $model): self;
}
```

### `AssetMetaDTO`
```php
readonly class AssetMetaDTO {
    public function __construct(
        public int $id,
        public string $name,
        public AssetType $type,
        public ?string $ticker = null,
        public ?string $isin = null,
        public ?string $exchange = null,
    ) {}

    public static function fromModel(Asset $asset): self;  // lit ->info pour ticker/isin
}
```

---

## Ports

Contrairement aux Ports du domaine Asset, **pas de méthode `supports()`** — un seul adaptateur Eloquent, pas de fournisseurs multiples.

### `AssetPriceViewPort`
```php
interface AssetPriceViewPort {
    /** @return Collection<int, PriceHistoryDTO> */
    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection;

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO;
}
```

### `AssetSectorViewPort`
```php
interface AssetSectorViewPort {
    /** @return Collection<int, SectorWeightDTO> */
    public function getSectorWeights(int $assetId): Collection;
}
```

### `AssetMetaViewPort`
```php
interface AssetMetaViewPort {
    public function getMeta(int $assetId): ?AssetMetaDTO;

    /** @return Collection<int, AssetMetaDTO> */
    public function getAllAssets(): Collection;
}
```

---

## Adapters (Eloquent)

Chaque adapter injecte les **interfaces** repositories du domaine Asset (jamais les implémentations concrètes).

### `EloquentAssetPriceViewAdapter`
```php
class EloquentAssetPriceViewAdapter implements AssetPriceViewPort {
    public function __construct(
        private readonly AssetPriceRepositoryInterface $prices,
    ) {}

    public function getPriceHistory(int $assetId, Carbon $from, Carbon $to): Collection {
        return $this->prices->forAssetSince($assetId, $from)
            ->map(PriceHistoryDTO::fromModel(...));
    }

    public function getLatestPrice(int $assetId): ?PriceHistoryDTO {
        $price = $this->prices->latestForAsset($assetId);
        return $price ? PriceHistoryDTO::fromModel($price) : null;
    }
}
```

### `EloquentAssetSectorViewAdapter`
Pas de `AssetSectorRepositoryInterface` existant — query Eloquent directe. Exception délibérée, acceptable car `AssetSector` est un modèle simple sans logique de repository.

```php
class EloquentAssetSectorViewAdapter implements AssetSectorViewPort {
    public function getSectorWeights(int $assetId): Collection {
        return AssetSector::query()
            ->where('asset_id', $assetId)
            ->get()
            ->map(SectorWeightDTO::fromModel(...));
    }
}
```

### `EloquentAssetMetaViewAdapter`
`AssetRepositoryInterface` n'a pas de `findAll()`. Deux options :
- **Option retenue** : Étendre `AssetRepositoryInterface` avec `findAll(): Collection` dans le domaine Asset.
- Alternative : query Eloquent directe `Asset::query()->get()` — possible mais viole la règle de dépendance.

```php
class EloquentAssetMetaViewAdapter implements AssetMetaViewPort {
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function getMeta(int $assetId): ?AssetMetaDTO {
        $asset = $this->assets->findById($assetId);
        return $asset ? AssetMetaDTO::fromModel($asset) : null;
    }

    public function getAllAssets(): Collection {
        return $this->assets->findAll()->map(AssetMetaDTO::fromModel(...));
    }
}
```

**Impact :** Ajouter `findAll(): Collection<int, Asset>` à `AssetRepositoryInterface` et `EloquentAssetRepository`.

---

## Service Container (AppServiceProvider)

Ajouter dans `app/Providers/AppServiceProvider.php` :

```php
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use App\Domains\AssetView\Ports\AssetSectorViewPort;
use App\Domains\AssetView\Ports\AssetMetaViewPort;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetPriceViewAdapter;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetSectorViewAdapter;
use App\Domains\AssetView\Infrastructure\Adapters\EloquentAssetMetaViewAdapter;

$this->app->bind(AssetPriceViewPort::class, EloquentAssetPriceViewAdapter::class);
$this->app->bind(AssetSectorViewPort::class, EloquentAssetSectorViewAdapter::class);
$this->app->bind(AssetMetaViewPort::class, EloquentAssetMetaViewAdapter::class);
```

---

## Tests

### Unit tests (`tests/Domains/AssetView/Unit/`)
- `PriceHistoryDTOTest` — `fromModel()` mappe correctement tous les champs
- `SectorWeightDTOTest` — `fromModel()` résout l'enum `Sector` correctement
- `AssetMetaDTOTest` — `fromModel()` extrait ticker/isin via `->info`

### Feature tests (`tests/Domains/AssetView/Feature/`)
- `AssetPriceViewAdapterTest` — DB réelle, `getPriceHistory` retourne bons DTOs
- `AssetSectorViewAdapterTest` — DB réelle, `getSectorWeights` retourne bons DTOs
- `AssetMetaViewAdapterTest` — DB réelle, `getMeta` et `getAllAssets` retournent bons DTOs

---

## Ce qui N'est PAS dans ce scope

- Pages/Widgets Filament (après implémentation de Portfolio)
- Calculs analytiques (volatilité, corrélation) — ajoutés plus tard si besoin
- Stats dépendant de transactions (PRU, plus-value, réalisé)
