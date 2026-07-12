# Perfs portefeuille en haut du dashboard — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher, en haut du dashboard, un bloc « Gain / perte » + une row de pills de perfs portefeuille par période (YTD, mois, années), comme sur la fiche instrument.

**Architecture:** La logique « périodes depuis une série quotidienne » est extraite de `BuildAssetPerformances` vers `ValuationCalculator::trailingPerformances()`. Une nouvelle action `BuildPortfolioPerformances` construit la série quotidienne agrégée (toutes transactions, prix de tous les assets) et délègue à cette méthode. Le dashboard consomme le résultat via un prop Inertia déféré.

**Tech Stack:** Laravel 12 (DDD contexts), Inertia v3 + Vue 3 / TypeScript, ApexCharts, Pest 4, Tailwind v4.

## Global Constraints

- Tout texte visible utilisateur en **français**.
- Pas de `env()` hors config ; Eloquent plutôt que `DB::`.
- PHP : accolades obligatoires, types de retour explicites, promotion de constructeur.
- Après modif PHP : `vendor/bin/pint --dirty --format agent`.
- Tests via Pest, co-localisés dans `app/Contexts/**/` pour les contextes (voir siblings), ou `tests/Feature` pour les pages.
- DTO : `readonly implements JsonSerializable` (calqué sur `ValuationSeriesData`).

---

### Task 1: Extraire `trailingPerformances` + renommer le DTO

**Files:**
- Rename: `app/Contexts/Valuation/Datas/AssetPerformanceData.php` → `app/Contexts/Valuation/Datas/PerformanceData.php`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php`
- Modify: `app/Contexts/Valuation/Actions/BuildAssetPerformances.php`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`

**Interfaces:**
- Produces: `App\Contexts\Valuation\Datas\PerformanceData` (positional ctor `(string $key, string $label, ?float $pct)`), et `ValuationCalculator::trailingPerformances(ValuationSeriesData $daily): array` (`list<PerformanceData>`).
- Consumes: `ValuationCalculator::returnOverWindow(ValuationSeriesData, string): ?float` (existant), `ValuationCalculator::calculateDaily(...)` (existant).

- [ ] **Step 1: Renommer le DTO**

Renommer le fichier et la classe :

```bash
git mv app/Contexts/Valuation/Datas/AssetPerformanceData.php app/Contexts/Valuation/Datas/PerformanceData.php
```

Contenu de `app/Contexts/Valuation/Datas/PerformanceData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class PerformanceData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public ?float $pct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'pct' => $this->pct,
        ];
    }
}
```

- [ ] **Step 2: Écrire le test `trailingPerformances` (échec attendu)**

Ajouter à la fin de `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` :

```php
it('builds trailing performances: YTD, monthly, then one card per full year', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2023-01-01', '2023-07-01', '2024-07-01', '2025-07-01', '2026-01-01', '2026-04-01', '2026-06-01', '2026-07-01'],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1200.0],
        [1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0, 1000.0],
        [100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['YTD', '1M', '3M', '6M', '1Y', '2Y', '3Y'])
        ->and(array_map(fn ($perf) => $perf->label, $performances))->toBe(['YTD', '1 mois', '3 mois', '6 mois', '1 an', '2 ans', '3 ans'])
        ->and($performances[0]->pct)->toBe(20.0)
        ->and($performances[6]->pct)->toBe(20.0);
});

it('returns no trailing performances for an empty series', function () {
    expect((new ValuationCalculator)->trailingPerformances(App\Contexts\Valuation\Datas\ValuationSeriesData::empty()))->toBe([]);
});
```

- [ ] **Step 3: Lancer le test (échec)**

Run: `php artisan test --compact --filter='trailing performances'`
Expected: FAIL — `Call to undefined method ...::trailingPerformances()`.

- [ ] **Step 4: Ajouter `trailingPerformances` au calculateur**

Dans `app/Contexts/Valuation/Services/ValuationCalculator.php`, ajouter l'import en tête :

```php
use App\Contexts\Valuation\Datas\PerformanceData;
```

Puis ajouter la méthode (par ex. juste après `returnOverWindow`) :

```php
/**
 * Perfs de position par période sur la série quotidienne : YTD, 1/3/6 mois,
 * puis une card par année pleine jusqu'au premier jour de la série.
 *
 * @return list<PerformanceData>
 */
public function trailingPerformances(ValuationSeriesData $daily): array
{
    if ($daily->labels === []) {
        return [];
    }

    $anchor = Carbon::parse($daily->labels[count($daily->labels) - 1]);
    $firstDay = $daily->labels[0];

    $performances = [
        new PerformanceData('YTD', 'YTD', $this->returnOverWindow($daily, $anchor->copy()->startOfYear()->format('Y-m-d'))),
    ];

    foreach ([['1M', '1 mois', 1], ['3M', '3 mois', 3], ['6M', '6 mois', 6]] as [$key, $label, $months]) {
        $boundary = $anchor->copy()->subMonthsNoOverflow($months)->format('Y-m-d');
        $performances[] = new PerformanceData($key, $label, $this->returnOverWindow($daily, $boundary));
    }

    $fullYears = 0;
    while ($anchor->copy()->subYearsNoOverflow($fullYears + 1)->format('Y-m-d') >= $firstDay) {
        $fullYears++;
    }

    for ($year = 1; $year <= $fullYears; $year++) {
        $boundary = $anchor->copy()->subYearsNoOverflow($year)->format('Y-m-d');
        $performances[] = new PerformanceData(
            $year.'Y',
            $year === 1 ? '1 an' : $year.' ans',
            $this->returnOverWindow($daily, $boundary),
        );
    }

    return $performances;
}
```

