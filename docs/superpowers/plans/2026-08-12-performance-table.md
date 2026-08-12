# Tableau des performances par période — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer la ligne de cartes « Performance par période » par un tableau une-ligne-par-période exposant les grandeurs déjà calculées mais jetées (date de début, valeur de début, apports nets, gain €), via un composant Vue unique partagé par le dashboard et la fiche titre.

**Architecture :** `ValuationCalculator::returnOverWindow()` retourne un DTO riche `PerformanceWindowData` au lieu d'un `?float` ; `trailingPerformances()` en dérive des `PerformanceData` à sept champs et **omet** les périodes non couvertes par l'historique (plus de `pct` null, plus aucun champ nullable). Côté front, les helpers de formatage dupliqués entre les deux pages sont extraits dans `lib/format.ts`, et un composant `PerformanceTable.vue` rend le tableau pour les deux pages.

**Tech Stack :** PHP 8.4 / Laravel 12 (contexts DDD sous `app/Contexts`), Pest 4 (tests unitaires, feature et navigateur `visit()`), Inertia v3 + Vue 3 `<script setup lang="ts">`, Tailwind v4, composants shadcn-vue locaux sous `resources/js/components/ui`, build via `bun run build`.

**Spec :** `docs/superpowers/specs/2026-08-12-performance-table-design.md`

## Global Constraints

- Tout texte visible par l'utilisateur est en **français**, accentué correctement.
- Aucune nouvelle dépendance Composer ou npm.
- Tests PHP : `php artisan test --compact --filter=<motif>` ; le suite complète : `php artisan test --compact`.
- Les tests navigateur (`tests/Browser`) exigent un front construit : lancer `bun run build` avant.
- Après toute modification PHP : `vendor/bin/pint --dirty --format agent`.
- Un commit par tâche, message en français, préfixe conventionnel (`feat:`, `refactor:`, `test:`).
- Ne pas supprimer de test existant ; les adapter quand le comportement change volontairement.
- Les DTO du contexte `Valuation` sont des `readonly class` avec promotion de constructeur et types explicites (voir `app/Contexts/Valuation/Datas/`).

## File Structure

**Créés**
- `app/Contexts/Valuation/Datas/PerformanceWindowData.php` — retour interne de `returnOverWindow()` : les cinq grandeurs de la fenêtre. Non sérialisé (jamais envoyé au front).
- `resources/js/lib/format.ts` — `eur()`, `pct()`, `frDate()`, `gainClass()`, partagés par les pages et le composant.
- `resources/js/lib/performance.ts` — l'interface TypeScript `Performance` (miroir de `PerformanceData`), importée par le composant et les deux pages.
- `resources/js/components/PerformanceTable.vue` — le tableau, sans état interne, une seule prop obligatoire.
- `tests/Browser/PerformanceTableTest.php` — rendu du tableau sur le dashboard.

**Modifiés**
- `app/Contexts/Valuation/Services/ValuationCalculator.php:169-200` (`returnOverWindow`) et `:244-282` (`trailingPerformances`).
- `app/Contexts/Valuation/Datas/PerformanceData.php` — quatre champs de plus.
- `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`, `app/Contexts/Valuation/Actions/BuildAssetPerformancesTest.php`, `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php`.
- `tests/Feature/DashboardPageTest.php`, `tests/Feature/InstrumentDetailPageTest.php`, `tests/Browser/DashboardPerformanceInfoTest.php`.
- `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Instruments/Show.vue`, `resources/js/components/PerformanceInfoDialog.vue`.

**Écart assumé vs la spec :** la spec proposait de réutiliser le wrapper `-mx-6 overflow-x-auto px-6` des cartes. `components/ui/table/Table.vue` enveloppe déjà son `<table>` dans un `div.relative.w-full.overflow-auto` : imbriquer deux conteneurs scrollables donnerait deux barres de scroll. On garde donc le seul scroll du composant `Table`, comme le fait déjà la table des positions du dashboard.

---

### Task 1 : `PerformanceWindowData` — `returnOverWindow` retourne les cinq grandeurs

