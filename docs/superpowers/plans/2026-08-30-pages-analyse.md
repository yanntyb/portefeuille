# Pages d'analyse par exposition — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Isoler secteurs, performances et revenus de la page liste vers une page d'analyse par exposition, et y ajouter concentration, contribution à la performance et drawdown.

**Architecture:** Trois calculateurs purs chez leurs contextes propriétaires (`Portfolio\Services\Concentration`, `Portfolio\Services\PerformanceContribution`, `Valuation\Services\Drawdown`), assemblés par une action de lecture par contexte, exposés à `MarketView` par ses ports existants, et rendus par une seconde page Inertia servie par un second contrôleur. La page entre dans l'instantané hors-ligne sous une clé `analysis` par classe.

**Tech Stack:** PHP 8.5, Laravel, Pest (tests co-localisés dans `app/Contexts/**`), Pint, Inertia v3 + Vue 3, Pinia, Vitest, Tailwind v4.

**Spec:** `docs/superpowers/specs/2026-08-30-pages-analyse-design.md`

## Global Constraints

- Un `Services/` ne connaît **ni Eloquent, ni port, ni conteneur** : entrées nues ou Datas de son propre contexte, sortie idem. Test co-localisé (`app/Contexts/<Contexte>/Services/<Nom>Test.php`) construisant l'objet avec `new`, sans base. Voir `.ai/rules/services.md`.
- `Support/` traduit Eloquent vers des Datas ; `Actions/` lit, appelle, emballe.
- `MarketView` ne lit ses voisins que **par ses ports** et ne calcule rien. Voir `.ai/rules/market-view.md`.
- Les Datas de `MarketView\Datas` jumellent celles de leurs voisins et doivent reproduire leur JSON **à l'octet près, ordre des clés compris**.
- Aucun nouveau dossier de base : tout sous `app/Contexts/<Contexte>/`.
- **Une absence n'est pas un zéro** : un indicateur non défini rend `null`, jamais `0.0`. C'est la règle déjà appliquée à `gainPct` (`.ai/rules/portfolio.md`).
- Tout texte visible par l'utilisateur est en **français**, accents corrects. Idem commentaires, noms de tests, messages de commit.
- PHP 8.5 : types de retour explicites, promotion de propriétés au constructeur, accolades toujours.
- Après toute modification PHP : `vendor/bin/pint --dirty --format agent`. Ne jamais lancer `vendor/bin/pint --test`.
- Tests : `php artisan test --compact` (suite complète avant chaque commit), `--filter=<nom>` pour cibler. `bun run test:js` et `bun run typecheck` dès qu'un `.ts` ou `.vue` change.
- `tests/Feature/SnapshotInvariantTest.php` fige un hash de l'instantané. **Seule la Task 8 a le droit de le faire bouger**, et elle doit écrire la raison à côté de la constante.
- `InstrumentFactory` tire `isin` et `ticker` au sort. Tout `Instrument::factory()` ajouté à un jeu qui alimente le filet **doit fixer son `ticker`** (`.ai/rules/factories.md`).
- `share.txt` est un fichier non suivi préexistant, étranger à ce chantier : ne le stage jamais, et préfère `git add <chemins>` à `git add -A`.
- Fixtures globales de `tests/Pest.php` : `portfolioFixture()`, `cryptoFixture()`, `propertyFixture(['loan' => true])`, `dividendFixture()`.

---

### Task 1 : `Concentration`

**Files:**
- Create: `app/Contexts/Portfolio/Services/Concentration.php`
- Create: `app/Contexts/Portfolio/Services/ConcentrationTest.php`
- Create: `app/Contexts/Portfolio/Datas/ConcentrationData.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `ConcentrationData` : `readonly class` avec `?float $top1, ?float $top3, ?float $top5, ?float $hhi`, plus `public static function empty(): self` (les quatre à `null`) et `jsonSerialize()` dans l'ordre `top1, top3, top5, hhi`.
  - `Concentration::of(array $values): ConcentrationData` où `$values` est `list<?float>` — les valeurs de marché des positions, `null` compris.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Portfolio/Services/ConcentrationTest.php` :

```php
<?php

use App\Contexts\Portfolio\Services\Concentration;

it('mesure la concentration de quatre positions égales', function () {
    $concentration = (new Concentration)->of([250.0, 250.0, 250.0, 250.0]);

    expect($concentration->top1)->toBe(25.0)
        ->and($concentration->top3)->toBe(75.0)
        ->and($concentration->top5)->toBe(100.0)
        ->and($concentration->hhi)->toBe(0.25);
});

it('classe les positions par valeur décroissante avant de cumuler', function () {
    $concentration = (new Concentration)->of([100.0, 700.0, 200.0]);

    expect($concentration->top1)->toBe(70.0)
        ->and($concentration->top3)->toBe(100.0);
});

it('rend un top complet même avec moins de positions que le rang demandé', function () {
    $concentration = (new Concentration)->of([600.0, 400.0]);

    expect($concentration->top3)->toBe(100.0)
        ->and($concentration->top5)->toBe(100.0);
});

it('rend un HHI de un sur une position unique', function () {
    expect((new Concentration)->of([1000.0])->hhi)->toBe(1.0);
});

it('exclut les positions sans cours connu au lieu de les compter à zéro', function () {
    $concentration = (new Concentration)->of([500.0, 500.0, null]);

    expect($concentration->top1)->toBe(50.0)
        ->and($concentration->hhi)->toBe(0.5);
});

it('ne définit aucune concentration sur un portefeuille vide', function () {
    expect((new Concentration)->of([]))->toEqual(ConcentrationData::empty());
});

it('ne définit aucune concentration quand aucune position n\'a de cours', function () {
    expect((new Concentration)->of([null, null]))->toEqual(ConcentrationData::empty());
});

it('ignore les valeurs négatives ou nulles, qui ne sont pas des expositions', function () {
    $concentration = (new Concentration)->of([500.0, 500.0, 0.0]);

    expect($concentration->top1)->toBe(50.0);
});
```

Ajouter `use App\Contexts\Portfolio\Datas\ConcentrationData;` en tête.

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=Concentration`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\Concentration" not found`

- [ ] **Step 3: Écrire la Data**

Créer `app/Contexts/Portfolio/Datas/ConcentrationData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/**
 * Ce qu'un portefeuille concentre. Les quatre indicateurs sont nuls, et non zéro, quand aucune
 * position n'a de valeur connue : un portefeuille sans exposition n'a pas une concentration de
 * zéro, il n'en a pas.
 */
readonly class ConcentrationData implements JsonSerializable
{
    public function __construct(
        public ?float $top1,
        public ?float $top3,
        public ?float $top5,
        public ?float $hhi,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'top1' => $this->top1,
            'top3' => $this->top3,
            'top5' => $this->top5,
            'hhi' => $this->hhi,
        ];
    }
}
```

- [ ] **Step 4: Écrire le calculateur**

