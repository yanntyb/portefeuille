# Fiche par instrument (contexte `InstrumentView`) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une page catalogue listant tous les instruments et une fiche détaillée par instrument (position, courbe de prix, transactions, secteurs), plus des liens depuis le Dashboard.

**Architecture:** Nouveau contexte read-only `app/Contexts/InstrumentView/` qui compose ses vues via 3 ports (MarketData, Holdings, Transactions) implémentés par des adapters anti-corruption lisant Market et Portfolio. Ports bindés par `InstrumentViewProvider` appelé dans `AppServiceProvider`. Deux controllers invokables + deux pages Inertia/Vue. Dépendance cross-contexte uniquement par id + Port (règle d'or respectée).

**Tech Stack:** Laravel 12, PHP 8.4, Inertia v3, Vue 3 + TypeScript, ApexCharts (vue3-apexcharts), shadcn-vue (Card/Table/Button), Tailwind v4, Pest 4.

## Global Constraints

- Textes UI **en français** (labels, messages vides, titres).
- Cross-contexte **par id + Port uniquement** ; les adapters sont le seul endroit lisant `Market\*` / `Portfolio\*`.
- DTOs de sortie Inertia = `readonly` + `JsonSerializable`, dans `InstrumentView\Datas`.
- Types explicites partout (params + retours). Accolades obligatoires.
- Après toute modif PHP : `vendor/bin/pint --dirty --format agent` avant commit.
- Tests Pest : `php artisan test --compact --filter=...`. Front : `bun run typecheck` puis `bun run build`.
- Utilisateur courant (mono-user v1) : `auth()->user() ?? User::query()->first()`.
- Route param instrument = **id entier**, résolution par port (pas de route-model-binding cross-contexte).
- Historique prix fiche = **12 mois** (`now()->subMonths(12)`).
- Position affichée **seulement si détenu ET prix disponible** (cohérent Dashboard).

**Note de refinement vs spec :** la position est un read mono-ligne bon marché → livrée **inline** dans la prop `instrument` (pas en `defer`). Seul l'historique de prix 12 mois (lourd) est livré en `Inertia::defer`. Justifié : YAGNI, une seule requête `holdingFor`.

---

## File Structure

**Créés — contexte :**
- `app/Contexts/InstrumentView/Datas/InstrumentSummaryData.php` — value objet interne (id,name,ticker,type)
- `app/Contexts/InstrumentView/Datas/InstrumentMetaData.php` — interne (id,name,ticker,isin,type,lastPrice,lastPriceDate)
- `app/Contexts/InstrumentView/Datas/HoldingSnapshotData.php` — interne (assetId,quantity,avgCost)
- `app/Contexts/InstrumentView/Datas/CatalogLineData.php` — JsonSerializable
- `app/Contexts/InstrumentView/Datas/InstrumentCatalogData.php` — JsonSerializable (wrapper lines)
- `app/Contexts/InstrumentView/Datas/PositionData.php` — JsonSerializable
- `app/Contexts/InstrumentView/Datas/TransactionLineData.php` — JsonSerializable
- `app/Contexts/InstrumentView/Datas/SectorWeightData.php` — JsonSerializable
- `app/Contexts/InstrumentView/Datas/PriceHistoryData.php` — JsonSerializable (labels,close)
- `app/Contexts/InstrumentView/Datas/InstrumentDetailData.php` — JsonSerializable (composite)
- `app/Contexts/InstrumentView/Ports/MarketDataPort.php`
- `app/Contexts/InstrumentView/Ports/HoldingsPort.php`
- `app/Contexts/InstrumentView/Ports/TransactionsPort.php`
- `app/Contexts/InstrumentView/Infrastructure/MarketData.php`
- `app/Contexts/InstrumentView/Infrastructure/PortfolioHoldings.php`
- `app/Contexts/InstrumentView/Infrastructure/PortfolioTransactions.php`
- `app/Contexts/InstrumentView/InstrumentViewProvider.php`
- `app/Contexts/InstrumentView/Actions/GetInstrumentCatalog.php`
- `app/Contexts/InstrumentView/Actions/GetInstrumentDetail.php`
- `app/Contexts/InstrumentView/Http/InstrumentCatalogController.php`
- `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`

**Créés — front & tests :**
- `resources/js/Pages/Instruments/Index.vue`
- `resources/js/Pages/Instruments/Show.vue`
- `tests/Unit/InstrumentView/MarketDataTest.php`
- `tests/Unit/InstrumentView/PortfolioHoldingsTest.php`
- `tests/Unit/InstrumentView/PortfolioTransactionsTest.php`
- `tests/Unit/InstrumentView/GetInstrumentCatalogTest.php`
- `tests/Unit/InstrumentView/GetInstrumentDetailTest.php`
- `tests/Feature/InstrumentCatalogPageTest.php`
- `tests/Feature/InstrumentDetailPageTest.php`

**Modifiés :**
- `app/Providers/AppServiceProvider.php` — appeler `InstrumentViewProvider::registers`
- `routes/web.php` — 2 routes
- `app/Contexts/Portfolio/Datas/HoldingLineData.php` — ajouter `assetId`
- `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` — peupler `assetId`
- `resources/js/Pages/Dashboard.vue` — interface + lignes cliquables
- `tests/Feature/DashboardPageTest.php` — asserter `assetId`

---

## Task 1: DTOs `InstrumentView\Datas`

**Files:**
- Create: les 10 fichiers `app/Contexts/InstrumentView/Datas/*.php`
- Test: `tests/Unit/InstrumentView/DatasTest.php`

**Interfaces:**
- Produces (value objects internes, plain readonly) :
  - `InstrumentSummaryData(int $id, string $name, ?string $ticker, InstrumentType $type)`
  - `InstrumentMetaData(int $id, string $name, ?string $ticker, ?string $isin, InstrumentType $type, ?float $lastPrice, ?string $lastPriceDate)`
  - `HoldingSnapshotData(int $assetId, float $quantity, ?float $avgCost)`
- Produces (JsonSerializable) :
  - `CatalogLineData(int $id, string $name, ?string $ticker, InstrumentType $type, ?float $lastPrice, bool $held, ?float $quantity, ?float $marketValue)` → json: `id,name,ticker,type,typeLabel,lastPrice,held,quantity,marketValue`
  - `InstrumentCatalogData(array $lines)` → json: `{ lines: [...] }` (`list<CatalogLineData>`)
  - `PositionData(float $quantity, ?float $avgCost, ?float $marketValue, ?float $gain, ?float $gainPct)` → json même clés
  - `TransactionLineData(string $date, bool $isSell, string $typeLabel, float $quantity, float $unitPrice, float $fees, float $total)` → json même clés
  - `SectorWeightData(string $label, float $weight)` → json même clés
  - `PriceHistoryData(array $labels, array $close)` → json: `{ labels: string[], close: float[] }`
  - `InstrumentDetailData(int $id, string $name, ?string $ticker, ?string $isin, InstrumentType $type, ?float $lastPrice, ?string $lastPriceDate, ?PositionData $position, array $transactions, array $sectors)` → json: `id,name,ticker,isin,type,typeLabel,lastPrice,lastPriceDate,position,transactions,sectors`

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/DatasTest.php`
```php
<?php

use App\Contexts\InstrumentView\Datas\CatalogLineData;
use App\Contexts\InstrumentView\Datas\InstrumentCatalogData;
use App\Contexts\InstrumentView\Datas\InstrumentDetailData;
use App\Contexts\InstrumentView\Datas\PositionData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use App\Contexts\InstrumentView\Datas\SectorWeightData;
use App\Contexts\InstrumentView\Datas\TransactionLineData;
use App\Contexts\Market\Enums\InstrumentType;

it('serializes a catalog line with a type label', function () {
    $line = new CatalogLineData(
        id: 7, name: 'ACME', ticker: 'ACM', type: InstrumentType::Stock,
        lastPrice: 100.0, held: true, quantity: 10.0, marketValue: 1000.0,
    );

    expect($line->jsonSerialize())->toMatchArray([
        'id' => 7, 'name' => 'ACME', 'ticker' => 'ACM',
        'type' => 'stock', 'typeLabel' => 'Stock',
        'lastPrice' => 100.0, 'held' => true, 'quantity' => 10.0, 'marketValue' => 1000.0,
    ]);
});

it('wraps catalog lines', function () {
    $catalog = new InstrumentCatalogData(lines: []);

    expect($catalog->jsonSerialize())->toBe(['lines' => []]);
});

it('serializes an instrument detail with nested position', function () {
    $detail = new InstrumentDetailData(
        id: 7, name: 'ACME', ticker: 'ACM', isin: 'US0000000001', type: InstrumentType::Stock,
        lastPrice: 100.0, lastPriceDate: '2026-07-01',
        position: new PositionData(10.0, 80.0, 1000.0, 200.0, 25.0),
        transactions: [new TransactionLineData('2026-01-01', false, 'Achat', 10.0, 80.0, 0.0, 800.0)],
        sectors: [new SectorWeightData('Technologie', 0.5)],
    );

    $json = $detail->jsonSerialize();

    expect($json['typeLabel'])->toBe('Stock');
    expect($json['position'])->toBeInstanceOf(PositionData::class);
    expect($json['transactions'])->toHaveCount(1);
    expect($json['sectors'][0])->toBeInstanceOf(SectorWeightData::class);
});

it('serializes price history as parallel arrays', function () {
    $history = new PriceHistoryData(labels: ['2026-01-01'], close: [100.0]);

    expect($history->jsonSerialize())->toBe(['labels' => ['2026-01-01'], 'close' => [100.0]]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DatasTest`
Expected: FAIL (classes introuvables)

- [ ] **Step 3: Write the DTOs**

`app/Contexts/InstrumentView/Datas/InstrumentSummaryData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentSummaryData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public InstrumentType $type,
    ) {}
}
```

`app/Contexts/InstrumentView/Datas/InstrumentMetaData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;

readonly class InstrumentMetaData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public ?float $lastPrice,
        public ?string $lastPriceDate,
    ) {}
}
```

`app/Contexts/InstrumentView/Datas/HoldingSnapshotData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