**Files:**
- Create: `app/Contexts/Valuation/Datas/PerformanceWindowData.php`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php:169-200`
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php:260-304`

**Interfaces:**
- Consumes: `App\Contexts\Valuation\Datas\ValuationSeriesData` (existant : `labels`, `valuations`, `invested`, `prices`, toutes des `list`).
- Produces: `ValuationCalculator::returnOverWindow(ValuationSeriesData $daily, string $boundary): ?PerformanceWindowData` avec
  `PerformanceWindowData{string $startDate, float $valueStart, float $contributions, float $pnl, float $pct}`.

- [ ] **Step 1 : Écrire les tests qui échouent**

Dans `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`, remplacer les quatre tests des lignes 260-304 par ceci (les deux cas `null` sont conservés à l'identique, les deux autres passent par `->pct`, et un cinquième test couvre les nouveaux champs) :

```php
it('computes the window return excluding contributions', function () {
    // Début 1000, apport de 200 pendant la fenêtre, fin 1400 => (1400 - 1000 - 200) / 1000 = +20%.
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 1250.0, 1400.0],
        [1000.0, 1200.0, 1200.0],
        [100.0, 110.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01')->pct)->toBe(20.0);
});

it('exposes the window start, value, contributions and gain', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 1250.0, 1400.0],
        [1000.0, 1200.0, 1200.0],
        [100.0, 110.0, 120.0],
    );

    $window = (new ValuationCalculator)->returnOverWindow($daily, '2026-01-01');

    expect($window->startDate)->toBe('2026-01-01')
        ->and($window->valueStart)->toBe(1000.0)
        ->and($window->contributions)->toBe(200.0)
        ->and($window->pnl)->toBe(200.0)
        ->and($window->pct)->toBe(20.0);
});

it('anchors the window start on the last day at or before the boundary', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-01-15', '2026-03-01'],
        [1000.0, 2000.0, 3000.0],
        [1000.0, 1000.0, 1000.0],
        [10.0, 20.0, 30.0],
    );

    // Boundary 2026-02-01 => début pris au 2026-01-15 (valeur 2000) : (3000 - 2000) / 2000 = +50%.
    $window = (new ValuationCalculator)->returnOverWindow($daily, '2026-02-01');

    expect($window->pct)->toBe(50.0)
        ->and($window->startDate)->toBe('2026-01-15')
        ->and($window->valueStart)->toBe(2000.0)
        ->and($window->contributions)->toBe(0.0);
});

it('returns null when the series does not reach the boundary', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-02-01', '2026-03-01'],
        [1000.0, 1200.0],
        [1000.0, 1000.0],
        [100.0, 120.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});

it('returns null when the starting value is zero', function () {
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-01-01', '2026-02-01'],
        [0.0, 500.0],
        [0.0, 0.0],
        [0.0, 50.0],
    );

    expect((new ValuationCalculator)->returnOverWindow($daily, '2026-01-01'))->toBeNull();
});
```

- [ ] **Step 2 : Lancer les tests pour les voir échouer**

Run: `php artisan test --compact --filter="window"`
Expected: FAIL — `Attempt to read property "pct" on float` sur les trois premiers tests, les deux `toBeNull()` passent.

- [ ] **Step 3 : Créer le DTO**

Créer `app/Contexts/Valuation/Datas/PerformanceWindowData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

/**
 * Résultat du calcul de performance sur une fenêtre : les grandeurs intermédiaires
 * du rendement, pas seulement le pourcentage.
 */
readonly class PerformanceWindowData
{
    public function __construct(
        public string $startDate,
        public float $valueStart,
        public float $contributions,
        public float $pnl,
        public float $pct,
    ) {}
}
```

- [ ] **Step 4 : Changer le retour de `returnOverWindow`**

Dans `app/Contexts/Valuation/Services/ValuationCalculator.php`, ajouter l'import
`use App\Contexts\Valuation\Datas\PerformanceWindowData;` (bloc d'imports lignes 5-15, ordre alphabétique : juste après `PerformanceData`) puis remplacer la méthode des lignes 169-200 par :

```php
    /**
     * Rendement de la position sur la fenêtre [$boundary, dernier jour], hors apports
     * (Modified-Dietz simplifié) : (valeur_fin - valeur_début - apports) / valeur_début.
     * Les apports sont l'évolution de l'investi cumulé sur la fenêtre. Retourne null si
     * la série ne remonte pas jusqu'à $boundary ou si la valeur de début est nulle.
     */
    public function returnOverWindow(ValuationSeriesData $daily, string $boundary): ?PerformanceWindowData
    {
        $startIndex = null;
        foreach ($daily->labels as $i => $label) {
            if ($label > $boundary) {
                break;
            }
            $startIndex = $i;
        }

        if ($startIndex === null) {
            return null;
        }

        $valueStart = $daily->valuations[$startIndex];

        if ($valueStart <= 0.0) {
            return null;
        }

        $last = count($daily->labels) - 1;
        $contributions = $daily->invested[$last] - $daily->invested[$startIndex];
        $pnl = ($daily->valuations[$last] - $valueStart) - $contributions;

        return new PerformanceWindowData(
            startDate: $daily->labels[$startIndex],
            valueStart: $valueStart,
            contributions: $contributions,
            pnl: $pnl,
            pct: $pnl / $valueStart * 100,
        );
    }
```

- [ ] **Step 5 : Adapter l'appelant interne**

`trailingPerformances()` appelle encore `returnOverWindow()` en attendant un `?float` (lignes ~259 et ~264 et ~277). Rendre le fichier cohérent **sans changer le comportement** : dans les trois emplacements, remplacer l'expression `$this->returnOverWindow($daily, $x)` par `$this->returnOverWindow($daily, $x)?->pct`. La Task 2 réécrira entièrement cette méthode.

- [ ] **Step 6 : Lancer les tests**

Run: `php artisan test --compact --filter="ValuationCalculator"`
Expected: PASS (tous les tests du fichier, y compris `trailing performances`).

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation/Datas/PerformanceWindowData.php app/Contexts/Valuation/Services/ValuationCalculator.php app/Contexts/Valuation/Services/ValuationCalculatorTest.php
git commit -m "refactor: returnOverWindow expose les grandeurs de la fenêtre"
```

---

### Task 2 : `PerformanceData` enrichi + périodes non couvertes omises

**Files:**
- Modify: `app/Contexts/Valuation/Datas/PerformanceData.php`
- Modify: `app/Contexts/Valuation/Services/ValuationCalculator.php` (`trailingPerformances`)
- Test: `app/Contexts/Valuation/Services/ValuationCalculatorTest.php:306-324`, `app/Contexts/Valuation/Actions/BuildAssetPerformancesTest.php`, `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php`, `tests/Feature/DashboardPageTest.php:107-129`, `tests/Feature/InstrumentDetailPageTest.php:11-34`, `tests/Browser/DashboardPerformanceInfoTest.php`

**Interfaces:**
- Consumes: `PerformanceWindowData` (Task 1).
- Produces: `PerformanceData{string $key, string $label, string $startDate, float $valueStart, float $contributions, float $gain, float $pct}`, sérialisé en JSON avec exactement ces sept clés. `trailingPerformances()` retourne `list<PerformanceData>` sans trou : une période dont la fenêtre est `null` est absente du résultat.

- [ ] **Step 1 : Écrire les tests qui échouent (calculateur)**

Dans `app/Contexts/Valuation/Services/ValuationCalculatorTest.php`, remplacer le test `builds trailing performances...` (ligne 306) par la version enrichie, et ajouter juste après un test sur les périodes non couvertes :

```php
it('builds trailing performances: YTD, monthly, then one row per full year', function () {
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
        ->and($performances[6]->pct)->toBe(20.0)
        ->and($performances[0]->startDate)->toBe('2026-01-01')
        ->and($performances[0]->valueStart)->toBe(1000.0)
        ->and($performances[0]->contributions)->toBe(0.0)
        ->and($performances[0]->gain)->toBe(200.0);
});

it('omits the periods the series does not cover', function () {
    // Série qui démarre le 2026-05-01 : ni le début d'année, ni 3 mois, ni 6 mois ne sont couverts.
    $daily = new App\Contexts\Valuation\Datas\ValuationSeriesData(
        ['2026-05-01', '2026-07-01'],
        [1000.0, 1200.0],
        [1000.0, 1000.0],
        [100.0, 120.0],
    );

    $performances = (new ValuationCalculator)->trailingPerformances($daily);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['1M'])
        ->and($performances[0]->startDate)->toBe('2026-05-01');
});
```

- [ ] **Step 2 : Lancer les tests pour les voir échouer**

Run: `php artisan test --compact --filter="trailing performances|omits the periods"`
Expected: FAIL — `Undefined property: PerformanceData::$startDate` sur le premier, et le second retourne `['YTD', '1M', '3M', '6M']` au lieu de `['1M']`.

- [ ] **Step 3 : Enrichir le DTO**

Remplacer entièrement `app/Contexts/Valuation/Datas/PerformanceData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

readonly class PerformanceData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
        public string $startDate,
        public float $valueStart,
        public float $contributions,
        public float $gain,
        public float $pct,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'startDate' => $this->startDate,
            'valueStart' => $this->valueStart,
            'contributions' => $this->contributions,
            'gain' => $this->gain,
            'pct' => $this->pct,
        ];
    }
}
```

- [ ] **Step 4 : Réécrire `trailingPerformances`**

Dans `app/Contexts/Valuation/Services/ValuationCalculator.php`, remplacer la méthode entière (docblock compris) par :

```php
    /**
     * Perfs de position par période sur la série quotidienne : YTD, 1/3/6 mois, puis une
     * ligne par année pleine jusqu'au premier jour de la série. Les périodes que la série
     * ne couvre pas sont absentes du résultat.
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

        /** @var list<array{0: string, 1: string, 2: string}> $windows */
        $windows = [['YTD', 'YTD', $anchor->copy()->startOfYear()->format('Y-m-d')]];

        foreach ([['1M', '1 mois', 1], ['3M', '3 mois', 3], ['6M', '6 mois', 6]] as [$key, $label, $months]) {
            $windows[] = [$key, $label, $anchor->copy()->subMonthsNoOverflow($months)->format('Y-m-d')];
        }

        $fullYears = 0;
        while ($anchor->copy()->subYearsNoOverflow($fullYears + 1)->format('Y-m-d') >= $firstDay) {
            $fullYears++;
        }

        for ($year = 1; $year <= $fullYears; $year++) {
            $windows[] = [
                $year.'Y',
                $year === 1 ? '1 an' : $year.' ans',
                $anchor->copy()->subYearsNoOverflow($year)->format('Y-m-d'),
            ];
        }

        $performances = [];

        foreach ($windows as [$key, $label, $boundary]) {
            $window = $this->returnOverWindow($daily, $boundary);

            if ($window === null) {
                continue;
            }

            $performances[] = new PerformanceData(
                key: $key,
                label: $label,
                startDate: $window->startDate,
                valueStart: $window->valueStart,
                contributions: $window->contributions,
                gain: $window->pnl,
                pct: $window->pct,
            );
        }

        return $performances;
    }