Créer `app/Contexts/Portfolio/Services/Concentration.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\ConcentrationData;

/**
 * La concentration d'un portefeuille : le poids de ses plus grosses positions, et l'indice de
 * Herfindahl-Hirschman, somme des carrés des poids — 1 pour une position unique, 1/n pour n
 * positions égales.
 *
 * Prend des valeurs et non des poids : la normalisation vit ici, pour que l'appelant n'ait pas à
 * diviser avant et que la règle d'exclusion n'ait qu'un site.
 */
class Concentration
{
    /** @param  list<?float>  $values  Valeurs de marché des positions, ordre indifférent. */
    public function of(array $values): ConcentrationData
    {
        /** Une position sans cours connu, nulle ou négative n'est pas une exposition à mesurer. */
        $kept = array_values(array_filter(
            $values,
            fn (?float $value): bool => $value !== null && $value > 0.0,
        ));

        $total = array_sum($kept);

        if ($total <= 0.0) {
            return ConcentrationData::empty();
        }

        $weights = array_map(fn (float $value): float => $value / $total, $kept);
        rsort($weights);

        return new ConcentrationData(
            top1: $this->topN($weights, 1),
            top3: $this->topN($weights, 3),
            top5: $this->topN($weights, 5),
            hhi: array_sum(array_map(fn (float $weight): float => $weight ** 2, $weights)),
        );
    }

    /**
     * Le cumul des `$n` plus gros poids, en pourcentage. Moins de `$n` positions donne 100 %, ce
     * qui est la réponse juste et non une valeur manquante.
     *
     * @param  list<float>  $weights  Décroissants.
     */
    private function topN(array $weights, int $n): float
    {
        return array_sum(array_slice($weights, 0, $n)) * 100;
    }
}
```

- [ ] **Step 5: Lancer les tests**

Run: `php artisan test --compact --filter=Concentration`
Expected: PASS

- [ ] **Step 6: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: mesure la concentration d'un portefeuille"
```

---

### Task 2 : `PerformanceContribution`

**Files:**
- Create: `app/Contexts/Portfolio/Services/PerformanceContribution.php`
- Create: `app/Contexts/Portfolio/Services/PerformanceContributionTest.php`
- Create: `app/Contexts/Portfolio/Datas/ContributionData.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `ContributionData` : `readonly class` avec `int $assetId, string $assetName, ?float $contribution, ?float $weight`, `jsonSerialize()` dans cet ordre.
  - `PerformanceContribution::of(array $positions, float $totalValue): array` où `$positions` est `list<array{assetId: int, assetName: string, gain: ?float, marketValue: ?float}>` et le retour `list<ContributionData>` trié par contribution décroissante.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Portfolio/Services/PerformanceContributionTest.php` :

```php
<?php

use App\Contexts\Portfolio\Services\PerformanceContribution;

function contributionPosition(int $id, string $name, ?float $gain, ?float $marketValue): array
{
    return ['assetId' => $id, 'assetName' => $name, 'gain' => $gain, 'marketValue' => $marketValue];
}

it('rapporte le gain d\'une position à la valeur totale, pas à son propre coût', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Petite ligne', 80.0, 200.0),
        contributionPosition(2, 'Grosse ligne', 360.0, 3000.0),
    ], 10000.0);

    expect($lines[0]->assetName)->toBe('Grosse ligne')
        ->and($lines[0]->contribution)->toBe(3.6)
        ->and($lines[0]->weight)->toBe(30.0)
        ->and($lines[1]->contribution)->toBe(0.8)
        ->and($lines[1]->weight)->toBe(2.0);
});

it('trie par contribution décroissante', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Faible', 100.0, 1000.0),
        contributionPosition(2, 'Forte', 900.0, 1000.0),
    ], 10000.0);

    expect(array_map(fn ($line): string => $line->assetName, $lines))->toBe(['Forte', 'Faible']);
});

it('garde les contributions négatives d\'un portefeuille en perte', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Perdante', -500.0, 1000.0),
    ], 10000.0);

    expect($lines[0]->contribution)->toBe(-5.0);
});

it('exclut les positions dont le gain est inconnu', function () {
    $lines = (new PerformanceContribution)->of([
        contributionPosition(1, 'Connue', 100.0, 1000.0),
        contributionPosition(2, 'Inconnue', null, 1000.0),
    ], 10000.0);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]->assetName)->toBe('Connue');
});

it('ne définit ni contribution ni poids sur une valeur totale nulle', function () {
    $lines = (new PerformanceContribution)->of([contributionPosition(1, 'Ligne', 100.0, 1000.0)], 0.0);

    expect($lines[0]->contribution)->toBeNull()
        ->and($lines[0]->weight)->toBeNull();
});

it('ne définit aucun poids pour une position sans valeur de marché', function () {
    $lines = (new PerformanceContribution)->of([contributionPosition(1, 'Ligne', 100.0, null)], 10000.0);

    expect($lines[0]->contribution)->toBe(1.0)
        ->and($lines[0]->weight)->toBeNull();
});

it('rend une liste vide sans aucune position', function () {
    expect((new PerformanceContribution)->of([], 10000.0))->toBe([]);
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=PerformanceContribution`
Expected: FAIL avec `Class "App\Contexts\Portfolio\Services\PerformanceContribution" not found`

- [ ] **Step 3: Écrire la Data**

Créer `app/Contexts/Portfolio/Datas/ContributionData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/** Ce qu'une position apporte au rendement du portefeuille, et la place qu'elle y occupe. */
readonly class ContributionData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $assetName,
        public ?float $contribution,
        public ?float $weight,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'contribution' => $this->contribution,
            'weight' => $this->weight,
        ];
    }
}
```

- [ ] **Step 4: Écrire le calculateur**

Créer `app/Contexts/Portfolio/Services/PerformanceContribution.php` :

```php
<?php

namespace App\Contexts\Portfolio\Services;

use App\Contexts\Portfolio\Datas\ContributionData;

/**
 * Ce que chaque position apporte au rendement du portefeuille, en points de ce rendement.
 *
 * Le dénominateur est la valeur totale, jamais le gain total. « Quelle part du gain vient de cette
 * ligne » paraît plus direct mais s'effondre dès que le portefeuille perd : le dénominateur
 * devient négatif, et une position gagnante afficherait une contribution négative. Rapportée à la
 * valeur, la mesure garde son sens dans les deux cas.
 */
class PerformanceContribution
{
    /**
     * @param  list<array{assetId: int, assetName: string, gain: ?float, marketValue: ?float}>  $positions
     * @return list<ContributionData>
     */
    public function of(array $positions, float $totalValue): array
    {
        /** Une position dont le gain est inconnu ne contribue pas de zéro : elle ne se mesure pas. */
        $measurable = array_values(array_filter(
            $positions,
            fn (array $position): bool => $position['gain'] !== null,
        ));

        $lines = array_map(fn (array $position): ContributionData => new ContributionData(
            assetId: $position['assetId'],
            assetName: $position['assetName'],
            contribution: $totalValue > 0.0 ? $position['gain'] / $totalValue * 100 : null,
            weight: ($totalValue > 0.0 && $position['marketValue'] !== null)
                ? $position['marketValue'] / $totalValue * 100
                : null,
        ), $measurable);

        usort($lines, fn (ContributionData $a, ContributionData $b): int => ($b->contribution ?? 0.0) <=> ($a->contribution ?? 0.0));

        return $lines;
    }
}
```

- [ ] **Step 5: Lancer les tests**

Run: `php artisan test --compact --filter=PerformanceContribution`
Expected: PASS

- [ ] **Step 6: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: mesure la contribution de chaque position au rendement"
```

---

### Task 3 : `Drawdown`

**Files:**
- Create: `app/Contexts/Valuation/Services/Drawdown.php`
- Create: `app/Contexts/Valuation/Services/DrawdownTest.php`
- Create: `app/Contexts/Valuation/Datas/DrawdownData.php`

**Interfaces:**
- Consumes: rien.
- Produces:
  - `DrawdownData` : `readonly class` avec `?float $maxDepth, ?string $peakLabel, ?string $troughLabel, ?float $currentDepth`, `empty()`, `jsonSerialize()` dans cet ordre.
  - `Drawdown::of(array $labels, array $values): DrawdownData` où `$labels` est `list<string>` et `$values` `list<float>`.

`maxDepth` et `currentDepth` sont des pourcentages **positifs** : une chute de 1000 à 750 rend `25.0`.

- [ ] **Step 1: Écrire les tests**

Créer `app/Contexts/Valuation/Services/DrawdownTest.php` :