readonly class HoldingSnapshotData
{
    public function __construct(
        public int $assetId,
        public float $quantity,
        public ?float $avgCost,
    ) {}
}
```

`app/Contexts/InstrumentView/Datas/CatalogLineData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

readonly class CatalogLineData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public InstrumentType $type,
        public ?float $lastPrice,
        public bool $held,
        public ?float $quantity,
        public ?float $marketValue,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticker' => $this->ticker,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'lastPrice' => $this->lastPrice,
            'held' => $this->held,
            'quantity' => $this->quantity,
            'marketValue' => $this->marketValue,
        ];
    }
}
```

`app/Contexts/InstrumentView/Datas/InstrumentCatalogData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class InstrumentCatalogData implements JsonSerializable
{
    /** @param list<CatalogLineData> $lines */
    public function __construct(public array $lines) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['lines' => $this->lines];
    }
}
```

`app/Contexts/InstrumentView/Datas/PositionData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class PositionData implements JsonSerializable
{
    public function __construct(
        public float $quantity,
        public ?float $avgCost,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'quantity' => $this->quantity,
            'avgCost' => $this->avgCost,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
```

`app/Contexts/InstrumentView/Datas/TransactionLineData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class TransactionLineData implements JsonSerializable
{
    public function __construct(
        public string $date,
        public bool $isSell,
        public string $typeLabel,
        public float $quantity,
        public float $unitPrice,
        public float $fees,
        public float $total,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'isSell' => $this->isSell,
            'typeLabel' => $this->typeLabel,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'fees' => $this->fees,
            'total' => $this->total,
        ];
    }
}
```

`app/Contexts/InstrumentView/Datas/SectorWeightData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class SectorWeightData implements JsonSerializable
{
    public function __construct(
        public string $label,
        public float $weight,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'label' => $this->label,
            'weight' => $this->weight,
        ];
    }
}
```

`app/Contexts/InstrumentView/Datas/PriceHistoryData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use JsonSerializable;

readonly class PriceHistoryData implements JsonSerializable
{
    /**
     * @param list<string> $labels
     * @param list<float> $close
     */
    public function __construct(
        public array $labels,
        public array $close,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'close' => $this->close,
        ];
    }
}
```

`app/Contexts/InstrumentView/Datas/InstrumentDetailData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Datas;

use App\Contexts\Market\Enums\InstrumentType;
use JsonSerializable;