```

- [ ] **Step 5 : Lancer les tests du calculateur**

Run: `php artisan test --compact --filter="ValuationCalculator"`
Expected: PASS.

- [ ] **Step 6 : Adapter les tests des actions**

Dans `app/Contexts/Valuation/Actions/BuildAssetPerformancesTest.php` :

1. Dans le premier test, ajouter après `->and($performances[6]->pct)->toBe(20.0)` :

```php
        ->and($performances[0]->startDate)->toBe('2026-01-01')
        ->and($performances[0]->valueStart)->toBe(1000.0)
        ->and($performances[0]->contributions)->toBe(0.0)
        ->and($performances[0]->gain)->toBe(200.0);
```

(remplacer le `;` final de la chaîne d'expectations précédente par `.`-chaînage, c'est-à-dire retirer le point-virgule de la ligne `->and($performances[6]->pct)->toBe(20.0);`)

2. Le deuxième test (`shows only YTD and monthly cards when history is under a year`) change de nom **et** d'attente : la série ne contient que `2026-05-01` et `2026-07-01`, donc seule la fenêtre 1 mois est couverte. Le remplacer par :

```php
it('keeps only the periods the price history covers', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create();

    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id,
        'quantity' => 10, 'unit_price' => 100, 'date' => '2026-05-01',
    ]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-05-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);

    $performances = app(BuildAssetPerformances::class)($user->id, $asset->id);

    expect(array_map(fn ($perf) => $perf->key, $performances))->toBe(['1M']);
});
```

Dans `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php`, les clés attendues ne changent pas (la série couvre toutes les fenêtres). Ajouter au premier test, après `->and($performances[5]->pct)->toBe(20.0)` (retirer son point-virgule) :

```php
        ->and($performances[0]->valueStart)->toBe(1500.0)
        ->and($performances[0]->gain)->toBe(300.0)
        ->and($performances[0]->contributions)->toBe(0.0);
