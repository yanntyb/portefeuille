# Simplification des calculs — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sortir le calcul des actions qui lisent la base, et ramener à un site unique cinq règles aujourd'hui écrites à plusieurs endroits, sans changer aucune sortie sauf une correction de bug.

**Architecture:** Le triptyque déjà en place dans `RealEstate` généralisé à tous les contextes — `Support/` traduit Eloquent → Data, `Services/` calcule sur des valeurs nues, `Actions/` lit, appelle, emballe. Les duplications inter-contextes se résolvent en donnant un propriétaire au calcul (`Portfolio` pour le gain et la position) et en faisant demander les autres par leur port, jamais en créant un espace partagé. Un test qui fige le `sha1` de l'instantané hors-ligne sert de filet à tout le chantier.

**Tech Stack:** PHP 8.5, Laravel, Pest (tests co-localisés dans `app/Contexts/**`), Pint, Inertia + Vue 3, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-29-simplification-calculs-design.md`

## Global Constraints

- Chaque `Services/` ne connaît **ni Eloquent, ni port, ni conteneur** : entrées nues ou Datas de son propre contexte, sortie idem.
- Chaque `Services/` a son test co-localisé (`app/Contexts/<Contexte>/Services/<Nom>Test.php`) qui construit l'objet avec `new`, sans base.
- Aucun nouveau dossier de base : tout reste sous `app/Contexts/<Contexte>/`.
- Aucune sortie ne change, sauf la correction de `gainPct` en Task 2. Le hash de Task 1 est la preuve.
- Après toute modification PHP : `vendor/bin/pint --dirty --format agent`.
- Tests : `php artisan test --compact --filter=<nom>` ; `bun run test:js` pour le TypeScript.
- Un commit par task. Message en français, préfixe `refactor:` sauf mention contraire.
- Les tests Pest de `app/Contexts/**` utilisent déjà `RefreshDatabase` et remettent `Carbon::setTestNow()` à zéro après chaque test (`tests/Pest.php`).
- Fixtures disponibles dans `tests/Pest.php` : `portfolioFixture()`, `cryptoFixture()`, `propertyFixture(['loan' => true])`, `dividendFixture()`.
- Les Datas de `MarketView\Datas` jumellent celles de leurs voisins et doivent reproduire leur JSON à l'octet près, **ordre des clés compris**.

---

### Task 1 : Le filet — figer le hash de l'instantané

**Files:**
- Create: `tests/Feature/SnapshotInvariantTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: un test que chaque task suivante relance. Aucune API.

Ce test ne prouve rien sur le code : il prouve que le refactor ne bouge rien. Il doit tourner vert avant la première modification.

- [ ] **Step 1: Écrire le test avec un hash volontairement faux**

Créer `tests/Feature/SnapshotInvariantTest.php` :

```php
<?php

use Illuminate\Support\Carbon;

/**
 * Le filet du chantier de simplification : l'instantané hors-ligne réunit les quatre contextes —
 * `dashboard` (Wealth), `classes` et `assets` (MarketView), `properties` (RealEstate, fiches et
 * échéanciers compris). Un hash inchangé prouve que les six pages rendent le même JSON, ordre des
 * clés compris.
 *
 * L'horloge est gelée : presque tout le code lit `Carbon::now()` — fenêtres glissantes,
 * échéanciers, projections — et un hash figé sur l'heure réelle casserait dès le lendemain.
 *
 * Le jeu couvre les deux pièges du chantier : un actif tenu dans deux enveloppes (la moyenne
 * pondérée) et un bien avec prêt (`loanSummary()` et l'échéancier).
 */
const SNAPSHOT_VERSION = 'a_remplir_au_premier_lancement';

it('rend un instantané hors-ligne identique au hash de référence', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');

    seedSnapshotFixture();

    $response = $this->getJson('/instantane')->assertOk();

    expect($response->json('version'))->toBe(SNAPSHOT_VERSION);
});
```

- [ ] **Step 2: Écrire le jeu de données**

Ajouter au même fichier, au-dessus du test :

```php
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;

/**
 * Un seul utilisateur porte tout : les titres et la crypto de `cryptoFixture()`, un bien avec
 * prêt, et une seconde enveloppe sur le titre déjà détenu.
 */
function seedSnapshotFixture(): void
{
    ['user' => $user, 'crypto' => $crypto] = cryptoFixture();

    /** Le même titre dans une deuxième enveloppe, à un autre prix de revient. */
    $stock = Holding::query()->where('user_id', $user->id)->where('asset_id', '!=', $crypto->id)->first();
    $second = Wallet::factory()->for($user)->create();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $second->id,
        'asset_id' => $stock->asset_id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(20)->toDateString();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'name' => 'T2 Lyon 7e',
        'address' => '12 rue Garibaldi, Lyon',
        'acquisition_date' => $start,
        'acquisition_price' => 100000,
        'acquisition_fees' => 8000,
    ]);

    Lease::factory()->create([
        'property_id' => $property->id,
        'monthly_rent' => 600,
        'start_date' => $start,
        'end_date' => null,
    ]);

    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => Carbon::now()->toDateString(),
        'value' => 150000,
    ]);

    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 80000,
        'annual_rate' => 0.0,
        'term_months' => 240,
        'start_date' => $start,
        'monthly_insurance' => 0,
    ]);
}
```

Retirer les `use` déjà présents en double, et laisser `Price` importé seulement s'il sert — le supprimer sinon, Pint ne le fait pas.

- [ ] **Step 3: Lancer le test pour lire le vrai hash**

Run: `php artisan test --compact --filter=SnapshotInvariant`
Expected: FAIL, message de la forme `Failed asserting that '<40 caractères hexadécimaux>' is identical to 'a_remplir_au_premier_lancement'`.

Copier le hash effectif affiché.

- [ ] **Step 4: Coller le hash et relancer**

Remplacer `'a_remplir_au_premier_lancement'` par le hash lu.

Run: `php artisan test --compact --filter=SnapshotInvariant`
Expected: PASS

- [ ] **Step 5: Vérifier la stabilité du hash**

Run: `php artisan test --compact --filter=SnapshotInvariant` (deux fois de suite)
Expected: PASS les deux fois, même hash.

Si le hash varie d'un lancement à l'autre, la cause est un identifiant auto-incrémenté ou un ordre non déterministe qui a fui dans le JSON. Dans ce cas, remplacer l'assertion par une comparaison de tableau normalisé : `expect($response->json('classes'))->toEqual($attendu)` sur un tableau figé, plutôt que d'affaiblir le jeu de données.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/SnapshotInvariantTest.php
git commit -m "test: fige le hash de l'instantané comme filet de refactor"
```

---

### Task 2 : `HoldingValuator` et la correction de `gainPct`

**Files:**
- Create: `app/Contexts/Portfolio/Services/HoldingValuator.php`
- Create: `app/Contexts/Portfolio/Services/HoldingValuatorTest.php`
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php:63-126`
- Modify: `app/Contexts/Portfolio/Datas/PortfolioOverviewData.php:14`
- Modify: `app/Contexts/MarketView/Datas/PortfolioSummaryData.php:19`
- Modify: `resources/js/lib/portfolio.ts:23`
- Modify: `tests/Feature/SnapshotInvariantTest.php` (hash mis à jour)

**Interfaces:**
- Consumes: `HoldingLineData` (existant, `app/Contexts/Portfolio/Datas/HoldingLineData.php`).
- Produces:
  - `HoldingValuator::value(float $quantity, ?float $avgCost, ?float $lastPrice): array{marketValue: ?float, cost: ?float, gain: ?float, gainPct: ?float}`
  - `HoldingValuator::pct(?float $gain, ?float $cost): ?float`
  - `HoldingValuator::totals(array $lines): array{totalValue: float, totalCost: float, totalGain: float, totalGainPct: ?float}` où `$lines` est `list<HoldingLineData>`
  - `PortfolioOverviewData::$totalGainPct` devient `?float`

- [ ] **Step 1: Écrire les tests du calculateur**

Créer `app/Contexts/Portfolio/Services/HoldingValuatorTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Services\HoldingValuator;

function line(?float $marketValue, ?float $gain, float $quantity = 1.0, ?float $avgCost = null): HoldingLineData
{
    return new HoldingLineData(
        assetId: 1,
        assetName: 'ACME',
        ticker: 'ACM',
        type: InstrumentType::Stock,
        assetClass: AssetClass::Equity,
        quantity: $quantity,
        avgCost: $avgCost,
        lastPrice: null,
        marketValue: $marketValue,
        gain: $gain,
        gainPct: null,
    );
}

it('valorise une ligne complète', function () {
    expect((new HoldingValuator)->value(10, 80, 100))->toBe([
        'marketValue' => 1000.0,
        'cost' => 800.0,
        'gain' => 200.0,
        'gainPct' => 25.0,
    ]);
});

it('laisse tout à null sans dernier cours', function () {
    expect((new HoldingValuator)->value(10, 80, null))->toBe([
        'marketValue' => null,
        'cost' => null,
        'gain' => null,
        'gainPct' => null,
    ]);
});

it('valorise sans prix de revient mais ne calcule aucun gain', function () {
    expect((new HoldingValuator)->value(10, null, 100))->toBe([
        'marketValue' => 1000.0,
        'cost' => null,
        'gain' => null,
        'gainPct' => null,
    ]);
});

it('rend un pourcentage nul plutôt que zéro sur un coût nul', function () {
    expect((new HoldingValuator)->pct(200.0, 0.0))->toBeNull();
});

it('totalise les lignes en ignorant celles sans valeur', function () {
    $totals = (new HoldingValuator)->totals([
        line(marketValue: 1000.0, gain: 200.0, quantity: 10, avgCost: 80),
        line(marketValue: null, gain: null),
    ]);

    expect($totals)->toBe([
        'totalValue' => 1000.0,
        'totalCost' => 800.0,
        'totalGain' => 200.0,
        'totalGainPct' => 25.0,
    ]);
});