readonly class InstrumentDetailData implements JsonSerializable
{
    /**
     * @param list<TransactionLineData> $transactions
     * @param list<SectorWeightData> $sectors
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $ticker,
        public ?string $isin,
        public InstrumentType $type,
        public ?float $lastPrice,
        public ?string $lastPriceDate,
        public ?PositionData $position,
        public array $transactions,
        public array $sectors,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'ticker' => $this->ticker,
            'isin' => $this->isin,
            'type' => $this->type->value,
            'typeLabel' => $this->type->getLabel(),
            'lastPrice' => $this->lastPrice,
            'lastPriceDate' => $this->lastPriceDate,
            'position' => $this->position,
            'transactions' => $this->transactions,
            'sectors' => $this->sectors,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DatasTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Datas tests/Unit/InstrumentView/DatasTest.php
git commit -m "feat: DTOs InstrumentView (catalogue + fiche)"
```

---

## Task 2: Port `MarketDataPort` + adapter `MarketData`

**Files:**
- Create: `app/Contexts/InstrumentView/Ports/MarketDataPort.php`, `app/Contexts/InstrumentView/Infrastructure/MarketData.php`
- Test: `tests/Unit/InstrumentView/MarketDataTest.php`

**Interfaces:**
- Consumes: `Market\Contracts\PriceRepositoryContract` (`latestForAsset`, `forAssetSince`), `Market\Models\{Instrument,SectorAllocation}`, DTOs Task 1.
- Produces:
  - `MarketDataPort::listInstruments(): array` → `list<InstrumentSummaryData>`
  - `MarketDataPort::findInstrument(int $id): ?InstrumentMetaData`
  - `MarketDataPort::latestPrice(int $id): ?float`
  - `MarketDataPort::priceHistory(int $id, Carbon $since): PriceHistoryData`
  - `MarketDataPort::sectors(int $id): array` → `list<SectorWeightData>`

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/MarketDataTest.php`
```php
<?php

use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->market = app(MarketDataPort::class);
});

it('lists all market instruments as summaries', function () {
    Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => 'World ETF', 'ticker' => 'IWDA']);

    $summaries = $this->market->listInstruments();

    expect($summaries)->toHaveCount(1);
    expect($summaries[0]->name)->toBe('World ETF');
    expect($summaries[0]->type)->toBe(InstrumentType::ETF);
});

it('finds an instrument with its latest price', function () {
    $asset = Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM', 'isin' => 'US0000000001']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-06-01', 'close' => 90]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $meta = $this->market->findInstrument($asset->id);

    expect($meta->name)->toBe('ACME');
    expect($meta->isin)->toBe('US0000000001');
    expect($meta->lastPrice)->toBe(120.0);
    expect($meta->lastPriceDate)->toBe('2026-07-01');
});

it('returns null meta for an unknown instrument', function () {
    expect($this->market->findInstrument(999))->toBeNull();
});

it('returns the latest price for an instrument', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 55.5]);

    expect($this->market->latestPrice($asset->id))->toBe(55.5);
    expect($this->market->latestPrice(999))->toBeNull();
});

it('builds price history since a date as parallel arrays', function () {
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-02-01', 'close' => 20]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2025-01-01', 'close' => 5]);

    $history = $this->market->priceHistory($asset->id, Carbon::parse('2026-01-01'));

    expect($history->labels)->toBe(['2026-01-01', '2026-02-01']);
    expect($history->close)->toBe([10.0, 20.0]);
});

it('maps sector allocations to labelled weights', function () {
    $asset = Instrument::factory()->create();
    SectorAllocation::factory()->create(['asset_id' => $asset->id, 'sector' => Sector::Technology, 'weight' => 0.6]);

    $sectors = $this->market->sectors($asset->id);

    expect($sectors)->toHaveCount(1);
    expect($sectors[0]->label)->toBe('Technologie');
    expect($sectors[0]->weight)->toBe(0.6);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MarketDataTest`
Expected: FAIL (`MarketDataPort` introuvable / binding manquant)

- [ ] **Step 3: Write the port and adapter**

`app/Contexts/InstrumentView/Ports/MarketDataPort.php`
```php
<?php

namespace App\Contexts\InstrumentView\Ports;

use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use Illuminate\Support\Carbon;

interface MarketDataPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\InstrumentSummaryData> */
    public function listInstruments(): array;

    public function findInstrument(int $id): ?InstrumentMetaData;

    public function latestPrice(int $id): ?float;

    public function priceHistory(int $id, Carbon $since): PriceHistoryData;

    /** @return list<\App\Contexts\InstrumentView\Datas\SectorWeightData> */
    public function sectors(int $id): array;
}
```

`app/Contexts/InstrumentView/Infrastructure/MarketData.php`
```php
<?php

namespace App\Contexts\InstrumentView\Infrastructure;

use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Datas\PriceHistoryData;
use App\Contexts\InstrumentView\Datas\SectorWeightData;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use Illuminate\Support\Carbon;

class MarketData implements MarketDataPort
{
    public function __construct(private PriceRepositoryContract $prices) {}

    /** @return list<InstrumentSummaryData> */
    public function listInstruments(): array
    {
        return Instrument::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Instrument $instrument) => new InstrumentSummaryData(
                id: $instrument->id,
                name: (string) $instrument->name,
                ticker: $instrument->ticker,
                type: $instrument->type,
            ))
            ->values()
            ->all();
    }

    public function findInstrument(int $id): ?InstrumentMetaData
    {
        $instrument = Instrument::query()->find($id);

        if ($instrument === null) {
            return null;
        }

        $latest = $this->prices->latestForAsset($id);

        return new InstrumentMetaData(
            id: $instrument->id,
            name: (string) $instrument->name,
            ticker: $instrument->ticker,
            isin: $instrument->isin,
            type: $instrument->type,
            lastPrice: $latest !== null ? (float) $latest->close : null,
            lastPriceDate: $latest !== null ? $latest->date->format('Y-m-d') : null,
        );
    }

    public function latestPrice(int $id): ?float
    {
        $latest = $this->prices->latestForAsset($id);

        return $latest !== null ? (float) $latest->close : null;
    }

    public function priceHistory(int $id, Carbon $since): PriceHistoryData
    {
        $prices = $this->prices->forAssetSince($id, $since);

        return new PriceHistoryData(
            labels: $prices->map(fn (Price $price) => $price->date->format('Y-m-d'))->values()->all(),
            close: $prices->map(fn (Price $price) => (float) $price->close)->values()->all(),
        );
    }

    /** @return list<SectorWeightData> */
    public function sectors(int $id): array
    {
        return SectorAllocation::query()
            ->where('asset_id', $id)
            ->orderByDesc('weight')
            ->get()
            ->map(fn (SectorAllocation $allocation) => new SectorWeightData(
                label: $allocation->sector->getLabel(),
                weight: (float) $allocation->weight,
            ))
            ->values()
            ->all();
    }
}
```

**Note :** le binding `MarketDataPort → MarketData` est créé en Task 5. Pour que ce test passe avant, ajouter le binding maintenant dans `AppServiceProvider::register()` via `InstrumentViewProvider` (Task 5) OU lier temporairement. **Décision :** implémenter Task 5 (provider) juste après l'écriture de l'adapter et avant de lancer ce test — voir Step 4.

- [ ] **Step 4: Créer le provider minimal pour binder ce port**

Créer `app/Contexts/InstrumentView/InstrumentViewProvider.php` avec le seul binding `MarketDataPort` pour l'instant (les 2 autres ports seront ajoutés à la même méthode en Tasks 3-4) :
```php
<?php

namespace App\Contexts\InstrumentView;

use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\InstrumentView\Ports\TransactionsPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class InstrumentViewProvider extends ServiceProvider
{
    /**
     * @param class-string<MarketDataPort> $marketData
     * @param class-string<HoldingsPort> $holdings
     * @param class-string<TransactionsPort> $transactions
     */
    public static function registers(
        Application $app,
        string $marketData,
        string $holdings,
        string $transactions,
    ): void {
        $app->bind(MarketDataPort::class, $marketData);
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(TransactionsPort::class, $transactions);
    }
}
```

Ce provider référence `HoldingsPort` et `TransactionsPort` qui n'existent pas encore → créer d'abord des **stubs d'interface vides** pour ne pas casser l'autoload, puis les compléter en Tasks 3-4 :

`app/Contexts/InstrumentView/Ports/HoldingsPort.php`
```php
<?php

namespace App\Contexts\InstrumentView\Ports;

interface HoldingsPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\HoldingSnapshotData> */
    public function holdingsFor(int $userId): array;

    public function holdingFor(int $userId, int $assetId): ?\App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
}
```

`app/Contexts/InstrumentView/Ports/TransactionsPort.php`
```php
<?php

namespace App\Contexts\InstrumentView\Ports;

