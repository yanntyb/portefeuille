# Graphe fusionné base 100 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sur la fiche instrument, quand une position est détenue, remplacer les deux graphes (Cours 12 mois + Valeur vs Investi €) par un unique graphe base 100 à 3 courbes (Cours, Valeur, Investi).

**Architecture:** Le backend `ValuationCalculator` expose déjà les prix par jour en interne (`$lastClose`) ; on ajoute un tableau `prices` parallèle à `valuations`/`invested` dans `ValuationSeriesData`. Le front `Instruments/Show.vue` normalise les 3 séries en base 100 et rend un seul graphe `line` quand une position existe (branche sur `instrument.position`, synchrone), sinon le graphe Cours actuel.

**Tech Stack:** PHP 8.4 / Laravel 12, Pest 4, Inertia v3 + Vue 3 (`<script setup lang="ts">`), vue3-apexcharts, Tailwind v4.

## Global Constraints

- Tout texte visible par l'utilisateur en français.
- PHP : accolades obligatoires, types de retour explicites, promotion de propriétés dans `__construct`, pas de commentaires inline superflus.
- Après modif PHP : `vendor/bin/pint --dirty --format agent`.
- Committer après chaque tâche.
- Toute modif doit être testée programmatiquement (unit/feature Pest ; front vérifié via `bun run typecheck`).

---

### Task 1: Backend — `prices` dans `ValuationSeriesData` + `ValuationCalculator`

**Files:**
- Create: `tests/Unit/Valuation/ValuationCalculatorTest.php`
- Modify: `app/Contexts/Valuation/Datas/ValuationSeriesData.php`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php:68-99`

**Interfaces:**
- Produces: `ValuationSeriesData` avec propriété publique `list<float> $prices` (4ᵉ paramètre positionnel du constructeur, après `$invested`). `ValuationCalculator::calculate(array $transactions, array $prices, int $maxPoints = 200): ValuationSeriesData` renvoie `prices` = cours unitaire du titre par label, même longueur et mêmes indices de downsampling que `valuations`/`invested`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Valuation/ValuationCalculatorTest.php`:

```php
<?php

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Services\ValuationCalculator;
use Illuminate\Support\Carbon;

it('exposes the unit price aligned with the valuation labels', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [
        new PriceRecordData(assetId: 1, date: '2026-01-01', close: 100.0),
        new PriceRecordData(assetId: 1, date: '2026-01-02', close: 110.0),
        new PriceRecordData(assetId: 1, date: '2026-01-03', close: 90.0),
    ];

    $series = (new ValuationCalculator)->calculate($transactions, $prices);

    expect($series->prices)->toBe([100.0, 110.0, 90.0])
        ->and($series->prices)->toHaveCount(count($series->labels))
        ->and($series->valuations)->toBe([1000.0, 1100.0, 900.0]);
});

it('downsamples prices with the same indices as the other series', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [
        new PriceRecordData(assetId: 1, date: '2026-01-01', close: 100.0),
        new PriceRecordData(assetId: 1, date: '2026-01-02', close: 110.0),
        new PriceRecordData(assetId: 1, date: '2026-01-03', close: 90.0),
    ];

    $series = (new ValuationCalculator)->calculate($transactions, $prices, maxPoints: 2);

    expect($series->labels)->toBe(['2026-01-01', '2026-01-03'])
        ->and($series->prices)->toBe([100.0, 90.0]);
});

it('returns an empty prices array for an empty series', function () {
    $series = (new ValuationCalculator)->calculate([], []);

    expect($series->prices)->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: FAIL — `Property [prices] does not exist` / erreur d'argument (le champ `prices` n'existe pas encore).

- [ ] **Step 3: Add `prices` to `ValuationSeriesData`**

Replace the whole file `app/Contexts/Valuation/Datas/ValuationSeriesData.php`:

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class ValuationSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $valuations
     * @param  list<float>  $invested
     * @param  list<float>  $prices
     */
    public function __construct(
        public array $labels,
        public array $valuations,
        public array $invested,
        public array $prices,
    ) {}

    public static function empty(): self
    {
        return new self([], [], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'valuations' => $this->valuations,
            'invested' => $this->invested,
            'prices' => $this->prices,
        ];
    }
}
```

- [ ] **Step 4: Capture the unit price in `ValuationCalculator::calculate`**

In `app/Contexts/Valuation/Services/ValuationCalculator.php`, replace the block from `$labels = [];` (line 68) through the final `return` (line 99) with:

```php
        $labels = [];
        $valuations = [];
        $invested = [];
        $prices = [];
        $lastClose = [];
        $primaryAsset = $assetIds[0] ?? null;

        foreach ($days as $day) {
            $value = 0.0;
            foreach ($assetIds as $assetId) {
                if (isset($priceByDay[$day][$assetId])) {
                    $lastClose[$assetId] = $priceByDay[$day][$assetId];
                }
                $close = $lastClose[$assetId] ?? null;
                if ($close === null) {
                    continue;
                }
                $value += $this->valueAtDate($quantities[$assetId], $day) * $close;
            }

            $labels[] = $day;
            $valuations[] = round($value, 2);
            $invested[] = round($this->valueAtDate($investedSeries, $day), 2);
            $prices[] = round($primaryAsset === null ? 0.0 : ($lastClose[$primaryAsset] ?? 0.0), 2);
        }

        $indices = self::downsampleIndices(count($labels), $maxPoints);

        if (count($indices) < count($labels)) {
            $labels = array_map(fn (int $i): string => $labels[$i], $indices);
            $valuations = array_map(fn (int $i): float => $valuations[$i], $indices);
            $invested = array_map(fn (int $i): float => $invested[$i], $indices);
            $prices = array_map(fn (int $i): float => $prices[$i], $indices);
        }

        return new ValuationSeriesData($labels, $valuations, $invested, $prices);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: PASS (3 tests).

- [ ] **Step 6: Format**

Run: `vendor/bin/pint --dirty --format agent`
Expected: no style errors.

- [ ] **Step 7: Commit**

```bash
git add tests/Unit/Valuation/ValuationCalculatorTest.php app/Contexts/Valuation/Datas/ValuationSeriesData.php app/Contexts/Valuation/Services/ValuationCalculator.php
git commit -m "feat: ValuationSeriesData expose le cours unitaire (prices)"
```

---

### Task 2: Feature — le prop déferré `valuation.prices` est chargé

**Files:**
- Modify: `tests/Feature/InstrumentDetailPageTest.php:71-75`

**Interfaces:**
- Consumes: `ValuationSeriesData::prices` (Task 1), propagé tel quel par `BuildAssetValuationSeries` et sérialisé dans le prop Inertia `valuation`.

- [ ] **Step 1: Add the assertion (failing until Task 1 shipped; here it locks the HTTP contract)**

In `tests/Feature/InstrumentDetailPageTest.php`, inside the test `it('defers the per-title valuation series and loads it on demand', ...)`, add a `prices` assertion to the `loadDeferredProps` block:

```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels', 1)
                ->has('valuation.valuations', 1)
                ->has('valuation.invested', 1)
                ->has('valuation.prices', 1)
            )
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test --compact --filter=InstrumentDetailPage`
Expected: PASS (all tests in the file, including the updated one — `valuation.prices` has 1 entry).

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/InstrumentDetailPageTest.php
git commit -m "test: le prop valuation deferre expose prices"
```

---

### Task 3: Frontend — graphe base 100 unique quand position détenue

**Files:**
- Modify: `resources/js/Pages/Instruments/Show.vue`

**Interfaces:**
- Consumes: prop `valuation` de forme `{ labels: string[]; valuations: number[]; invested: number[]; prices: number[] }`.

- [ ] **Step 1: Étendre l'interface `ValuationSeries`**

In the `<script setup>` of `resources/js/Pages/Instruments/Show.vue`, add `prices` to the interface (currently lines 64-68):

```ts
interface ValuationSeries {
    labels: string[];
    valuations: number[];
    invested: number[];
    prices: number[];
}
```

- [ ] **Step 2: Ajouter les helpers base 100 + performance signée**

Just after the `pct` helper (around line 80), add:

```ts
const signedPct = (value: number): string => {
    const delta = value - 100;
    return `${delta >= 0 ? '+' : ''}${delta.toFixed(1)} %`;
};

const base100 = (serie: number[]): number[] =>
    serie.length === 0 || serie[0] === 0
        ? serie.map((): number => 100)
        : serie.map((value: number): number => (value / serie[0]) * 100);
```

- [ ] **Step 3: Remplacer les séries/options du graphe de valorisation**

Replace the `hasValuation` / `valuationChartSeries` / `valuationChartOptions` block (currently lines 113-137) with:

```ts
const hasPosition = computed<boolean>(() => props.instrument.position !== null);

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const performanceChartSeries = computed(() => [
    { name: 'Cours', data: base100(props.valuation?.prices ?? []) },
    { name: 'Valeur', data: base100(props.valuation?.valuations ?? []) },
    { name: 'Investi', data: base100(props.valuation?.invested ?? []) },
]);

const performanceChartOptions = computed<ApexOptions>(() => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors: ['#10b981', '#4f46e5', '#64748b'],
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => signedPct(value) } },
    tooltip: { y: { formatter: (value: number): string => signedPct(value) } },
    legend: { position: 'top' },
}));
```

- [ ] **Step 4: Remplacer les deux cartes de graphes dans le template**

Replace both `<Card>` blocks — the "Cours" card (currently lines 191-214) and the "Valeur vs Investi" card (currently lines 216-239) — with a single conditional block:

```html
            <Card v-if="hasPosition" :class="flatCard">
                <CardHeader>
                    <CardTitle>Performance</CardTitle>
                    <CardDescription>Base 100 depuis la première transaction</CardDescription>
                </CardHeader>
                <CardContent>
                    <Deferred data="valuation">
                        <template #fallback>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </template>

                        <VueApexCharts
                            v-if="hasValuation"
                            type="line"
                            height="300"
                            :options="performanceChartOptions"
                            :series="performanceChartSeries"
                        />
                        <p v-else class="py-8 text-center text-sm text-muted-foreground">
                            Pas encore d'historique de valorisation.
                        </p>
                    </Deferred>
                </CardContent>
            </Card>

            <Card v-else :class="flatCard">
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
```