```php
<?php

use App\Contexts\Valuation\Datas\DrawdownData;
use App\Contexts\Valuation\Services\Drawdown;

it('mesure la chute la plus profonde depuis un plus-haut', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01'],
        [1000.0, 1200.0, 900.0, 1100.0],
    );

    expect($drawdown->maxDepth)->toBe(25.0)
        ->and($drawdown->peakLabel)->toBe('2026-02-01')
        ->and($drawdown->troughLabel)->toBe('2026-03-01');
});

it('mesure la chute en cours depuis le dernier plus-haut', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 2000.0, 1500.0],
    );

    expect($drawdown->currentDepth)->toBe(25.0);
});

it('ne rend aucune chute en cours quand le plus-haut est le dernier point', function () {
    $drawdown = (new Drawdown)->of(['2026-01-01', '2026-02-01'], [1000.0, 1200.0]);

    expect($drawdown->currentDepth)->toBe(0.0)
        ->and($drawdown->maxDepth)->toBe(0.0);
});

it('ne rend aucune date sur une série qui ne baisse jamais', function () {
    $drawdown = (new Drawdown)->of(['2026-01-01', '2026-02-01'], [1000.0, 1200.0]);

    expect($drawdown->peakLabel)->toBeNull()
        ->and($drawdown->troughLabel)->toBeNull();
});

it('prend le premier point comme plus-haut d\'une série décroissante', function () {
    $drawdown = (new Drawdown)->of(
        ['2026-01-01', '2026-02-01', '2026-03-01'],
        [1000.0, 800.0, 500.0],
    );

    expect($drawdown->maxDepth)->toBe(50.0)
        ->and($drawdown->peakLabel)->toBe('2026-01-01')
        ->and($drawdown->troughLabel)->toBe('2026-03-01')
        ->and($drawdown->currentDepth)->toBe(50.0);
});

it('ne mesure rien sur une série vide', function () {
    expect((new Drawdown)->of([], []))->toEqual(DrawdownData::empty());
});

it('ne mesure rien tant que la valeur reste nulle ou négative', function () {
    expect((new Drawdown)->of(['2026-01-01'], [0.0]))->toEqual(DrawdownData::empty());
});
```

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=Drawdown`
Expected: FAIL avec `Class "App\Contexts\Valuation\Services\Drawdown" not found`

- [ ] **Step 3: Écrire la Data**

Créer `app/Contexts/Valuation/Datas/DrawdownData.php` :

```php
<?php

namespace App\Contexts\Valuation\Datas;

use JsonSerializable;

/**
 * La pire perte vécue depuis un plus-haut, et celle en cours. Les deux profondeurs sont des
 * pourcentages positifs : une chute de 1000 à 750 vaut 25.
 *
 * Les dates sont nulles quand la série ne baisse jamais — il n'y a alors ni plus-haut suivi d'un
 * creux, ni creux à dater.
 */
readonly class DrawdownData implements JsonSerializable
{
    public function __construct(
        public ?float $maxDepth,
        public ?string $peakLabel,
        public ?string $troughLabel,
        public ?float $currentDepth,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'maxDepth' => $this->maxDepth,
            'peakLabel' => $this->peakLabel,
            'troughLabel' => $this->troughLabel,
            'currentDepth' => $this->currentDepth,
        ];
    }
}
```

- [ ] **Step 4: Écrire le calculateur**

Créer `app/Contexts/Valuation/Services/Drawdown.php` :

```php
<?php

namespace App\Contexts\Valuation\Services;

use App\Contexts\Valuation\Datas\DrawdownData;

/**
 * La perte maximale depuis un plus-haut, en une passe. Mesure le risque vécu, et non la
 * volatilité : c'est ce qu'un porteur a réellement encaissé avant de se refaire.
 */
class Drawdown
{
    /**
     * @param  list<string>  $labels  Même longueur et même ordre que `$values`.
     * @param  list<float>   $values
     */
    public function of(array $labels, array $values): DrawdownData
    {
        $peak = null;
        $peakLabel = null;
        $maxDepth = null;
        $maxPeakLabel = null;
        $maxTroughLabel = null;
        $currentDepth = null;

        foreach ($values as $index => $value) {
            /** Tant que rien ne vaut, il n'y a pas de plus-haut auquel rapporter une chute. */
            if ($value <= 0.0) {
                continue;
            }

            if ($peak === null || $value >= $peak) {
                $peak = $value;
                $peakLabel = $labels[$index];
            }

            $depth = ($peak - $value) / $peak * 100;
            $currentDepth = $depth;
            $maxDepth ??= 0.0;

            if ($depth > $maxDepth) {
                $maxDepth = $depth;
                $maxPeakLabel = $peakLabel;
                $maxTroughLabel = $labels[$index];
            }
        }

        if ($maxDepth === null) {
            return DrawdownData::empty();
        }

        return new DrawdownData(
            maxDepth: $maxDepth,
            peakLabel: $maxPeakLabel,
            troughLabel: $maxTroughLabel,
            currentDepth: $currentDepth,
        );
    }
}
```

- [ ] **Step 5: Lancer les tests**

Run: `php artisan test --compact --filter=Drawdown`
Expected: PASS

- [ ] **Step 6: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: mesure la perte maximale depuis un plus-haut"
```

---

### Task 4 : `BuildExposureSeries`

**Files:**
- Create: `app/Contexts/Valuation/Actions/BuildExposureSeries.php`
- Create: `app/Contexts/Valuation/Actions/BuildExposureSeriesTest.php`

**Interfaces:**
- Consumes: rien des tasks précédentes.
- Produces: `BuildExposureSeries::__invoke(int $userId, ?array $classes = null): ValuationSeriesData` où `$classes` est `?list<AssetClass>`.

`ValuationSeriesData` existe déjà (`app/Contexts/Valuation/Datas/ValuationSeriesData.php`) et porte `labels`, `valuations`, `invested`, `prices`. `valuations` est la série totale cherchée.

Cette action reproduit le début de `BuildPortfolioPerformances::build()` — même filtre, même cache, même `calculateDaily()` — mais rend la série au lieu d'en tirer des fenêtres. **Lis `app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php` avant d'écrire** : le nom de cache doit porter le filtre, sinon une classe servirait le résultat d'une autre.

- [ ] **Step 1: Écrire le test**

Créer `app/Contexts/Valuation/Actions/BuildExposureSeriesTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Valuation\Actions\BuildExposureSeries;

it('rend la série totale de l\'exposition demandée', function () {
    ['user' => $user] = cryptoFixture();

    $series = app(BuildExposureSeries::class)($user->id, [AssetClass::Equity]);

    expect($series->labels)->not->toBeEmpty()
        ->and($series->valuations)->toHaveCount(count($series->labels))
        ->and(end($series->valuations))->toBe(1000.0);
});

it('ne mêle pas deux expositions sous le même nom de cache', function () {
    ['user' => $user] = cryptoFixture();

    $equity = app(BuildExposureSeries::class)($user->id, [AssetClass::Equity]);
    $crypto = app(BuildExposureSeries::class)($user->id, [AssetClass::Crypto]);

    expect(end($equity->valuations))->not->toBe(end($crypto->valuations));
});

it('rend une série vide pour un utilisateur sans transaction', function () {
    expect(app(BuildExposureSeries::class)(0, [AssetClass::Equity])->labels)->toBe([]);
});
```

`cryptoFixture()` pose 10 titres à 100 € (soit 1000 €) et 1 bitcoin à 400 €, **mais aucune transaction sur le bitcoin** — seulement une ligne de portefeuille. Le second test comparerait donc une série garnie à une série vide, et `end([])` rend `false`, ce qui ferait passer l'assertion sans rien prouver.

