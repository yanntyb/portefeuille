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