```

- [ ] **Step 7 : Lancer les tests des actions**

Run: `php artisan test --compact --filter="Performances"`
Expected: PASS.

- [ ] **Step 8 : Adapter les tests de page**

`tests/Feature/DashboardPageTest.php` : la fixture couvre déjà YTD (prix au 2026-01-01). Compléter l'assertion des lignes 125-126 :

```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('performances')
                ->where('performances.0.key', 'YTD')
                ->where('performances.0.startDate', '2026-01-01')
                ->has('performances.0.gain')
                ->has('performances.0.contributions')
                ->has('performances.0.valueStart')
            )
```

`tests/Feature/InstrumentDetailPageTest.php:11-34` : la fixture n'a qu'un seul prix (2026-07-01), donc aucune fenêtre n'est couverte et `performances` deviendrait vide. Ajouter un prix d'ouverture d'année pour que le test garde du sens. Après la ligne 16 (`Price::factory()->create([... '2026-07-01' ...])`), ajouter :

```php
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 80]);
```

Puis, dans le bloc `assertInertia`, garder `->has('performances', 4)` et `->where('performances.0.key', 'YTD')`, ajouter `->where('performances.0.startDate', '2026-01-01')`, et **corriger le compte de `priceHistory`** qui passe de 1 à 2 points :

```php
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('priceHistory.labels', 2)
            )