interface TransactionsPort
{
    /** @return list<\App\Contexts\InstrumentView\Datas\TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array;
}
```

Créer aussi les adapters stubs pour que le provider puisse binder (implémentations réelles Tasks 3-4) — **non**, préférable : n'enregistrer le provider dans `AppServiceProvider` qu'en Task 5 une fois les 3 adapters écrits. Pour faire passer le test MarketData **maintenant**, ajouter dans `AppServiceProvider::register()` un binding direct temporaire :
```php
$this->app->bind(
    \App\Contexts\InstrumentView\Ports\MarketDataPort::class,
    \App\Contexts\InstrumentView\Infrastructure\MarketData::class,
);
```
Ce binding temporaire sera remplacé par l'appel `InstrumentViewProvider::registers(...)` en Task 5.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=MarketDataTest`
Expected: PASS (6 tests)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView app/Providers/AppServiceProvider.php tests/Unit/InstrumentView/MarketDataTest.php
git commit -m "feat: MarketDataPort + adapter (liste/meta/prix/secteurs)"
```

---

## Task 3: Adapter `PortfolioHoldings`

**Files:**
- Create: `app/Contexts/InstrumentView/Infrastructure/PortfolioHoldings.php`
- Test: `tests/Unit/InstrumentView/PortfolioHoldingsTest.php`
- (`HoldingsPort` interface déjà créée en Task 2)

**Interfaces:**
- Consumes: `Portfolio\Models\Holding`, `HoldingSnapshotData`.
- Produces: `PortfolioHoldings implements HoldingsPort` (`holdingsFor`, `holdingFor`).

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/PortfolioHoldingsTest.php`
```php
<?php

use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->adapter = new PortfolioHoldings();
});

it('returns holdings for a user only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Holding::factory()->create(['user_id' => $other->id, 'wallet_id' => Wallet::factory()->for($other)->create()->id, 'asset_id' => $asset->id, 'quantity' => 5, 'avg_cost' => 50]);

    $holdings = $this->adapter->holdingsFor($user->id);

    expect($holdings)->toHaveCount(1);
    expect($holdings[0]->assetId)->toBe($asset->id);
    expect($holdings[0]->quantity)->toBe(10.0);
    expect($holdings[0]->avgCost)->toBe(80.0);
});

it('returns a single holding for a user and asset', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 3, 'avg_cost' => 40]);

    expect($this->adapter->holdingFor($user->id, $asset->id)->quantity)->toBe(3.0);
    expect($this->adapter->holdingFor($user->id, 999))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PortfolioHoldingsTest`
Expected: FAIL (`PortfolioHoldings` introuvable)

- [ ] **Step 3: Write the adapter**

`app/Contexts/InstrumentView/Infrastructure/PortfolioHoldings.php`
```php
<?php

namespace App\Contexts\InstrumentView\Infrastructure;

use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\Portfolio\Models\Holding;

class PortfolioHoldings implements HoldingsPort
{
    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array
    {
        return Holding::query()
            ->where('user_id', $userId)
            ->get()
            ->map(fn (Holding $holding) => $this->toSnapshot($holding))
            ->values()
            ->all();
    }

    public function holdingFor(int $userId, int $assetId): ?HoldingSnapshotData
    {
        $holding = Holding::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->first();

        return $holding !== null ? $this->toSnapshot($holding) : null;
    }

    private function toSnapshot(Holding $holding): HoldingSnapshotData
    {
        return new HoldingSnapshotData(
            assetId: (int) $holding->asset_id,
            quantity: (float) $holding->quantity,
            avgCost: $holding->avg_cost !== null ? (float) $holding->avg_cost : null,
        );
    }
}
```

**Note :** `Holding` a une PK composite ; `holdingFor` utilise `->where(...)->first()` (pas `find`), donc pas de casse (lecture seule).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=PortfolioHoldingsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Infrastructure/PortfolioHoldings.php tests/Unit/InstrumentView/PortfolioHoldingsTest.php
git commit -m "feat: adapter PortfolioHoldings (HoldingsPort)"
```

---

## Task 4: Adapter `PortfolioTransactions`

**Files:**
- Create: `app/Contexts/InstrumentView/Infrastructure/PortfolioTransactions.php`
- Test: `tests/Unit/InstrumentView/PortfolioTransactionsTest.php`
- (`TransactionsPort` interface déjà créée en Task 2)

**Interfaces:**
- Consumes: `Portfolio\Models\Transaction`, `Portfolio\Enums\TransactionType`, `TransactionLineData`.
- Produces: `PortfolioTransactions implements TransactionsPort` (`transactionsFor`). `total = quantity * unitPrice`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/PortfolioTransactionsTest.php`
```php
<?php

use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

beforeEach(function () {
    $this->adapter = new PortfolioTransactions();
});

it('returns transactions for a user and asset, newest first', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80, 'fees' => 1]);
    Transaction::factory()->sell()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-03-01', 'quantity' => 4, 'unit_price' => 100, 'fees' => 2]);

    $lines = $this->adapter->transactionsFor($user->id, $asset->id);

    expect($lines)->toHaveCount(2);
    expect($lines[0]->date)->toBe('2026-03-01');
    expect($lines[0]->isSell)->toBeTrue();
    expect($lines[0]->typeLabel)->toBe('Vente');
    expect($lines[0]->total)->toBe(400.0);
    expect($lines[1]->date)->toBe('2026-01-01');
    expect($lines[1]->typeLabel)->toBe('Achat');
    expect($lines[1]->total)->toBe(800.0);
});

it('excludes other users and other assets', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    $otherAsset = Instrument::factory()->create();
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $otherAsset->id]);

    expect($this->adapter->transactionsFor($user->id, $asset->id))->toBeEmpty();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PortfolioTransactionsTest`
Expected: FAIL (`PortfolioTransactions` introuvable)

- [ ] **Step 3: Write the adapter**

`app/Contexts/InstrumentView/Infrastructure/PortfolioTransactions.php`
```php
<?php

namespace App\Contexts\InstrumentView\Infrastructure;

use App\Contexts\InstrumentView\Datas\TransactionLineData;
use App\Contexts\InstrumentView\Ports\TransactionsPort;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Transaction;

class PortfolioTransactions implements TransactionsPort
{
    /** @return list<TransactionLineData> */
    public function transactionsFor(int $userId, int $assetId): array
    {
        return Transaction::query()
            ->where('user_id', $userId)
            ->where('asset_id', $assetId)
            ->orderByDesc('date')
            ->get()
            ->map(function (Transaction $transaction): TransactionLineData {
                $quantity = (float) $transaction->quantity;
                $unitPrice = (float) $transaction->unit_price;

                return new TransactionLineData(
                    date: $transaction->date->format('Y-m-d'),
                    isSell: $transaction->type === TransactionType::Sell,
                    typeLabel: $transaction->type->getLabel(),
                    quantity: $quantity,
                    unitPrice: $unitPrice,
                    fees: (float) $transaction->fees,
                    total: $quantity * $unitPrice,
                );
            })
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=PortfolioTransactionsTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Infrastructure/PortfolioTransactions.php tests/Unit/InstrumentView/PortfolioTransactionsTest.php
git commit -m "feat: adapter PortfolioTransactions (TransactionsPort)"
```

---

## Task 5: Câbler `InstrumentViewProvider` dans `AppServiceProvider`

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/InstrumentView/ProviderBindingTest.php`

**Interfaces:**
- Consumes: `InstrumentViewProvider::registers`, les 3 ports + 3 adapters.
- Produces: les 3 ports résolvent vers leurs adapters via le container.

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/ProviderBindingTest.php`
```php
<?php

use App\Contexts\InstrumentView\Infrastructure\MarketData;
use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\InstrumentView\Ports\TransactionsPort;

it('binds each InstrumentView port to its adapter', function () {
    expect(app(MarketDataPort::class))->toBeInstanceOf(MarketData::class);
    expect(app(HoldingsPort::class))->toBeInstanceOf(PortfolioHoldings::class);
    expect(app(TransactionsPort::class))->toBeInstanceOf(PortfolioTransactions::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ProviderBindingTest`
Expected: FAIL (`HoldingsPort`/`TransactionsPort` non liés)

- [ ] **Step 3: Remplacer le binding temporaire par l'appel provider**

Dans `app/Providers/AppServiceProvider.php` : supprimer le binding direct temporaire de `MarketDataPort` (Task 2), importer `InstrumentViewProvider` + les 3 adapters, et ajouter dans `register()` après `ValuationProvider::registers(...)` :
```php
InstrumentViewProvider::registers(
    app: $this->app,
    marketData: MarketData::class,
    holdings: PortfolioHoldings::class,
    transactions: PortfolioTransactions::class,
);
```
Imports à ajouter en tête :
```php
use App\Contexts\InstrumentView\Infrastructure\MarketData;
use App\Contexts\InstrumentView\Infrastructure\PortfolioHoldings;
use App\Contexts\InstrumentView\Infrastructure\PortfolioTransactions;
use App\Contexts\InstrumentView\InstrumentViewProvider;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter="ProviderBindingTest|MarketDataTest"`
Expected: PASS (les deux fichiers)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Providers/AppServiceProvider.php app/Contexts/InstrumentView/InstrumentViewProvider.php tests/Unit/InstrumentView/ProviderBindingTest.php
git commit -m "feat: bind InstrumentView ports via InstrumentViewProvider"
```

---

## Task 6: Action `GetInstrumentCatalog`

**Files:**
- Create: `app/Contexts/InstrumentView/Actions/GetInstrumentCatalog.php`
- Test: `tests/Unit/InstrumentView/GetInstrumentCatalogTest.php`

**Interfaces:**
- Consumes: `MarketDataPort` (`listInstruments`, `latestPrice`), `HoldingsPort` (`holdingsFor`), DTOs.
- Produces: `GetInstrumentCatalog::__invoke(int $userId): InstrumentCatalogData`. Pour chaque instrument : `held` = présent dans holdings ; `quantity`/`marketValue` peuplés si détenu ET prix ; `marketValue = quantity * lastPrice`.

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/GetInstrumentCatalogTest.php`
```php
<?php

use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('lists instruments and flags the ones held with value', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co']);
    $notHeld = Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $held->id, 'quantity' => 10, 'avg_cost' => 80]);

    $catalog = app(GetInstrumentCatalog::class)($user->id);

    $lines = collect($catalog->lines)->keyBy('id');
    expect($lines[$held->id]->held)->toBeTrue();
    expect($lines[$held->id]->lastPrice)->toBe(100.0);
    expect($lines[$held->id]->marketValue)->toBe(1000.0);
    expect($lines[$notHeld->id]->held)->toBeFalse();
    expect($lines[$notHeld->id]->marketValue)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetInstrumentCatalogTest`
Expected: FAIL (`GetInstrumentCatalog` introuvable)

- [ ] **Step 3: Write the action**

`app/Contexts/InstrumentView/Actions/GetInstrumentCatalog.php`
```php
<?php

namespace App\Contexts\InstrumentView\Actions;

use App\Contexts\InstrumentView\Datas\CatalogLineData;
use App\Contexts\InstrumentView\Datas\InstrumentCatalogData;
use App\Contexts\InstrumentView\Datas\InstrumentSummaryData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;

class GetInstrumentCatalog
{
    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
    ) {}

    public function __invoke(int $userId): InstrumentCatalogData
    {
        $heldByAsset = [];
        foreach ($this->holdings->holdingsFor($userId) as $snapshot) {
            $heldByAsset[$snapshot->assetId] = $snapshot;
        }

        $lines = [];
        foreach ($this->market->listInstruments() as $summary) {
            $lines[] = $this->toLine($summary, $heldByAsset[$summary->id] ?? null);
        }

        return new InstrumentCatalogData(lines: $lines);
    }

    private function toLine(InstrumentSummaryData $summary, ?object $holding): CatalogLineData
    {
        $lastPrice = $this->market->latestPrice($summary->id);
        $quantity = $holding?->quantity;
        $marketValue = ($quantity !== null && $lastPrice !== null) ? $quantity * $lastPrice : null;

        return new CatalogLineData(
            id: $summary->id,
            name: $summary->name,
            ticker: $summary->ticker,
            type: $summary->type,
            lastPrice: $lastPrice,
            held: $holding !== null,
            quantity: $quantity,
            marketValue: $marketValue,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GetInstrumentCatalogTest`
Expected: PASS

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Actions/GetInstrumentCatalog.php tests/Unit/InstrumentView/GetInstrumentCatalogTest.php
git commit -m "feat: action GetInstrumentCatalog (liste + flag détenu)"
```

---

## Task 7: Action `GetInstrumentDetail`

**Files:**
- Create: `app/Contexts/InstrumentView/Actions/GetInstrumentDetail.php`
- Test: `tests/Unit/InstrumentView/GetInstrumentDetailTest.php`

**Interfaces:**
- Consumes: `MarketDataPort` (`findInstrument`, `sectors`), `HoldingsPort` (`holdingFor`), `TransactionsPort` (`transactionsFor`), DTOs.
- Produces: `GetInstrumentDetail::__invoke(int $userId, int $instrumentId): ?InstrumentDetailData`. `null` si instrument inconnu. Position non-null seulement si détenu ET `lastPrice` non-null ; `gain = marketValue - quantity*avgCost` ; `gainPct = gain / cost * 100` si `cost > 0`, sinon null.

- [ ] **Step 1: Write the failing test**

`tests/Unit/InstrumentView/GetInstrumentDetailTest.php`
```php
<?php

use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('returns null for an unknown instrument', function () {
    $user = User::factory()->create();

    expect(app(GetInstrumentDetail::class)($user->id, 999))->toBeNull();
});

it('composes a held instrument with position, gain, transactions and sectors', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME', 'ticker' => 'ACM']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);
    SectorAllocation::factory()->create(['asset_id' => $asset->id, 'sector' => Sector::Technology, 'weight' => 1]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->name)->toBe('ACME');
    expect($detail->lastPrice)->toBe(100.0);
    expect($detail->position->marketValue)->toBe(1000.0);
    expect($detail->position->gain)->toBe(200.0);
    expect($detail->position->gainPct)->toBe(25.0);
    expect($detail->transactions)->toHaveCount(1);
    expect($detail->sectors[0]->label)->toBe('Technologie');
});

it('omits the position when the instrument is not held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->position)->toBeNull();
});

it('omits the position when no price is available', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);

    $detail = app(GetInstrumentDetail::class)($user->id, $asset->id);

    expect($detail->position)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetInstrumentDetailTest`
Expected: FAIL (`GetInstrumentDetail` introuvable)

- [ ] **Step 3: Write the action**

`app/Contexts/InstrumentView/Actions/GetInstrumentDetail.php`
```php
<?php

namespace App\Contexts\InstrumentView\Actions;

use App\Contexts\InstrumentView\Datas\HoldingSnapshotData;
use App\Contexts\InstrumentView\Datas\InstrumentDetailData;
use App\Contexts\InstrumentView\Datas\InstrumentMetaData;
use App\Contexts\InstrumentView\Datas\PositionData;
use App\Contexts\InstrumentView\Ports\HoldingsPort;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\InstrumentView\Ports\TransactionsPort;

class GetInstrumentDetail
{
    public function __construct(
        private MarketDataPort $market,
        private HoldingsPort $holdings,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(int $userId, int $instrumentId): ?InstrumentDetailData
    {
        $meta = $this->market->findInstrument($instrumentId);

        if ($meta === null) {
            return null;
        }

        $holding = $this->holdings->holdingFor($userId, $instrumentId);

        return new InstrumentDetailData(
            id: $meta->id,
            name: $meta->name,
            ticker: $meta->ticker,
            isin: $meta->isin,
            type: $meta->type,
            lastPrice: $meta->lastPrice,
            lastPriceDate: $meta->lastPriceDate,
            position: $this->buildPosition($holding, $meta),
            transactions: $this->transactions->transactionsFor($userId, $instrumentId),
            sectors: $this->market->sectors($instrumentId),
        );
    }

    private function buildPosition(?HoldingSnapshotData $holding, InstrumentMetaData $meta): ?PositionData
    {
        if ($holding === null || $meta->lastPrice === null) {
            return null;
        }

        $marketValue = $holding->quantity * $meta->lastPrice;
        $cost = $holding->avgCost !== null ? $holding->quantity * $holding->avgCost : null;
        $gain = $cost !== null ? $marketValue - $cost : null;
        $gainPct = ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;

        return new PositionData(
            quantity: $holding->quantity,
            avgCost: $holding->avgCost,
            marketValue: $marketValue,
            gain: $gain,
            gainPct: $gainPct,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GetInstrumentDetailTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Actions/GetInstrumentDetail.php tests/Unit/InstrumentView/GetInstrumentDetailTest.php
git commit -m "feat: action GetInstrumentDetail (position/gain/transactions/secteurs)"
```

---

## Task 8: Controllers + routes + feature tests

**Files:**
- Create: `app/Contexts/InstrumentView/Http/InstrumentCatalogController.php`, `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/InstrumentCatalogPageTest.php`, `tests/Feature/InstrumentDetailPageTest.php`

**Interfaces:**
- Consumes: `GetInstrumentCatalog`, `GetInstrumentDetail`, `MarketDataPort` (priceHistory deferred), `Identity\Models\User`.
- Produces:
  - `GET /instruments` (`instruments.index`) → `Inertia::render('Instruments/Index', ['catalog' => InstrumentCatalogData])`
  - `GET /instruments/{id}` (`instruments.show`) → `Inertia::render('Instruments/Show', ['instrument' => InstrumentDetailData, 'priceHistory' => Inertia::defer(PriceHistoryData)])` ; 404 si inconnu.

- [ ] **Step 1: Write the failing feature tests**

`tests/Feature/InstrumentCatalogPageTest.php`
```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the instrument catalogue with a held flag', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $held = Instrument::factory()->create(['name' => 'Held Co']);
    Instrument::factory()->create(['name' => 'Absent Co']);
    Price::factory()->create(['asset_id' => $held->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $held->id, 'quantity' => 10, 'avg_cost' => 80]);

    $this->get('/instruments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Index')
            ->has('catalog.lines', 2)
        );
});
```

`tests/Feature/InstrumentDetailPageTest.php`
```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

it('renders a held instrument sheet with its position and transactions', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);
    Transaction::factory()->buy()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'date' => '2026-01-01', 'quantity' => 10, 'unit_price' => 80]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->where('instrument.name', 'ACME')
            ->where('instrument.position.marketValue', fn ($v) => (float) $v === 1000.0)
            ->has('instrument.transactions', 1)
            ->missing('priceHistory')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('priceHistory.labels', 1)
            )
        );
});

it('hides the position when the instrument is not held', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->where('instrument.position', null)
        );
});

it('returns 404 for an unknown instrument', function () {
    User::query()->delete();
    User::factory()->create();

    $this->get('/instruments/999')->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="InstrumentCatalogPageTest|InstrumentDetailPageTest"`
Expected: FAIL (routes 404 / composant absent)

- [ ] **Step 3: Write the controllers**

`app/Contexts/InstrumentView/Http/InstrumentCatalogController.php`
```php
<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentCatalogController
{
    public function __construct(private GetInstrumentCatalog $getCatalog) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        return Inertia::render('Instruments/Index', [
            'catalog' => ($this->getCatalog)($user?->id ?? 0),
        ]);
    }
}
```

`app/Contexts/InstrumentView/Http/InstrumentDetailController.php`
```php
<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentDetailController
{
    public function __construct(
        private GetInstrumentDetail $getDetail,
        private MarketDataPort $market,
    ) {}

    public function __invoke(int $id): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;

        $detail = ($this->getDetail)($userId, $id);

        if ($detail === null) {
            abort(404);
        }

        return Inertia::render('Instruments/Show', [
            'instrument' => $detail,
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
        ]);
    }
}
```

- [ ] **Step 4: Add the routes**

Dans `routes/web.php`, ajouter les imports en tête :
```php
use App\Contexts\InstrumentView\Http\InstrumentCatalogController;
use App\Contexts\InstrumentView\Http\InstrumentDetailController;
```
et les routes après la route `/dashboard` :
```php
Route::get('/instruments', InstrumentCatalogController::class)->name('instruments.index');
Route::get('/instruments/{id}', InstrumentDetailController::class)->name('instruments.show');
```

- [ ] **Step 5: Créer des pages Vue placeholder pour que le rendu Inertia n'échoue pas**

Créer un stub minimal `resources/js/Pages/Instruments/Index.vue` (remplacé en Task 9) :
```vue
<script setup lang="ts">
defineProps<{ catalog: { lines: unknown[] } }>();
</script>

<template>
    <div>Catalogue</div>
</template>
```
Créer un stub minimal `resources/js/Pages/Instruments/Show.vue` (remplacé en Task 10) :
```vue
<script setup lang="ts">
defineProps<{ instrument: Record<string, unknown> }>();
</script>

<template>
    <div>Fiche</div>
</template>
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact --filter="InstrumentCatalogPageTest|InstrumentDetailPageTest"`
Expected: PASS (4 tests)

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/InstrumentView/Http routes/web.php resources/js/Pages/Instruments tests/Feature/InstrumentCatalogPageTest.php tests/Feature/InstrumentDetailPageTest.php
git commit -m "feat: routes + controllers InstrumentView (catalogue + fiche, 404)"
```

---

## Task 9: Page catalogue `Instruments/Index.vue`

**Files:**
- Modify (remplace le stub): `resources/js/Pages/Instruments/Index.vue`

**Interfaces:**
- Consumes: prop `catalog: { lines: CatalogLine[] }` où `CatalogLine = { id, name, ticker, type, typeLabel, lastPrice, held, quantity, marketValue }`.
- Produces: table de tous les instruments ; ligne = `<Link href="/instruments/{id}">`.

- [ ] **Step 1: Écrire la page complète**

`resources/js/Pages/Instruments/Index.vue`
```vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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

interface CatalogLine {
    id: number;
    name: string;
    ticker: string | null;
    type: string;
    typeLabel: string;
    lastPrice: number | null;
    held: boolean;
    quantity: number | null;
    marketValue: number | null;
}

defineProps<{ catalog: { lines: CatalogLine[] } }>();

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
</script>

<template>
    <Head title="Instruments" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header>
                <h1 class="text-2xl font-semibold">Instruments</h1>
                <p class="text-sm text-muted-foreground">Catalogue de tous les instruments</p>
            </header>

            <Card class="border-0 bg-transparent shadow-none rounded-none">
                <CardHeader>
                    <CardTitle>Tous les instruments</CardTitle>
                    <CardDescription>Cliquez une ligne pour ouvrir la fiche</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="catalog.lines.length">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nom</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Dernier prix</TableHead>
                                <TableHead class="text-right">Détenu</TableHead>
                                <TableHead class="text-right">Valeur</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="line in catalog.lines"
                                :key="line.id"
                                class="cursor-pointer hover:bg-muted/50"
                            >
                                <TableCell class="font-medium">
                                    <Link :href="`/instruments/${line.id}`" class="block">
                                        {{ line.name }}
                                        <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                    </Link>
                                </TableCell>
                                <TableCell>{{ line.typeLabel }}</TableCell>
                                <TableCell class="text-right">
                                    <span v-if="line.lastPrice === null" class="text-muted-foreground">N/D</span>
                                    <span v-else>{{ eur(line.lastPrice) }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span v-if="line.held" class="text-emerald-600 dark:text-emerald-400">Oui</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-right">{{ eur(line.marketValue) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="py-8 text-center text-sm text-muted-foreground">
                        Aucun instrument connu.
                    </p>
                </CardContent>
            </Card>
        </div>
    </main>
</template>
```

- [ ] **Step 2: Typecheck**

Run: `bun run typecheck`
Expected: aucune erreur sur `Index.vue`

- [ ] **Step 3: Build**

Run: `bun run build`
Expected: build OK

- [ ] **Step 4: Re-run le feature test catalogue**

Run: `php artisan test --compact --filter=InstrumentCatalogPageTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Instruments/Index.vue
git commit -m "feat: page catalogue Instruments/Index"
```

---

## Task 10: Page fiche `Instruments/Show.vue`

**Files:**
- Modify (remplace le stub): `resources/js/Pages/Instruments/Show.vue`

**Interfaces:**
- Consumes:
  - prop `instrument`: `{ id, name, ticker, isin, type, typeLabel, lastPrice, lastPriceDate, position: Position | null, transactions: TransactionLine[], sectors: SectorWeight[] }`
  - prop deferred `priceHistory?`: `{ labels: string[], close: number[] }`
  - `Position = { quantity, avgCost, marketValue, gain, gainPct }`
  - `TransactionLine = { date, isSell, typeLabel, quantity, unitPrice, fees, total }`
  - `SectorWeight = { label, weight }`
- Produces: fiche complète (header, position, courbe deferred, transactions, secteurs).

- [ ] **Step 1: Écrire la page complète**

`resources/js/Pages/Instruments/Show.vue`
```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
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

interface Position {
    quantity: number;
    avgCost: number | null;
    marketValue: number | null;
    gain: number | null;
    gainPct: number | null;
}

interface TransactionLine {
    date: string;
    isSell: boolean;
    typeLabel: string;
    quantity: number;
    unitPrice: number;
    fees: number;
    total: number;
}

interface SectorWeight {
    label: string;
    weight: number;
}

interface Instrument {
    id: number;
    name: string;
    ticker: string | null;
    isin: string | null;
    type: string;
    typeLabel: string;
    lastPrice: number | null;
    lastPriceDate: string | null;
    position: Position | null;
    transactions: TransactionLine[];
    sectors: SectorWeight[];
}

interface PriceHistory {
    labels: string[];
    close: number[];
}

const props = defineProps<{ instrument: Instrument; priceHistory?: PriceHistory }>();

const flatCard = 'border-0 bg-transparent shadow-none rounded-none';

const eur = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 2 });

const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';

const hasPriceHistory = computed<boolean>(() => (props.priceHistory?.labels.length ?? 0) > 0);

const priceChartSeries = computed(() => [
    { name: 'Cours', data: props.priceHistory?.close ?? [] },
]);

const priceChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#4f46e5'],
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0 } },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.priceHistory?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => eur(value) } },
    tooltip: { y: { formatter: (value: number): string => eur(value) } },
}));
</script>

