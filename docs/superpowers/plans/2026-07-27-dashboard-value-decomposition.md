# Décomposition valeur par titre + visibilité via table — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Le graph Évolution empile la **valeur de marché par titre** (aires grises), sans légende/bande/ligne ; la visibilité de chaque titre est pilotée par une colonne œil dans la table Positions ; total & gain lus dans le tooltip (somme des titres visibles).

**Architecture:** Backend — `ValuationCalculator::evolution` expose par actif la valeur (quantité×prix forward-fill) en plus de l'investi, via un DTO `AssetSeriesData`, et `EvolutionSeriesData` est réduit à `{labels, perAsset}`. Frontend — `buildEvolutionChart` réécrit (aires de valeur empilées, filtrées par visibilité, tooltip custom), `Dashboard.vue` porte un `Set<number>` d'`assetId` masqués piloté par une colonne œil dans la table Positions.

**Tech Stack:** PHP 8.4 / Laravel 12 / Pest ; Vue 3 + Inertia v3 ; ApexCharts (vue3-apexcharts) ; lucide-vue-next ; Tailwind v4.

## Global Constraints

- Contexts DDD sous `app/Contexts/Valuation/…` ; tests colocalisés `*Test.php`.
- Type declarations explicites (params + retours), property promotion, DTO `readonly … implements JsonSerializable` (`jsonSerialize()` + `empty()`).
- Labels = chaînes ISO `Y-m-d`.
- Ne PAS toucher `investedByAsset()` / `AssetInvestedSeriesData` / `BuildInvestedByAssetSeries` (encore utilisés ailleurs) → nouveau DTO `AssetSeriesData` pour l'évolution.
- `vendor/bin/pint --dirty --format agent` après toute modif PHP.
- Front : `bun run build` doit passer ; UI en français ; réutiliser `formatTooltipDate` (privé) + `GREY_SCALE`/`GAIN_COLOR`/`LOSS_COLOR` dans `chart.ts` ; ne pas modifier `buildTimeSeriesOptions`/`buildDonutOptions`.
- Tests : `php artisan test --compact --filter=…`. Modifier (pas supprimer) les tests impactés.
- Palette aires : `GREY_SCALE` (slate). Gain vert `#10b981`, perte rouge `#ef4444`, ligne tooltip Valeur indigo `#4f46e5`.

---

### Task 1: Backend — valeur par actif dans la série d'évolution

Tâche cohérente unique : les DTO et leurs consommateurs changent de forme ensemble pour que la suite reste verte à la fin.

**Files:**
- Create: `app/Contexts/Valuation/Datas/AssetSeriesData.php` (+ `AssetSeriesDataTest.php`)
- Modify: `app/Contexts/Valuation/Datas/EvolutionSeriesData.php` (+ `EvolutionSeriesDataTest.php`)
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php` (+ `ValuationCalculatorTest.php`)
- Modify: `app/Contexts/Valuation/Actions/BuildEvolutionSeries.php` (+ `BuildEvolutionSeriesTest.php`)
- Modify: `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Produces: `AssetSeriesData(int $assetId, string $name, list<float> $value, list<float> $invested)`, `jsonSerialize()` keys `assetId,name,value,invested`.
- Produces: `EvolutionSeriesData(list<string> $labels, list<AssetSeriesData> $perAsset)`, `::empty()`, keys `labels,perAsset`.
- Produces: `ValuationCalculator::evolution(list<TransactionRecordData> $transactions, list<PriceRecordData> $prices, ValuationRange $range, ValuationGranularity $granularity): EvolutionSeriesData` ; `perAsset[k].name === '#'.$assetId` ; invariant `Σ_k perAsset[k].value[i]` == valorisation totale windowée à `labels[i]` (arrondis 2 déc.).
- Produces (privé): `perAssetQuantityTimelines(list<TransactionRecordData>): array<int, list<array{date:string,value:float}>>`.

- [ ] **Step 1: `AssetSeriesData` — test RED**