```

`tests/Browser/DashboardPerformanceInfoTest.php` : la fixture n'a qu'un prix (`now()`), donc le bloc « Performance par période » disparaîtrait et `assertSee('Performance par période')` échouerait. Ajouter un second prix daté du 1er janvier de l'année courante, juste après la ligne `Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);` :

```php
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now()->startOfYear(), 'close' => 80]);
```

- [ ] **Step 9 : Lancer les tests de page**

Run: `bun run build && php artisan test --compact --filter="DashboardPageTest|InstrumentDetailPageTest|DashboardPerformanceInfo"`
Expected: PASS.

- [ ] **Step 10 : Suite complète, Pint, commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add app/Contexts/Valuation tests/Feature tests/Browser
git commit -m "feat: expose gain, apports et valeur de début par période"
```

Expected: suite verte avant le commit.

---

### Task 3 : Extraction des helpers de formatage front

**Files:**
- Create: `resources/js/lib/format.ts`
- Create: `resources/js/lib/performance.ts`
- Modify: `resources/js/Pages/Dashboard.vue:1-58,110-125`
- Modify: `resources/js/Pages/Instruments/Show.vue:1-96,165-170`

**Interfaces:**
- Produces:
  - `eur(value: number | null, digits?: number): string` — `digits` par défaut `2` ; `null` rend `'—'`.
  - `pct(value: number | null): string` — signé, une décimale, suffixe ` %` ; `null` rend `'—'`.
  - `frDate(value: string): string` — `'2026-01-01'` → `'01/01/2026'`.
  - `gainClass(value: number | null): string` — classes Tailwind vert / rouge / neutre.
  - `interface Performance { key: string; label: string; startDate: string; valueStart: number; contributions: number; gain: number; pct: number }`.