- [ ] **Step 5: Typecheck**

Run: `bun run typecheck`
Expected: PASS — pas d'erreur vue-tsc (le prop `prices` et les computed sont typés).

- [ ] **Step 6: Vérification visuelle**

Ouvrir la fiche d'un instrument détenu via Herd (`get-absolute-url` pour l'URL correcte, ex. `https://argent.test/instruments/<id>`) et vérifier :
- position détenue → un seul graphe « Performance », 3 courbes base 100, axe Y en `+x %` ;
- instrument non détenu → graphe « Cours » 12 mois seul.

Si le front n'est pas à jour, lancer `bun run build` ou demander à l'utilisateur `bun run dev` / `composer run dev`.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Instruments/Show.vue
git commit -m "feat: graphe base 100 unique quand la position est detenue"
```

---

## Self-Review

- **Spec coverage :** `prices` dans `ValuationSeriesData`/`empty`/`jsonSerialize` (Task 1) ; capture + downsampling dans `calculate` (Task 1) ; `BuildAssetValuationSeries` propage sans changement de signature, vérifié par le feature test (Task 2) ; interface front + base 100 + `signedPct` + graphe `line` + branche `hasPosition`/`hasValuation` + suppression du graphe Cours quand position (Task 3). Tests unit/feature + typecheck couverts.
- **Placeholders :** aucun ; tout le code est fourni.
- **Cohérence des types :** `prices: list<float>` (PHP) ↔ `prices: number[]` (TS) ; `ValuationSeriesData` construit avec 4 arguments positionnels partout (`calculate`, `empty`) ; helpers `base100`/`signedPct` définis avant usage.
- **Note d'implémentation :** la branche de rendu top-level utilise `hasPosition` (synchrone, via `instrument.position`) et non le prop déferré `valuation`, pour éviter le flicker pendant le chargement ; `hasValuation` sert uniquement à l'état vide interne. Raffinement conforme à l'intention du spec.

---

# Révision v2 — filtres range + granularité (server-side)

Voir la section « Révision v2 » du spec. Tasks 1-3 restent en l'état (mergées). Ces tâches ajoutent deux filtres synchronisés (range + granularité) qui refont, via un partial reload Inertia, la série `valuation` fenêtrée + agrégée côté serveur ; le front affiche deux graphes base 100 en grid 2. La base 100 reste calculée côté client (helpers `base100`/`signedPct` de Task 3).

### Task 4: Enums `ValuationRange` + `ValuationGranularity`

**Files:**
- Create: `app/Contexts/Valuation/Enums/ValuationRange.php`
- Create: `app/Contexts/Valuation/Enums/ValuationGranularity.php`
- Test: `app/Contexts/Valuation/Enums/ValuationRangeTest.php`
- Test: `app/Contexts/Valuation/Enums/ValuationGranularityTest.php`

**Interfaces:**
- Produces: `ValuationRange` (backed string `1M|6M|1Y|max`) avec `months(): ?int`, `fromRequest(?string): self` (défaut `Max`), `getLabel(): string`. `ValuationGranularity` (backed string `day|week|month`) avec `bucketKey(string $ymd): string`, `fromRequest(?string): self` (défaut `Month`), `getLabel(): string`.

- [ ] **Step 1: Write the failing tests**

Create `app/Contexts/Valuation/Enums/ValuationRangeTest.php`:

```php
<?php

use App\Contexts\Valuation\Enums\ValuationRange;

it('maps each range to a month count', function () {
    expect(ValuationRange::OneMonth->months())->toBe(1)
        ->and(ValuationRange::SixMonths->months())->toBe(6)
        ->and(ValuationRange::OneYear->months())->toBe(12)
        ->and(ValuationRange::Max->months())->toBeNull();
});

it('resolves from a request value with a Max default', function () {
    expect(ValuationRange::fromRequest('6M'))->toBe(ValuationRange::SixMonths)
        ->and(ValuationRange::fromRequest(null))->toBe(ValuationRange::Max)
        ->and(ValuationRange::fromRequest('nope'))->toBe(ValuationRange::Max);
});
```

Create `app/Contexts/Valuation/Enums/ValuationGranularityTest.php`:

```php
<?php

use App\Contexts\Valuation\Enums\ValuationGranularity;

it('buckets a date by granularity', function () {
    expect(ValuationGranularity::Day->bucketKey('2026-03-15'))->toBe('2026-03-15')
        ->and(ValuationGranularity::Month->bucketKey('2026-03-15'))->toBe('2026-03')
        ->and(ValuationGranularity::Week->bucketKey('2026-03-15'))->toBe(ValuationGranularity::Week->bucketKey('2026-03-09'));
});