it('rend un pourcentage total nul quand aucune ligne n\'a de coût connu', function () {
    $totals = (new HoldingValuator)->totals([line(marketValue: 1000.0, gain: null)]);

    expect($totals['totalGainPct'])->toBeNull()
        ->and($totals['totalValue'])->toBe(1000.0);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=HoldingValuator`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\HoldingValuator" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/Portfolio/Services/HoldingValuator.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\HoldingLineData;

/**
 * La valorisation d'une position et son gain, à la ligne comme au total. Seul site de cette
 * formule : `MarketView` et `Wealth` la demandent plutôt que de la refaire.
 */
class HoldingValuator
{
    /** @return array{marketValue: ?float, cost: ?float, gain: ?float, gainPct: ?float} */
    public function value(float $quantity, ?float $avgCost, ?float $lastPrice): array
    {
        $marketValue = $lastPrice !== null ? $quantity * $lastPrice : null;
        $cost = $avgCost !== null ? $quantity * $avgCost : null;
        $gain = ($marketValue !== null && $cost !== null) ? $marketValue - $cost : null;

        return [
            'marketValue' => $marketValue,
            'cost' => $cost,
            'gain' => $gain,
            'gainPct' => $this->pct($gain, $cost),
        ];
    }

    /**
     * Nul, et non zéro, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas
     * de pourcentage, et « 0 % » mentirait.
     */
    public function pct(?float $gain, ?float $cost): ?float
    {
        return ($gain !== null && $cost !== null && $cost > 0.0) ? $gain / $cost * 100 : null;
    }

    /**
     * @param  list<HoldingLineData>  $lines
     * @return array{totalValue: float, totalCost: float, totalGain: float, totalGainPct: ?float}
     */
    public function totals(array $lines): array
    {
        $totalValue = 0.0;
        $totalCost = 0.0;
        $totalGain = 0.0;

        foreach ($lines as $line) {
            if ($line->marketValue !== null) {
                $totalValue += $line->marketValue;
            }

            if ($line->gain !== null && $line->avgCost !== null) {
                $totalCost += $line->quantity * $line->avgCost;
                $totalGain += $line->gain;
            }
        }

        return [
            'totalValue' => $totalValue,
            'totalCost' => $totalCost,
            'totalGain' => $totalGain,
            'totalGainPct' => $this->pct($totalGain, $totalCost),
        ];
    }
}
```

- [ ] **Step 4: Lancer les tests du calculateur**

Run: `php artisan test --compact --filter=HoldingValuator`
Expected: PASS

- [ ] **Step 5: Rendre `totalGainPct` nullable dans les deux Datas et le type TypeScript**

Dans `app/Contexts/Portfolio/Datas/PortfolioOverviewData.php`, ligne 14 :

```php
        public ?float $totalGainPct,
```

et `empty()` devient :

```php
    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, null, []);
    }
```

Dans `app/Contexts/MarketView/Datas/PortfolioSummaryData.php`, ligne 19, même changement en `?float` ; adapter son `empty()` de la même façon si elle en a un.

Dans `resources/js/lib/portfolio.ts`, ligne 23 :

```ts
    totalGainPct: number | null;
```

Aucun composant Vue à toucher : `pct()` (`resources/js/lib/format.ts:13`) accepte déjà `number | null`, et `WealthSummarySection.vue` garde déjà son `v-if`.

- [ ] **Step 6: Brancher l'action sur le calculateur**

Dans `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` : injecter le calculateur, remplacer le bloc arithmétique de `readLines()` et tout `summarize()`.

```php
    public function __construct(
        private PriceRepositoryContract $prices,
        private HoldingValuator $valuator,
    ) {}
```

Dans `readLines()`, remplacer les lignes 66-74 par :

```php
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;
            $lastPrice = $lastPrices[(int) $holding->asset_id] ?? null;

            $valued = $this->valuator->value($quantity, $avgCost, $lastPrice);
```

et le `new HoldingLineData(...)` reprend `marketValue: $valued['marketValue']`, `gain: $valued['gain']`, `gainPct: $valued['gainPct']`.

Remplacer `summarize()` par :

```php
    /** @param  list<HoldingLineData>  $lines */
    private function summarize(array $lines): PortfolioOverviewData
    {
        $totals = $this->valuator->totals($lines);

        return new PortfolioOverviewData(
            totalValue: $totals['totalValue'],
            totalCost: $totals['totalCost'],
            totalGain: $totals['totalGain'],
            totalGainPct: $totals['totalGainPct'],
            holdings: $lines,
        );
    }
```

Ajouter `use App\Contexts\Portfolio\Services\HoldingValuator;`.

- [ ] **Step 7: Lancer les tests de Portfolio et de MarketView**

Run: `php artisan test --compact --filter="GetPortfolioOverview|PortfolioTotals|InstrumentsPage"`
Expected: PASS, sauf d'éventuelles assertions sur `totalGainPct === 0.0` dans un cas sans coût — les corriger vers `null` en notant la raison.

- [ ] **Step 8: Mettre à jour le hash du filet**

Run: `php artisan test --compact --filter=SnapshotInvariant`

Si le jeu de Task 1 comporte au moins une ligne sans coût connu, le hash change : coller le nouveau et ajouter au-dessus de la constante le commentaire `/** Modifié une fois : gainPct rend null, et non 0.0, sur un total à coût nul. */`. S'il ne change pas, ne rien toucher — c'est aussi une preuve valide.

- [ ] **Step 9: Pint, suite complète, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
bun run test:js
git add -A
git commit -m "refactor: sort la valorisation d'une ligne de GetPortfolioOverview

gainPct rend désormais null sur un coût nul, au total comme à la ligne :
le total rendait 0.0 là où les trois autres sites rendent null, et
AssetClassData documente déjà pourquoi 0 % mentirait."
```

---

### Task 3 : `PositionAggregator` et `GetPortfolioPositions`

**Files:**
- Create: `app/Contexts/Portfolio/Services/PositionAggregator.php`
- Create: `app/Contexts/Portfolio/Services/PositionAggregatorTest.php`
- Create: `app/Contexts/Portfolio/Datas/PositionLineData.php`
- Create: `app/Contexts/Portfolio/Actions/GetPortfolioPositions.php`
- Create: `app/Contexts/Portfolio/Actions/GetPortfolioPositionsTest.php`
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioHoldings.php` (entier)
- Modify: `app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php:49-87`

**Interfaces:**
- Consumes: `HoldingValuator` (Task 2).
- Produces:
  - `PositionAggregator::__invoke(array $rows): array{quantity: float, avgCost: ?float}` où `$rows` est `list<array{quantity: float, avgCost: ?float}>`
  - `PositionLineData` : `readonly class` avec `int $assetId, float $quantity, ?float $avgCost, ?float $lastPrice, ?float $marketValue, ?float $gain, ?float $gainPct`
  - `GetPortfolioPositions::__invoke(int $userId): array<int, PositionLineData>` — indexé par `assetId`, dans l'ordre de `groupBy('asset_id')` sur `Holding::query()->where('user_id', …)->get()`

L'ordre compte : `PortfolioHoldings::holdingsFor()` alimente le blob et sa liste est ordonnée par ce `groupBy`. Reproduire exactement la même requête et le même groupement.

- [ ] **Step 1: Écrire les tests de l'agrégateur**

Créer `app/Contexts/Portfolio/Services/PositionAggregatorTest.php` :

```php
<?php

use App\Contexts\Portfolio\Services\PositionAggregator;

it('somme les quantités de deux enveloppes', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => 80.0],
        ['quantity' => 4.0, 'avgCost' => 95.0],
    ]))->toBe(['quantity' => 14.0, 'avgCost' => (10.0 * 80.0 + 4.0 * 95.0) / 14.0]);
});

it('ignore les enveloppes sans prix de revient dans la moyenne', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => 80.0],
        ['quantity' => 5.0, 'avgCost' => null],
    ]))->toBe(['quantity' => 15.0, 'avgCost' => 80.0]);
});

it('rend un prix de revient nul quand aucune enveloppe n\'en a', function () {
    expect((new PositionAggregator)([
        ['quantity' => 10.0, 'avgCost' => null],
    ]))->toBe(['quantity' => 10.0, 'avgCost' => null]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=PositionAggregator`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\PositionAggregator" not found`

- [ ] **Step 3: Écrire l'agrégateur**

Créer `app/Contexts/Portfolio/Services/PositionAggregator.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Les enveloppes d'un même actif ramenées à une position unique. Le prix de revient d'un actif
 * tenu dans deux enveloppes est la moyenne pondérée des leurs ; celles qui n'en déclarent aucun
 * comptent dans la quantité mais pas dans la moyenne.
 */
class PositionAggregator
{
    /**
     * @param  list<array{quantity: float, avgCost: ?float}>  $rows
     * @return array{quantity: float, avgCost: ?float}
     */
    public function __invoke(array $rows): array
    {
        $quantity = 0.0;
        $qtyWithCost = 0.0;
        $weighted = 0.0;

        foreach ($rows as $row) {
            $quantity += $row['quantity'];

            if ($row['avgCost'] !== null) {
                $qtyWithCost += $row['quantity'];
                $weighted += $row['quantity'] * $row['avgCost'];
            }
        }

        return [
            'quantity' => $quantity,
            'avgCost' => $qtyWithCost > 0.0 ? $weighted / $qtyWithCost : null,
        ];
    }
}
```

- [ ] **Step 4: Lancer les tests de l'agrégateur**

Run: `php artisan test --compact --filter=PositionAggregator`
Expected: PASS

- [ ] **Step 5: Créer la Data de position**

Créer `app/Contexts/Portfolio/Datas/PositionLineData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

/**
 * Une position par actif, enveloppes confondues — par opposition à `HoldingLineData`, qui compte
 * une ligne par enveloppe. Les deux notions coexistent volontairement : une page liste montre les
 * lignes, une fiche montre la position.
 */
readonly class PositionLineData
{
    public function __construct(
        public int $assetId,
        public float $quantity,
        public ?float $avgCost,
        public ?float $lastPrice,
        public ?float $marketValue,
        public ?float $gain,
        public ?float $gainPct,
    ) {}
}
```

- [ ] **Step 6: Écrire le test de l'action**

Créer `app/Contexts/Portfolio/Actions/GetPortfolioPositionsTest.php` :

```php
<?php

use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('réunit les enveloppes d\'un actif en une position valorisée', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    $positions = app(GetPortfolioPositions::class)($user->id);

    expect($positions)->toHaveKey($instrument->id);

    $position = $positions[$instrument->id];

    expect($position->quantity)->toBe(14.0)
        ->and($position->avgCost)->toBe((10.0 * 80.0 + 4.0 * 95.0) / 14.0)
        ->and($position->lastPrice)->toBe(100.0)
        ->and($position->marketValue)->toBe(1400.0);
});

it('rend un tableau vide pour un utilisateur inconnu', function () {
    expect(app(GetPortfolioPositions::class)(0))->toBe([]);
});
```

- [ ] **Step 7: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=GetPortfolioPositions`
Expected: FAIL avec `Target class [App\Contexts\Portfolio\Actions\GetPortfolioPositions] does not exist.`

- [ ] **Step 8: Écrire l'action**

Créer `app/Contexts/Portfolio/Actions/GetPortfolioPositions.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Services\HoldingValuator;
use App\Contexts\Portfolio\Services\PositionAggregator;
use Illuminate\Support\Collection;

/**
 * La position de chaque actif, enveloppes confondues et valorisée. Exposée par `Portfolio` parce
 * que `MarketView` et `Income` en avaient tous deux besoin et l'avaient tous deux réimplémentée.
 */
class GetPortfolioPositions
{
    public function __construct(
        private PriceRepositoryContract $prices,
        private PositionAggregator $aggregate,
        private HoldingValuator $valuator,
    ) {}

    /** @return array<int, PositionLineData> */
    public function __invoke(int $userId): array
    {
        $holdings = Holding::query()->where('user_id', $userId)->get();

        if ($holdings->isEmpty()) {
            return [];
        }

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        return $holdings
            ->groupBy('asset_id')
            ->mapWithKeys(fn (Collection $rows, int|string $assetId): array => [
                (int) $assetId => $this->position((int) $assetId, $rows, $lastPrices[(int) $assetId] ?? null),
            ])
            ->all();
    }

    /** @param  Collection<int, Holding>  $rows */
    private function position(int $assetId, Collection $rows, ?float $lastPrice): PositionLineData
    {
        $aggregated = ($this->aggregate)($rows->map(fn (Holding $holding): array => [
            'quantity' => (float) $holding->quantity,
            'avgCost' => $holding->avg_cost !== null ? (float) $holding->avg_cost : null,
        ])->values()->all());

        $valued = $this->valuator->value($aggregated['quantity'], $aggregated['avgCost'], $lastPrice);

        return new PositionLineData(
            assetId: $assetId,
            quantity: $aggregated['quantity'],
            avgCost: $aggregated['avgCost'],
            lastPrice: $lastPrice,
            marketValue: $valued['marketValue'],
            gain: $valued['gain'],
            gainPct: $valued['gainPct'],
        );
    }
}
```

- [ ] **Step 9: Lancer le test de l'action**

Run: `php artisan test --compact --filter=GetPortfolioPositions`
Expected: PASS

- [ ] **Step 10: Faire remapper les deux adaptateurs**

Réécrire `app/Contexts/MarketView/Infrastructure/PortfolioHoldings.php` en entier :

```php
<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;

/** Pur remappage : la position par actif est calculée par `Portfolio`, qui en est propriétaire. */
class PortfolioHoldings implements HoldingsPort
{
    public function __construct(private GetPortfolioPositions $positions) {}

    /** @return list<HoldingSnapshotData> */
    public function holdingsFor(int $userId): array
    {
        return array_values(array_map(
            fn (PositionLineData $position): HoldingSnapshotData => new HoldingSnapshotData(
                assetId: $position->assetId,
                quantity: $position->quantity,
                avgCost: $position->avgCost,
            ),
            ($this->positions)($userId),
        ));
    }

    public function holdingFor(int $userId, int $assetId): ?HoldingSnapshotData
    {
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new HoldingSnapshotData(
            assetId: $position->assetId,
            quantity: $position->quantity,
            avgCost: $position->avgCost,
        );
    }
}
```

Dans `app/Contexts/Income/Sources/Dividend/Infrastructure/PortfolioPositionHistory.php` : injecter `GetPortfolioPositions`, supprimer `aggregate()` et l'import de `Holding`, et réécrire les deux lectures de position :

```php
    public function positionFor(int $userId, int $assetId): ?PositionSnapshotData
    {
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new PositionSnapshotData($position->quantity, $position->avgCost);
    }

    /** @return array<int, PositionSnapshotData> */
    public function positionsFor(int $userId): array
    {
        return array_map(
            fn (PositionLineData $position): PositionSnapshotData => new PositionSnapshotData(
                quantity: $position->quantity,
                avgCost: $position->avgCost,
            ),
            ($this->positions)($userId),
        );
    }
```

`transactionsFor()` et `assetIdsFor()` ne changent pas — elles lisent `Transaction`, pas `Holding`.

- [ ] **Step 11: Vérifier que plus aucun adaptateur ne requête `Holding`**

Run: `grep -rn "Holding::query()" app/Contexts/MarketView app/Contexts/Income`
Expected: aucun résultat.

- [ ] **Step 12: Filet, Pint, commit**

Run: `php artisan test --compact`
Expected: PASS, hash de `SnapshotInvariant` **inchangé**.

Si le hash a bougé, l'ordre des positions diffère de celui de l'ancien `groupBy` : comparer les deux listes avant de toucher au hash — c'est une régression, pas une évolution.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: Portfolio expose la position par actif que ses deux voisins recopiaient"
```

---

### Task 4 : `CostBasis`

**Files:**
- Create: `app/Contexts/Portfolio/Services/CostBasis.php`
- Create: `app/Contexts/Portfolio/Services/CostBasisTest.php`
- Modify: `app/Contexts/Portfolio/Actions/ProjectHolding.php:20-45`
- Modify: `app/Contexts/Portfolio/Actions/CalculateRealizedGain.php:18-29`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces: `CostBasis::of(array $buys): array{quantity: float, cost: float, average: float}` où `$buys` est `list<array{quantity: float, unitPrice: float}>`. `average` vaut `0.0` quand la quantité est nulle — c'est le comportement actuel des deux appelants, conservé tel quel.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Portfolio/Services/CostBasisTest.php` :

```php
<?php

use App\Contexts\Portfolio\Services\CostBasis;

it('moyenne deux achats à des prix différents', function () {
    expect((new CostBasis)->of([
        ['quantity' => 10.0, 'unitPrice' => 80.0],
        ['quantity' => 10.0, 'unitPrice' => 100.0],
    ]))->toBe(['quantity' => 20.0, 'cost' => 1800.0, 'average' => 90.0]);
});

it('rend une moyenne de zéro sans aucun achat', function () {
    expect((new CostBasis)->of([]))->toBe(['quantity' => 0.0, 'cost' => 0.0, 'average' => 0.0]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=CostBasis`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\CostBasis" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/Portfolio/Services/CostBasis.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Le prix de revient d'un flux d'achats. Sans achat, la moyenne vaut zéro plutôt que nul : c'est
 * ce qu'attendent la projection d'une position et le calcul d'un gain réalisé, où l'absence
 * d'achat antérieur vaut un coût nul.
 */
class CostBasis
{
    /**
     * @param  list<array{quantity: float, unitPrice: float}>  $buys
     * @return array{quantity: float, cost: float, average: float}
     */
    public function of(array $buys): array
    {
        $quantity = 0.0;
        $cost = 0.0;

        foreach ($buys as $buy) {
            $quantity += $buy['quantity'];
            $cost += $buy['quantity'] * $buy['unitPrice'];
        }

        return [
            'quantity' => $quantity,
            'cost' => $cost,
            'average' => $quantity > 0.0 ? $cost / $quantity : 0.0,
        ];
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=CostBasis`
Expected: PASS

- [ ] **Step 5: Brancher les deux actions**

Dans `ProjectHolding`, ajouter le constructeur `public function __construct(private CostBasis $costBasis) {}`, importer `App\Contexts\Portfolio\Services\CostBasis`, et remplacer les lignes 20-26 par :

```php
        $buys = $this->costBasis->of(
            $transactions->where('type', TransactionType::Buy)
                ->map(fn (Transaction $t): array => [
                    'quantity' => (float) $t->quantity,
                    'unitPrice' => (float) $t->unit_price,
                ])
                ->values()
                ->all(),
        );

        $soldQty = (float) $transactions->where('type', TransactionType::Sell)->sum('quantity');
        $quantity = $buys['quantity'] - $soldQty;
```

et l'`updateOrCreate` prend `'avg_cost' => $buys['average']`.

Dans `CalculateRealizedGain`, même injection, et remplacer les lignes 26-28 par :

```php
        $pru = $this->costBasis->of(
            $buys->map(fn (Transaction $t): array => [
                'quantity' => (float) $t->quantity,
                'unitPrice' => (float) $t->unit_price,
            ])->values()->all(),
        )['average'];
```

- [ ] **Step 6: Filet, Pint, commit**

Run: `php artisan test --compact --filter="ProjectHolding|CalculateRealizedGain|SnapshotInvariant"`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: un seul site pour le prix de revient d'un flux d'achats"
```

---

### Task 5 : `SectorSplitter`

**Files:**
- Create: `app/Contexts/Portfolio/Services/SectorSplitter.php`
- Create: `app/Contexts/Portfolio/Services/SectorSplitterTest.php`
- Modify: `app/Contexts/Portfolio/Actions/GetSectorBreakdown.php:26-97`
- Modify: `app/Contexts/Portfolio/Actions/GetSectorBreakdownTest.php`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces: `SectorSplitter::split(array $valueByAsset, array $weightsByAsset, string $fallback): array` où `$valueByAsset` est `array<int, float>`, `$weightsByAsset` est `array<int, array<string, float>>` (poids bruts par clé de secteur), `$fallback` la clé de repli, et le retour `list<array{sector: string, value: float, pct: float}>` trié par valeur décroissante.

La clé de repli est passée en paramètre pour que le calculateur ignore l'enum `Sector`, qui appartient à `Market`.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Portfolio/Services/SectorSplitterTest.php` :

```php
<?php

use App\Contexts\Portfolio\Services\SectorSplitter;

it('répartit la valeur d\'un actif entre ses secteurs', function () {
    expect((new SectorSplitter)->split(
        [1 => 1000.0],
        [1 => ['technology' => 0.6, 'health' => 0.4]],
        'other',
    ))->toBe([
        ['sector' => 'technology', 'value' => 600.0, 'pct' => 60.0],
        ['sector' => 'health', 'value' => 400.0, 'pct' => 40.0],
    ]);
});

it('normalise des poids qui ne somment pas à un', function () {
    expect((new SectorSplitter)->split(
        [1 => 1000.0],
        [1 => ['technology' => 1.0, 'health' => 1.0]],
        'other',
    ))->toBe([
        ['sector' => 'technology', 'value' => 500.0, 'pct' => 50.0],
        ['sector' => 'health', 'value' => 500.0, 'pct' => 50.0],
    ]);
});

it('rabat sur le secteur de repli un actif sans poids connu', function () {
    expect((new SectorSplitter)->split([1 => 1000.0], [], 'other'))->toBe([
        ['sector' => 'other', 'value' => 1000.0, 'pct' => 100.0],
    ]);
});

it('rabat sur le secteur de repli un actif dont les poids somment à zéro', function () {
    expect((new SectorSplitter)->split([1 => 1000.0], [1 => ['technology' => 0.0]], 'other'))->toBe([
        ['sector' => 'other', 'value' => 1000.0, 'pct' => 100.0],
    ]);
});

it('trie les secteurs par valeur décroissante', function () {
    $slices = (new SectorSplitter)->split(
        [1 => 100.0, 2 => 900.0],
        [1 => ['health' => 1.0], 2 => ['technology' => 1.0]],
        'other',
    );

    expect(array_column($slices, 'sector'))->toBe(['technology', 'health']);
});

it('rend une liste vide sans aucune valeur', function () {
    expect((new SectorSplitter)->split([], [], 'other'))->toBe([]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=SectorSplitter`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\SectorSplitter" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/Portfolio/Services/SectorSplitter.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

/**
 * La valeur du portefeuille répartie entre secteurs. Les poids d'un actif sont normalisés avant
 * répartition : un fournisseur qui rend 0,6 et 0,5 décrit des parts, pas des fractions de un.
 *
 * La clé de repli est passée par l'appelant : `Sector` appartient à `Market`, et ce calculateur
 * ne connaît que des chaînes.
 */
class SectorSplitter
{
    /**
     * @param  array<int, float>  $valueByAsset
     * @param  array<int, array<string, float>>  $weightsByAsset  Poids bruts par clé de secteur.
     * @return list<array{sector: string, value: float, pct: float}>
     */
    public function split(array $valueByAsset, array $weightsByAsset, string $fallback): array
    {
        if ($valueByAsset === []) {
            return [];
        }

        $total = array_sum($valueByAsset);

        /** @var array<string, float> $valueBySector */
        $valueBySector = [];

        foreach ($valueByAsset as $assetId => $value) {
            $weights = $weightsByAsset[$assetId] ?? [];
            $totalWeight = array_sum($weights);

            if ($totalWeight <= 0.0) {
                $valueBySector[$fallback] = ($valueBySector[$fallback] ?? 0.0) + $value;

                continue;
            }

            foreach ($weights as $sector => $weight) {
                $valueBySector[$sector] = ($valueBySector[$sector] ?? 0.0) + $value * ($weight / $totalWeight);
            }
        }

        arsort($valueBySector);

        $slices = [];

        foreach ($valueBySector as $sector => $value) {
            $slices[] = [
                'sector' => $sector,
                'value' => $value,
                'pct' => $total > 0.0 ? $value / $total * 100 : 0.0,
            ];
        }

        return $slices;
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=SectorSplitter`
Expected: PASS

- [ ] **Step 5: Réduire l'action à sa lecture**

Réécrire le corps de `GetSectorBreakdown::__invoke()` :

```php
    /** @return list<AllocationSliceData> */
    public function __invoke(User $user): array
    {
        $holdings = Holding::query()->where('user_id', $user->id)->get();

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        /** @var array<int, float> $valueByAsset */
        $valueByAsset = [];

        foreach ($holdings as $holding) {
            $assetId = (int) $holding->asset_id;
            $close = $lastPrices[$assetId] ?? null;

            if ($close === null) {
                continue;
            }

            $valueByAsset[$assetId] = ($valueByAsset[$assetId] ?? 0.0) + (float) $holding->quantity * $close;
        }

        if ($valueByAsset === []) {
            return [];
        }

        $slices = $this->splitter->split($valueByAsset, $this->weightsFor(array_keys($valueByAsset)), Sector::Other->value);

        return array_map(fn (array $slice): AllocationSliceData => new AllocationSliceData(
            label: Sector::from($slice['sector'])->getLabel(),
            value: $slice['value'],
            pct: $slice['pct'],
            color: Sector::from($slice['sector'])->getColor(),
        ), $slices);
    }

    /**
     * @param  list<int>  $assetIds
     * @return array<int, array<string, float>>
     */
    private function weightsFor(array $assetIds): array
    {
        return $this->sectors
            ->forAssets($assetIds)
            ->groupBy('asset_id')
            ->map(fn (Collection $allocations): array => $allocations
                ->mapWithKeys(fn (SectorAllocation $allocation): array => [
                    $allocation->sector->value => (float) $allocation->weight,
                ])
                ->all())
            ->all();
    }
```

Ajouter au constructeur `private SectorSplitter $splitter,` et les imports `App\Contexts\Portfolio\Services\SectorSplitter` et `Illuminate\Support\Collection`.

- [ ] **Step 6: Alléger le test de l'action**

Dans `GetSectorBreakdownTest.php`, supprimer les cas qui ne vérifient qu'une division — poids nuls, actif sur plusieurs secteurs, poids ne sommant pas à un — désormais couverts sans base par `SectorSplitterTest`. Garder ceux qui vérifient la lecture : un actif sans cours est ignoré, un actif sans allocation tombe en « Autre », les libellés et les couleurs viennent bien de `Sector`.

- [ ] **Step 7: Filet, Pint, commit**

Run: `php artisan test --compact --filter="SectorBreakdown|SectorSplitter|SnapshotInvariant"`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: sort la répartition sectorielle de sa lecture SQL"
```

---

### Task 6 : MarketView cesse de calculer

**Files:**
- Create: `app/Contexts/MarketView/Services/SparklineReducer.php`
- Create: `app/Contexts/MarketView/Services/SparklineReducerTest.php`
- Modify: `app/Contexts/MarketView/Actions/GetHoldingTrends.php:52-88`
- Modify: `app/Contexts/MarketView/Actions/GetInstrumentDetail.php` (retrait de `buildPosition()`)
- Modify: `app/Contexts/MarketView/Ports/PortfolioOverviewPort.php`
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php`
- Modify: `app/Contexts/MarketView/Ports/HoldingsPort.php` (retrait de `holdingFor`)
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioHoldings.php` (retrait de `holdingFor`)

**Interfaces:**
- Consumes: `GetPortfolioPositions` et `PositionLineData` (Task 3).
- Produces:
  - `SparklineReducer::changePct(array $close): ?float` (`list<float>`)
  - `SparklineReducer::downsample(array $close, int $maxPoints): array` (`list<float>`)
  - `PortfolioOverviewPort::positionFor(int $userId, int $assetId): ?PositionData`

- [ ] **Step 1: Écrire les tests du réducteur**

Créer `app/Contexts/MarketView/Services/SparklineReducerTest.php` :

```php
<?php

use App\Contexts\MarketView\Services\SparklineReducer;

it('calcule la variation entre le premier et le dernier cours', function () {
    expect((new SparklineReducer)->changePct([100.0, 120.0, 110.0]))->toBe(10.0);
});

it('ne calcule aucune variation sur moins de deux points', function () {
    expect((new SparklineReducer)->changePct([100.0]))->toBeNull()
        ->and((new SparklineReducer)->changePct([]))->toBeNull();
});

it('ne calcule aucune variation depuis un cours nul', function () {
    expect((new SparklineReducer)->changePct([0.0, 120.0]))->toBeNull();
});

it('rend la série telle quelle quand elle tient sous la limite', function () {
    expect((new SparklineReducer)->downsample([1.0, 2.0, 3.0], 24))->toBe([1.0, 2.0, 3.0]);
});

it('sous-échantillonne en gardant les deux extrémités', function () {
    $points = (new SparklineReducer)->downsample(range(1.0, 100.0), 5);

    expect($points)->toHaveCount(5)
        ->and($points[0])->toBe(1.0)
        ->and($points[4])->toBe(100.0);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=SparklineReducer`
Expected: FAIL avec `Class "App\Contexts\MarketView\Services\SparklineReducer" not found`

- [ ] **Step 3: Écrire le réducteur**

Créer `app/Contexts/MarketView/Services/SparklineReducer.php` :

```php
<?php

namespace App\Contexts\MarketView\Services;

/**
 * Ce qu'une sparkline demande d'une série de cours : sa variation d'un bout à l'autre, et assez de
 * points pour être lisible sans gonfler la charge utile.
 */
class SparklineReducer
{
    /** @param  list<float>  $close */
    public function changePct(array $close): ?float
    {
        $count = count($close);

        if ($count < 2 || $close[0] === 0.0) {
            return null;
        }

        return ($close[$count - 1] - $close[0]) / $close[0] * 100;
    }

    /**
     * @param  list<float>  $close
     * @return list<float>
     */
    public function downsample(array $close, int $maxPoints): array
    {
        $count = count($close);

        if ($count <= $maxPoints) {
            return $close;
        }

        $points = [];

        for ($step = 0; $step < $maxPoints; $step++) {
            $points[] = $close[(int) round($step * ($count - 1) / ($maxPoints - 1))];
        }

        return $points;
    }
}
```

- [ ] **Step 4: Lancer les tests du réducteur**

Run: `php artisan test --compact --filter=SparklineReducer`
Expected: PASS

- [ ] **Step 5: Brancher `GetHoldingTrends`**

Injecter `private SparklineReducer $sparkline,`, supprimer `changePct()` et `downsample()`, et réduire `toTrend()` :

```php
    /** @param list<float> $close */
    private function toTrend(int $assetId, array $close): HoldingTrendData
    {
        return new HoldingTrendData(
            assetId: $assetId,
            changePct: $this->sparkline->changePct($close),
            points: $this->sparkline->downsample($close, self::MAX_POINTS),
        );
    }
```

`self::MAX_POINTS` reste dans l'action : le nombre de points est une décision de rendu de cette page.

- [ ] **Step 6: Ajouter `positionFor` au port et à son adaptateur**

Dans `app/Contexts/MarketView/Ports/PortfolioOverviewPort.php`, ajouter :

```php
    /** La position d'un actif, enveloppes confondues et valorisée. Nulle si l'actif n'est pas détenu. */
    public function positionFor(int $userId, int $assetId): ?PositionData;
```

avec `use App\Contexts\MarketView\Datas\PositionData;`.

Dans `PortfolioTotals`, injecter `private GetPortfolioPositions $positions,` et implémenter :

```php
    public function positionFor(int $userId, int $assetId): ?PositionData
    {
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new PositionData(
            quantity: $position->quantity,
            avgCost: $position->avgCost,
            marketValue: $position->marketValue,
            gain: $position->gain,
            gainPct: $position->gainPct,
        );
    }
```

- [ ] **Step 7: Vider `GetInstrumentDetail` de son arithmétique**

Remplacer l'injection de `HoldingsPort` par `PortfolioOverviewPort`, supprimer `buildPosition()` et l'import de `HoldingSnapshotData`, et poser :

```php
            position: $this->overview->positionFor($userId, $instrumentId),
```

Attention au cas que l'ancien code traitait : il rendait `null` quand `lastPrice` était nul. `PositionLineData` porte alors `marketValue: null`, et `PositionData` sortirait avec des champs nuls au lieu de disparaître. Garder le comportement exact :

```php
        $position = $this->overview->positionFor($userId, $instrumentId);

        if ($position !== null && $position->marketValue === null) {
            $position = null;
        }
```

- [ ] **Step 8: Retirer `holdingFor`, devenu sans appelant**

Run: `grep -rn "holdingFor" app resources`
Expected: seulement `HoldingsPort` et `PortfolioHoldings`.

Supprimer la méthode des deux fichiers.

- [ ] **Step 9: Filet, Pint, commit**

Run: `php artisan test --compact`
Expected: PASS, hash de `SnapshotInvariant` inchangé — c'est le lot où le filet compte le plus, puisqu'il touche toutes les fiches.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: MarketView demande sa position au lieu de la calculer"
```

---

### Task 7 : RealEstate — `SeriesStepper`

**Files:**
- Create: `app/Contexts/RealEstate/Services/SeriesStepper.php`
- Create: `app/Contexts/RealEstate/Services/SeriesStepperTest.php`
- Modify: `app/Contexts/RealEstate/Actions/BuildRealEstateSeries.php:80-175`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces:
  - `SeriesStepper::weeklyLabels(Carbon $from, Carbon $today): array` (`list<string>`)
  - `SeriesStepper::valueAt(array $points, string $label): float` où `$points` est `list<array{0: string, 1: float}>`
  - `SeriesStepper::sumUpTo(array $amountsByKey, string $label): float` où `$amountsByKey` est `array<string, float>`

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/RealEstate/Services/SeriesStepperTest.php` :

```php
<?php

use App\Contexts\RealEstate\Services\SeriesStepper;
use Illuminate\Support\Carbon;

it('pose tous les lundis puis aujourd\'hui', function () {
    $labels = (new SeriesStepper)->weeklyLabels(
        Carbon::parse('2026-01-15'),
        Carbon::parse('2026-02-04 15:30:00'),
    );

    expect($labels[0])->toBe('2026-01-12')
        ->and(end($labels))->toBe('2026-02-04');
});

it('ne duplique pas le dernier label quand aujourd\'hui est un lundi', function () {
    $labels = (new SeriesStepper)->weeklyLabels(
        Carbon::parse('2026-01-15'),
        Carbon::parse('2026-02-02 15:30:00'),
    );

    expect(array_count_values($labels)['2026-02-02'])->toBe(1);
});

it('rend la dernière valeur estimée de date antérieure ou égale', function () {
    $points = [['2026-01-01', 100.0], ['2026-06-01', 150.0]];

    expect((new SeriesStepper)->valueAt($points, '2026-03-01'))->toBe(100.0)
        ->and((new SeriesStepper)->valueAt($points, '2026-06-01'))->toBe(150.0);
});

it('rend zéro avant la première estimation', function () {
    expect((new SeriesStepper)->valueAt([['2026-01-01', 100.0]], '2025-12-31'))->toBe(0.0);
});

it('cumule les montants jusqu\'au label inclus', function () {
    expect((new SeriesStepper)->sumUpTo(['2026-01' => 100.0, '2026-06' => 50.0], '2026-03-01'))->toBe(100.0);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=SeriesStepper`
Expected: FAIL avec `Class "App\Contexts\RealEstate\Services\SeriesStepper" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/RealEstate/Services/SeriesStepper.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

use Illuminate\Support\Carbon;

/** La grille hebdomadaire d'une série et les lectures en escalier qu'elle demande. */
class SeriesStepper
{
    /**
     * Tous les lundis depuis celui qui précède `$from`, puis aujourd'hui — sans quoi le dernier
     * point serait vieux de six jours au plus mauvais moment.
     *
     * @return list<string>
     */
    public function weeklyLabels(Carbon $from, Carbon $today): array
    {
        $cursor = $from->copy()->startOfWeek();
        $todayLabel = $today->toDateString();
        $labels = [];

        // Comparaison en jour, pas en horodatage : `$today` porte l'heure courante, et un lundi
        // à 00:00:00 lui serait sinon antérieur, dupliquant le dernier label.
        while ($cursor->toDateString() < $todayLabel) {
            $labels[] = $cursor->toDateString();
            $cursor = $cursor->addWeek();
        }

        $labels[] = $todayLabel;

        return $labels;
    }

    /**
     * Escalier : la dernière valeur de date ≤ au label, zéro avant la première. Aucune
     * interpolation — une valeur n'est connue que le jour où elle a été estimée.
     *
     * @param  list<array{0: string, 1: float}>  $points  Ordre chronologique croissant.
     */
    public function valueAt(array $points, string $label): float
    {
        $value = 0.0;

        foreach ($points as [$date, $amount]) {
            if ($date > $label) {
                break;
            }

            $value = $amount;
        }

        return $value;
    }

    /** @param  array<string, float>  $amountsByKey  Clé comparable au label, mois ou jour. */
    public function sumUpTo(array $amountsByKey, string $label): float
    {
        $total = 0.0;

        foreach ($amountsByKey as $key => $amount) {
            if ($key <= $label) {
                $total += $amount;
            }
        }

        return $total;
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=SeriesStepper`
Expected: PASS

- [ ] **Step 5: Brancher `BuildRealEstateSeries`**

Injecter `private SeriesStepper $stepper,`. Supprimer `weeklyLabels()`, `valueAt()` et `injectedUpTo()`. Dans `build()` :

```php
        $labels = $this->stepper->weeklyLabels($properties->min('acquisition_date'), $today);
```

et dans la boucle :

```php
                $netWorth[$index] += $this->stepper->valueAt($valuations, $label) - $this->remainingAt($schedules, $label);
                $invested[$index] += $downPayment + $this->stepper->sumUpTo($injections, $label);
```

`remainingAt()`, `valuationPoints()` et `schedulesFor()` restent : les deux dernières traduisent des modèles, la première délègue déjà à `LoanAmortizationCalculator`.

- [ ] **Step 6: Filet, Pint, commit**

Run: `php artisan test --compact --filter="RealEstate|SnapshotInvariant"`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: sort la grille hebdomadaire et l'escalier de BuildRealEstateSeries"
```

---

### Task 8 : RealEstate — le prêt et les charges rendus à leurs calculateurs

**Files:**
- Create: `app/Contexts/RealEstate/Services/ExpenseGrouper.php`
- Create: `app/Contexts/RealEstate/Services/ExpenseGrouperTest.php`
- Modify: `app/Contexts/RealEstate/Services/LoanAmortizationCalculator.php`
- Modify: `app/Contexts/RealEstate/Services/LoanAmortizationCalculatorTest.php`
- Modify: `app/Contexts/RealEstate/Actions/GetPropertyDetail.php:90-170`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces:
  - `ExpenseGrouper::byYear(array $expenses): array` où `$expenses` est `list<array{year: int, category: string, label: string, amount: float}>` et le retour `list<array{year: int, total: float, byCategory: list<array{category: string, label: string, amount: float}>}>`, années décroissantes, catégories par montant décroissant
  - `LoanAmortizationCalculator::summaryOf(array $schedule, string $todayLabel): array{monthlyPayment: float, endDate: string, monthsPaid: int, principalRepaid: float, interestPaid: float, interestRemaining: float, totalPaid: float}` où `$schedule` est `list<AmortizationLineData>`

- [ ] **Step 1: Écrire les tests du groupeur de charges**

Créer `app/Contexts/RealEstate/Services/ExpenseGrouperTest.php` :

```php
<?php

use App\Contexts\RealEstate\Services\ExpenseGrouper;

it('groupe par année décroissante et par catégorie décroissante', function () {
    $years = (new ExpenseGrouper)->byYear([
        ['year' => 2025, 'category' => 'works', 'label' => 'Travaux', 'amount' => 300.0],
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 250.0],
        ['year' => 2026, 'category' => 'property_tax', 'label' => 'Taxe foncière', 'amount' => 750.0],
    ]);

    expect(array_column($years, 'year'))->toBe([2026, 2025])
        ->and($years[0]['total'])->toBe(1000.0)
        ->and(array_column($years[0]['byCategory'], 'category'))->toBe(['property_tax', 'works']);
});

it('additionne deux charges de même catégorie et même année', function () {
    $years = (new ExpenseGrouper)->byYear([
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 250.0],
        ['year' => 2026, 'category' => 'works', 'label' => 'Travaux', 'amount' => 100.0],
    ]);

    expect($years[0]['byCategory'])->toBe([
        ['category' => 'works', 'label' => 'Travaux', 'amount' => 350.0],
    ]);
});

it('rend une liste vide sans charge', function () {
    expect((new ExpenseGrouper)->byYear([]))->toBe([]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=ExpenseGrouper`
Expected: FAIL avec `Class "App\Contexts\RealEstate\Services\ExpenseGrouper" not found`

- [ ] **Step 3: Écrire le groupeur**

Créer `app/Contexts/RealEstate/Services/ExpenseGrouper.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

/**
 * Les charges d'un bien groupées par année civile, la plus récente en tête, et ventilées par
 * catégorie, la plus grosse en tête. À égalité l'ordre suit la première charge rencontrée (tri
 * stable).
 */
class ExpenseGrouper
{
    /**
     * @param  list<array{year: int, category: string, label: string, amount: float}>  $expenses
     * @return list<array{year: int, total: float, byCategory: list<array{category: string, label: string, amount: float}>}>
     */
    public function byYear(array $expenses): array
    {
        /** @var array<int, array<string, array{category: string, label: string, amount: float}>> $grouped */
        $grouped = [];

        foreach ($expenses as $expense) {
            $year = $expense['year'];
            $category = $expense['category'];

            $grouped[$year][$category] ??= [
                'category' => $category,
                'label' => $expense['label'],
                'amount' => 0.0,
            ];
            $grouped[$year][$category]['amount'] += $expense['amount'];
        }

        krsort($grouped);

        $years = [];

        foreach ($grouped as $year => $byCategory) {
            $entries = array_map(
                fn (array $entry): array => [...$entry, 'amount' => round($entry['amount'], 2)],
                array_values($byCategory),
            );

            usort($entries, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

            $years[] = [
                'year' => $year,
                'total' => round(array_sum(array_column($entries, 'amount')), 2),
                'byCategory' => $entries,
            ];
        }

        return $years;
    }
}
```

- [ ] **Step 4: Lancer les tests du groupeur**

Run: `php artisan test --compact --filter=ExpenseGrouper`
Expected: PASS

- [ ] **Step 5: Écrire le test du résumé de prêt**

Ajouter à `app/Contexts/RealEstate/Services/LoanAmortizationCalculatorTest.php` :

```php
it('résume un échéancier à une date donnée', function () {
    $calculator = new LoanAmortizationCalculator;
    $schedule = $calculator->schedule(1200.0, 0.0, 12, Carbon::parse('2026-01-01'), 0.0);

    $summary = $calculator->summaryOf($schedule, '2026-03-15');

    expect($summary['monthsPaid'])->toBe(3)
        ->and($summary['monthlyPayment'])->toBe(100.0)
        ->and($summary['endDate'])->toBe('2026-12-01')
        ->and($summary['principalRepaid'])->toBe(300.0)
        ->and($summary['interestPaid'])->toBe(0.0)
        ->and($summary['interestRemaining'])->toBe(0.0)
        ->and($summary['totalPaid'])->toBe(1200.0);
});
```

`schedule()` a bien cette signature : `schedule(float $principal, float $annualRate, int $termMonths, Carbon $startDate, float $monthlyInsurance = 0.0): array`.

`summaryOf()` suppose un échéancier non vide, comme le faisait `loanSummary()` avec son `$schedule[0]->payment`. `schedule()` rend `[]` pour `termMonths < 1` : les deux plantaient déjà dans ce cas, et le comportement est conservé tel quel — corriger ce trou changerait une sortie, ce que ce chantier s'interdit.

- [ ] **Step 6: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=LoanAmortizationCalculator`
Expected: FAIL avec `Call to undefined method … ::summaryOf()`

- [ ] **Step 7: Écrire `summaryOf`**

Ajouter à `LoanAmortizationCalculator` :

```php
    /**
     * Ce qu'un échéancier dit à une date : ce qui est réglé, ce qui reste. Une échéance compte dès
     * que son mois est entamé, celle du mois en cours comprise.
     *
     * @param  list<AmortizationLineData>  $schedule
     * @return array{monthlyPayment: float, endDate: string, monthsPaid: int, principalRepaid: float, interestPaid: float, interestRemaining: float, totalPaid: float}
     */
    public function summaryOf(array $schedule, string $todayLabel): array
    {
        $paid = array_filter(
            $schedule,
            fn (AmortizationLineData $line): bool => $line->month <= $todayLabel,
        );

        $interestPaid = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $paid));
        $interestTotal = array_sum(array_map(fn (AmortizationLineData $line): float => $line->interest, $schedule));

        return [
            'monthlyPayment' => $schedule[0]->payment,
            'endDate' => $schedule[count($schedule) - 1]->month,
            'monthsPaid' => count($paid),
            'principalRepaid' => round(array_sum(array_map(fn (AmortizationLineData $line): float => $line->principal, $paid)), 2),
            'interestPaid' => round($interestPaid, 2),
            'interestRemaining' => round($interestTotal - $interestPaid, 2),
            'totalPaid' => array_sum(array_map(fn (AmortizationLineData $line): float => $line->payment, $schedule)),
        ];
    }
```

- [ ] **Step 8: Lancer les tests du calculateur de prêt**

Run: `php artisan test --compact --filter=LoanAmortizationCalculator`
Expected: PASS

- [ ] **Step 9: Réduire `GetPropertyDetail`**

Injecter `private ExpenseGrouper $expenseGrouper,` et `private LoanAmortizationCalculator $amortization,`. Supprimer `expenseYears()`, `byCategory()`, et l'arithmétique de `loanSummary()`.

```php
    /** @return list<ExpenseYearData> */
    private function expenseYears(Property $property): array
    {
        $grouped = $this->expenseGrouper->byYear(
            $property->expenses
                ->map(fn (PropertyExpense $expense): array => [
                    'year' => $expense->date->year,
                    'category' => $expense->category->value,
                    'label' => $expense->category->getLabel(),
                    'amount' => (float) $expense->amount,
                ])
                ->values()
                ->all(),
        );

        return array_map(fn (array $year): ExpenseYearData => new ExpenseYearData(
            year: $year['year'],
            byCategory: $year['byCategory'],
            total: $year['total'],
        ), $grouped);
    }

    private function loanSummary(Property $property, Carbon $today): ?LoanSummaryData
    {
        $loan = $property->loans->first();

        if ($loan === null) {
            return null;
        }

        $schedule = $this->assembler->scheduleFor($loan);
        $summary = $this->amortization->summaryOf($schedule, $today->toDateString());

        return new LoanSummaryData(
            principal: (float) $loan->principal,
            annualRate: (float) $loan->annual_rate,
            termMonths: $loan->term_months,
            startDate: $loan->start_date->toDateString(),
            monthlyInsurance: (float) $loan->monthly_insurance,
            monthlyPayment: $summary['monthlyPayment'],
            remainingPrincipal: $this->assembler->remainingFor($loan, $today),
            totalCost: round($summary['totalPaid'] - (float) $loan->principal, 2),
            endDate: $summary['endDate'],
            monthsPaid: $summary['monthsPaid'],
            principalRepaid: $summary['principalRepaid'],
            interestPaid: $summary['interestPaid'],
            interestRemaining: $summary['interestRemaining'],
        );
    }
```

Ajouter `use App\Contexts\RealEstate\Models\PropertyExpense;` et retirer l'import devenu inutile de `AmortizationLineData` si plus rien ne le référence.

- [ ] **Step 10: Filet, Pint, commit**

Run: `php artisan test --compact`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: rend le résumé de prêt à son calculateur et sort le groupement des charges"
```

---

### Task 9 : RealEstate — `PropertyWindowTotals`, et `Support/` redevient traducteur

**Files:**
- Create: `app/Contexts/RealEstate/Services/PropertyWindowTotals.php`
- Create: `app/Contexts/RealEstate/Services/PropertyWindowTotalsTest.php`
- Modify: `app/Contexts/RealEstate/Support/PropertyFinancialsAssembler.php:29-80`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces: `PropertyWindowTotals::within(array $amountsByMonth, string $from, string $to): float` où `$amountsByMonth` est `list<array{month: string, amount: float}>`.

Une seule méthode : les trois agrégations de `financialsFor()` (loyers, charges, échéances) sont le même filtre par fenêtre suivi d'une somme. Les trois appelants la traversent avec leurs propres montants.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/RealEstate/Services/PropertyWindowTotalsTest.php` :

```php
<?php

use App\Contexts\RealEstate\Services\PropertyWindowTotals;

it('somme les montants de la fenêtre, bornes comprises', function () {
    expect((new PropertyWindowTotals)->within([
        ['month' => '2026-01-01', 'amount' => 100.0],
        ['month' => '2026-06-01', 'amount' => 50.0],
        ['month' => '2026-09-01', 'amount' => 25.0],
    ], '2026-01-01', '2026-06-30'))->toBe(150.0);
});

it('exclut ce qui précède la fenêtre', function () {
    expect((new PropertyWindowTotals)->within([
        ['month' => '2025-12-31', 'amount' => 100.0],
    ], '2026-01-01', '2026-06-30'))->toBe(0.0);
});

it('rend zéro sans aucun montant', function () {
    expect((new PropertyWindowTotals)->within([], '2026-01-01', '2026-06-30'))->toBe(0.0);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=PropertyWindowTotals`
Expected: FAIL avec `Class "App\Contexts\RealEstate\Services\PropertyWindowTotals" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/RealEstate/Services/PropertyWindowTotals.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

/**
 * Ce qu'une fenêtre retient d'une suite de montants datés. Loyers encaissés, charges payées et
 * échéances réglées y passent tous les trois : c'est le même filtre suivi de la même somme.
 */
class PropertyWindowTotals
{
    /** @param  list<array{month: string, amount: float}>  $amountsByMonth */
    public function within(array $amountsByMonth, string $from, string $to): float
    {
        $total = 0.0;

        foreach ($amountsByMonth as $entry) {
            if ($entry['month'] >= $from && $entry['month'] <= $to) {
                $total += $entry['amount'];
            }
        }

        return $total;
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=PropertyWindowTotals`
Expected: PASS

- [ ] **Step 5: Réduire `financialsFor()`**

Injecter `private PropertyWindowTotals $totals,` et réécrire le corps :

```php
    public function financialsFor(Property $property, Carbon $today): PropertyFinancialsData
    {
        $windowStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();
        $todayKey = $today->toDateString();

        $leases = $this->leaseTerms($property);
        $months = $this->rents->months($leases, $this->exceptions($property), $today);

        $rents12m = $this->totals->within(
            array_map(fn (RentMonthData $month): array => [
                'month' => $month->month,
                'amount' => $month->effective,
            ], $months),
            $windowStart,
            /**
             * Sans borne haute, à la différence des charges et des échéances : l'ancien code
             * n'en imposait aucune aux loyers (`if ($month->month >= $windowStart)`), et
             * `RentScheduleCalculator::months()` ne produit de toute façon rien après aujourd'hui.
             */
            '9999-12-31',
        );

        $expenses12m = $this->totals->within(
            $property->expenses
                ->map(fn (PropertyExpense $expense): array => [
                    'month' => $expense->date->toDateString(),
                    'amount' => (float) $expense->amount,
                ])
                ->values()
                ->all(),
            $windowStart,
            $todayKey,
        );

        $loanPayments12m = 0.0;
        $remaining = 0.0;
        $borrowed = 0.0;

        foreach ($property->loans as $loan) {
            $schedule = $this->scheduleFor($loan);
            $borrowed += (float) $loan->principal;
            $remaining += $this->amortization->remainingAt($schedule, $today);

            $loanPayments12m += $this->totals->within(
                array_map(fn (AmortizationLineData $line): array => [
                    'month' => $line->month,
                    'amount' => $line->payment,
                ], $schedule),
                $windowStart,
                $todayKey,
            );
        }

        $currentMonthlyRent = $this->rents->projectedAnnual($leases, $today) / 12;

        return new PropertyFinancialsData(
            acquisitionPrice: (float) $property->acquisition_price,
            acquisitionFees: (float) $property->acquisition_fees,
            currentMonthlyRent: round($currentMonthlyRent, 2),
            rents12m: round($rents12m, 2),
            expenses12m: round($expenses12m, 2),
            loanPayments12m: round($loanPayments12m, 2),
            borrowedPrincipal: round($borrowed, 2),
            remainingPrincipal: round($remaining, 2),
            currentValue: (float) ($property->valuations->last()?->value ?? 0.0),
        );
    }
```

**Attention** : l'ancien code n'imposait **aucune borne haute** aux loyers (`if ($month->month >= $windowStart)`), là où charges et échéances en avaient une. La borne `'9999-12-31'` ci-dessus préserve ce comportement. Si le hash bouge à l'étape suivante, c'est ici qu'il faut regarder en premier.

Ajouter les imports `RentMonthData`, `PropertyExpense`, `AmortizationLineData` s'ils manquent.

- [ ] **Step 6: Filet, Pint, commit**

Run: `php artisan test --compact`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: PropertyFinancialsAssembler redevient un traducteur"
```

---

### Task 10 : `ReceiptTotals`

**Files:**
- Create: `app/Contexts/Income/Services/ReceiptTotals.php`
- Create: `app/Contexts/Income/Services/ReceiptTotalsTest.php`
- Modify: `app/Contexts/Income/Actions/GetIncomeSummary.php:33-50`
- Modify: `app/Contexts/Income/Actions/GetAnnualIncome.php:20-48`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces:
  - `ReceiptTotals::summarize(array $receipts, Carbon $since): array{total: float, last12Months: float, bySource: array<string, float>}` où `$receipts` est `list<array{date: Carbon, amount: float, source: string}>`
  - `ReceiptTotals::byYear(array $receipts): array<int, array<string, float>>` — années croissantes, montants arrondis

`GetAssetDividendHistory` n'est pas branché ici : ses reçus portent `exDate` en chaîne et non `date` en `Carbon`, et le traduire pour le faire entrer coûterait plus que les six lignes gagnées. Sa boucle reste en place ; c'est un choix, pas un oubli.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Income/Services/ReceiptTotalsTest.php` :

```php
<?php

use App\Contexts\Income\Services\ReceiptTotals;
use Illuminate\Support\Carbon;

function receipt(string $date, float $amount, string $source = 'dividend'): array
{
    return ['date' => Carbon::parse($date), 'amount' => $amount, 'source' => $source];
}

it('totalise et ventile par origine', function () {
    $summary = (new ReceiptTotals)->summarize([
        receipt('2026-03-05', 8.0),
        receipt('2026-04-05', 12.0, 'rent'),
    ], Carbon::parse('2025-08-29'));

    expect($summary)->toBe([
        'total' => 20.0,
        'last12Months' => 20.0,
        'bySource' => ['dividend' => 8.0, 'rent' => 12.0],
    ]);
});

it('exclut des douze derniers mois ce qui les précède', function () {
    $summary = (new ReceiptTotals)->summarize([
        receipt('2024-03-05', 5.0),
        receipt('2026-03-05', 8.0),
    ], Carbon::parse('2025-08-29'));

    expect($summary['total'])->toBe(13.0)
        ->and($summary['last12Months'])->toBe(8.0);
});

it('groupe par année civile croissante', function () {
    expect((new ReceiptTotals)->byYear([
        receipt('2026-03-05', 8.0),
        receipt('2025-03-05', 5.0, 'rent'),
        receipt('2025-06-05', 5.0, 'rent'),
    ]))->toBe([
        2025 => ['rent' => 10.0],
        2026 => ['dividend' => 8.0],
    ]);
});

it('rend des totaux vides sans aucun reçu', function () {
    expect((new ReceiptTotals)->summarize([], Carbon::parse('2025-08-29')))->toBe([
        'total' => 0.0,
        'last12Months' => 0.0,
        'bySource' => [],
    ]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=ReceiptTotals`
Expected: FAIL avec `Class "App\Contexts\Income\Services\ReceiptTotals" not found`

- [ ] **Step 3: Écrire le calculateur**

Créer `app/Contexts/Income/Services/ReceiptTotals.php` :

```php
<?php

namespace App\Contexts\Income\Services;

use Illuminate\Support\Carbon;

/** Ce qu'une suite de reçus dit : son total, sa dernière année, sa ventilation. */
class ReceiptTotals
{
    /**
     * @param  list<array{date: Carbon, amount: float, source: string}>  $receipts
     * @return array{total: float, last12Months: float, bySource: array<string, float>}
     */
    public function summarize(array $receipts, Carbon $since): array
    {
        $total = 0.0;
        $last12Months = 0.0;
        $bySource = [];

        foreach ($receipts as $receipt) {
            $total += $receipt['amount'];
            $bySource[$receipt['source']] = ($bySource[$receipt['source']] ?? 0.0) + $receipt['amount'];

            if ($receipt['date']->gte($since)) {
                $last12Months += $receipt['amount'];
            }
        }

        return [
            'total' => round($total, 2),
            'last12Months' => round($last12Months, 2),
            'bySource' => array_map(fn (float $amount): float => round($amount, 2), $bySource),
        ];
    }

    /**
     * @param  list<array{date: Carbon, amount: float, source: string}>  $receipts
     * @return array<int, array<string, float>>  Années croissantes.
     */
    public function byYear(array $receipts): array
    {
        $byYear = [];

        foreach ($receipts as $receipt) {
            $year = (int) $receipt['date']->format('Y');
            $source = $receipt['source'];
            $byYear[$year][$source] = ($byYear[$year][$source] ?? 0.0) + $receipt['amount'];
        }

        ksort($byYear);

        return array_map(
            fn (array $bySource): array => array_map(fn (float $amount): float => round($amount, 2), $bySource),
            $byYear,
        );
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=ReceiptTotals`
Expected: PASS

- [ ] **Step 5: Brancher les deux actions**

Dans `GetIncomeSummary`, injecter `private ReceiptTotals $totals,` et remplacer le corps après la garde par :

```php
        $summary = $this->totals->summarize(
            array_map(fn (IncomeReceiptData $receipt): array => [
                'date' => $receipt->date,
                'amount' => $receipt->amount,
                'source' => $receipt->source->value,
            ], $receipts),
            Carbon::now()->subYear()->startOfDay(),
        );

        return new IncomeSummaryData(
            totalReceived: $summary['total'],
            last12Months: $summary['last12Months'],
            estimatedAnnual: $estimatedAnnual,
            bySource: $summary['bySource'],
        );
```

Dans `GetAnnualIncome` :

```php
        $byYear = $this->totals->byYear(
            array_map(fn (IncomeReceiptData $receipt): array => [
                'date' => $receipt->date,
                'amount' => $receipt->amount,
                'source' => $receipt->source->value,
            ], $this->sources->receiptsFor($userId, $only)),
        );

        return array_map(fn (int $year): AnnualIncomeData => new AnnualIncomeData(
            year: $year,
            total: round(array_sum($byYear[$year]), 2),
            bySource: $byYear[$year],
        ), array_keys($byYear));
```

Ajouter `use App\Contexts\Income\Datas\IncomeReceiptData;` aux deux.

- [ ] **Step 6: Filet, Pint, commit**

Run: `php artisan test --compact --filter="Income|SnapshotInvariant"`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: un seul site pour les totaux de reçus"
```

---

### Task 11 : `RollingWindow` — nommer les deux fenêtres

**Files:**
- Create: `app/Contexts/RealEstate/Services/RollingWindow.php`
- Create: `app/Contexts/RealEstate/Services/RollingWindowTest.php`
- Create: `app/Contexts/Income/Services/RollingWindow.php`
- Create: `app/Contexts/Income/Services/RollingWindowTest.php`
- Modify: `app/Contexts/RealEstate/Support/PropertyFinancialsAssembler.php`
- Modify: `app/Contexts/RealEstate/Actions/GetRealEstateIncome.php`
- Modify: `app/Contexts/RealEstate/Actions/GetPropertyDetail.php`
- Modify: `app/Contexts/Income/Actions/GetIncomeSummary.php`
- Modify: `app/Contexts/Income/Sources/Dividend/Actions/GetAssetDividendHistory.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `RealEstate\Services\RollingWindow::monthsFull(Carbon $today): string` — le premier jour du mois d'il y a onze mois, en `Y-m-d`
  - `Income\Services\RollingWindow::slidingDays(Carbon $today): Carbon` — le même jour un an plus tôt, à minuit

Deux classes de même nom dans deux contextes : les trois sites de `RealEstate` veulent tous les mois pleins, les deux sites d'`Income` tous la fenêtre glissante. Aucune ne traverse la frontière, aucune dépendance croisée n'est créée. **Aucun appelant ne change de fenêtre** — chacun cesse seulement de la redéfinir.

- [ ] **Step 1: Écrire les deux tests**

Créer `app/Contexts/RealEstate/Services/RollingWindowTest.php` :

```php
<?php

use App\Contexts\RealEstate\Services\RollingWindow;
use Illuminate\Support\Carbon;

it('remonte au premier jour du mois d\'il y a onze mois', function () {
    expect((new RollingWindow)->monthsFull(Carbon::parse('2026-08-29 15:30:00')))->toBe('2025-09-01');
});

it('ne déborde pas sur un mois plus court', function () {
    expect((new RollingWindow)->monthsFull(Carbon::parse('2026-03-31')))->toBe('2025-04-01');
});
```

Créer `app/Contexts/Income/Services/RollingWindowTest.php` :

```php
<?php

use App\Contexts\Income\Services\RollingWindow;
use Illuminate\Support\Carbon;

it('remonte au même jour un an plus tôt, à minuit', function () {
    expect((new RollingWindow)->slidingDays(Carbon::parse('2026-08-29 15:30:00'))->toDateTimeString())
        ->toBe('2025-08-29 00:00:00');
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=RollingWindow`
Expected: FAIL, les deux classes manquent.

- [ ] **Step 3: Écrire les deux classes**

Créer `app/Contexts/RealEstate/Services/RollingWindow.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

use Illuminate\Support\Carbon;

/**
 * La fenêtre des douze derniers mois, comptée en mois d'échéance : du premier jour du mois d'il y
 * a onze mois à aujourd'hui. C'est celle de tout le contexte — loyers, charges, échéances.
 *
 * `Income` en emploie une autre, glissante au jour, et la définit chez lui : les deux ne se
 * croisent jamais.
 */
class RollingWindow
{
    public function monthsFull(Carbon $today): string
    {
        return $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();
    }
}
```

Créer `app/Contexts/Income/Services/RollingWindow.php` :

```php
<?php

namespace App\Contexts\Income\Services;

use Illuminate\Support\Carbon;

/**
 * La fenêtre des douze derniers mois, glissante au jour : un reçu compte s'il date d'après le même
 * jour l'an dernier. C'est celle de tout le contexte — revenus perçus, dividendes d'un actif.
 *
 * `RealEstate` en emploie une autre, en mois pleins, et la définit chez lui.
 */
class RollingWindow
{
    public function slidingDays(Carbon $today): Carbon
    {
        return $today->copy()->subYear()->startOfDay();
    }
}
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=RollingWindow`
Expected: PASS

- [ ] **Step 5: Brancher les cinq appelants**

Injecter la classe du contexte et remplacer l'expression, sans rien changer d'autre :

- `PropertyFinancialsAssembler` : `$windowStart = $this->window->monthsFull($today);`
- `GetRealEstateIncome` : `$windowStart = $this->window->monthsFull($today);`
- `GetPropertyDetail::cashFlowWindowStart()` : `$slidingStart = Carbon::parse($this->window->monthsFull($today));`
- `GetIncomeSummary` : `$since = $this->window->slidingDays(Carbon::now());`
- `GetAssetDividendHistory` : `$since = $this->window->slidingDays(Carbon::now());`

- [ ] **Step 6: Vérifier qu'aucune fenêtre ne reste en dur**

Run: `grep -rn "subMonthsNoOverflow(11)\|subYear()->startOfDay()" app/Contexts --include=*.php | grep -v "Services/RollingWindow"`
Expected: aucun résultat hors fichiers de test.

- [ ] **Step 7: Filet, suite complète, Pint, commit**

Run: `php artisan test --compact`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
bun run test:js
git add -A
git commit -m "refactor: nomme les deux fenêtres de douze mois au lieu de les redéfinir"
```

---

### Task 12 : `SeriesAligner` absorbe l'empilement de séries

**Files:**
- Modify: `app/Contexts/Wealth/Services/SeriesAligner.php`
- Modify: `app/Contexts/Wealth/Services/SeriesAlignerTest.php`
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioAssetClass.php:88-104`
- Modify: `app/Contexts/Wealth/Actions/BuildWealthSeries.php:38-50`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces: `SeriesAligner::accumulate(array $series, int $length): array` où `$series` est `list<list<float>>` et le retour `list<float>` de longueur `$length`, arrondi à deux décimales.

`PortfolioAssetClass::sum()` et la boucle d'apports de `BuildWealthSeries` empilent toutes deux des séries index par index puis arrondissent. Les deux vivent dans `Wealth`, dont `SeriesAligner` est déjà le calculateur de séries.

`BuildRealEstateSeries` accumule aussi, mais scalaire par scalaire dans une boucle imbriquée sur les biens, pas série sur série : sa forme diffère, il reste tel quel, et le faire entrer ici lui imposerait une dépendance de `RealEstate` vers `Wealth`.

- [ ] **Step 1: Écrire les tests**

Ajouter à `app/Contexts/Wealth/Services/SeriesAlignerTest.php` :

```php
it('empile deux séries index par index', function () {
    expect((new SeriesAligner)->accumulate([[1.0, 2.0], [10.0, 20.0]], 2))->toBe([11.0, 22.0]);
});

it('complète une série plus courte que la grille', function () {
    expect((new SeriesAligner)->accumulate([[1.0]], 3))->toBe([1.0, 0.0, 0.0]);
});

it('rend une grille de zéros sans aucune série', function () {
    expect((new SeriesAligner)->accumulate([], 2))->toBe([0.0, 0.0]);
});

it('arrondit à deux décimales', function () {
    expect((new SeriesAligner)->accumulate([[0.1], [0.2]], 1))->toBe([0.3]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=SeriesAligner`
Expected: FAIL avec `Call to undefined method App\Contexts\Wealth\Services\SeriesAligner::accumulate()`

- [ ] **Step 3: Écrire la méthode**

Ajouter à `SeriesAligner` :

```php
    /**
     * Plusieurs séries empilées sur une même grille. Une série plus courte laisse la fin à zéro :
     * une classe apparue en cours de route n'a rien à ajouter avant son premier point.
     *
     * @param  list<list<float>>  $series
     * @return list<float>
     */
    public function accumulate(array $series, int $length): array
    {
        $totals = array_fill(0, $length, 0.0);

        foreach ($series as $one) {
            foreach ($one as $index => $amount) {
                if ($index < $length) {
                    $totals[$index] += $amount;
                }
            }
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }
```

- [ ] **Step 4: Lancer les tests**

Run: `php artisan test --compact --filter=SeriesAligner`
Expected: PASS

- [ ] **Step 5: Brancher les deux appelants**

Dans `PortfolioAssetClass`, injecter `private SeriesAligner $aligner,`, supprimer la méthode privée `sum()` et écrire :

```php
    public function seriesFor(int $userId): ClassSeriesData
    {
        /** Historique complet au pas hebdomadaire, comme le graphe du tableau de bord l'utilisait déjà. */
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week, [$this->exposure]);
        $length = count($series->labels);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->value, $series->perAsset),
                $length,
            ),
            invested: $this->aligner->accumulate(
                array_map(fn (AssetSeriesData $asset): array => $asset->invested, $series->perAsset),
                $length,
            ),
        );
    }
```

Ajouter `use App\Contexts\Wealth\Services\SeriesAligner;`.

Dans `BuildWealthSeries`, remplacer la boucle d'apports par une accumulation en deux temps :

```php
        $values = [];
        $investedSeries = [];

        foreach ($classes as $index => $class) {
            $values[] = new ClassValuesData(
                key: $class->key(),
                label: $class->label(),
                color: $class->color(),
                values: $this->aligner->onto($labels, $series[$index]->labels, $series[$index]->value),
            );

            $investedSeries[] = $this->aligner->onto($labels, $series[$index]->labels, $series[$index]->invested);
        }

        return new WealthSeriesData(
            labels: $labels,
            classes: $values,
            invested: $this->aligner->accumulate($investedSeries, count($labels)),
        );
```

- [ ] **Step 6: Filet, Pint, commit**

Run: `php artisan test --compact --filter="Wealth|Dashboard|SnapshotInvariant"`
Expected: PASS, hash inchangé.

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: SeriesAligner absorbe l'empilement que deux appelants recopiaient"
```

---

## Vérification finale

- [ ] `php artisan test --compact` — toute la suite verte
- [ ] `bun run test:js` — vert
- [ ] `bun run typecheck` — vert (le type `totalGainPct` a changé en Task 2)
- [ ] `vendor/bin/pint --test --format agent` **n'est pas à lancer** : la consigne du projet est `vendor/bin/pint --format agent`
- [ ] `grep -rn "gainPct" app/Contexts --include=*.php | grep -v Services | grep -v Datas | grep -v Test` — aucune formule hors `HoldingValuator`
- [ ] Le hash de `SnapshotInvariantTest` n'a bougé qu'une fois, en Task 2, avec son commentaire

## Suite

Les pages d'analyse (concentration, contribution à la performance, drawdown) ont motivé ce chantier et attendent sa fin. Elles s'appuieront sur `HoldingValuator` et `GetPortfolioPositions`, et devront trancher entre la ligne et la position — une concentration calculée sur les lignes sous-estimerait un titre à cheval sur deux enveloppes. Spec séparée.