- Aucun changement de comportement visible : cette tâche déplace du code identique.

- [ ] **Step 1 : Créer `resources/js/lib/format.ts`**

```ts
export const eur = (value: number | null, digits = 2): string =>
    value === null
        ? '—'
        : value.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: digits });

export const pct = (value: number | null): string =>
    value === null ? '—' : `${value >= 0 ? '+' : ''}${value.toFixed(1)} %`;

export const frDate = (value: string): string => {
    const date = new Date(`${value}T00:00:00`);

    return Number.isNaN(date.getTime())
        ? value
        : date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

export const gainClass = (value: number | null): string =>
    value === null || value === 0
        ? 'text-muted-foreground'
        : value > 0
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-red-600 dark:text-red-400';
```

- [ ] **Step 2 : Créer `resources/js/lib/performance.ts`**

```ts
export interface Performance {
    key: string;
    label: string;
    startDate: string;
    valueStart: number;
    contributions: number;
    gain: number;
    pct: number;
}
```

- [ ] **Step 3 : Brancher `Dashboard.vue`**

Dans le `<script setup>` :

1. Ajouter les imports après `import { buildEvolutionChart } from '@/lib/chart';` :

```ts
import { eur as formatEur, gainClass, pct } from '@/lib/format';
import type { Performance } from '@/lib/performance';
```

2. Supprimer l'`interface Performance` locale (lignes 47-51) — elle vient désormais de `@/lib/performance`.
3. Remplacer les trois helpers locaux (lignes 112-125) par un seul adaptateur, le dashboard affichant les montants sans décimale :

```ts
const eur = (value: number | null): string => formatEur(value, 0);
```

- [ ] **Step 4 : Brancher `Instruments/Show.vue`**

Dans le `<script setup>` :

1. Ajouter après `import { buildTimeSeriesOptions } from '@/lib/chart';` :

```ts
import { eur, gainClass, pct } from '@/lib/format';
import type { Performance } from '@/lib/performance';
```

2. Supprimer l'`interface AssetPerformance` locale (lignes 61-65) et changer le type de la prop en `performances: Performance[];`.
3. Supprimer les définitions locales de `eur` (lignes 90-93), `pct` (95-96) et `gainClass` (165-170). **Garder** `signedPct` et `base100`, spécifiques au graphe base 100.

- [ ] **Step 5 : Construire et vérifier**

Run: `bun run build`
Expected: build OK, aucune erreur TypeScript (`eur` utilisé sans second argument garde bien 2 décimales sur la fiche titre).

- [ ] **Step 6 : Tests navigateur de non-régression**

Run: `php artisan test --compact --filter="Browser"`
Expected: PASS — le rendu est identique, seuls les imports ont bougé.

- [ ] **Step 7 : Commit**

```bash
git add resources/js/lib/format.ts resources/js/lib/performance.ts resources/js/Pages/Dashboard.vue resources/js/Pages/Instruments/Show.vue
git commit -m "refactor: extrait les helpers de formatage dans lib/format"
```

---

### Task 4 : `PerformanceTable.vue` et remplacement des cartes

**Files:**
- Create: `resources/js/components/PerformanceTable.vue`
- Create: `tests/Browser/PerformanceTableTest.php`
- Modify: `resources/js/Pages/Dashboard.vue:165-192`
- Modify: `resources/js/Pages/Instruments/Show.vue:250-261`

**Interfaces:**
- Consumes: `Performance`, `eur`, `pct`, `frDate`, `gainClass` (Task 3) ; les sept champs sérialisés par `PerformanceData` (Task 2).
- Produces: composant `<PerformanceTable :performances="Performance[]" :currency-digits="number" />` (`currencyDigits` optionnel, défaut `2`). Son conteneur racine porte `data-testid="performance-table"`.