it('resolves from a request value with a Month default', function () {
    expect(ValuationGranularity::fromRequest('week'))->toBe(ValuationGranularity::Week)
        ->and(ValuationGranularity::fromRequest(null))->toBe(ValuationGranularity::Month)
        ->and(ValuationGranularity::fromRequest('nope'))->toBe(ValuationGranularity::Month);
});
```

Note: `2026-03-09` (lundi) et `2026-03-15` (dimanche) sont dans la même semaine ISO → même `bucketKey`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="ValuationRange|ValuationGranularity"`
Expected: FAIL — `Class "App\Contexts\Valuation\Enums\ValuationRange" not found`.

- [ ] **Step 3: Create `ValuationRange`**

```php
<?php

namespace App\Contexts\Valuation\Enums;

enum ValuationRange: string
{
    case OneMonth = '1M';
    case SixMonths = '6M';
    case OneYear = '1Y';
    case Max = 'max';

    public function months(): ?int
    {
        return match ($this) {
            self::OneMonth => 1,
            self::SixMonths => 6,
            self::OneYear => 12,
            self::Max => null,
        };
    }

    public static function fromRequest(?string $value): self
    {
        return ($value !== null ? self::tryFrom($value) : null) ?? self::Max;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OneMonth => '1M',
            self::SixMonths => '6M',
            self::OneYear => '1A',
            self::Max => 'Max',
        };
    }
}
```

- [ ] **Step 4: Create `ValuationGranularity`**

```php
<?php

namespace App\Contexts\Valuation\Enums;

use Illuminate\Support\Carbon;

enum ValuationGranularity: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    public function bucketKey(string $ymd): string
    {
        return match ($this) {
            self::Day => $ymd,
            self::Week => Carbon::parse($ymd)->format('o-W'),
            self::Month => substr($ymd, 0, 7),
        };
    }

    public static function fromRequest(?string $value): self
    {
        return ($value !== null ? self::tryFrom($value) : null) ?? self::Month;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Day => 'Jour',
            self::Week => 'Sem',
            self::Month => 'Mois',
        };
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter="ValuationRange|ValuationGranularity"`
Expected: PASS (4 tests).