**Ajoute une transaction d'achat sur le bitcoin dans ce test**, sur le modèle de celle que `portfolioFixture()` crée pour son titre :

```php
    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $crypto->id,
        'quantity' => 1,
        'unit_price' => 300,
        'date' => '2026-01-01',
    ]);
```

en récupérant `$crypto` par `['user' => $user, 'crypto' => $crypto] = cryptoFixture();`. Les deux séries portent alors des valeurs réelles et différentes.

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=BuildExposureSeries`
Expected: FAIL avec `Target class [App\Contexts\Valuation\Actions\BuildExposureSeries] does not exist.`

- [ ] **Step 3: Écrire l'action**

Créer `app/Contexts/Valuation/Actions/BuildExposureSeries.php` :

```php
<?php

namespace App\Contexts\Valuation\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Valuation\Datas\TransactionRecordData;
use App\Contexts\Valuation\Datas\ValuationSeriesData;
use App\Contexts\Valuation\Ports\InstrumentDirectoryPort;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use App\Contexts\Valuation\Ports\SeriesCachePort;
use App\Contexts\Valuation\Ports\TransactionHistoryPort;
use App\Contexts\Valuation\Services\ValuationCalculator;

/**
 * La valeur totale d'une exposition dans le temps, au pas quotidien.
 *
 * Même chemin que `BuildPortfolioPerformances` avant ses fenêtres — les séries par actif de
 * `BuildEvolutionSeries` ne conviendraient pas : les sommer côté appelant rouvrirait l'idiome
 * « accumuler index par index » que le chantier de simplification vient de réduire.
 */
class BuildExposureSeries
{
    public function __construct(
        private TransactionHistoryPort $transactions,
        private PriceHistoryPort $prices,
        private ValuationCalculator $calculator,
        private SeriesCachePort $cache,
        private InstrumentDirectoryPort $directory,
    ) {}

    /** @param  ?list<AssetClass>  $classes  Null pour tout le portefeuille. */
    public function __invoke(int $userId, ?array $classes = null): ValuationSeriesData
    {
        /**
         * Le filtre entre dans le nom retenu : la série agrège les transactions avant d'exister,
         * elle ne se découpe pas après coup. Un nom réutilisé servirait une classe à l'autre.
         */
        return $this->cache->remember(
            $classes === null ? 'exposition' : 'exposition.'.$this->nameOf($classes),
            $userId,
            fn (): ValuationSeriesData => $this->build($userId, $classes),
        );
    }

    /** @param  list<AssetClass>  $classes */
    private function nameOf(array $classes): string
    {
        return implode('-', array_map(fn (AssetClass $class): string => $class->value, $classes));
    }

    /** @param  ?list<AssetClass>  $classes */
    private function build(int $userId, ?array $classes): ValuationSeriesData
    {
        $transactions = $this->transactions->forUser($userId);

        if ($classes !== null) {
            $kept = array_flip($this->directory->idsOfClasses($classes));
            $transactions = array_values(array_filter(
                $transactions,
                fn (TransactionRecordData $transaction): bool => isset($kept[$transaction->assetId]),
            ));
        }

        if ($transactions === []) {
            return ValuationSeriesData::empty();
        }

        $assetIds = array_values(array_unique(array_map(
            fn (TransactionRecordData $transaction): int => $transaction->assetId,
            $transactions,
        )));

        return $this->calculator->calculateDaily(
            $transactions,
            $this->prices->forAssetsSince($assetIds, $transactions[0]->date),
        );
    }
}
```

- [ ] **Step 4: Lancer le test**

Run: `php artisan test --compact --filter=BuildExposureSeries`
Expected: PASS

- [ ] **Step 5: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: expose la série totale d'une exposition dans le temps"
```

---

### Task 5 : `GetPortfolioAnalysis`

**Files:**
- Create: `app/Contexts/Portfolio/Actions/GetPortfolioAnalysis.php`
- Create: `app/Contexts/Portfolio/Actions/GetPortfolioAnalysisTest.php`
- Create: `app/Contexts/Portfolio/Datas/PortfolioAnalysisData.php`

**Interfaces:**
- Consumes: `Concentration::of()` (Task 1), `PerformanceContribution::of()` (Task 2), et l'existant `PositionAggregator::__invoke(array $rows): array{quantity: float, avgCost: ?float}`, `HoldingValuator::value(float $quantity, ?float $avgCost, ?float $lastPrice): array{marketValue: ?float, cost: ?float, gain: ?float, gainPct: ?float}`.
- Produces:
  - `PortfolioAnalysisData` : `readonly class` avec `ConcentrationData $concentration` et `list<ContributionData> $contributions`, `empty()`, `jsonSerialize()` dans l'ordre `concentration, contributions`.
  - `GetPortfolioAnalysis::__invoke(User $user, ?array $classes = null): PortfolioAnalysisData`.

**Pourquoi cette action et pas `GetPortfolioPositions`** : les analyses ont besoin de positions **filtrées par exposition** et **nommées**. `GetPortfolioPositions` ne fait ni l'un ni l'autre, tandis que `GetPortfolioOverview($user, $classes)` rend des `HoldingLineData` filtrées et nommées, déjà mémoïsées par utilisateur. On regroupe donc ses lignes par actif ici même. C'est aussi, en petit, la consolidation que la revue du chantier précédent recommandait.

- [ ] **Step 1: Écrire le test**

Créer `app/Contexts/Portfolio/Actions/GetPortfolioAnalysisTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioAnalysis;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

it('regroupe les enveloppes d\'un actif avant de mesurer la concentration', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $instrument->id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    $analysis = app(GetPortfolioAnalysis::class)($user, [AssetClass::Equity]);

    /** Deux enveloppes, un seul actif : la concentration est totale, pas partagée en deux. */
    expect($analysis->concentration->top1)->toBe(100.0)
        ->and($analysis->concentration->hhi)->toBe(1.0)
        ->and($analysis->contributions)->toHaveCount(1);
});

it('nomme chaque contribution et la rapporte à la valeur totale', function () {
    ['user' => $user] = portfolioFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, [AssetClass::Equity]);

    expect($analysis->contributions[0]->assetName)->toBe('ACME')
        ->and($analysis->contributions[0]->weight)->toBe(100.0)
        ->and($analysis->contributions[0]->contribution)->toBe(20.0);
});

it('ne retient que l\'exposition demandée', function () {
    ['user' => $user] = cryptoFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, [AssetClass::Crypto]);

    expect($analysis->contributions)->toHaveCount(1)
        ->and($analysis->contributions[0]->assetName)->toBe('Bitcoin');
});

it('ne mesure rien sur un portefeuille vide', function () {
    ['user' => $user] = portfolioFixture();

    $analysis = app(GetPortfolioAnalysis::class)($user, [AssetClass::Bond]);

    expect($analysis->concentration->hhi)->toBeNull()
        ->and($analysis->contributions)->toBe([]);
});
```

`portfolioFixture()` pose 10 titres à 100 € payés 80 € : valeur 1000 €, gain 200 €, donc une contribution de 200/1000 = 20 points.

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=GetPortfolioAnalysis`
Expected: FAIL avec `Target class [App\Contexts\Portfolio\Actions\GetPortfolioAnalysis] does not exist.`

- [ ] **Step 3: Écrire la Data**

Créer `app/Contexts/Portfolio/Datas/PortfolioAnalysisData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use JsonSerializable;