- [ ] **Step 1 : Écrire le test navigateur qui échoue**

Créer `tests/Browser/PerformanceTableTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('renders the period performances as a table', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the controller resolves this user.
    User::query()->delete();
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['name' => 'ACME', 'ticker' => 'ACM']);

    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 120]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 100,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'unit_price' => 100,
        'date' => '2026-01-01',
    ]);

    $this->actingAs($user);

    $page = visit('/');

    $page->assertSee('Performance par période')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-testid=performance-table] thead th')).map(th => th.textContent.trim()).join('|')",
            'Période|Depuis|Valeur début|Apports|Gain|Perf.',
        )
        ->assertScript(
            "document.querySelectorAll('[data-testid=performance-table] tbody tr').length",
            4,
        )
        ->assertScript(
            "document.querySelector('[data-testid=performance-table] tbody tr td').textContent.trim()",
            'YTD',
        )
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2 : Lancer le test pour le voir échouer**

Run: `bun run build && php artisan test --compact --filter="renders the period performances as a table"`
Expected: FAIL — le sélecteur `[data-testid=performance-table]` ne trouve rien (les cartes sont encore en place).

- [ ] **Step 3 : Créer le composant**

Créer `resources/js/components/PerformanceTable.vue` :

```vue
<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { eur, frDate, gainClass, pct } from '@/lib/format';
import type { Performance } from '@/lib/performance';

const props = withDefaults(
    defineProps<{ performances: Performance[]; currencyDigits?: number }>(),
    { currencyDigits: 2 },
);

const amount = (value: number): string => eur(value, props.currencyDigits);

const signedAmount = (value: number): string => (value > 0 ? `+${amount(value)}` : amount(value));
</script>

<template>
    <Table data-testid="performance-table">
        <TableHeader>
            <TableRow>
                <TableHead>Période</TableHead>
                <TableHead class="text-right">Depuis</TableHead>
                <TableHead class="text-right">Valeur début</TableHead>
                <TableHead class="text-right">Apports</TableHead>
                <TableHead class="text-right">Gain</TableHead>
                <TableHead class="text-right">Perf.</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-for="perf in props.performances" :key="perf.key">
                <TableCell class="font-medium">{{ perf.label }}</TableCell>
                <TableCell class="text-right text-muted-foreground">{{ frDate(perf.startDate) }}</TableCell>
                <TableCell class="text-right">{{ amount(perf.valueStart) }}</TableCell>
                <TableCell class="text-right">{{ signedAmount(perf.contributions) }}</TableCell>
                <TableCell class="text-right" :class="gainClass(perf.gain)">{{ signedAmount(perf.gain) }}</TableCell>
                <TableCell class="text-right" :class="gainClass(perf.pct)">{{ pct(perf.pct) }}</TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
```

- [ ] **Step 4 : Brancher le dashboard**

Dans `resources/js/Pages/Dashboard.vue`, ajouter l'import après `import PerformanceInfoDialog from '@/components/PerformanceInfoDialog.vue';` :

```ts
import PerformanceTable from '@/components/PerformanceTable.vue';
```

Puis remplacer tout le bloc `<Deferred data="performances"> … </Deferred>` (lignes 165-192) par :

```vue
                    <Deferred data="performances">
                        <template #fallback>
                            <div class="flex flex-col gap-2">
                                <div v-for="n in 5" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                            </div>
                        </template>

                        <div v-if="performances && performances.length" class="flex flex-col gap-1">
                            <div class="flex items-center gap-1">
                                <p class="text-sm text-muted-foreground">Performance par période</p>
                                <PerformanceInfoDialog variant="periods" />
                            </div>
                            <PerformanceTable :performances="performances" :currency-digits="0" />
                        </div>
                    </Deferred>