- [ ] **Step 5: Simplifier `BuildAssetPerformances`**

Remplacer intégralement `app/Contexts/Valuation/Actions/BuildAssetPerformances.php` par :

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildAssetPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<PerformanceData> */
    public function __invoke(int $userId, int $assetId): array
    {
        $transactions = array_values(array_filter(
            $this->transactions->forUser($userId),
            fn (TransactionRecordData $transaction) => $transaction->assetId === $assetId,
        ));

        if ($transactions === []) {
            return [];
        }

        $prices = $this->prices->forAssetsSince([$assetId], $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->trailingPerformances($daily);
    }
}
```

- [ ] **Step 6: Pint + lancer les tests concernés**

Run:
```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter='ValuationCalculator|BuildAssetPerformances'
```
Expected: PASS (les anciens tests de `BuildAssetPerformances` restent verts — comportement inchangé — + nouveaux tests `trailing performances`).

- [ ] **Step 7: Commit**

```bash
git add app/Contexts/Valuation/
git commit -m "refactor: extrait trailingPerformances dans ValuationCalculator + renomme PerformanceData"
```

---

### Task 2: Action `BuildPortfolioPerformances`

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php`
- Test: `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php`

**Interfaces:**
- Consumes: `TransactionHistoryPort::forUser(int): list<TransactionRecordData>` (trié par date), `PriceHistoryPort::forAssetsSince(list<int>, Carbon): list<PriceRecordData>`, `ValuationCalculator::calculateDaily(...)`, `ValuationCalculator::trailingPerformances(...)` (Task 1).
- Produces: `BuildPortfolioPerformances::__invoke(int $userId): array` (`list<PerformanceData>`).

- [ ] **Step 1: Écrire le test (échec attendu)**

Créer `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;

it('aggregates two holdings into portfolio trailing performances', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $a = Instrument::factory()->create();
    $b = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $a->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $b->id,
        'quantity' => 5, 'unit_price' => 100, 'date' => '2024-01-01',
    ]);
    foreach (['2024-01-01', '2024-07-01', '2025-01-01', '2025-07-01', '2026-01-01', '2026-04-01', '2026-06-01'] as $date) {
        Price::factory()->create(['asset_id' => $a->id, 'date' => $date, 'close' => 100]);
        Price::factory()->create(['asset_id' => $b->id, 'date' => $date, 'close' => 100]);
    }
    Price::factory()->create(['asset_id' => $a->id, 'date' => '2026-07-01', 'close' => 120]);
    Price::factory()->create(['asset_id' => $b->id, 'date' => '2026-07-01', 'close' => 120]);

    $performances = app(BuildPortfolioPerformances::class)($user->id);

    // Portefeuille début 2026-07-01: (10+5)*100 = 1500 ; fin (10+5)*120 = 1800.
    // Historique 2024-01-01 -> 2026-07-01 => 2 années pleines.
    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['YTD', '1M', '3M', '6M', '1Y', '2Y'])
        ->and($performances[0]->key)->toBe('YTD')
        ->and($performances[5]->pct)->toBe(20.0);
});

it('returns no performances without any transaction', function () {
    $user = User::factory()->create();

    expect(app(BuildPortfolioPerformances::class)($user->id))->toBe([]);
});
```

- [ ] **Step 2: Lancer le test (échec)**

Run: `php artisan test --compact --filter=BuildPortfolioPerformances`
Expected: FAIL — classe `BuildPortfolioPerformances` introuvable.

- [ ] **Step 3: Créer l'action**

Créer `app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php` :

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Valuation\Datas\PerformanceData;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