/** Ce qu'une exposition apprend d'elle-même : ce qu'elle concentre, et ce qui la fait avancer. */
readonly class PortfolioAnalysisData implements JsonSerializable
{
    /** @param  list<ContributionData>  $contributions */
    public function __construct(
        public ConcentrationData $concentration,
        public array $contributions,
    ) {}

    public static function empty(): self
    {
        return new self(ConcentrationData::empty(), []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'concentration' => $this->concentration->jsonSerialize(),
            'contributions' => array_map(
                fn (ContributionData $line): array => $line->jsonSerialize(),
                $this->contributions,
            ),
        ];
    }
}
```

- [ ] **Step 4: Écrire l'action**

Créer `app/Contexts/Portfolio/Actions/GetPortfolioAnalysis.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioAnalysisData;
use App\Contexts\Portfolio\Services\Concentration;
use App\Contexts\Portfolio\Services\HoldingValuator;
use App\Contexts\Portfolio\Services\PerformanceContribution;
use App\Contexts\Portfolio\Services\PositionAggregator;

/**
 * Les analyses d'une exposition, mesurées sur ses positions et non sur ses lignes : un titre tenu
 * dans deux enveloppes est une seule exposition au marché, et le compter deux fois sous-estimerait
 * précisément ce que la concentration sert à détecter.
 *
 * Repart de `GetPortfolioOverview`, mémoïsée par utilisateur et déjà filtrée par exposition, plutôt
 * que de `GetPortfolioPositions`, qui ne filtre ni ne nomme.
 */
class GetPortfolioAnalysis
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private PositionAggregator $aggregate,
        private HoldingValuator $valuator,
        private Concentration $concentration,
        private PerformanceContribution $contribution,
    ) {}

    /** @param  ?list<AssetClass>  $classes */
    public function __invoke(User $user, ?array $classes = null): PortfolioAnalysisData
    {
        $overview = ($this->overview)($user, $classes);

        if ($overview->holdings === []) {
            return PortfolioAnalysisData::empty();
        }

        $positions = $this->positionsOf($overview->holdings);

        return new PortfolioAnalysisData(
            concentration: $this->concentration->of(array_column($positions, 'marketValue')),
            contributions: $this->contribution->of($positions, $overview->totalValue),
        );
    }

    /**
     * Les lignes regroupées par actif : quantités sommées, prix de revient moyenné, valeur et gain
     * recalculés sur la position entière.
     *
     * @param  list<HoldingLineData>  $lines
     * @return list<array{assetId: int, assetName: string, gain: ?float, marketValue: ?float}>
     */
    private function positionsOf(array $lines): array
    {
        $byAsset = [];

        foreach ($lines as $line) {
            $byAsset[$line->assetId][] = $line;
        }

        $positions = [];

        foreach ($byAsset as $assetId => $assetLines) {
            $aggregated = ($this->aggregate)(array_map(fn (HoldingLineData $line): array => [
                'quantity' => $line->quantity,
                'avgCost' => $line->avgCost,
            ], $assetLines));

            $valued = $this->valuator->value(
                $aggregated['quantity'],
                $aggregated['avgCost'],
                $assetLines[0]->lastPrice,
            );

            $positions[] = [
                'assetId' => (int) $assetId,
                'assetName' => $assetLines[0]->assetName,
                'gain' => $valued['gain'],
                'marketValue' => $valued['marketValue'],
            ];
        }

        return $positions;
    }
}
```

- [ ] **Step 5: Lancer le test**

Run: `php artisan test --compact --filter=GetPortfolioAnalysis`
Expected: PASS

- [ ] **Step 6: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: assemble les analyses d'une exposition sur ses positions"
```

---

### Task 6 : Les ports et leurs jumelles

**Files:**
- Create: `app/Contexts/MarketView/Datas/ConcentrationData.php`
- Create: `app/Contexts/MarketView/Datas/ContributionLineData.php`
- Create: `app/Contexts/MarketView/Datas/AnalysisData.php`
- Create: `app/Contexts/MarketView/Datas/DrawdownData.php`
- Modify: `app/Contexts/MarketView/Ports/PortfolioOverviewPort.php`
- Modify: `app/Contexts/MarketView/Ports/ValuationPort.php`
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php`
- Modify: `app/Contexts/MarketView/Infrastructure/ValuationHistory.php`
- Modify: `tests/Unit/MarketView/DatasTest.php`

**Interfaces:**
- Consumes: `GetPortfolioAnalysis::__invoke(User $user, ?array $classes): PortfolioAnalysisData` (Task 5), `BuildExposureSeries::__invoke(int $userId, ?array $classes): ValuationSeriesData` (Task 4), `Drawdown::of(array $labels, array $values): DrawdownData` (Task 3).
- Produces:
  - `PortfolioOverviewPort::analysisFor(int $userId, AssetClass $exposure): AnalysisData`
  - `ValuationPort::drawdownFor(int $userId, AssetClass $exposure): DrawdownData` (celui de `MarketView\Datas`)

Les quatre Datas de `MarketView` jumellent celles de `Portfolio` et `Valuation` : **mêmes clés, même ordre**. `AnalysisData` porte `concentration` puis `contributions`, `ContributionLineData` porte `assetId, assetName, contribution, weight`, `ConcentrationData` porte `top1, top3, top5, hhi`, `DrawdownData` porte `maxDepth, peakLabel, troughLabel, currentDepth`.

- [ ] **Step 1: Écrire les jumelles**

Créer `app/Contexts/MarketView/Datas/ConcentrationData.php` :

```php
<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\ConcentrationData` : mêmes clés, même ordre. */
readonly class ConcentrationData implements JsonSerializable
{
    public function __construct(
        public ?float $top1,
        public ?float $top3,
        public ?float $top5,
        public ?float $hhi,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'top1' => $this->top1,
            'top3' => $this->top3,
            'top5' => $this->top5,
            'hhi' => $this->hhi,
        ];
    }
}
```

Créer `app/Contexts/MarketView/Datas/ContributionLineData.php` :

```php
<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\ContributionData` : mêmes clés, même ordre. */
readonly class ContributionLineData implements JsonSerializable
{
    public function __construct(
        public int $assetId,
        public string $assetName,
        public ?float $contribution,
        public ?float $weight,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'assetId' => $this->assetId,
            'assetName' => $this->assetName,
            'contribution' => $this->contribution,
            'weight' => $this->weight,
        ];
    }
}
```

Créer `app/Contexts/MarketView/Datas/AnalysisData.php` :

```php
<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Portfolio\Datas\PortfolioAnalysisData` : mêmes clés, même ordre. */
readonly class AnalysisData implements JsonSerializable
{
    /** @param  list<ContributionLineData>  $contributions */
    public function __construct(
        public ConcentrationData $concentration,
        public array $contributions,
    ) {}

    public static function empty(): self
    {
        return new self(ConcentrationData::empty(), []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'concentration' => $this->concentration->jsonSerialize(),
            'contributions' => array_map(
                fn (ContributionLineData $line): array => $line->jsonSerialize(),
                $this->contributions,
            ),
        ];
    }
}
```

Créer `app/Contexts/MarketView/Datas/DrawdownData.php` :

```php
<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/** Jumelle de `Valuation\Datas\DrawdownData` : mêmes clés, même ordre. */
readonly class DrawdownData implements JsonSerializable
{
    public function __construct(
        public ?float $maxDepth,
        public ?string $peakLabel,
        public ?string $troughLabel,
        public ?float $currentDepth,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'maxDepth' => $this->maxDepth,
            'peakLabel' => $this->peakLabel,
            'troughLabel' => $this->troughLabel,
            'currentDepth' => $this->currentDepth,
        ];
    }
}
```

- [ ] **Step 2: Écrire le test de jumelage**

Ajouter à `tests/Unit/MarketView/DatasTest.php` :