<template>
    <Head :title="props.instrument.name" />

    <main class="min-h-screen bg-background p-6 text-foreground">
        <div class="mx-auto flex max-w-6xl flex-col gap-6">
            <header class="flex flex-col gap-1">
                <Link href="/instruments" class="text-sm text-muted-foreground hover:underline">← Instruments</Link>
                <h1 class="text-2xl font-semibold">
                    {{ props.instrument.name }}
                    <span v-if="props.instrument.ticker" class="text-muted-foreground">({{ props.instrument.ticker }})</span>
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ props.instrument.typeLabel }}
                    <span v-if="props.instrument.isin"> · ISIN {{ props.instrument.isin }}</span>
                    <span v-if="props.instrument.lastPrice !== null">
                        · {{ eur(props.instrument.lastPrice) }}
                        <span v-if="props.instrument.lastPriceDate" class="text-xs">au {{ props.instrument.lastPriceDate }}</span>
                    </span>
                </p>
            </header>

            <section v-if="props.instrument.position" class="grid gap-4 sm:grid-cols-4">
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Quantité</CardDescription>
                        <CardTitle class="text-2xl">{{ props.instrument.position.quantity }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>PRU</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(props.instrument.position.avgCost) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Valeur</CardDescription>
                        <CardTitle class="text-2xl">{{ eur(props.instrument.position.marketValue) }}</CardTitle>
                    </CardHeader>
                </Card>
                <Card :class="flatCard">
                    <CardHeader>
                        <CardDescription>Gain / perte</CardDescription>
                        <CardTitle class="text-2xl" :class="gainClass(props.instrument.position.gain)">
                            {{ eur(props.instrument.position.gain) }}
                            <span class="text-sm">({{ pct(props.instrument.position.gainPct) }})</span>
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            <Card :class="flatCard">
                <CardHeader>
                    <CardTitle>Cours</CardTitle>
                    <CardDescription>Historique sur 12 mois</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="priceHistory">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasPriceHistory"
                            type="area"
                            height="300"
                            :options="priceChartOptions"
                            :series="priceChartSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas d'historique de prix disponible.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>

            <section class="grid gap-4 lg:grid-cols-3">
                <Card :class="[flatCard, 'lg:col-span-2']">
                    <CardHeader>
                        <CardTitle>Transactions</CardTitle>
                        <CardDescription>Mes mouvements sur cet actif</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="props.instrument.transactions.length">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Sens</TableHead>
                                    <TableHead class="text-right">Quantité</TableHead>
                                    <TableHead class="text-right">Prix unit.</TableHead>
                                    <TableHead class="text-right">Frais</TableHead>
                                    <TableHead class="text-right">Total</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(line, index) in props.instrument.transactions" :key="index">
                                    <TableCell>{{ line.date }}</TableCell>
                                    <TableCell :class="line.isSell ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'">
                                        {{ line.typeLabel }}
                                    </TableCell>
                                    <TableCell class="text-right">{{ line.quantity }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.unitPrice) }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.fees) }}</TableCell>
                                    <TableCell class="text-right">{{ eur(line.total) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Aucune transaction sur cet actif.
                        </p>
                    </CardContent>
                </Card>

                <Card v-if="props.instrument.sectors.length" :class="flatCard">
                    <CardHeader>
                        <CardTitle>Secteurs</CardTitle>
                        <CardDescription>Répartition sectorielle</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="flex flex-col gap-2">
                            <li v-for="(sector, index) in props.instrument.sectors" :key="index" class="flex justify-between text-sm">
                                <span>{{ sector.label }}</span>
                                <span class="text-muted-foreground">{{ (sector.weight * 100).toFixed(1) }} %</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </section>
        </div>
    </main>
</template>
```

- [ ] **Step 2: Typecheck**

Run: `bun run typecheck`
Expected: aucune erreur sur `Show.vue`

- [ ] **Step 3: Build**

Run: `bun run build`
Expected: build OK

- [ ] **Step 4: Re-run le feature test fiche**

Run: `php artisan test --compact --filter=InstrumentDetailPageTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Instruments/Show.vue
git commit -m "feat: page fiche Instruments/Show (position, cours, transactions, secteurs)"
```

---

## Task 11: Lier le Dashboard aux fiches

**Files:**
- Modify: `app/Contexts/Portfolio/Datas/HoldingLineData.php`, `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`, `resources/js/Pages/Dashboard.vue`, `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Consumes: `HoldingLineData` gagne `int $assetId` (premier param), exposé en JSON sous `assetId`.
- Produces: chaque ligne Positions du Dashboard devient `<Link href="/instruments/{assetId}">`.

- [ ] **Step 1: Mettre à jour le feature test Dashboard existant**

Dans `tests/Feature/DashboardPageTest.php`, test « renders the Dashboard with the user portfolio overview », ajouter après l'assertion `overview.holdings.0.assetName` :
```php
            ->where('overview.holdings.0.assetId', $asset->id)
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: FAIL (`assetId` manquant dans le JSON)

- [ ] **Step 3: Ajouter `assetId` au DTO**

Dans `app/Contexts/Portfolio/Datas/HoldingLineData.php`, ajouter le paramètre en tête du constructeur :
```php
    public function __construct(
        public int $assetId,
        public string $assetName,
```
et dans `jsonSerialize()`, ajouter en tête du tableau :
```php
            'assetId' => $this->assetId,
```

- [ ] **Step 4: Peupler `assetId` dans l'action**

Dans `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`, dans la construction `new HoldingLineData(`, ajouter en premier argument :
```php
            $lines[] = new HoldingLineData(
                assetId: (int) $holding->asset_id,
                assetName: $holding->asset->name,
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=DashboardPageTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Rendre les lignes Positions cliquables**

Dans `resources/js/Pages/Dashboard.vue` :

a) ajouter `Link` à l'import Inertia :
```ts
import { Deferred, Head, Link } from '@inertiajs/vue3';
```
b) ajouter `assetId` à l'interface `HoldingLine` (en tête) :
```ts
interface HoldingLine {
    assetId: number;
    assetName: string;
```
c) rendre la cellule Actif cliquable — remplacer le `<TableCell class="font-medium">…</TableCell>` de la colonne Actif par :
```vue
                                    <TableCell class="font-medium">
                                        <Link :href="`/instruments/${line.assetId}`" class="hover:underline">
                                            {{ line.assetName }}
                                            <span v-if="line.ticker" class="text-muted-foreground">({{ line.ticker }})</span>
                                        </Link>
                                    </TableCell>
```

- [ ] **Step 7: Typecheck + build**

Run: `bun run typecheck && bun run build`
Expected: OK

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Datas/HoldingLineData.php app/Contexts/Portfolio/Actions/GetPortfolioOverview.php resources/js/Pages/Dashboard.vue tests/Feature/DashboardPageTest.php
git commit -m "feat: lignes du dashboard cliquables vers la fiche instrument"
```

---

## Task 12: Vérification finale

- [ ] **Step 1: Suite complète**

Run: `php artisan test --compact`
Expected: tous les tests verts (existants + nouveaux)

- [ ] **Step 2: Typecheck + build**

Run: `bun run typecheck && bun run build`
Expected: OK

- [ ] **Step 3: Vérif manuelle (Herd)**

Ouvrir `/instruments` puis cliquer une ligne détenue → fiche `/instruments/{id}` : position, courbe (après chargement deferred), transactions, secteurs. Vérifier `/instruments/999` → 404. Depuis `/dashboard`, cliquer une ligne Positions → fiche correspondante.

---

## Self-Review

**1. Spec coverage :**
- Contexte read-only + ports + adapters + provider → Tasks 2-5. ✓
- 3 ports (MarketData/Holdings/Transactions) → Tasks 2-4. ✓
- Catalogue `/instruments` + ligne cliquable → Tasks 6, 8, 9. ✓
- Fiche `/instruments/{id}` + 404 → Tasks 7, 8, 10. ✓
- Sections header/position/courbe/transactions/secteurs → Task 10. ✓
- Position masquée si non détenu / prix absent → Tasks 7, 10. ✓
- Courbe 12 mois deferred + skeleton → Tasks 8, 10. ✓
- Lien Dashboard + `assetId` → Task 11. ✓
- Tests feature + unit → chaque task. ✓
- Actifs perso hors scope, id-based route, francophone → respectés. ✓

**2. Placeholder scan :** stubs Vue en Task 8 explicitement remplacés en Tasks 9-10 (pas des placeholders finaux) ; binding temporaire Task 2 explicitement remplacé en Task 5. Aucun « TODO/TBD » résiduel dans le livrable final.

**3. Type consistency :** noms de ports/méthodes cohérents entre Consumes/Produces (`listInstruments`, `findInstrument`, `latestPrice`, `priceHistory`, `sectors`, `holdingsFor`, `holdingFor`, `transactionsFor`). DTOs identiques entre définition (Task 1) et usages (Tasks 2-11). `assetId` ajouté en tête de `HoldingLineData` — l'action `GetPortfolioOverview` mise à jour au même endroit (Task 11).

**Nuance connue (non bloquante) :** ordre de Task 2/5 — un binding direct temporaire de `MarketDataPort` est posé en Task 2 pour rendre son test vert immédiatement, puis remplacé par `InstrumentViewProvider::registers` en Task 5. Cela évite d'exiger les 3 adapters avant de tester le premier.

## Dette différée (reportée de la spec)

- Calcul du gain dupliqué (`GetInstrumentDetail` vs `GetPortfolioOverview`) → extraire un helper pur au 3ᵉ consommateur.
- `latestPrice` appelé par ligne dans le catalogue = N requêtes (précédent : `GetPortfolioOverview` fait pareil) → batch si le catalogue grossit.
- Variation du jour, sélecteur de période, fiche actifs perso = itérations ultérieures.