- [ ] **Step 6: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Enums/
git commit -m "feat: enums ValuationRange + ValuationGranularity"
```

---

### Task 5: `ValuationCalculator::calculateDaily` (extraction sans downsampling)

**Files:**
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php:18-104`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`

**Interfaces:**
- Produces: `ValuationCalculator::calculateDaily(array $transactions, array $prices): ValuationSeriesData` — série **quotidienne pleine** (un point par jour de prix, aucun downsampling). `calculate(array, array, int $maxPoints = 200): ValuationSeriesData` conserve son comportement (= `calculateDaily` + downsampling), donc `BuildPortfolioValuationSeries` reste inchangé.

- [ ] **Step 1: Write the failing test**

Append to `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`:

```php
it('calculateDaily returns one point per price day without downsampling', function () {
    $transactions = [
        new TransactionRecordData(
            date: Carbon::parse('2026-01-01'),
            assetId: 1,
            isSell: false,
            quantity: 10.0,
            unitPrice: 100.0,
            fees: 0.0,
        ),
    ];
    $prices = [];
    for ($d = 1; $d <= 250; $d++) {
        $prices[] = new PriceRecordData(assetId: 1, date: Carbon::parse('2026-01-01')->addDays($d - 1)->format('Y-m-d'), close: 100.0 + $d);
    }

    $daily = (new ValuationCalculator)->calculateDaily($transactions, $prices);
    $capped = (new ValuationCalculator)->calculate($transactions, $prices, maxPoints: 200);

    expect($daily->labels)->toHaveCount(250)
        ->and($daily->prices)->toHaveCount(250)
        ->and($capped->labels)->toHaveCount(200);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="calculateDaily"`
Expected: FAIL — `Call to undefined method ...::calculateDaily()`.

- [ ] **Step 3: Extract `calculateDaily` and rewrite `calculate`**

In `app/Contexts/Valuation/Services/ValuationCalculator.php`, replace the entire `calculate()` method (lines 18-104) with these two methods:

```php
    /**
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     * @param  int  $maxPoints  plafond de points de la série (downsampling adaptatif)
     */
    public function calculate(array $transactions, array $prices, int $maxPoints = 200): ValuationSeriesData
    {
        $daily = $this->calculateDaily($transactions, $prices);
        $indices = self::downsampleIndices(count($daily->labels), $maxPoints);

        if (count($indices) === count($daily->labels)) {
            return $daily;
        }

        return new ValuationSeriesData(
            array_map(fn (int $i): string => $daily->labels[$i], $indices),
            array_map(fn (int $i): float => $daily->valuations[$i], $indices),
            array_map(fn (int $i): float => $daily->invested[$i], $indices),
            array_map(fn (int $i): float => $daily->prices[$i], $indices),
        );
    }

    /**
     * Série quotidienne pleine (un point par jour de prix), sans downsampling.
     *
     * @param  list<TransactionRecordData>  $transactions
     * @param  list<PriceRecordData>  $prices
     */
    public function calculateDaily(array $transactions, array $prices): ValuationSeriesData
    {
        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
            ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

        /** @var array<int, list<array{date: string, value: float}>> $quantities */
        $quantities = [];
        /** @var list<array{date: string, value: float}> $investedSeries */
        $investedSeries = [];
        $buyQty = [];
        $buyCost = [];
        $totalInvested = 0.0;

        foreach ($transactions as $transaction) {
            $day = $transaction->date->format('Y-m-d');
            $assetId = $transaction->assetId;
            $quantities[$assetId] ??= [];

            $previous = end($quantities[$assetId]);
            $previousQty = $previous === false ? 0.0 : $previous['value'];
            $delta = $transaction->isSell ? -$transaction->quantity : $transaction->quantity;
            $quantities[$assetId][] = ['date' => $day, 'value' => $previousQty + $delta];

            if ($transaction->isSell) {
                $qty = $buyQty[$assetId] ?? 0.0;
                $cost = $buyCost[$assetId] ?? 0.0;
                $pru = $qty > 0.0 ? $cost / $qty : 0.0;
                $totalInvested -= $transaction->quantity * $pru - $transaction->fees;
            } else {
                $buyQty[$assetId] = ($buyQty[$assetId] ?? 0.0) + $transaction->quantity;
                $buyCost[$assetId] = ($buyCost[$assetId] ?? 0.0) + $transaction->quantity * $transaction->unitPrice;
                $totalInvested += $transaction->quantity * $transaction->unitPrice + $transaction->fees;
            }

            $investedSeries[] = ['date' => $day, 'value' => $totalInvested];
        }

        $days = collect($prices)->map(fn (PriceRecordData $p) => $p->date)->unique()->sort()->values()->all();
        $assetIds = array_keys($quantities);

        /** @var array<string, array<int, float>> $priceByDay */
        $priceByDay = [];
        foreach ($prices as $price) {
            $priceByDay[$price->date][$price->assetId] = $price->close;
        }

        $labels = [];
        $valuations = [];
        $invested = [];
        $unitPrices = [];
        $lastClose = [];
        $primaryAsset = $assetIds[0] ?? null;

        foreach ($days as $day) {
            $value = 0.0;
            foreach ($assetIds as $assetId) {
                if (isset($priceByDay[$day][$assetId])) {
                    $lastClose[$assetId] = $priceByDay[$day][$assetId];
                }
                $close = $lastClose[$assetId] ?? null;
                if ($close === null) {
                    continue;
                }
                $value += $this->valueAtDate($quantities[$assetId], $day) * $close;
            }

            $labels[] = $day;
            $valuations[] = round($value, 2);
            $invested[] = round($this->valueAtDate($investedSeries, $day), 2);
            $unitPrices[] = round($primaryAsset === null ? 0.0 : ($lastClose[$primaryAsset] ?? 0.0), 2);
        }

        return new ValuationSeriesData($labels, $valuations, $invested, $unitPrices);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: PASS — the new `calculateDaily` test plus all pre-existing calculator tests (downsampling behavior of `calculate` unchanged).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php
git commit -m "refactor: extrait ValuationCalculator::calculateDaily (serie pleine)"
```

---

### Task 6: `ValuationCalculator::windowAndAggregate`

**Files:**
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php` (add one method)
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`

**Interfaces:**
- Consumes: `ValuationRange`, `ValuationGranularity` (Task 4), `ValuationSeriesData` (Task 1), `calculateDaily` (Task 5).
- Produces: `ValuationCalculator::windowAndAggregate(ValuationSeriesData $series, ValuationRange $range, ValuationGranularity $granularity): ValuationSeriesData` — filtre les labels ≥ (dernière date − `range->months()` mois ; `Max` = tout), puis garde le **dernier point de chaque bucket** de granularité, dans l'ordre croissant. Ne recalcule pas la base 100 (fait côté client).

- [ ] **Step 1: Write the failing test**

Append to `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` (add `use App\Contexts\Valuation\Enums\ValuationRange;` and `use App\Contexts\Valuation\Enums\ValuationGranularity;` at the top if not present):

```php
it('windows the series to the requested range', function () {
    $labels = [];
    $series = [];
    for ($d = 0; $d < 400; $d++) {
        $labels[] = Carbon::parse('2025-01-01')->addDays($d)->format('Y-m-d');
    }
    $values = array_map(fn (int $i): float => (float) ($i + 1), array_keys($labels));
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $windowed = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::OneMonth, ValuationGranularity::Day);

    $lastDate = Carbon::parse($labels[399]);
    $cutoff = $lastDate->copy()->subMonthsNoOverflow(1)->format('Y-m-d');
    expect($windowed->labels[0])->toBeGreaterThanOrEqual($cutoff)
        ->and(end($windowed->labels))->toBe($labels[399])
        ->and(count($windowed->labels))->toBeLessThan(400);
});

it('aggregates by keeping the last point of each month bucket', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31', '2026-02-05', '2026-02-28'];
    $values = [1.0, 2.0, 3.0, 4.0, 5.0];
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $monthly = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::Max, ValuationGranularity::Month);

    expect($monthly->labels)->toBe(['2026-01-31', '2026-02-28'])
        ->and($monthly->valuations)->toBe([3.0, 5.0])
        ->and($monthly->prices)->toBe([3.0, 5.0]);
});