```php
it('reproduit le JSON des analyses de Portfolio à la clé près', function () {
    $twin = new App\Contexts\MarketView\Datas\ConcentrationData(25.0, 75.0, 100.0, 0.25);
    $origin = new App\Contexts\Portfolio\Datas\ConcentrationData(25.0, 75.0, 100.0, 0.25);

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON du drawdown de Valuation à la clé près', function () {
    $twin = new App\Contexts\MarketView\Datas\DrawdownData(25.0, '2026-02-01', '2026-03-01', 10.0);
    $origin = new App\Contexts\Valuation\Datas\DrawdownData(25.0, '2026-02-01', '2026-03-01', 10.0);

    expect(json_encode($twin))->toBe(json_encode($origin));
});

it('reproduit le JSON d\'une contribution à la clé près', function () {
    $twin = new App\Contexts\MarketView\Datas\ContributionLineData(1, 'ACME', 3.6, 30.0);
    $origin = new App\Contexts\Portfolio\Datas\ContributionData(1, 'ACME', 3.6, 30.0);

    expect(json_encode($twin))->toBe(json_encode($origin));
});
```

- [ ] **Step 3: Lancer pour vérifier**

Run: `php artisan test --compact --filter=Datas`
Expected: PASS (les jumelles existent déjà depuis le step 1 ; si un test échoue, c'est un ordre de clés à corriger, pas un test à ajuster)

- [ ] **Step 4: Étendre les deux ports**

Dans `app/Contexts/MarketView/Ports/PortfolioOverviewPort.php` :

```php
    /** Les analyses d'une exposition : ce qu'elle concentre, et ce qui la fait avancer. */
    public function analysisFor(int $userId, AssetClass $exposure): AnalysisData;
```

Dans `app/Contexts/MarketView/Ports/ValuationPort.php` :

```php
    /** La perte maximale vécue par une exposition, et celle en cours. */
    public function drawdownFor(int $userId, AssetClass $exposure): DrawdownData;
```

Avec les `use` correspondants vers `App\Contexts\MarketView\Datas\…`.

- [ ] **Step 5: Implémenter dans les deux adaptateurs**

Dans `PortfolioTotals`, injecter `private GetPortfolioAnalysis $analysis,` et ajouter :

```php
    public function analysisFor(int $userId, AssetClass $exposure): AnalysisData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return AnalysisData::empty();
        }

        $analysis = ($this->analysis)($user, [$exposure]);

        return new AnalysisData(
            concentration: new ConcentrationData(
                top1: $analysis->concentration->top1,
                top3: $analysis->concentration->top3,
                top5: $analysis->concentration->top5,
                hhi: $analysis->concentration->hhi,
            ),
            contributions: array_map(
                fn (ContributionData $line): ContributionLineData => new ContributionLineData(
                    assetId: $line->assetId,
                    assetName: $line->assetName,
                    contribution: $line->contribution,
                    weight: $line->weight,
                ),
                $analysis->contributions,
            ),
        );
    }
```

Dans `ValuationHistory`, injecter `private BuildExposureSeries $exposureSeries,` et `private Drawdown $drawdown,` (import `App\Contexts\Valuation\Services\Drawdown`), puis :

```php
    public function drawdownFor(int $userId, AssetClass $exposure): DrawdownData
    {
        $series = ($this->exposureSeries)($userId, [$exposure]);
        $drawdown = $this->drawdown->of($series->labels, $series->valuations);

        return new DrawdownData(
            maxDepth: $drawdown->maxDepth,
            peakLabel: $drawdown->peakLabel,
            troughLabel: $drawdown->troughLabel,
            currentDepth: $drawdown->currentDepth,
        );
    }
```

**Attention à la règle de `MarketView`** : l'adaptateur appelle un `Services/` de `Valuation`, ce qui reste du remappage — il ne calcule rien lui-même. Si tu juges que cela franchit la ligne, remonte le calcul dans une action `Valuation\Actions\BuildExposureDrawdown` et dis-le dans ton rapport.

- [ ] **Step 6: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add -A
git commit -m "feat: expose analyses et drawdown aux ports de MarketView"
```

---

### Task 7 : La page d'analyse, en ligne

**Files:**
- Create: `app/Contexts/MarketView/Http/AssetClassAnalysisController.php`
- Create: `app/Contexts/MarketView/Http/AssetClassAnalysisControllerTest.php`
- Create: `resources/js/Pages/AssetClass/Analysis.vue`
- Create: `resources/js/components/instruments/ConcentrationSection.vue`
- Create: `resources/js/components/instruments/ContributionSection.vue`
- Create: `resources/js/components/instruments/DrawdownSection.vue`
- Create: `resources/js/lib/analysis.ts`
- Modify: `routes/web.php:20-24`

**Interfaces:**
- Consumes: `PortfolioOverviewPort::analysisFor()` et `ValuationPort::drawdownFor()` (Task 6).
- Produces: la route nommée `classes.<valeur>.analyse`, la page Inertia `AssetClass/Analysis`, et les types TypeScript `Concentration`, `Contribution`, `Drawdown`, `Analysis` dans `resources/js/lib/analysis.ts`.

À ce stade la page ne porte **que** les trois nouvelles sections. Le déménagement des trois anciennes est la Task 8 — les séparer garde chaque commit vert et chaque diff lisible.

- [ ] **Step 1: Écrire le test du contrôleur**

Créer `app/Contexts/MarketView/Http/AssetClassAnalysisControllerTest.php` :

```php
<?php

use Inertia\Testing\AssertableInertia as Assert;

it('rend la page analyse d\'une exposition', function () {
    cryptoFixture();

    $this->get('/actions/analyse')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AssetClass/Analysis')
            ->where('assetClass.key', 'equity')
            ->where('assetClass.label', 'Actions')
        );
});