Créer `app/Contexts/Valuation/Datas/AssetSeriesDataTest.php` :
```php
<?php

use App\Contexts\Valuation\Datas\AssetSeriesData;

it('serializes an asset series with value and invested', function () {
    $serie = new AssetSeriesData(assetId: 7, name: 'ACME', value: [1200.0, 1500.0], invested: [1000.0, 1000.0]);

    expect($serie->jsonSerialize())->toBe([
        'assetId' => 7,
        'name' => 'ACME',
        'value' => [1200.0, 1500.0],
        'invested' => [1000.0, 1000.0],
    ]);
});
```
Run: `php artisan test --compact --filter=AssetSeriesData` → FAIL (classe absente).

- [ ] **Step 2: `AssetSeriesData` — implémentation**

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class AssetSeriesData implements JsonSerializable
{
    /**
     * @param  list<float>  $value
     * @param  list<float>  $invested
     */
    public function __construct(
        public int $assetId,
        public string $name,
        public array $value,
        public array $invested,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'name' => $this->name,
            'value' => $this->value,
            'invested' => $this->invested,
        ];
    }
}
```
Run: `php artisan test --compact --filter=AssetSeriesData` → PASS.

- [ ] **Step 3: Reshape `EvolutionSeriesData` + test**

Remplacer `EvolutionSeriesDataTest.php` par :
```php
<?php

use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;

it('serializes the evolution series', function () {
    $data = new EvolutionSeriesData(
        labels: ['2026-01-01', '2026-02-01'],
        perAsset: [new AssetSeriesData(7, 'ACME', [1200.0, 1500.0], [1000.0, 1000.0])],
    );

    $json = $data->jsonSerialize();

    expect($json['labels'])->toBe(['2026-01-01', '2026-02-01'])
        ->and($json['perAsset'][0])->toBeInstanceOf(AssetSeriesData::class);
});

it('builds an empty evolution series', function () {
    expect(EvolutionSeriesData::empty()->jsonSerialize())->toBe([
        'labels' => [], 'perAsset' => [],
    ]);
});
```
Remplacer `EvolutionSeriesData.php` par :
```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class EvolutionSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<AssetSeriesData>  $perAsset
     */
    public function __construct(
        public array $labels,
        public array $perAsset,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'perAsset' => $this->perAsset,
        ];
    }
}
```

- [ ] **Step 4: `ValuationCalculator` — tests RED (remplacer les cas `evolution` existants)**

Dans `ValuationCalculatorTest.php`, remplacer les 3 cas ajoutés précédemment (`aligns per-asset invested…`, `keeps sum of per-asset invested…`, `returns an empty evolution series…`) par :
```php
it('exposes per-asset market value aligned on the valuation labels (evolution)', function () {
    $series = (new ValuationCalculator)->evolution(
        [tx('2026-01-01', 1, false, 10, 100), tx('2026-02-01', 2, false, 5, 50)],
        [
            new P(1, '2026-01-01', 100), new P(1, '2026-02-01', 120),
            new P(2, '2026-02-01', 50),
        ],
        ValuationRange::Max,
        ValuationGranularity::Day,
    );

    expect($series->labels)->toBe(['2026-01-01', '2026-02-01']);

    $byName = collect($series->perAsset)->keyBy('name');
    // asset 1: 10@100 → 1000 puis 10@120 → 1200 ; investi 1000/1000
    expect($byName['#1']->value)->toBe([1000.0, 1200.0])
        ->and($byName['#1']->invested)->toBe([1000.0, 1000.0])
        // asset 2 acheté au 2026-02-01 : valeur 0 puis 5@50 = 250 ; investi 0/250
        ->and($byName['#2']->value)->toBe([0.0, 250.0])
        ->and($byName['#2']->invested)->toBe([0.0, 250.0]);
});