it('keeps every point when granularity is Day', function () {
    $labels = ['2026-01-10', '2026-01-20', '2026-01-31'];
    $values = [1.0, 2.0, 3.0];
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData($labels, $values, $values, $values);

    $result = (new ValuationCalculator)->windowAndAggregate($daily, ValuationRange::Max, ValuationGranularity::Day);

    expect($result->labels)->toBe($labels);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter="windowAndAggregate|window|aggregate|granularity is Day"`
Expected: FAIL — `Call to undefined method ...::windowAndAggregate()`.

- [ ] **Step 3: Implement `windowAndAggregate`**

Add these imports at the top of `ValuationCalculator.php` (after existing `use` lines):

```php
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;
```

Add this method to the `ValuationCalculator` class (e.g. after `calculateDaily`):

```php
    public function windowAndAggregate(ValuationSeriesData $series, ValuationRange $range, ValuationGranularity $granularity): ValuationSeriesData
    {
        if ($series->labels === []) {
            return $series;
        }

        $months = $range->months();
        $cutoff = $months === null
            ? null
            : Carbon::parse($series->labels[count($series->labels) - 1])->subMonthsNoOverflow($months)->format('Y-m-d');

        $labels = [];
        $valuations = [];
        $invested = [];
        $prices = [];

        foreach ($series->labels as $i => $label) {
            if ($cutoff !== null && $label < $cutoff) {
                continue;
            }
            $labels[] = $label;
            $valuations[] = $series->valuations[$i];
            $invested[] = $series->invested[$i];
            $prices[] = $series->prices[$i];
        }

        /** @var array<string, int> $lastIndexByBucket */
        $lastIndexByBucket = [];
        foreach ($labels as $i => $label) {
            $lastIndexByBucket[$granularity->bucketKey($label)] = $i;
        }

        $keep = array_values($lastIndexByBucket);
        sort($keep);

        return new ValuationSeriesData(
            array_map(fn (int $i): string => $labels[$i], $keep),
            array_map(fn (int $i): float => $valuations[$i], $keep),
            array_map(fn (int $i): float => $invested[$i], $keep),
            array_map(fn (int $i): float => $prices[$i], $keep),
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValuationCalculator`
Expected: PASS (all calculator tests, including the 3 new windowing/aggregation cases).

- [ ] **Step 5: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php
git commit -m "feat: ValuationCalculator::windowAndAggregate (fenetre + buckets)"
```

---

### Task 7: `BuildAssetValuationSeries` + `InstrumentDetailController` params

**Files:**
- Modify: `app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php`
- Modify: `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`
- Test: `app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php`
- Test: `tests/Feature/InstrumentDetailPageTest.php`

**Interfaces:**
- Consumes: `ValuationRange`, `ValuationGranularity` (Task 4), `calculateDaily` + `windowAndAggregate` (Tasks 5-6).
- Produces: `BuildAssetValuationSeries::__invoke(int $userId, int $assetId, ValuationRange $range = ValuationRange::Max, ValuationGranularity $granularity = ValuationGranularity::Month): ValuationSeriesData`. The controller reads `range`/`granularity` from the query string and passes them into the deferred `valuation` prop.

- [ ] **Step 1: Write the failing test (action)**

Read the existing `app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php` first to reuse its setup helpers/ports. Append a test that a monthly granularity collapses multiple same-month price days into one point. Use the same port fakes / model factories the file already uses. Concretely, add:

```php
it('windows and aggregates according to range and granularity', function () {
    // Reuse this file's existing setup (ports/fakes or factories) to build:
    // - one asset with a single buy on 2026-01-01
    // - daily prices from 2026-01-01 through 2026-03-31
    // then request Max + Month.
    [$userId, $assetId] = seedAssetWithDailyPrices('2026-01-01', '2026-03-31'); // helper: mirror existing setup in this file

    $series = app(App\Contexts\Valuation\Actions\BuildAssetValuationSeries::class)(
        $userId,
        $assetId,
        App\Contexts\Valuation\Enums\ValuationRange::Max,
        App\Contexts\Valuation\Enums\ValuationGranularity::Month,
    );

    // 3 month buckets (Jan, Feb, Mar) => 3 points, last day of each present
    expect($series->labels)->toHaveCount(3)
        ->and(end($series->labels))->toBe('2026-03-31');
});
```

Note for the implementer: this file already builds its fixtures a certain way (ports vs factories). Do NOT invent a `seedAssetWithDailyPrices` helper if the file uses inline setup — inline the fixture the same way the existing tests in this file do, generating daily `Price`/`PriceRecordData` rows from 2026-01-01 to 2026-03-31. Keep the assertion (3 monthly points, last label `2026-03-31`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildAssetValuationSeries`
Expected: FAIL — too few arguments / method signature mismatch (the action does not yet accept range/granularity).

- [ ] **Step 3: Update `BuildAssetValuationSeries`**

Replace the body of `app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php`:

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildAssetValuationSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    public function __invoke(
        int $userId,
        int $assetId,
        ValuationRange $range = ValuationRange::Max,
        ValuationGranularity $granularity = ValuationGranularity::Month,
    ): ValuationSeriesData {
        $transactions = array_values(array_filter(
            $this->transactions->forUser($userId),
            fn (TransactionRecordData $transaction) => $transaction->assetId === $assetId,
        ));

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $prices = $this->prices->forAssetsSince([$assetId], $transactions[0]->date);

        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->windowAndAggregate($daily, $range, $granularity);
    }
}
```

- [ ] **Step 4: Update `InstrumentDetailController`**

Replace `app/Contexts/InstrumentView/Http/InstrumentDetailController.php`:

```php
<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
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

        $range = ValuationRange::fromRequest(request()->query('range'));
        $granularity = ValuationGranularity::fromRequest(request()->query('granularity'));

        return Inertia::render('Instruments/Show', [
            'instrument' => $detail,
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
            'valuation' => Inertia::defer(
                fn () => app(BuildAssetValuationSeries::class)($userId, $id, $range, $granularity)
            ),
        ]);
    }
}
```

Note: `request()->query('range')` returns `?string`; `ValuationRange::fromRequest` handles null/invalid.

- [ ] **Step 5: Add a feature test for the query params**

Append to `tests/Feature/InstrumentDetailPageTest.php`:

```php
it('accepts range and granularity query params for the valuation series', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}?range=1M&granularity=week")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Instruments/Show')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('valuation.labels')
                ->has('valuation.prices')
            )
        );
});

it('falls back to defaults for invalid range and granularity', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);

    $this->get("/instruments/{$asset->id}?range=bogus&granularity=bogus")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload->has('valuation.labels'))
        );
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact --filter="BuildAssetValuationSeries|InstrumentDetailPage"`
Expected: PASS.

- [ ] **Step 7: Format + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Actions/BuildAssetValuationSeries.php app/Contexts/InstrumentView/Http/InstrumentDetailController.php app/Contexts/Valuation/Actions/BuildAssetValuationSeriesTest.php tests/Feature/InstrumentDetailPageTest.php
git commit -m "feat: fiche instrument accepte range + granularite (valuation fenetree)"
```

---

### Task 8: Frontend — 2 graphes base 100 + filtres range/granularité

**Files:**
- Modify: `resources/js/Pages/Instruments/Show.vue`

**Interfaces:**
- Consumes: prop `valuation` (fenêtré/agrégé côté serveur selon les query params) de forme `{ labels: string[]; valuations: number[]; invested: number[]; prices: number[] }`.

- [ ] **Step 1: Imports + refs + reload**

In the `<script setup>` of `resources/js/Pages/Instruments/Show.vue`:

1. Add `router` to the `@inertiajs/vue3` import and `ref` to the `vue` import:

```ts
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
```

2. After the existing `base100` / `signedPct` helpers, add the filter state and reload logic:

```ts
type RangeKey = '1M' | '6M' | '1Y' | 'max';
type GranularityKey = 'day' | 'week' | 'month';

const rangeOptions: { key: RangeKey; label: string }[] = [
    { key: '1M', label: '1M' },
    { key: '6M', label: '6M' },
    { key: '1Y', label: '1A' },
    { key: 'max', label: 'Max' },
];

const granularityOptions: { key: GranularityKey; label: string }[] = [
    { key: 'day', label: 'Jour' },
    { key: 'week', label: 'Sem' },
    { key: 'month', label: 'Mois' },
];

const selectedRange = ref<RangeKey>('max');
const selectedGranularity = ref<GranularityKey>('month');
const reloading = ref<boolean>(false);

const reloadValuation = (): void => {
    router.reload({
        only: ['valuation'],
        data: { range: selectedRange.value, granularity: selectedGranularity.value },
        preserveState: true,
        preserveScroll: true,
        onStart: (): void => {
            reloading.value = true;
        },
        onFinish: (): void => {
            reloading.value = false;
        },
    });
};

const selectRange = (key: RangeKey): void => {
    if (selectedRange.value === key) {
        return;
    }
    selectedRange.value = key;
    reloadValuation();
};

const selectGranularity = (key: GranularityKey): void => {
    if (selectedGranularity.value === key) {
        return;
    }
    selectedGranularity.value = key;
    reloadValuation();
};
```

- [ ] **Step 2: Replace the Task-3 performance computeds with two chart computeds**

Replace the `hasValuation` / `performanceChartSeries` / `performanceChartOptions` block from Task 3 with (keep `hasPosition` and `hasValuation`):

```ts
const hasPosition = computed<boolean>(() => props.instrument.position !== null);

const hasValuation = computed<boolean>(() => (props.valuation?.labels.length ?? 0) > 0);

const baseChartOptions = (colors: string[]): ApexOptions => ({
    chart: { toolbar: { show: false }, fontFamily: 'inherit', animations: { enabled: false } },
    colors,
    stroke: { curve: 'smooth', width: 2 },
    dataLabels: { enabled: false },
    grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
    xaxis: {
        type: 'datetime',
        categories: props.valuation?.labels ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { hideOverlappingLabels: true },
    },
    yaxis: { labels: { formatter: (value: number): string => signedPct(value) } },
    tooltip: { y: { formatter: (value: number): string => signedPct(value) } },
    legend: { position: 'top' },
});

const coursChartSeries = computed(() => [
    { name: 'Cours', data: base100(props.valuation?.prices ?? []) },
]);

const coursChartOptions = computed<ApexOptions>(() => baseChartOptions(['#10b981']));

const positionChartSeries = computed(() => [
    { name: 'Valeur', data: base100(props.valuation?.valuations ?? []) },
    { name: 'Investi', data: base100(props.valuation?.invested ?? []) },
]);

const positionChartOptions = computed<ApexOptions>(() => baseChartOptions(['#4f46e5', '#64748b']));
```

- [ ] **Step 3: Replace the Task-3 template block**

Replace the Task-3 `<Card v-if="hasPosition">` (Performance) block AND its `<Card v-else>` (Cours) block with:

```html
            <section v-if="hasPosition" class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-md border border-border p-0.5">
                        <button
                            v-for="opt in rangeOptions"
                            :key="opt.key"
                            type="button"
                            class="rounded px-3 py-1 text-sm transition-colors"
                            :class="selectedRange === opt.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="selectRange(opt.key)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                    <div class="inline-flex rounded-md border border-border p-0.5">
                        <button
                            v-for="opt in granularityOptions"
                            :key="opt.key"
                            type="button"
                            class="rounded px-3 py-1 text-sm transition-colors"
                            :class="selectedGranularity === opt.key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'"
                            @click="selectGranularity(opt.key)"
                        >
                            {{ opt.label }}
                        </button>
                    </div>
                </div>

                <Deferred data="valuation">
                    <template #fallback>
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                            <div class="h-[300px] w-full animate-pulse rounded-md bg-muted"></div>
                        </div>
                    </template>

                    <div
                        v-if="hasValuation"
                        class="grid gap-4 transition-opacity lg:grid-cols-2"
                        :class="reloading ? 'opacity-50' : ''"
                    >
                        <Card :class="flatCard">
                            <CardHeader>
                                <CardTitle>Cours</CardTitle>
                                <CardDescription>Performance base 100 sur la période</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts type="line" height="300" :options="coursChartOptions" :series="coursChartSeries" />
                            </CardContent>
                        </Card>

                        <Card :class="flatCard">
                            <CardHeader>
                                <CardTitle>Valeur vs Investi</CardTitle>
                                <CardDescription>Performance base 100 sur la période</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <VueApexCharts type="line" height="300" :options="positionChartOptions" :series="positionChartSeries" />
                            </CardContent>
                        </Card>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-muted-foreground">
                        Pas encore d'historique de valorisation.
                    </p>
                </Deferred>
            </section>

            <Card v-else :class="flatCard">
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
```

- [ ] **Step 4: Typecheck**

Run: `bun run typecheck`
Expected: PASS (no vue-tsc errors).

- [ ] **Step 5: Visual verification (controller does this)**

Build (`bun run build`), open a held instrument via Herd, verify: two charts side by side (Cours / Valeur vs Investi), both base 100; clicking a Range button (e.g. `1M`) and a Granularité button (e.g. `Jour`) triggers a reload and both charts update and rebase; percentages are relative to the first visible point. No-position instrument still shows the single 12-month Cours chart.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Instruments/Show.vue
git commit -m "feat: 2 graphes base 100 avec filtres range + granularite"
```

---

## Self-Review v2

- **Spec coverage :** enums Range/Granularité (Task 4) ; `calculateDaily` sans cap + `calculate` inchangé pour le portefeuille (Task 5) ; `windowAndAggregate` fenêtre + buckets (Task 6) ; `BuildAssetValuationSeries` + controller params (Task 7) ; front 2 graphes + 2 filtres synchronisés + `router.reload` + état `reloading`, base 100 client (Task 8). Tests unit (enums, calculateDaily, windowAndAggregate, action) + feature (controller params/défauts) + typecheck.
- **Placeholders :** aucun, sauf la note explicite en Task 7 Step 1 disant à l'implémenteur de calquer le fixture sur le style existant du fichier de test (ports vs factories) — instruction, pas de code manquant ; l'assertion est fournie.
- **Cohérence des types :** valeurs backing enums (`1M/6M/1Y/max`, `day/week/month`) = valeurs `data` envoyées par le front ; `BuildAssetValuationSeries` signature (userId, assetId, ValuationRange, ValuationGranularity) alignée controller ↔ action ; base 100 client réutilise `base100`/`signedPct` de Task 3 (inchangés).
- **Note :** la base 100 n'est jamais recalculée côté serveur ; le serveur renvoie les valeurs brutes de la fenêtre, le client ancre 100 sur le premier point retourné → pourcentages relatifs au début de la fenêtre affichée.