it('sert une page analyse par exposition', function () {
    cryptoFixture();

    foreach (['/actions/analyse', '/crypto/analyse', '/obligations/analyse', '/matieres-premieres/analyse'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('résout les analyses en prop différée', function () {
    cryptoFixture();

    $this->get('/actions/analyse', ['X-Inertia' => true, 'X-Inertia-Partial-Component' => 'AssetClass/Analysis', 'X-Inertia-Partial-Data' => 'analysis'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('analysis.concentration.top1', 100.0));
});
```

Si l'en-tête partiel ne se comporte pas comme attendu, remplace le troisième test par une résolution directe des props différées via `Inertia::resolveDeferredProps()` — mais garde une assertion sur `analysis.concentration.top1`.

- [ ] **Step 2: Lancer pour vérifier l'échec**

Run: `php artisan test --compact --filter=AssetClassAnalysisController`
Expected: FAIL avec une 404

- [ ] **Step 3: Écrire le contrôleur**

Créer `app/Contexts/MarketView/Http/AssetClassAnalysisController.php` :

```php
<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Ports\PortfolioOverviewPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page analyse d'une exposition. Aucune prop synchrone, à la différence de la page liste :
 * tout ce qu'elle montre demande un calcul, et rien n'a besoin d'être là au premier rendu.
 */
class AssetClassAnalysisController
{
    public function __construct(
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
    ) {}

    public function __invoke(): Response
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        return Inertia::render('AssetClass/Analysis', [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
            ],
            /** Un seul groupe : les trois analyses viennent des mêmes lectures mémoïsées. */
            'analysis' => Inertia::defer(
                fn () => $this->overview->analysisFor($userId, $exposure), 'analyses',
            ),
            'drawdown' => Inertia::defer(
                fn () => $this->valuation->drawdownFor($userId, $exposure), 'analyses',
            ),
        ]);
    }
}
```

- [ ] **Step 4: Déclarer la route**

Dans `routes/web.php`, à l'intérieur de la boucle existante sur `AssetClass::cases()`, après la route liste :

```php
    Route::get("{$assetClass->slug()}/analyse", AssetClassAnalysisController::class)
        ->defaults('exposure', $assetClass->value)
        ->name("classes.{$assetClass->value}.analyse");
```

Ajouter l'import `use App\Contexts\MarketView\Http\AssetClassAnalysisController;`.

- [ ] **Step 5: Écrire les types TypeScript**

Créer `resources/js/lib/analysis.ts` :

```ts
export interface Concentration {
    top1: number | null;
    top3: number | null;
    top5: number | null;
    hhi: number | null;
}

export interface Contribution {
    assetId: number;
    assetName: string;
    contribution: number | null;
    weight: number | null;
}

export interface Analysis {
    concentration: Concentration;
    contributions: Contribution[];
}

export interface Drawdown {
    maxDepth: number | null;
    peakLabel: string | null;
    troughLabel: string | null;
    currentDepth: number | null;
}
```

- [ ] **Step 6: Écrire les trois sections**

**Lis `resources/js/components/instruments/PerformancesSection.vue` d'abord** : les trois nouveaux composants reprennent sa structure — en-tête de section, squelette animé tant que la prop vaut `null`, classes Tailwind. Le code ci-dessous donne le premier en entier ; les deux autres suivent la même charpente avec le contenu décrit.

Créer `resources/js/components/instruments/ConcentrationSection.vue` :

```vue
<script setup lang="ts">
import { pct } from '@/lib/format';
import type { Concentration } from '@/lib/analysis';

const props = defineProps<{ concentration: Concentration | null }>();

/** Le HHI n'est pas un pourcentage : 1 pour une position unique, 1/n pour n positions égales. */
const hhi = (value: number | null): string => (value === null ? '—' : value.toFixed(2));
</script>

<template>
    <section data-section="concentration" class="flex shrink-0 flex-col gap-3 px-6">
        <header class="flex flex-col gap-0.5">
            <h2 class="text-sm font-semibold">Concentration des positions</h2>
            <p class="text-[13px] text-muted-foreground">
                Un titre détenu dans plusieurs enveloppes compte pour une seule position.
            </p>
        </header>

        <div v-if="props.concentration === null" class="h-16 animate-pulse rounded-lg bg-muted" />

        <dl v-else class="grid grid-cols-4 gap-3">
            <div v-for="entry in [
                    { label: 'Top 1', value: pct(props.concentration.top1) },
                    { label: 'Top 3', value: pct(props.concentration.top3) },
                    { label: 'Top 5', value: pct(props.concentration.top5) },
                    { label: 'HHI', value: hhi(props.concentration.hhi) },
                ]" :key="entry.label" class="flex flex-col gap-0.5">
                <dt class="text-[13px] text-muted-foreground">{{ entry.label }}</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ entry.value }}</dd>
            </div>
        </dl>
    </section>
</template>
```

`ContributionSection.vue` — prop `contributions: Contribution[] | null`, `data-section="contribution"`. Titre « Contribution au rendement », sous-titre « En points de performance du portefeuille ». Une ligne par position : `assetName`, `pct(contribution)`, et `pct(weight)` en gris. Tableau vide → « Aucune position mesurable ».

`DrawdownSection.vue` — prop `drawdown: Drawdown | null`, `data-section="drawdown"`. Titre « Perte maximale ». Affiche `pct(maxDepth)` avec ses deux dates sous la forme « du {peakLabel} au {troughLabel} », et `pct(currentDepth)` sous le libellé « Perte en cours ». Quand `peakLabel` est `null` → « Aucune baisse depuis le plus-haut ».

Les trois `data-section` servent aux tests de navigateur, qui repèrent les sections par cet attribut.

- [ ] **Step 7: Écrire la page**

Créer `resources/js/Pages/AssetClass/Analysis.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import ConcentrationSection from '@/components/instruments/ConcentrationSection.vue';
import ContributionSection from '@/components/instruments/ContributionSection.vue';
import DrawdownSection from '@/components/instruments/DrawdownSection.vue';
import type { Analysis, Drawdown } from '@/lib/analysis';

const props = defineProps<{
    assetClass: { key: string; label: string; slug: string };
    analysis?: Analysis;
    drawdown?: Drawdown;
}>();
</script>

<template>
    <Head :title="`Analyse — ${props.assetClass.label}`" />

    <AppPage>
        <ConcentrationSection :concentration="props.analysis?.concentration ?? null" />
        <ContributionSection :contributions="props.analysis?.contributions ?? null" />
        <DrawdownSection :drawdown="props.drawdown ?? null" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.assetClass.label, href: `/${props.assetClass.slug}` },
            { label: 'Analyse' },
        ]"
    />
</template>
```

Le slug **ne se déduit pas de la clé** (`equity` → `actions`) : il vient du contrôleur. Ajoute `'slug' => $exposure->slug(),` à la prop `assetClass` du step 3, et une assertion `->where('assetClass.slug', 'actions')` au premier test du step 1.

- [ ] **Step 8: Lancer tout**

Run: `php artisan test --compact --filter=AssetClassAnalysisController`
Expected: PASS

Run: `bun run typecheck` puis `bun run test:js`
Expected: PASS

- [ ] **Step 9: Pint, suite, commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
bun run typecheck
git add -A
git commit -m "feat: sert une page d'analyse par exposition"
```

---

### Task 8 : Le déménagement et l'instantané

**Files:**
- Modify: `app/Contexts/MarketView/Http/AssetClassController.php:40-75`
- Modify: `app/Contexts/MarketView/Http/AssetClassAnalysisController.php`
- Modify: `resources/js/Pages/AssetClass/Index.vue`
- Modify: `resources/js/Pages/AssetClass/Analysis.vue`
- Modify: `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php:65-84`
- Modify: `resources/js/lib/snapshotContract.ts:29-37`
- Modify: `resources/js/stores/snapshot.ts`
- Modify: `tests/Feature/SnapshotInvariantTest.php`
- Modify: `app/Contexts/MarketView/Http/AssetClassControllerTest.php`
- Modify: `tests/Feature/InstrumentsPageTest.php`

**Interfaces:**
- Consumes: tout ce qui précède.
- Produces: `AssetClassListSnapshot` perd `sectorBreakdown`, `income`, `annualIncome` ; `Snapshot` gagne `analyses: Record<string, AssetClassAnalysisSnapshot>` ; le store gagne `classAnalysis(key: string): AssetClassAnalysisSnapshot | null`.

**C'est la seule task autorisée à faire bouger le hash du filet**, et elle le fera une fois. Ne la découpe pas en deux commits qui laisseraient le blob incohérent — soit les sections sont parties des deux côtés à la fois, soit elles ne sont parties nulle part.

- [ ] **Step 1: Déplacer les trois sections côté serveur**

Dans `AssetClassController`, supprimer les blocs `if ($exposure->hasSectors())` et `if ($this->income->supportsExposure($exposure))`, ainsi que `performances`. Retirer les dépendances `SectorBreakdownPort` et `IncomePort` du constructeur si plus rien ne les utilise. La prop `assetClass` perd `hasSectors` et `hasIncome`, qui déménagent aussi.

Dans `AssetClassAnalysisController`, ajouter les trois props reprises telles quelles depuis l'ancien contrôleur — `performances` (groupe `performances`), `sectorBreakdown` (groupe `secteurs`), `income` et `annualIncome` (groupe `revenus`) — sous les mêmes gates, et ajouter `hasSectors` / `hasIncome` à la prop `assetClass`.

- [ ] **Step 2: Déplacer les trois sections côté client**

`Index.vue` perd ses imports et ses balises `PerformancesSection`, `IncomeSection`, `SectorsSection`, ainsi que les `aheadOfNetwork` correspondants. `Analysis.vue` les gagne, avec les mêmes `aheadOfNetwork` branchés sur `snapshot.classAnalysis(props.assetClass.key)`.