it('keeps sum of per-asset value equal to the total valuation (evolution invariant)', function () {
    $calc = new ValuationCalculator;
    $transactions = [tx('2026-01-01', 1, false, 10, 100), tx('2026-01-01', 2, false, 4, 25)];
    $prices = [new P(1, '2026-01-01', 110), new P(2, '2026-01-01', 30)];

    $series = $calc->evolution($transactions, $prices, ValuationRange::Max, ValuationGranularity::Day);
    $daily = $calc->calculateDaily($transactions, $prices);

    foreach ($series->labels as $i => $label) {
        $sum = collect($series->perAsset)->sum(fn ($s) => $s->value[$i]);
        expect(round($sum, 2))->toBe($daily->valuations[$i]);
    }
});

it('returns an empty evolution series without transactions', function () {
    expect((new ValuationCalculator)->evolution([], [], ValuationRange::Max, ValuationGranularity::Day))
        ->toEqual(\App\Contexts\Valuation\Datas\EvolutionSeriesData::empty());
});
```
(`use App\Contexts\Valuation\Datas\PriceRecordData as P;` est déjà en tête depuis le lot précédent.)
Run: `php artisan test --compact --filter=ValuationCalculator` → les cas `evolution` FAIL (nouvelle forme), le reste vert.

- [ ] **Step 5: `ValuationCalculator` — implémentation**

Mettre à jour le `use` : `use App\Contexts\Valuation\Datas\AssetSeriesData;` (garder `EvolutionSeriesData`). Ajouter le helper privé (près de `perAssetInvestedTimelines`) :
```php
/**
 * Timelines de quantité cumulée par asset (escalier sur les dates de transaction).
 *
 * @param  list<TransactionRecordData>  $transactions
 * @return array<int, list<array{date: string, value: float}>>
 */
private function perAssetQuantityTimelines(array $transactions): array
{
    usort($transactions, fn (TransactionRecordData $a, TransactionRecordData $b) => ($a->date <=> $b->date)
        ?: (($a->isSell ? 1 : 0) <=> ($b->isSell ? 1 : 0)));

    /** @var array<int, list<array{date: string, value: float}>> $quantities */
    $quantities = [];

    foreach ($transactions as $transaction) {
        $day = $transaction->date->format('Y-m-d');
        $assetId = $transaction->assetId;
        $quantities[$assetId] ??= [];
        $previous = end($quantities[$assetId]);
        $previousQty = $previous === false ? 0.0 : $previous['value'];
        $delta = $transaction->isSell ? -$transaction->quantity : $transaction->quantity;
        $quantities[$assetId][] = ['date' => $day, 'value' => $previousQty + $delta];
    }

    return $quantities;
}
```
Réécrire `evolution()` :
```php
public function evolution(
    array $transactions,
    array $prices,
    ValuationRange $range,
    ValuationGranularity $granularity,
): EvolutionSeriesData {
    if ($transactions === []) {
        return EvolutionSeriesData::empty();
    }

    $windowed = $this->windowAndAggregate(
        $this->calculateDaily($transactions, $prices),
        $range,
        $granularity,
    );

    if ($windowed->labels === []) {
        return EvolutionSeriesData::empty();
    }

    $investedTimelines = $this->perAssetInvestedTimelines($transactions);
    $quantityTimelines = $this->perAssetQuantityTimelines($transactions);

    /** @var array<int, list<array{date: string, value: float}>> $priceTimelines */
    $priceTimelines = [];
    foreach ($prices as $price) {
        $priceTimelines[$price->assetId][] = ['date' => $price->date, 'value' => $price->close];
    }
    foreach ($priceTimelines as &$entries) {
        usort($entries, fn (array $a, array $b): int => $a['date'] <=> $b['date']);
    }
    unset($entries);

    $perAsset = [];
    foreach ($investedTimelines as $assetId => $investedEntries) {
        $qtyEntries = $quantityTimelines[$assetId] ?? [];
        $priceEntries = $priceTimelines[$assetId] ?? [];

        $perAsset[] = new AssetSeriesData(
            assetId: $assetId,
            name: '#'.$assetId,
            value: array_map(
                fn (string $day): float => round($this->valueAtDate($qtyEntries, $day) * $this->valueAtDate($priceEntries, $day), 2),
                $windowed->labels,
            ),
            invested: array_map(
                fn (string $day): float => round($this->valueAtDate($investedEntries, $day), 2),
                $windowed->labels,
            ),
        );
    }

    return new EvolutionSeriesData(labels: $windowed->labels, perAsset: $perAsset);
}
```
Run: `php artisan test --compact --filter=ValuationCalculator` → PASS. Régression : `php artisan test --compact --filter=InvestedByAsset` → PASS (intouché).

- [ ] **Step 6: `BuildEvolutionSeries` — nouvelle forme + test**

Remplacer, dans `BuildEvolutionSeriesTest.php`, les assertions `perAsset` par la forme value+invested :
```php
    $data = app(BuildEvolutionSeries::class)($user->id, ValuationRange::Max, ValuationGranularity::Day);

    expect($data->labels)->toBe(['2026-01-01']);

    $byName = collect($data->perAsset)->keyBy('name');
    expect($byName)->toHaveKeys(['Apple', 'Amazon'])
        ->and($byName['Apple']->value)->toBe([1000.0])
        ->and($byName['Apple']->invested)->toBe([1000.0])
        ->and($byName['Amazon']->value)->toBe([250.0])
        ->and($byName['Amazon']->invested)->toBe([250.0]);