class BuildPortfolioPerformances
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
    ) {}

    /** @return list<PerformanceData> */
    public function __invoke(int $userId): array
    {
        $transactions = $this->transactions->forUser($userId);

        if ($transactions === []) {
            return [];
        }

        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction) => $transaction->assetId,
            $transactions,
        )));

        $prices = $this->prices->forAssetsSince($assetIds, $transactions[0]->date);
        $daily = $this->calculator->calculateDaily($transactions, $prices);

        return $this->calculator->trailingPerformances($daily);
    }
}
```

- [ ] **Step 4: Pint + lancer le test (succès)**

Run:
```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=BuildPortfolioPerformances
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php
git commit -m "feat: action BuildPortfolioPerformances (perfs portefeuille par periode)"
```

---

### Task 3: Dashboard — prop déféré, headline + pills, retrait des KPI

**Files:**
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.vue`
- Test: `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Consumes: `BuildPortfolioPerformances::__invoke(int): list<PerformanceData>` (Task 2), sérialisé en `{ key, label, pct }`.
- Produces: prop Inertia déféré `performances`.

- [ ] **Step 1: Écrire le test feature (échec attendu)**

Ajouter à `tests/Feature/DashboardPageTest.php` :

```php
it('defers the portfolio performances and loads them on demand', function () {
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-01-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('performances')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('performances')
                ->where('performances.0.key', 'YTD')
            )
        );
});
```

- [ ] **Step 2: Lancer le test (échec)**

Run: `php artisan test --compact --filter='defers the portfolio performances'`
Expected: FAIL — `performances` absent après `loadDeferredProps`.

- [ ] **Step 3: Ajouter le prop déféré au controller**

Dans `app/Contexts/Portfolio/Http/DashboardController.php`, ajouter l'import :

```php
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
```

Puis dans le tableau `Inertia::render('Dashboard', [...])`, après `'overview' => $overview,` :

```php
'performances' => Inertia::defer(fn () => $user !== null
    ? app(BuildPortfolioPerformances::class)($user->id)
    : []),
```

- [ ] **Step 4: Lancer le test feature (succès)**

Run: `php artisan test --compact --filter='defers the portfolio performances'`
Expected: PASS.

- [ ] **Step 5: Frontend — interface + prop**

Dans `resources/js/Pages/Dashboard.vue`, ajouter l'interface près des autres interfaces :

```ts
interface Performance {
    key: string;
    label: string;
    pct: number | null;
}
```

Et dans le `defineProps<{...}>()`, ajouter la ligne `performances?: Performance[];` (à côté de `valuationSeries?`).

- [ ] **Step 6: Frontend — bloc headline + pills en haut**

Dans `<template>`, juste après le `<header>...</header>` et **avant** la première `<Card>` (Évolution), insérer :

```html
<section v-if="overview.holdings.length" class="flex flex-col gap-3">
    <div class="flex flex-col gap-0.5">
        <p class="text-sm text-muted-foreground">Gain / perte</p>
        <p class="text-2xl font-semibold" :class="gainClass(overview.totalGain)">
            {{ eur(overview.totalGain) }}
            <span class="text-sm">({{ pct(overview.totalGainPct) }})</span>
        </p>
        <p class="text-sm text-muted-foreground">Valeur totale {{ eur(overview.totalValue) }}</p>
    </div>

    <Deferred data="performances">
        <template #fallback>
            <div class="-mx-6 overflow-x-hidden px-6">
                <div class="flex min-w-max gap-2">
                    <div v-for="n in 6" :key="n" class="h-[52px] w-[64px] animate-pulse rounded-md bg-muted"></div>
                </div>
            </div>
        </template>

        <div v-if="performances && performances.length" class="-mx-6 overflow-x-auto px-6">
            <div class="flex min-w-max gap-2">
                <div
                    v-for="perf in performances"
                    :key="perf.key"
                    class="flex min-w-[64px] flex-col gap-0.5 rounded-md border border-border px-3 py-2"
                >
                    <span class="text-xs text-muted-foreground">{{ perf.label }}</span>
                    <span class="text-sm font-medium" :class="gainClass(perf.pct)">{{ pct(perf.pct) }}</span>
                </div>
            </div>
        </div>
    </Deferred>
</section>
```

- [ ] **Step 7: Frontend — retirer les 3 cards KPI**

Supprimer entièrement la `<section class="grid gap-4 sm:grid-cols-3">...</section>` contenant les cards « Valeur totale », « Gains / pertes » et « Rendement ».

- [ ] **Step 8: Build**

Run: `bun run build`
Expected: `✓ built` sans erreur TypeScript.

- [ ] **Step 9: Vérif visuelle (mobile 390px)**

Ouvrir le dashboard via l'URL Herd (`https://argent.test/dashboard`) en viewport 390px. Vérifier : headline « Gain / perte » + « Valeur totale », skeleton pulsant puis row de pills scrollable (YTD en premier), et `document.documentElement.scrollWidth - clientWidth === 0` (pas de débordement horizontal).

- [ ] **Step 10: Commit**

```bash
git add app/Contexts/Portfolio/Http/DashboardController.php resources/js/Pages/Dashboard.vue tests/Feature/DashboardPageTest.php
git commit -m "feat: dashboard affiche Gain/perte + perfs portefeuille par periode en haut"
```

---

## Self-Review

- **Spec coverage** : rename DTO (T1), extract `trailingPerformances` (T1), simplifier action asset (T1), `BuildPortfolioPerformances` (T2), controller déféré (T3), frontend headline+pills+retrait KPI (T3), tests calculator/portfolio/feature (T1–T3). Le test asset existant reste vert (T1 step 6). ✔
- **Placeholders** : aucun — code complet à chaque step. ✔
- **Type consistency** : `PerformanceData(key,label,pct)` positionnel utilisé partout ; `trailingPerformances(ValuationSeriesData): list<PerformanceData>` cohérent entre T1/T2 ; prop `performances` cohérent controller/Vue/test. ✔