Garder le commentaire d'origine d'`Index.vue` sur le fait que les sections se décident sur la classe et jamais sur la valeur — il reste vrai, et il explique pourquoi `hasSectors` voyage avec la classe.

- [ ] **Step 3: Déplacer dans l'instantané**

Dans `BuildMarketViewSnapshot`, `listFor()` perd ses deux blocs conditionnels et `performances`. Ajouter une méthode `analysisFor()` bâtie sur la composition du nouveau contrôleur, et une clé `analyses` au retour de `__invoke()` :

```php
        return [
            'classes' => $classes,
            'analyses' => $analyses,
            'assets' => $this->pagesFor($userId),
        ];
```

`SnapshotController` reprend la clé telle quelle dans son `$body`, après `classes`.

- [ ] **Step 4: Suivre côté TypeScript**

Dans `snapshotContract.ts`, retirer les trois clés optionnelles d'`AssetClassListSnapshot`, ajouter :

```ts
export interface AssetClassAnalysisSnapshot {
    analysis: Analysis;
    drawdown: Drawdown;
    performances: Performance[];
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}
```

et `analyses: Record<string, AssetClassAnalysisSnapshot>;` à `Snapshot`. Dans `snapshot.ts`, ajouter à côté de `classList()` :

```ts
    function classAnalysis(key: string): AssetClassAnalysisSnapshot | null {
        return snapshot.value?.analyses[key] ?? null;
    }
```

et l'exposer dans le `return` du store.

**`isCurrentShape()` doit aussi savoir reconnaître la nouvelle forme** : un blob retenu avant ce déploiement porte `classes` mais pas `analyses`, et le lire à moitié ferait planter `classAnalysis()`. Ajouter `&& 'analyses' in candidate`, sur le motif du commentaire déjà présent.

- [ ] **Step 5: Enrichir le jeu du filet, puis refiger le hash**

Le jeu de `seedSnapshotFixture()` doit produire une page analyse non vide, sans quoi on refigerait un hash sur des sections vides — l'erreur relevée sur les charges d'un bien au chantier précédent. `cryptoFixture()` donne déjà des titres avec transaction et secteur, donc concentration, contribution et secteurs sont couverts ; **vérifie que le drawdown l'est aussi** en dumpant le corps de la réponse, et si `analyses.equity.drawdown.maxDepth` est nul, ajoute au jeu un cours intermédiaire plus haut que le dernier pour créer une baisse.

Puis lancer le test, lire le hash effectif, le coller, et ajouter sous les lignes existantes :

```
 * Modifié une troisième fois : secteurs, performances et revenus quittent la composition liste
 * pour la page analyse, et le blob gagne une clé `analyses` par exposition.
```

- [ ] **Step 6: Rattraper les tests des pages**

`AssetClassControllerTest` et `tests/Feature/InstrumentsPageTest.php` affirment la présence de sections qui ont déménagé. Déplacer ces assertions vers `AssetClassAnalysisControllerTest` plutôt que les supprimer — les règles du projet interdisent de supprimer un test sans accord, et la règle qu'elles vérifient existe toujours, ailleurs. Signale dans ton rapport chaque assertion déplacée, avec sa nouvelle adresse.

- [ ] **Step 7: Lancer tout**

Run: `php artisan test --compact`
Expected: PASS

Run: `bun run typecheck` puis `bun run test:js`
Expected: PASS

Run: `node tests/pwa-offline.mjs`
Expected: PASS — c'est le test qui vérifie le chemin hors-ligne de bout en bout.

- [ ] **Step 8: Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: déplace secteurs, performances et revenus vers la page analyse"
```

---

### Task 9 : Le lien, et le ménage

**Files:**
- Modify: `resources/js/Pages/AssetClass/Index.vue`
- Create: `tests/Browser/AnalysisLinkTest.php` *(ou une assertion dans `tests/Feature/InstrumentsPageTest.php` — voir step 1)*
- Modify: `app/Contexts/Portfolio/Datas/PositionLineData.php` *(conditionnel, voir step 3)*

**Interfaces:**
- Consumes: la route `classes.<valeur>.analyse` (Task 7).
- Produces: rien que les tasks suivantes consomment.

- [ ] **Step 1: Ajouter le lien en bas du listing**

`Index.vue` doit recevoir `slug` dans sa prop `assetClass` — ajoute `'slug' => $exposure->slug(),` à `AssetClassController` comme cela a été fait pour l'autre contrôleur, et au type de la prop.

Puis, après la dernière section du template :

```vue
        <Link
            data-analysis-link
            :href="`/${props.assetClass.slug}/analyse`"
            class="mx-6 flex items-center justify-between rounded-lg border px-4 py-3 text-sm font-medium hover:bg-muted"
        >
            Analyse de l'exposition
            <ChevronRight class="size-4 text-muted-foreground" />
        </Link>
```

avec `import { Link } from '@inertiajs/vue3';` et `import { ChevronRight } from 'lucide-vue-next';` — les deux sont déjà employés ailleurs dans l'application, notamment par `AppBottomBar.vue`.

Ajouter à `tests/Feature/InstrumentsPageTest.php` :

```php
it('mène de la page liste à sa page analyse', function () {
    cryptoFixture();

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('assetClass.slug', 'actions'));
});
```

Regarde `tests/Browser/` avant de choisir : si un test de navigation y suit déjà ce motif, un test de navigateur cliquant `[data-analysis-link]` et vérifiant l'arrivée sur `/actions/analyse` vaut mieux que l'assertion de prop ci-dessus.

- [ ] **Step 2: Lancer**

Run: `php artisan test --compact --filter=InstrumentsPage`
Expected: PASS

- [ ] **Step 3: Trancher `PositionLineData::$lastPrice`**

Ce champ n'a aucun appelant en production depuis le chantier précédent, et les analyses ne lui en donnent pas — `GetPortfolioAnalysis` lit `lastPrice` sur `HoldingLineData`, pas sur lui.

Vérifie par `grep -rn "lastPrice" app/Contexts/Portfolio` et retire-le de `PositionLineData` si rien ne le lit hors de son propre test. Ajuste `GetPortfolioPositions` et `GetPortfolioPositionsTest` en conséquence. Si un appelant existe, laisse-le et dis lequel dans ton rapport.

- [ ] **Step 4: Lancer tout**

Run: `php artisan test --compact`
Expected: PASS

Run: `bun run typecheck` puis `bun run test:js`
Expected: PASS

- [ ] **Step 5: Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: relie la page liste à sa page d'analyse"
```

---

## Vérification finale

- [ ] `php artisan test --compact` — toute la suite verte
- [ ] `bun run test:js` — vert
- [ ] `bun run typecheck` — vert
- [ ] `node tests/pwa-offline.mjs` — vert
- [ ] Le hash de `SnapshotInvariantTest` n'a bougé **qu'une fois**, en Task 8, avec sa raison écrite
- [ ] `grep -rn "SectorsSection\|IncomeSection\|PerformancesSection" resources/js/Pages` — seulement dans `Analysis.vue`
- [ ] Les quatre pages analyse répondent : `/actions/analyse`, `/crypto/analyse`, `/obligations/analyse`, `/matieres-premieres/analyse`
- [ ] La page analyse de la crypto ne montre que les trois nouvelles sections, sans trou de mise en page

## Suite

Deux consolidations que la revue du chantier précédent a signalées restent ouvertes, et ce chantier en effleure une : `GetPortfolioPositions` reste un troisième lecteur de `holdings_projection` alors que `GetPortfolioAnalysis` montre qu'on sait dériver des positions depuis `GetPortfolioOverview`. Les fusionner ferait passer de trois lecteurs à deux. L'autre — `SeriesStepper::sumUpTo()` et `PropertyWindowTotals::within()` sous deux noms — ne touche pas ce chantier.