```
(le cas « empty » reste : `->perAsset)->toBe([])`.)
Mettre à jour `BuildEvolutionSeries.php` : le `use` `AssetInvestedSeriesData` devient `AssetSeriesData`, et le `array_map` reconstruit des `AssetSeriesData` en préservant `value` + `invested` :
```php
use App\Contexts\Valuation\Datas\AssetSeriesData;
```
```php
    $names = $this->directory->namesFor(array_map(
        fn (AssetSeriesData $serie): int => $serie->assetId,
        $raw->perAsset,
    ));

    $perAsset = array_map(
        fn (AssetSeriesData $serie): AssetSeriesData => new AssetSeriesData(
            assetId: $serie->assetId,
            name: $names[$serie->assetId] ?? $serie->name,
            value: $serie->value,
            invested: $serie->invested,
        ),
        $raw->perAsset,
    );

    return new EvolutionSeriesData($raw->labels, $perAsset);
```
Run: `php artisan test --compact --filter=BuildEvolutionSeries` → PASS.

- [ ] **Step 7: `DashboardPageTest` — nouvelle forme du prop**

Dans `tests/Feature/DashboardPageTest.php`, cas `defers the evolution series…`, remplacer le bloc `loadDeferredProps` par :
```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('evolutionSeries.labels', 1)
                ->has('evolutionSeries.perAsset', 1)
                ->where('evolutionSeries.perAsset.0.name', 'ACME')
                ->has('evolutionSeries.perAsset.0.value')
                ->has('evolutionSeries.perAsset.0.invested')
            )
```
Dans le cas `accepts range and granularity…`, remplacer le `loadDeferredProps` par :
```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('evolutionSeries.labels')
                ->has('evolutionSeries.perAsset')
            )
```
Run: `php artisan test --compact --filter=DashboardPageTest` → PASS.

- [ ] **Step 8: pint + commit**
```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Datas/AssetSeriesData.php app/Contexts/Valuation/Datas/AssetSeriesDataTest.php \
        app/Contexts/Valuation/Datas/EvolutionSeriesData.php app/Contexts/Valuation/Datas/EvolutionSeriesDataTest.php \
        app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php \
        app/Contexts/Valuation/Actions/BuildEvolutionSeries.php app/Contexts/Valuation/Actions/BuildEvolutionSeriesTest.php \
        tests/Feature/DashboardPageTest.php