```

- [ ] **Step 5 : Brancher la fiche titre**

Dans `resources/js/Pages/Instruments/Show.vue`, ajouter l'import après `import AppBreadcrumb from '@/components/AppBreadcrumb.vue';` :

```ts
import PerformanceTable from '@/components/PerformanceTable.vue';
```

Puis remplacer le bloc des lignes 250-261 (`<div v-if="props.performances.length" class="-mx-6 overflow-x-auto px-6"> … </div>`) par :

```vue
                <PerformanceTable v-if="props.performances.length" :performances="props.performances" />
```

- [ ] **Step 6 : Construire et lancer le test**

Run: `bun run build && php artisan test --compact --filter="renders the period performances as a table"`
Expected: PASS.

- [ ] **Step 7 : Non-régression navigateur**

Run: `php artisan test --compact --filter="Browser"`
Expected: PASS — `DashboardHoldingsTableTest` interroge `thead th` sans portée mais sa fixture n'a aucune transaction, donc aucun tableau de performances n'est rendu et son assertion reste valide.

- [ ] **Step 8 : Commit**

```bash
git add resources/js/components/PerformanceTable.vue resources/js/Pages/Dashboard.vue resources/js/Pages/Instruments/Show.vue tests/Browser/PerformanceTableTest.php
git commit -m "feat: affiche les performances par période en tableau"
```

---

### Task 5 : Mise à jour de l'aide « performances par période »

**Files:**
- Modify: `resources/js/components/PerformanceInfoDialog.vue:53-83`
- Test: `tests/Browser/DashboardPerformanceInfoTest.php:43-48`

**Interfaces:**
- Consumes: le composant `PerformanceTable` livré en Task 4 (le texte y fait référence).
- Produces: aucune API ; seul le contenu du dialogue `variant="periods"` change.

- [ ] **Step 1 : Écrire l'assertion qui échoue**

Dans `tests/Browser/DashboardPerformanceInfoTest.php`, dans le second bloc d'assertions, ajouter après `->assertSee('cumulés, pas annualisés')` :

```php
        ->assertSee('Apports')
        ->assertSee('les versements de la période')
```

- [ ] **Step 2 : Lancer le test pour le voir échouer**

Run: `bun run build && php artisan test --compact --filter="explains both performance metrics"`
Expected: FAIL — texte `les versements de la période` absent du dialogue.

- [ ] **Step 3 : Mettre à jour le dialogue**

Dans `resources/js/components/PerformanceInfoDialog.vue`, remplacer la `DialogDescription` de la ligne 56 et le premier paragraphe (lignes 60-63) par :

```vue
          <DialogDescription>Le tableau YTD, 1 mois, … 4 ans.</DialogDescription>
```

```vue
          <p>
            Chaque ligne montre la <strong>performance du portefeuille sur une période</strong>
            (depuis le début d'année, le dernier mois, la dernière année, etc.).
          </p>
```

Puis, juste après le paragraphe « On <strong>retire les versements</strong> … » (lignes 67-70), insérer :

```vue
          <p>
            La colonne <strong>Apports</strong> montre justement les versements de la période
            qui sont retirés du calcul, et <strong>Gain</strong> le résultat en euros une fois
            ces versements exclus. <strong>Valeur début</strong> est le dénominateur, pris au
            jour indiqué dans <strong>Depuis</strong>.
          </p>
```

- [ ] **Step 4 : Construire et lancer le test**

Run: `bun run build && php artisan test --compact --filter="explains both performance metrics"`
Expected: PASS.

- [ ] **Step 5 : Suite complète**

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 6 : Commit**

```bash
git add resources/js/components/PerformanceInfoDialog.vue tests/Browser/DashboardPerformanceInfoTest.php
git commit -m "feat: explique les colonnes du tableau des performances"
```

---

## Vérification finale

- [ ] `php artisan test --compact` — suite complète verte.
- [ ] `vendor/bin/pint --dirty --format agent` — aucun fichier PHP restant à formater.
- [ ] `bun run build` — build front sans erreur.
- [ ] Contrôle visuel sur `/` et `/instruments/{id}` (desktop et largeur mobile ~375 px) : le tableau scrolle horizontalement au lieu de déborder, aucune ligne à valeurs vides, alignement à droite des colonnes numériques.