git commit -m "feat: evolution expose la valeur de marché par titre"
```

---

### Task 2: Frontend — réécriture de `buildEvolutionChart`

**Files:**
- Modify: `resources/js/lib/chart.ts` (réécrire `buildEvolutionChart` ; supprimer `BAND_NAMES` ; garder `GREY_SCALE`/`GAIN_COLOR`/`LOSS_COLOR`/`VALUE_LINE_COLOR`)

**Interfaces:**
- Produces (export) :
```ts
type AssetSeries = { assetId: number; name: string; value: number[]; invested: number[] };
type EvolutionInput = { labels: string[]; perAsset: AssetSeries[]; hiddenIds: Set<number>; valueFormatter: (value: number) => string };
export function buildEvolutionChart(input: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions }
```
Aires empilées (pré-cumul) = **valeur** des actifs **visibles** ; pas de bande/ligne/légende ; tooltip = Valeur + Gain + valeur/titre visibles.

- [ ] **Step 1: Remplacer le bloc `buildEvolutionChart` (et ses consts) dans `chart.ts`**

Supprimer la ligne `const BAND_NAMES = [...]`. Garder `GREY_SCALE`, `VALUE_LINE_COLOR`, `GAIN_COLOR`, `LOSS_COLOR`. Remplacer le type `EvolutionInput` + toute la fonction `buildEvolutionChart` par :
```ts
type AssetSeries = { assetId: number; name: string; value: number[]; invested: number[] };

type EvolutionInput = {
    labels: string[];
    perAsset: AssetSeries[];
    hiddenIds: Set<number>;
    valueFormatter: (value: number) => string;
};

export function buildEvolutionChart({
    labels,
    perAsset,
    hiddenIds,
    valueFormatter,
}: EvolutionInput): { series: ApexAxisChartSeries; options: ApexOptions } {
    const visible = perAsset.filter((asset) => !hiddenIds.has(asset.assetId));
    const point = (i: number, y: number): { x: string; y: number } => ({ x: labels[i], y });

    const cumulative: number[][] = visible.map((_, k) =>
        labels.map((_label, i) => visible.slice(0, k + 1).reduce((sum, asset) => sum + (asset.value[i] ?? 0), 0)),
    );

    const series: ApexAxisChartSeries = [];
    const colors: string[] = [];
    for (let k = visible.length - 1; k >= 0; k -= 1) {
        series.push({
            name: visible[k].name,
            type: 'area',
            data: labels.map((_label, i) => point(i, cumulative[k][i])),
        });
        colors.push(GREY_SCALE[k % GREY_SCALE.length]);
    }

    const options: ApexOptions = {
        chart: { type: 'area', toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit', animations: { enabled: false } },
        colors,
        stroke: { curve: 'smooth', width: 0 },
        fill: { type: 'solid', opacity: 0.9 },
        dataLabels: { enabled: false },
        markers: { size: 0 },
        grid: { borderColor: 'rgba(128,128,128,0.15)', strokeDashArray: 4 },
        xaxis: {
            type: 'datetime',
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { hideOverlappingLabels: true, style: { colors: 'oklch(0.708 0 0)' } },
        },
        yaxis: {
            labels: {
                formatter: (v: number): string => (v == null || !Number.isFinite(v) ? '' : valueFormatter(v)),
                style: { colors: 'oklch(0.708 0 0)' },
            },
        },
        legend: { show: false },
        responsive: LEGEND_BELOW_ON_MOBILE,
        tooltip: {
            shared: true,
            intersect: false,
            custom: ({ dataPointIndex }): string => {
                const i = dataPointIndex;
                const totalValue = visible.reduce((sum, asset) => sum + (asset.value[i] ?? 0), 0);
                const totalInvested = visible.reduce((sum, asset) => sum + (asset.invested[i] ?? 0), 0);
                const gain = totalValue - totalInvested;
                const gainColor = gain >= 0 ? GAIN_COLOR : LOSS_COLOR;
                const gainSign = gain >= 0 ? '+' : '−';

                const row = (color: string, label: string, text: string): string =>
                    `<div class="apexcharts-tooltip-series-group apexcharts-active" style="display: flex;">`
                    + `<span class="apexcharts-tooltip-marker" style="background-color: ${color};"></span>`
                    + `<div class="apexcharts-tooltip-text" style="font-family: inherit; font-size: 12px;">`
                    + `<div class="apexcharts-tooltip-y-group">`
                    + `<span class="apexcharts-tooltip-text-y-label">${label}: </span>`
                    + `<span class="apexcharts-tooltip-text-y-value">${text}</span>`
                    + `</div></div></div>`;

                const header = `<div class="apexcharts-tooltip-title" style="font-family: inherit; font-size: 12px;">${formatTooltipDate(labels[i])}</div>`;
                const valueRow = row(VALUE_LINE_COLOR, 'Valeur', valueFormatter(totalValue));
                const gainRow = row(gainColor, gain >= 0 ? 'Gain' : 'Perte', `${gainSign} ${valueFormatter(Math.abs(gain))}`);
                const assetRows = visible
                    .map((asset, k) => row(GREY_SCALE[k % GREY_SCALE.length], asset.name, valueFormatter(asset.value[i] ?? 0)))
                    .join('');

                return header + valueRow + gainRow + assetRows;
            },
        },
    };

    return { series, options };
}
```

- [ ] **Step 2: build + typecheck**

Run: `bun run build` → `✓ built`. Si présent : `bun run typecheck` → clean. (Pas de runner JS ; validation runtime en Task 3.)

- [ ] **Step 3: commit**
```bash
git add resources/js/lib/chart.ts
git commit -m "feat: buildEvolutionChart empile la valeur par titre, filtré par visibilité"
```

---

### Task 3: Frontend — visibilité pilotée par la table Positions

**Files:**
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `tests/Browser/DashboardChartLegendTest.php`

**Interfaces:**
- Consumes: `buildEvolutionChart` (Task 2), prop `evolutionSeries { labels, perAsset: {assetId,name,value[],invested[]}[] }` (Task 1).
- Produces: état `hiddenAssetIds: Set<number>` + colonne œil dans la table Positions couplant visibilité et graph par `assetId`.

- [ ] **Step 1: Maj du test browser**

Dans `tests/Browser/DashboardChartLegendTest.php` : supprimer `->assertSee('Valeur')` ; remplacer `->assertCount('.apx-legend-position-left', 2)` par `->assertCount('.apx-legend-position-left', 1)` (seul le donut a une légende à gauche). Conserver `assertSee('Évolution')`, `assertSee('Répartition')`, `assertSee('GLOBEX')`, `assertNoJavaScriptErrors()`.

- [ ] **Step 2: `<script setup>` — interface, état, chart**

- Ajouter l'import icônes : `import { Eye, EyeOff } from 'lucide-vue-next';`
- Remplacer l'interface `EvolutionSeries` par :
```ts
interface EvolutionSeries {
    labels: string[];
    perAsset: { assetId: number; name: string; value: number[]; invested: number[] }[];
}
```
- Après les `ref` existants, ajouter l'état de visibilité :
```ts
const hiddenAssetIds = ref<Set<number>>(new Set());

const toggleAsset = (assetId: number): void => {
    const next = new Set(hiddenAssetIds.value);
    if (next.has(assetId)) {
        next.delete(assetId);
    } else {
        next.add(assetId);
    }
    hiddenAssetIds.value = next;
};
```
- Remplacer le computed `evolutionChart` par :
```ts
const evolutionChart = computed(() =>
    buildEvolutionChart({
        labels: props.evolutionSeries?.labels ?? [],
        perAsset: props.evolutionSeries?.perAsset ?? [],
        hiddenIds: hiddenAssetIds.value,
        valueFormatter: eur,
    }),
);
```
(`hasEvolution` reste inchangé : basé sur `evolutionSeries?.labels.length`.)

- [ ] **Step 3: `<template>` — colonne œil dans la table Positions**

Dans la `<Table>` Positions, ajouter une colonne œil en tête. En-tête : ajouter en **première** position `<TableHead class="w-10"></TableHead>`. Dans le `<TableRow v-for=...>`, ajouter en première cellule :
```vue
                                    <TableCell class="w-10">
                                        <button
                                            type="button"
                                            class="text-muted-foreground transition-colors hover:text-foreground"
                                            :aria-label="hiddenAssetIds.has(line.assetId) ? 'Afficher' : 'Masquer'"
                                            @click="toggleAsset(line.assetId)"
                                        >
                                            <EyeOff v-if="hiddenAssetIds.has(line.assetId)" class="size-4" />
                                            <Eye v-else class="size-4" />
                                        </button>
                                    </TableCell>
```
Et griser le nom si masqué — sur la cellule `Actif`, lier une classe :
```vue
                                    <TableCell class="font-medium" :class="hiddenAssetIds.has(line.assetId) ? 'opacity-40' : ''">
```
(le graph « Évolution » n'a plus de légende ; rien d'autre à retirer côté template.)

- [ ] **Step 4: build**

Run: `bun run build` → `✓ built`. Corriger toute erreur TS minimalement.

- [ ] **Step 5: Vérification runtime (contrôleur, Playwright)**

`/dashboard` desktop (1100px) + mobile (420px) :
1. Graph = aires grises empilées de **valeur** ; **aucune légende** ; sommet = valeur totale.
2. Clic sur l'œil d'une ligne Positions → l'aire de ce titre disparaît, le sommet baisse ; l'œil devient barré et le nom se grise. Re-clic → réapparaît.
3. Tooltip : date + Valeur (somme visibles) + Gain/Perte (vert/rouge) + valeur par titre visible ; les valeurs Valeur/Gain baissent quand un titre est masqué.
4. `document.querySelectorAll('.apexcharts-canvas').length === 2` ; `0` erreur console au hover.

- [ ] **Step 6: test browser**

Run: `php artisan test --compact --filter=DashboardChartLegend` → PASS.

- [ ] **Step 7: commit**
```bash
git add resources/js/Pages/Dashboard.vue tests/Browser/DashboardChartLegendTest.php
git commit -m "feat: visibilité des titres du graph pilotée par une colonne œil (Positions)"
```

---

## Notes / risques

- **Réactivité du `Set`** : `toggleAsset` réassigne `hiddenAssetIds.value = new Set(...)` pour déclencher le recalcul du computed (muter en place ne suffirait pas).
- **Couplage `assetId`** : la table Positions (`overview.holdings[].assetId`) et `perAsset[].assetId` référencent le même id d'instrument. Un titre entièrement vendu (dans `perAsset` mais pas dans `holdings`) n'a pas de ligne → reste visible (accepté, hors périmètre).
- **Invariant valeur** : `Σ perAsset.value == valorisation totale` tient car la valeur par actif utilise le même `qty(t)×lastClose(t)` que `calculateDaily`, aux arrondis 2 déc. près (test avec fixtures rondes).
- Aires en gris (décision conservée) ; sans légende, l'identification titre↔aire passe par le tooltip et la table.

## Self-review (fait)

- Couverture spec : valeur par actif backend (T1: DTO `AssetSeriesData`, `evolution`, invariant), reshape `EvolutionSeriesData` + wiring action/controller/tests (T1), chart valeur+visibilité sans légende/bande/ligne (T2), état + colonne œil + couplage assetId + tests browser (T3). ✓
- Placeholders : aucun (code complet). ✓
- Cohérence types : `AssetSeriesData{assetId,name,value[],invested[]}` identique backend → interface Vue → `AssetSeries` de `buildEvolutionChart` ; `EvolutionSeriesData{labels,perAsset}` cohérent T1→T3. ✓
- Non-régression : `investedByAsset`/`AssetInvestedSeriesData`/`BuildInvestedByAssetSeries` intouchés (nouveau DTO séparé). ✓
