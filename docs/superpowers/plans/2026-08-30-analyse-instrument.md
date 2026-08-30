# Section « Analyse » de la fiche instrument — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter à `/asset/{id}` une section repliable « Analyse » qui affiche PRU, MM200, RSI 14, plus-haut 52 semaines, ATR 14, max drawdown et poids dans le portefeuille, chaque ligne portant un dialogue d'aide expliquant comment la lire.

**Architecture:** Les formules vivent dans des calculateurs purs de `app/Contexts/Market/Services/` (nouveau dossier) et `Portfolio\Services\PositionWeight`. Un adaptateur `MarketView\Infrastructure\InstrumentAnalysis`, derrière le port `InstrumentAnalysisPort`, compose ces calculateurs avec le dépôt de cours et les actions Portfolio, sans jamais écrire de formule. La prop `analysis` est différée sur la fiche et incluse dans l'instantané hors ligne.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest, Inertia v3 + Vue 3 `<script setup>` TypeScript, Tailwind, shadcn-vue (`components/ui/dialog`), Vitest, Pest Browser.

**Spec:** `docs/superpowers/specs/2026-08-30-analyse-instrument-design.md`

## Global Constraints

- **Tout texte visible est en français**, accents compris. Les identifiants de code restent en anglais quand le contexte l'est déjà.
- **Doctrine `Services/`** (`.ai/rules/services.md`) : un calculateur ne connaît ni Eloquent, ni port, ni conteneur. Entrées nues ou Datas de son propre contexte, sortie nue ou Data. **Son test est co-localisé** (`XxxTest.php` à côté de la classe), construit l'objet avec `new`, sans base de données ni `RefreshDatabase`.
- **Doctrine MarketView** (`.ai/rules/market-view.md`) : MarketView ne calcule rien. Un adaptateur de `Infrastructure/` peut composer une action et un `Services/` du contexte propriétaire, jamais écrire la formule.
- **L'ordre des clés de `jsonSerialize()` est un contrat** : `SnapshotController` publie `sha1(json_encode($body))` comme version du blob hors ligne.
- **PHP** : accolades toujours, promotion de propriétés en constructeur, types de retour explicites, PHPDoc plutôt que commentaires en ligne, formes de tableaux documentées en PHPDoc.
- **Après toute modification PHP** : `vendor/bin/pint --dirty --format agent`.
- **Commandes** : `php artisan test --compact --filter=...` pour les tests PHP, `bunx vitest run <fichier>` pour les tests front, `bun run build` avant tout test navigateur.
- **Commit après chaque tâche**, message en français, préfixe `feat:` / `test:` / `refactor:`, terminé par les deux lignes `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>` et `Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2`.

**État connu à la rédaction :** `tests/Browser/HeroSectionTest.php` porte **trois échecs préexistants** (le libellé « (latent) » ajouté par le commit `177ab76`), sans rapport avec ce plan. Ne pas les compter comme des régressions ; ne pas les corriger ici non plus.

---

### Task 1 : `MovingAverage` et `PriceGap`

Deux calculateurs minuscules, livrés ensemble : le second n'existe que pour servir le premier et le PRU, et un commit qui n'apporterait que trois lignes ne mérite pas sa propre revue.

**Files:**
- Create: `app/Contexts/Market/Services/MovingAverage.php`
- Create: `app/Contexts/Market/Services/MovingAverageTest.php`
- Create: `app/Contexts/Market/Services/PriceGap.php`
- Create: `app/Contexts/Market/Services/PriceGapTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `MovingAverage::of(array $closes, int $period): ?float` — `list<float>` de clôtures dans l'ordre chronologique. `PriceGap::pct(?float $reference, ?float $price): ?float` — écart en points de pourcentage, signé.

- [ ] **Step 1: Écrire les tests qui échouent**

`app/Contexts/Market/Services/MovingAverageTest.php` :

```php
<?php

use App\Contexts\Market\Services\MovingAverage;

it('moyenne les dernières clôtures de la fenêtre', function () {
    expect((new MovingAverage)->of([10.0, 20.0, 30.0, 40.0], 2))->toBe(35.0);
});

it('ignore les clôtures antérieures à la fenêtre', function () {
    expect((new MovingAverage)->of([1.0, 1.0, 10.0, 20.0, 30.0], 3))->toBe(20.0);
});

it('ne rend rien quand la série est plus courte que la fenêtre', function () {
    expect((new MovingAverage)->of([10.0, 20.0], 3))->toBeNull();
});

it('ne rend rien sur une fenêtre nulle ou négative', function () {
    expect((new MovingAverage)->of([10.0, 20.0], 0))->toBeNull();
});
```

`app/Contexts/Market/Services/PriceGapTest.php` :

```php
<?php

use App\Contexts\Market\Services\PriceGap;

it('rend l\'écart d\'un cours à sa référence en pourcentage', function () {
    expect((new PriceGap)->pct(80.0, 100.0))->toBe(25.0);
});

it('rend un écart négatif quand le cours passe sous sa référence', function () {
    expect((new PriceGap)->pct(100.0, 90.0))->toBe(-10.0);
});

it('ne rend rien sans référence ou sans cours', function () {
    expect((new PriceGap)->pct(null, 100.0))->toBeNull();
    expect((new PriceGap)->pct(100.0, null))->toBeNull();
});

it('ne rend rien sur une référence nulle, qui ne divise pas', function () {
    expect((new PriceGap)->pct(0.0, 100.0))->toBeNull();
});
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run: `php artisan test --compact --filter='MovingAverage|PriceGap'`
Expected: FAIL — `Class "App\Contexts\Market\Services\MovingAverage" not found`.

- [ ] **Step 3: Écrire l'implémentation minimale**

`app/Contexts/Market/Services/MovingAverage.php` :

```php
<?php

namespace App\Contexts\Market\Services;

/**
 * La moyenne mobile simple des dernières clôtures : la tendance de fond, débarrassée du bruit
 * quotidien. Rend `null` plutôt que zéro sur une série trop courte — un instrument jeune n'a pas
 * de tendance longue, et zéro se lirait comme un cours.
 */
class MovingAverage
{
    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes, int $period): ?float
    {
        if ($period <= 0 || count($closes) < $period) {
            return null;
        }

        return array_sum(array_slice($closes, -$period)) / $period;
    }
}
```

`app/Contexts/Market/Services/PriceGap.php` :

```php
<?php

namespace App\Contexts\Market\Services;

/**
 * L'écart d'un cours à une référence, en points de pourcentage signés. Deux lignes de la fiche
 * le demandent — l'écart au prix de revient et l'écart à la moyenne mobile — et l'adaptateur qui
 * les compose n'a pas à savoir diviser.
 */
class PriceGap
{
    public function pct(?float $reference, ?float $price): ?float
    {
        if ($reference === null || $price === null || $reference <= 0.0) {
            return null;
        }

        return ($price - $reference) / $reference * 100;
    }
}
```

- [ ] **Step 4: Relancer les tests**

Run: `php artisan test --compact --filter='MovingAverage|PriceGap'`
Expected: PASS, 8 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Services/
git commit -m "$(cat <<'EOF'
feat: calcule la moyenne mobile et l'écart d'un cours à sa référence

Deux calculateurs purs du contexte Market, premiers habitants de son
dossier Services.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 2 : `RelativeStrengthIndex`

**Files:**
- Create: `app/Contexts/Market/Services/RelativeStrengthIndex.php`
- Create: `app/Contexts/Market/Services/RelativeStrengthIndexTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `RelativeStrengthIndex::of(array $closes, int $period = 14): ?float` — valeur entre 0 et 100.

Le piège de cette tâche : le **lissage de Wilder**. La première moyenne des gains et des pertes est une moyenne simple sur `$period` variations ; chaque variation suivante entre par `(précédente × ($period − 1) + valeur) / $period`. Une moyenne simple glissante donnerait un chiffre proche mais faux, et aucun test naïf ne le verrait. Le test de l'étape 1 est construit pour séparer les deux : sur `[10, 11, 10.5, 11.5]` en période 2, Wilder rend ≈ 85,71 là où une moyenne simple rendrait ≈ 66,67.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Market\Services\RelativeStrengthIndex;

it('rend le RSI d\'une série sans lissage à faire', function () {
    /** Variations +1 puis −0,5 : gains moyens 0,5, pertes moyennes 0,25, RS = 2. */
    expect(round((new RelativeStrengthIndex)->of([10.0, 11.0, 10.5], 2), 4))
        ->toBe(round(100 - 100 / 3, 4));
});

it('lisse à la Wilder et non en moyenne simple', function () {
    /**
     * Gains [1, 0, 1], pertes [0, 0,5, 0]. Wilder : gains 0,75, pertes 0,125, RS = 6, RSI ≈ 85,71.
     * Une moyenne simple des deux dernières variations donnerait RS = 2 et RSI ≈ 66,67.
     */
    expect(round((new RelativeStrengthIndex)->of([10.0, 11.0, 10.5, 11.5], 2), 4))
        ->toBe(round(100 - 100 / 7, 4));
});

it('rend 100 sur une série qui ne baisse jamais', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 11.0, 12.0], 2))->toBe(100.0);
});

it('rend 0 sur une série qui ne monte jamais', function () {
    expect((new RelativeStrengthIndex)->of([12.0, 11.0, 10.0], 2))->toBe(0.0);
});

it('rend 100 sur une série plate, faute de baisse à mesurer', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 10.0, 10.0], 2))->toBe(100.0);
});

it('ne rend rien sans assez de variations pour remplir la fenêtre', function () {
    expect((new RelativeStrengthIndex)->of([10.0, 11.0], 2))->toBeNull();
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=RelativeStrengthIndex`
Expected: FAIL — classe absente.

- [ ] **Step 3: Écrire l'implémentation minimale**

```php
<?php

namespace App\Contexts\Market\Services;

/**
 * L'indice de force relative de Wilder : la vigueur des hausses face aux baisses sur une fenêtre
 * de séances, ramenée entre 0 et 100.
 *
 * Le lissage est celui de Wilder et non une moyenne simple glissante : la première moyenne porte
 * sur `$period` variations, les suivantes pèsent la moyenne précédente `$period - 1` fois contre
 * une fois la nouvelle variation. Les deux méthodes rendent des chiffres proches, ce qui rend
 * l'erreur silencieuse — d'où le cas de référence dans le test.
 *
 * Sans aucune baisse sur la fenêtre, l'indice vaut 100 : la division par des pertes nulles n'a pas
 * de sens, et une série qui ne recule jamais est bien à son maximum de force.
 */
class RelativeStrengthIndex
{
    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes, int $period = 14): ?float
    {
        if ($period <= 0 || count($closes) < $period + 1) {
            return null;
        }

        $gains = [];
        $losses = [];

        for ($index = 1; $index < count($closes); $index++) {
            $change = $closes[$index] - $closes[$index - 1];
            $gains[] = max($change, 0.0);
            $losses[] = max(-$change, 0.0);
        }

        $averageGain = array_sum(array_slice($gains, 0, $period)) / $period;
        $averageLoss = array_sum(array_slice($losses, 0, $period)) / $period;

        for ($index = $period; $index < count($gains); $index++) {
            $averageGain = ($averageGain * ($period - 1) + $gains[$index]) / $period;
            $averageLoss = ($averageLoss * ($period - 1) + $losses[$index]) / $period;
        }

        if ($averageLoss <= 0.0) {
            return 100.0;
        }

        return 100 - 100 / (1 + $averageGain / $averageLoss);
    }
}
```

- [ ] **Step 4: Relancer le test**

Run: `php artisan test --compact --filter=RelativeStrengthIndex`
Expected: PASS, 6 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Services/RelativeStrengthIndex.php app/Contexts/Market/Services/RelativeStrengthIndexTest.php
git commit -m "$(cat <<'EOF'
feat: calcule le RSI d'une série de clôtures

Lissage de Wilder, avec un cas de référence qui le sépare d'une moyenne
simple glissante.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 3 : `AverageTrueRange` et ses Datas

**Files:**
- Create: `app/Contexts/Market/Datas/TrueRangeBar.php`
- Create: `app/Contexts/Market/Datas/AtrData.php`
- Create: `app/Contexts/Market/Services/AverageTrueRange.php`
- Create: `app/Contexts/Market/Services/AverageTrueRangeTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `TrueRangeBar` (`readonly`, `public float $high, $low, $close`), `AtrData` (`readonly`, `public float $value`, `public ?float $percent`), `AverageTrueRange::of(array $bars, int $period = 14): ?AtrData` avec `$bars` en `list<TrueRangeBar>` chronologique.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Market\Datas\TrueRangeBar;
use App\Contexts\Market\Services\AverageTrueRange;

/** @param  list<array{float, float, float}>  $rows */
function bars(array $rows): array
{
    return array_map(
        fn (array $row): TrueRangeBar => new TrueRangeBar(high: $row[0], low: $row[1], close: $row[2]),
        $rows,
    );
}

it('moyenne les amplitudes vraies de la fenêtre', function () {
    /** Amplitudes : 4 (10 → 106-102) puis 3 (105-102), moyenne 3,5 sur une fenêtre de 2. */
    $atr = (new AverageTrueRange)->of(bars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 103.0],
    ]), 2);

    expect($atr->value)->toBe(3.5);
});

it('compte l\'écart à la clôture de la veille dans l\'amplitude', function () {
    /** Trou baissier : la barre s'ouvre et se ferme sous la clôture de la veille. */
    $atr = (new AverageTrueRange)->of(bars([
        [104.0, 100.0, 104.0],
        [99.0, 97.0, 98.0],
        [99.0, 97.0, 98.0],
    ]), 2);

    /** Amplitudes : max(2, |99-104|, |97-104|) = 7, puis max(2, 1, 1) = 2. Moyenne 4,5. */
    expect($atr->value)->toBe(4.5);
});

it('lisse à la Wilder au-delà de la première fenêtre', function () {
    $atr = (new AverageTrueRange)->of(bars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 103.0],
        [113.0, 103.0, 110.0],
    ]), 2);

    /** Première moyenne 3,5 ; dernière amplitude 10 ; Wilder : (3,5 × 1 + 10) / 2 = 6,75. */
    expect($atr->value)->toBe(6.75);
});

it('exprime l\'amplitude en pourcentage du dernier cours', function () {
    $atr = (new AverageTrueRange)->of(bars([
        [104.0, 100.0, 102.0],
        [106.0, 102.0, 105.0],
        [105.0, 102.0, 100.0],
    ]), 2);

    expect($atr->percent)->toBe(3.5);
});

it('ne rend rien avec moins de barres que la fenêtre plus une', function () {
    expect((new AverageTrueRange)->of(bars([[104.0, 100.0, 102.0], [106.0, 102.0, 105.0]]), 2))
        ->toBeNull();
});

it('rend une amplitude nulle sur une série parfaitement plate', function () {
    $atr = (new AverageTrueRange)->of(bars([
        [100.0, 100.0, 100.0],
        [100.0, 100.0, 100.0],
        [100.0, 100.0, 100.0],
    ]), 2);

    expect($atr->value)->toBe(0.0)
        ->and($atr->percent)->toBe(0.0);
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=AverageTrueRange`
Expected: FAIL — classes absentes.

- [ ] **Step 3: Écrire l'implémentation minimale**

`app/Contexts/Market/Datas/TrueRangeBar.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

/** Une séance réduite à ce que l'amplitude vraie demande : ses extrêmes et sa clôture. */
readonly class TrueRangeBar
{
    public function __construct(
        public float $high,
        public float $low,
        public float $close,
    ) {}
}
```

`app/Contexts/Market/Datas/AtrData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

/**
 * L'amplitude quotidienne moyenne, en valeur et en pourcentage du dernier cours. Le pourcentage
 * est calculé ici et nulle part ailleurs : c'est lui qu'on lit, parce qu'il se compare d'un
 * instrument à l'autre là où la valeur absolue ne le peut pas.
 *
 * `percent` est nul quand le dernier cours ne vaut rien — il n'y a alors rien à rapporter.
 */
readonly class AtrData
{
    public function __construct(
        public float $value,
        public ?float $percent,
    ) {}
}
```

`app/Contexts/Market/Services/AverageTrueRange.php` :

```php
<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\AtrData;
use App\Contexts\Market\Datas\TrueRangeBar;

/**
 * L'amplitude vraie moyenne de Wilder : de combien bouge une séance ordinaire. L'amplitude vraie
 * d'une séance est le plus grand des trois écarts — son propre plus-haut à son plus-bas, et chacun
 * d'eux à la clôture de la veille — ce qui compte les trous d'ouverture qu'un simple haut-bas
 * manquerait.
 *
 * Même lissage que le RSI : moyenne simple sur la première fenêtre, puis pondération de Wilder.
 */
class AverageTrueRange
{
    /** @param  list<TrueRangeBar>  $bars  Séances dans l'ordre chronologique. */
    public function of(array $bars, int $period = 14): ?AtrData
    {
        if ($period <= 0 || count($bars) < $period + 1) {
            return null;
        }

        $ranges = [];

        for ($index = 1; $index < count($bars); $index++) {
            $bar = $bars[$index];
            $previousClose = $bars[$index - 1]->close;

            $ranges[] = max(
                $bar->high - $bar->low,
                abs($bar->high - $previousClose),
                abs($bar->low - $previousClose),
            );
        }

        $average = array_sum(array_slice($ranges, 0, $period)) / $period;

        for ($index = $period; $index < count($ranges); $index++) {
            $average = ($average * ($period - 1) + $ranges[$index]) / $period;
        }

        $lastClose = $bars[count($bars) - 1]->close;

        return new AtrData(
            value: $average,
            percent: $lastClose > 0.0 ? $average / $lastClose * 100 : null,
        );
    }
}
```

- [ ] **Step 4: Relancer le test**

Run: `php artisan test --compact --filter=AverageTrueRange`
Expected: PASS, 6 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Datas/TrueRangeBar.php app/Contexts/Market/Datas/AtrData.php app/Contexts/Market/Services/AverageTrueRange.php app/Contexts/Market/Services/AverageTrueRangeTest.php
git commit -m "$(cat <<'EOF'
feat: calcule l'amplitude vraie moyenne d'un instrument

ATR de Wilder sur les plus-hauts, plus-bas et clôtures, rendu aussi en
pourcentage du dernier cours pour être comparable d'un titre à l'autre.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 4 : `FiftyTwoWeekRange`

**Files:**
- Create: `app/Contexts/Market/Datas/FiftyTwoWeekData.php`
- Create: `app/Contexts/Market/Services/FiftyTwoWeekRange.php`
- Create: `app/Contexts/Market/Services/FiftyTwoWeekRangeTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `FiftyTwoWeekData` (`readonly`, `public float $high, $low`, `public ?float $gapPct`), `FiftyTwoWeekRange::of(array $closes): ?FiftyTwoWeekData`, constante `FiftyTwoWeekRange::SESSIONS = 252`.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Market\Services\FiftyTwoWeekRange;

it('rend le plus-haut, le plus-bas et la distance au plus-haut', function () {
    $range = (new FiftyTwoWeekRange)->of([80.0, 120.0, 90.0]);

    expect($range->high)->toBe(120.0)
        ->and($range->low)->toBe(80.0)
        ->and($range->gapPct)->toBe(-25.0);
});

it('rend une distance nulle quand le cours est sur son plus-haut', function () {
    expect((new FiftyTwoWeekRange)->of([80.0, 100.0, 120.0])->gapPct)->toBe(0.0);
});

it('ne regarde que les 252 dernières séances', function () {
    /** Un sommet ancien, hors fenêtre, ne doit pas peser sur la distance au plus-haut. */
    $closes = array_merge([1000.0], array_fill(0, FiftyTwoWeekRange::SESSIONS, 100.0));

    expect((new FiftyTwoWeekRange)->of($closes)->high)->toBe(100.0);
});

it('accepte une série plus courte que la fenêtre', function () {
    expect((new FiftyTwoWeekRange)->of([90.0, 100.0])->high)->toBe(100.0);
});

it('ne rend rien sur une série vide', function () {
    expect((new FiftyTwoWeekRange)->of([]))->toBeNull();
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=FiftyTwoWeekRange`
Expected: FAIL — classes absentes.

- [ ] **Step 3: Écrire l'implémentation minimale**

`app/Contexts/Market/Datas/FiftyTwoWeekData.php` :

```php
<?php

namespace App\Contexts\Market\Datas;

/**
 * Les extrêmes de l'année boursière et la distance du dernier cours à son sommet, négative ou
 * nulle : situer le prix dans son propre historique, sans le comparer à quoi que ce soit d'autre.
 */
readonly class FiftyTwoWeekData
{
    public function __construct(
        public float $high,
        public float $low,
        public ?float $gapPct,
    ) {}
}
```

`app/Contexts/Market/Services/FiftyTwoWeekRange.php` :

```php
<?php

namespace App\Contexts\Market\Services;

use App\Contexts\Market\Datas\FiftyTwoWeekData;

/**
 * Le plus-haut et le plus-bas de l'année boursière, comptés en séances et non en mois : une année
 * civile n'a pas le même nombre de jours cotés selon les places et les fériés, et la fenêtre
 * doit rester la même d'un instrument à l'autre.
 *
 * Une série plus courte que la fenêtre est lue en entier plutôt que rejetée : un instrument coté
 * depuis six mois a bien un plus-haut, simplement plus jeune.
 */
class FiftyTwoWeekRange
{
    /** Cinquante-deux semaines cotées, à cinq séances la semaine, fériés déduits. */
    public const SESSIONS = 252;

    /** @param  list<float>  $closes  Clôtures dans l'ordre chronologique. */
    public function of(array $closes): ?FiftyTwoWeekData
    {
        if ($closes === []) {
            return null;
        }

        $window = array_slice($closes, -self::SESSIONS);
        $high = max($window);
        $last = $window[count($window) - 1];

        return new FiftyTwoWeekData(
            high: $high,
            low: min($window),
            gapPct: $high > 0.0 ? ($last - $high) / $high * 100 : null,
        );
    }
}
```

- [ ] **Step 4: Relancer le test**

Run: `php artisan test --compact --filter=FiftyTwoWeekRange`
Expected: PASS, 5 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Datas/FiftyTwoWeekData.php app/Contexts/Market/Services/FiftyTwoWeekRange.php app/Contexts/Market/Services/FiftyTwoWeekRangeTest.php
git commit -m "$(cat <<'EOF'
feat: situe un cours dans ses cinquante-deux semaines

Plus-haut, plus-bas et distance au sommet sur les 252 dernières séances.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 5 : `Portfolio\Services\PositionWeight`

**Files:**
- Create: `app/Contexts/Portfolio/Services/PositionWeight.php`
- Create: `app/Contexts/Portfolio/Services/PositionWeightTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `PositionWeight::of(?float $value, array $values): ?float` — pourcentage du portefeuille, `$values` en `list<?float>` de valeurs de marché.

La règle d'exclusion recopie celle de `Portfolio\Services\Concentration` : une position sans cours connu, nulle ou négative n'est pas une exposition à mesurer, ni au numérateur ni au dénominateur.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Portfolio\Services\PositionWeight;

it('rapporte une position au total du portefeuille', function () {
    expect((new PositionWeight)->of(250.0, [250.0, 750.0]))->toBe(25.0);
});

it('exclut du total les positions sans cours connu', function () {
    expect((new PositionWeight)->of(250.0, [250.0, 250.0, null]))->toBe(50.0);
});

it('ne rend rien sans valeur de position', function () {
    expect((new PositionWeight)->of(null, [250.0, 750.0]))->toBeNull();
});

it('ne rend rien sur un portefeuille sans valeur', function () {
    expect((new PositionWeight)->of(250.0, []))->toBeNull();
});

it('rend cent pour cent sur une position unique', function () {
    expect((new PositionWeight)->of(250.0, [250.0]))->toBe(100.0);
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=PositionWeight`
Expected: FAIL — classe absente.

- [ ] **Step 3: Écrire l'implémentation minimale**

```php
<?php

namespace App\Contexts\Portfolio\Services;

/**
 * Le poids d'une position dans son portefeuille, en pourcentage. Le pendant à une ligne de
 * `Concentration`, qui mesure le portefeuille entier là où celui-ci répond pour une seule ligne :
 * de combien elle pèse, donc de combien la renforcer déplacerait l'ensemble.
 *
 * Même règle d'exclusion que `Concentration` : une position sans cours connu, nulle ou négative
 * n'est pas une exposition à mesurer.
 */
class PositionWeight
{
    /** @param  list<?float>  $values  Valeurs de marché de toutes les positions, celle-ci comprise. */
    public function of(?float $value, array $values): ?float
    {
        if ($value === null || $value <= 0.0) {
            return null;
        }

        $total = array_sum(array_filter(
            $values,
            fn (?float $candidate): bool => $candidate !== null && $candidate > 0.0,
        ));

        return $total > 0.0 ? $value / $total * 100 : null;
    }
}
```

- [ ] **Step 4: Relancer le test**

Run: `php artisan test --compact --filter=PositionWeight`
Expected: PASS, 5 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio/Services/PositionWeight.php app/Contexts/Portfolio/Services/PositionWeightTest.php
git commit -m "$(cat <<'EOF'
feat: pèse une position dans son portefeuille

Même règle d'exclusion que Concentration : une position sans cours connu
ne compte ni au numérateur ni au dénominateur.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 6 : `InstrumentAnalysisData`

**Files:**
- Create: `app/Contexts/MarketView/Datas/InstrumentAnalysisData.php`
- Create: `app/Contexts/MarketView/Datas/InstrumentAnalysisDataTest.php`

**Interfaces:**
- Consomme : rien.
- Produit : `InstrumentAnalysisData` — `readonly`, `implements JsonSerializable`, propriétés dans cet ordre : `?float $pru, ?float $pruGapPct, ?float $ma200, ?float $ma200GapPct, ?float $rsi14, ?float $high52w, ?float $high52wGapPct, ?float $atr, ?float $atrPct, ?float $maxDrawdown, ?float $portfolioWeightPct`. Toutes nommées à l'appel.

Le nom évite `AnalysisData`, déjà pris dans le même dossier par l'analyse d'exposition (concentration + contributions). `maxDrawdown` est un **pourcentage positif**, comme `Valuation\Datas\DrawdownData::$maxDepth` dont il vient : une chute de 1000 à 750 vaut 25. C'est l'écran qui lui pose son signe.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\MarketView\Datas\InstrumentAnalysisData;

it('sérialise ses onze chiffres dans un ordre stable', function () {
    $data = new InstrumentAnalysisData(
        pru: 80.0,
        pruGapPct: 25.0,
        ma200: 82.4,
        ma200GapPct: -11.2,
        rsi14: 38.0,
        high52w: 94.1,
        high52wGapPct: -22.2,
        atr: 1.9,
        atrPct: 1.9,
        maxDrawdown: 31.4,
        portfolioWeightPct: 4.2,
    );

    expect(array_keys($data->jsonSerialize()))->toBe([
        'pru',
        'pruGapPct',
        'ma200',
        'ma200GapPct',
        'rsi14',
        'high52w',
        'high52wGapPct',
        'atr',
        'atrPct',
        'maxDrawdown',
        'portfolioWeightPct',
    ]);
});

it('sérialise les chiffres absents en nul plutôt que de les omettre', function () {
    $empty = InstrumentAnalysisData::empty();

    expect($empty->jsonSerialize())->each->toBeNull();
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=InstrumentAnalysisData`
Expected: FAIL — classe absente.

- [ ] **Step 3: Écrire l'implémentation minimale**

```php
<?php

namespace App\Contexts\MarketView\Datas;

use JsonSerializable;

/**
 * Les repères d'analyse d'une position : sa référence de prix, sa tendance, son excès et son
 * risque. Nom distinct d'`AnalysisData`, qui porte déjà l'analyse d'une exposition entière.
 *
 * Tout est nullable : un instrument coté depuis six mois n'a pas de moyenne à deux cents séances,
 * une série plate pas d'amplitude utile. L'écran rend un tiret plutôt que de masquer la ligne.
 *
 * `maxDrawdown` est un pourcentage positif, comme le `maxDepth` de `Valuation\Datas\DrawdownData`
 * dont il vient : une chute de 1000 à 750 vaut 25. Le signe est posé à l'affichage.
 *
 * L'ordre des clés de `jsonSerialize()` est un contrat : le hash de l'instantané hors-ligne en
 * dépend, et un ordre différent le ferait retélécharger à tous les clients.
 */
readonly class InstrumentAnalysisData implements JsonSerializable
{
    public function __construct(
        public ?float $pru,
        public ?float $pruGapPct,
        public ?float $ma200,
        public ?float $ma200GapPct,
        public ?float $rsi14,
        public ?float $high52w,
        public ?float $high52wGapPct,
        public ?float $atr,
        public ?float $atrPct,
        public ?float $maxDrawdown,
        public ?float $portfolioWeightPct,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, null);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'pru' => $this->pru,
            'pruGapPct' => $this->pruGapPct,
            'ma200' => $this->ma200,
            'ma200GapPct' => $this->ma200GapPct,
            'rsi14' => $this->rsi14,
            'high52w' => $this->high52w,
            'high52wGapPct' => $this->high52wGapPct,
            'atr' => $this->atr,
            'atrPct' => $this->atrPct,
            'maxDrawdown' => $this->maxDrawdown,
            'portfolioWeightPct' => $this->portfolioWeightPct,
        ];
    }
}
```

- [ ] **Step 4: Relancer le test**

Run: `php artisan test --compact --filter=InstrumentAnalysisData`
Expected: PASS, 2 tests.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/MarketView/Datas/InstrumentAnalysisData.php app/Contexts/MarketView/Datas/InstrumentAnalysisDataTest.php
git commit -m "$(cat <<'EOF'
feat: décrit les repères d'analyse d'une position

Onze chiffres nullables, sérialisés dans un ordre contractuel dont dépend
le hash de l'instantané hors-ligne.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 7 : Port et adaptateur `InstrumentAnalysis`

**Files:**
- Create: `app/Contexts/MarketView/Ports/InstrumentAnalysisPort.php`
- Create: `app/Contexts/MarketView/Infrastructure/InstrumentAnalysis.php`
- Create: `app/Contexts/MarketView/Infrastructure/InstrumentAnalysisTest.php`
- Modify: `app/Contexts/MarketView/MarketViewProvider.php` (nouveau paramètre nommé `instrumentAnalysis`)
- Modify: `app/Providers/AppServiceProvider.php:78-87` (l'appel à `MarketViewProvider::registers`)

**Interfaces:**
- Consomme : `MovingAverage::of()`, `PriceGap::pct()`, `RelativeStrengthIndex::of()`, `AverageTrueRange::of()` + `TrueRangeBar`, `FiftyTwoWeekRange::of()`, `PositionWeight::of()`, `InstrumentAnalysisData` (tâches 1 à 6). Existants : `Market\Contracts\PriceRepositoryContract::forAssetSince()`, `Valuation\Services\Drawdown::of()`, `Portfolio\Actions\GetPortfolioPositions::__invoke(int $userId): array<int, PositionLineData>`, `MarketView\Services\PriceHistoryWindow::since()`.
- Produit : `InstrumentAnalysisPort::forAsset(int $userId, int $assetId): ?InstrumentAnalysisData` — `null` quand l'actif n'est pas détenu.

L'adaptateur **compose et ne calcule pas**. Les seules opérations qu'il s'autorise sont la lecture, la conversion de modèles en `TrueRangeBar` et le passage de valeurs d'un calculateur à l'autre. Aucune division, aucun pourcentage écrit à la main : `PriceGap` et les autres s'en chargent.

Il injecte les actions Portfolio directement plutôt que `PortfolioOverviewPort` : ce port ne répond que par exposition, alors que le poids d'une ligne se rapporte au portefeuille entier. `PortfolioTotals` injecte déjà ces actions de la même façon, et `GetPortfolioPositions` est liée en `scoped` — la construire ici relirait tout le portefeuille à chaque appel.

- [ ] **Step 1: Écrire le test qui échoue**

```php
<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\MarketView\Ports\InstrumentAnalysisPort;
use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->analysis = app(InstrumentAnalysisPort::class);
});

/** Deux cent soixante séances montant de 1 € par jour : de quoi remplir MM200, RSI et ATR. */
function seedRisingPrices(int $assetId, int $sessions = 260, float $start = 100.0): void
{
    foreach (range(0, $sessions - 1) as $offset) {
        $close = $start + $offset;

        Price::factory()->create([
            'asset_id' => $assetId,
            'date' => Carbon::parse('2026-08-29')->subDays($sessions - 1 - $offset),
            'open' => $close,
            'high' => $close + 1,
            'low' => $close - 1,
            'close' => $close,
        ]);
    }
}

it('ne rend rien pour un actif que l\'utilisateur ne détient pas', function () {
    $user = User::factory()->create();
    $instrument = Instrument::factory()->create();
    seedRisingPrices($instrument->id, 5);

    expect($this->analysis->forAsset($user->id, $instrument->id))->toBeNull();
});

it('rend le prix de revient et son écart au dernier cours', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    /** La fixture achète 10 titres à 80 € et cote le dernier à 100 €. */
    expect($data->pru)->toBe(80.0)
        ->and($data->pruGapPct)->toBe(25.0);
});

it('rend la moyenne à deux cents séances et l\'écart du cours à cette moyenne', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    seedRisingPrices($instrument->id);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => 100,
    ]);

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    /** Clôtures 100 à 359 : la moyenne des 200 dernières vaut 259,5, le dernier cours 359. */
    expect($data->ma200)->toBe(259.5)
        ->and(round($data->ma200GapPct, 4))->toBe(round((359 - 259.5) / 259.5 * 100, 4))
        ->and($data->rsi14)->toBe(100.0)
        ->and($data->high52w)->toBe(359.0)
        ->and($data->high52wGapPct)->toBe(0.0);
});

it('laisse la moyenne longue à nul quand l\'historique est trop court', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $data = $this->analysis->forAsset($user->id, $instrument->id);

    expect($data->ma200)->toBeNull()
        ->and($data->ma200GapPct)->toBeNull();
});

it('rend le poids de la position dans le portefeuille entier', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();

    $other = Instrument::factory()->create(['name' => 'AUTRE', 'ticker' => 'AUT']);
    Price::factory()->create(['asset_id' => $other->id, 'date' => now(), 'close' => 300]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $other->id,
        'quantity' => 10,
        'avg_cost' => 300,
    ]);

    /** 1 000 € sur 4 000 € : la position pèse un quart du portefeuille. */
    expect($this->analysis->forAsset($user->id, $instrument->id)->portfolioWeightPct)->toBe(25.0);
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=InstrumentAnalysisTest`
Expected: FAIL — `Target [App\Contexts\MarketView\Ports\InstrumentAnalysisPort] is not instantiable`.

- [ ] **Step 3: Écrire le port**

`app/Contexts/MarketView/Ports/InstrumentAnalysisPort.php` :

```php
<?php

namespace App\Contexts\MarketView\Ports;

use App\Contexts\MarketView\Datas\InstrumentAnalysisData;

/**
 * Les repères d'analyse d'une position. Un port à part et non une méthode de plus sur
 * `MarketDataPort` : cette lecture croise le marché, le portefeuille et la valorisation, elle
 * n'est pas une lecture de marché.
 */
interface InstrumentAnalysisPort
{
    /** Nul quand l'utilisateur ne détient pas l'actif : sans position, il n'y a rien à analyser. */
    public function forAsset(int $userId, int $assetId): ?InstrumentAnalysisData;
}
```

- [ ] **Step 4: Écrire l'adaptateur**

`app/Contexts/MarketView/Infrastructure/InstrumentAnalysis.php` :

```php
<?php

namespace App\Contexts\MarketView\Infrastructure;

use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\TrueRangeBar;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Services\AverageTrueRange;
use App\Contexts\Market\Services\FiftyTwoWeekRange;
use App\Contexts\Market\Services\MovingAverage;
use App\Contexts\Market\Services\PriceGap;
use App\Contexts\Market\Services\RelativeStrengthIndex;
use App\Contexts\MarketView\Datas\InstrumentAnalysisData;
use App\Contexts\MarketView\Ports\InstrumentAnalysisPort;
use App\Contexts\MarketView\Services\PriceHistoryWindow;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\PositionLineData;
use App\Contexts\Portfolio\Services\PositionWeight;
use App\Contexts\Valuation\Services\Drawdown;

/**
 * Compose les repères d'analyse d'une position : le dépôt de cours fournit la matière, les
 * calculateurs des contextes propriétaires font les formules, cet adaptateur ne fait que les
 * enchaîner. Aucun pourcentage ne s'écrit ici — `PriceGap` et les Datas s'en chargent.
 *
 * `GetPortfolioPositions` est injectée et jamais construite : elle est liée en `scoped` et
 * mémoïse ses positions par utilisateur, si bien que la fiche et l'instantané la relisent sans
 * repayer la lecture du portefeuille.
 *
 * Le poids se rapporte au portefeuille entier, toutes expositions confondues — c'est l'échelle à
 * laquelle se décide la taille d'un renfort — d'où l'action plutôt que `PortfolioOverviewPort`,
 * qui ne répond que par exposition.
 */
class InstrumentAnalysis implements InstrumentAnalysisPort
{
    /** La moyenne longue de référence : deux cents séances, environ dix mois cotés. */
    private const LONG_TERM_SESSIONS = 200;

    public function __construct(
        private PriceRepositoryContract $prices,
        private GetPortfolioPositions $positions,
        private MovingAverage $movingAverage,
        private PriceGap $priceGap,
        private RelativeStrengthIndex $rsi,
        private AverageTrueRange $atr,
        private FiftyTwoWeekRange $fiftyTwoWeeks,
        private Drawdown $drawdown,
        private PositionWeight $weight,
    ) {}

    public function forAsset(int $userId, int $assetId): ?InstrumentAnalysisData
    {
        $positions = ($this->positions)($userId);
        $position = $positions[$assetId] ?? null;

        if ($position === null) {
            return null;
        }

        $prices = $this->prices->forAssetSince($assetId, PriceHistoryWindow::since());

        if ($prices->isEmpty()) {
            return InstrumentAnalysisData::empty();
        }

        $labels = $prices->map(fn (Price $price): string => $price->date->format('Y-m-d'))->values()->all();
        $closes = $prices->map(fn (Price $price): float => (float) $price->close)->values()->all();
        $bars = $prices->map(fn (Price $price): TrueRangeBar => new TrueRangeBar(
            high: (float) ($price->high ?? $price->close),
            low: (float) ($price->low ?? $price->close),
            close: (float) $price->close,
        ))->values()->all();

        $lastClose = $closes[count($closes) - 1];
        $movingAverage = $this->movingAverage->of($closes, self::LONG_TERM_SESSIONS);
        $atr = $this->atr->of($bars);
        $fiftyTwoWeeks = $this->fiftyTwoWeeks->of($closes);

        return new InstrumentAnalysisData(
            pru: $position->avgCost,
            pruGapPct: $this->priceGap->pct($position->avgCost, $lastClose),
            ma200: $movingAverage,
            ma200GapPct: $this->priceGap->pct($movingAverage, $lastClose),
            rsi14: $this->rsi->of($closes),
            high52w: $fiftyTwoWeeks?->high,
            high52wGapPct: $fiftyTwoWeeks?->gapPct,
            atr: $atr?->value,
            atrPct: $atr?->percent,
            maxDrawdown: $this->drawdown->of($labels, $closes)->maxDepth,
            portfolioWeightPct: $this->weight->of(
                $position->marketValue,
                array_map(fn (PositionLineData $line): ?float => $line->marketValue, array_values($positions)),
            ),
        );
    }
}
```

- [ ] **Step 5: Lier le port dans le provider**

Dans `app/Contexts/MarketView/MarketViewProvider.php`, ajouter le paramètre nommé après `valuation`, avec son `@param class-string<InstrumentAnalysisPort> $instrumentAnalysis` dans le PHPDoc, l'import, et la liaison :

```php
$app->bind(InstrumentAnalysisPort::class, $instrumentAnalysis);
```

Dans `app/Providers/AppServiceProvider.php`, compléter l'appel existant :

```php
MarketViewProvider::registers(
    app: $this->app,
    marketData: MarketData::class,
    holdings: PortfolioHoldings::class,
    transactions: PortfolioTransactions::class,
    sectorBreakdown: PortfolioSectors::class,
    income: IncomeTotals::class,
    portfolioOverview: PortfolioTotals::class,
    valuation: ValuationHistory::class,
    instrumentAnalysis: InstrumentAnalysis::class,
);
```

- [ ] **Step 6: Relancer le test**

Run: `php artisan test --compact --filter=InstrumentAnalysisTest`
Expected: PASS, 5 tests.

- [ ] **Step 7: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/MarketView/Ports/InstrumentAnalysisPort.php app/Contexts/MarketView/Infrastructure/InstrumentAnalysis.php app/Contexts/MarketView/Infrastructure/InstrumentAnalysisTest.php app/Contexts/MarketView/MarketViewProvider.php app/Providers/AppServiceProvider.php
git commit -m "$(cat <<'EOF'
feat: compose les repères d'analyse d'une position

Un port dédié : la lecture croise marché, portefeuille et valorisation.
L'adaptateur enchaîne les calculateurs sans écrire de formule.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 8 : La prop `analysis` sur la fiche

**Files:**
- Modify: `app/Contexts/MarketView/Http/AssetController.php:22-46`
- Modify: `tests/Feature/InstrumentDetailPageTest.php` (nouveaux tests en fin de fichier)

**Interfaces:**
- Consomme : `InstrumentAnalysisPort::forAsset()` (tâche 7).
- Produit : la prop Inertia différée `analysis` sur `Asset/Show`, absente quand l'actif n'est pas détenu.

- [ ] **Step 1: Écrire les tests qui échouent**

À ajouter à la fin de `tests/Feature/InstrumentDetailPageTest.php` :

```php
it('defers the analysis figures and loads them on demand', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->create(['name' => 'ACME']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 80]);
    Holding::factory()->create(['user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $asset->id, 'quantity' => 10, 'avg_cost' => 80]);

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->missing('analysis')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('analysis.pru', fn ($v) => (float) $v === 80.0)
                ->where('analysis.pruGapPct', fn ($v) => (float) $v === 25.0)
                ->where('analysis.ma200', null)
            )
        );
});

it('omits the analysis when the instrument is not held', function () {
    $user = User::factory()->create();
    $asset = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-07-01', 'close' => 100]);

    $this->actingAs($user)
        ->get("/asset/{$asset->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Asset/Show')
            ->loadDeferredProps(fn (Assert $reload) => $reload->missing('analysis'))
        );
});
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=InstrumentDetailPageTest`
Expected: FAIL — la prop `analysis` n'existe pas.

- [ ] **Step 3: Brancher la prop**

Dans `AssetController`, injecter `private InstrumentAnalysisPort $analysis` au constructeur, puis ajouter la prop après `valuation` :

```php
/**
 * Différée comme l'historique de cours, qu'elle relit : cinq ans de barres pour une moyenne
 * longue, un RSI et une amplitude vraie. Sans position, la section n'a rien à dire — pas de
 * prix de revient, pas de poids — et la prop reste absente plutôt que vide.
 */
'analysis' => Inertia::defer(fn () => $this->analysis->forAsset($userId, $id)),
```

- [ ] **Step 4: Relancer les tests**

Run: `php artisan test --compact --filter=InstrumentDetailPageTest`
Expected: PASS.

Note : `forAsset()` rendant `null` pour un actif non détenu, la prop différée se charge à `null`. Si `->missing('analysis')` échoue au rechargement dans le second test, remplacer l'assertion par `->where('analysis', null)` — c'est la même intention, exprimée selon ce que rend Inertia v3 sur une valeur nulle. Ne pas contourner en conditionnant la prop côté contrôleur : la section se décide côté écran.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/MarketView/Http/AssetController.php tests/Feature/InstrumentDetailPageTest.php
git commit -m "$(cat <<'EOF'
feat: sert les repères d'analyse à la fiche instrument

Prop différée comme l'historique de cours qu'elle relit.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 9 : L'analyse dans l'instantané hors ligne

**Files:**
- Modify: `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php:107-120`
- Modify: `resources/js/lib/snapshotContract.ts:40-46`
- Modify: `tests/Feature/SnapshotInvariantTest.php` (commentaire d'en-tête + `SNAPSHOT_VERSION`, ligne 65)

**Interfaces:**
- Consomme : `InstrumentAnalysisPort::forAsset()` (tâche 7).
- Produit : la clé `analysis` des entrées `assets` du blob, et le champ `analysis?: InstrumentAnalysis` de `AssetPageSnapshot`.

Le type TypeScript `InstrumentAnalysis` est défini à la tâche 10 ; cette tâche l'importe depuis `@/lib/instrument`. Faire les deux dans l'ordre 10 puis 9 est acceptable si le contrôle de types gêne.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `tests/Feature/SnapshotInvariantTest.php`, avant le test de hash :

```php
it('porte les repères d\'analyse de chaque actif détenu', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');

    seedSnapshotFixture();

    $assets = $this->getJson('/instantane')->assertOk()->json('assets');

    expect($assets)->not->toBeEmpty();

    foreach ($assets as $page) {
        expect($page)->toHaveKey('analysis')
            ->and($page['analysis'])->toHaveKey('pru');
    }
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `php artisan test --compact --filter=SnapshotInvariantTest`
Expected: FAIL — clé `analysis` absente ; le test de hash passe encore.

- [ ] **Step 3: Ajouter la clé au blob**

Dans `BuildMarketViewSnapshot`, injecter `private InstrumentAnalysisPort $analysis` au constructeur et compléter `page()` **après** `valuation`, avant le bloc conditionnel des dividendes :

```php
'analysis' => $this->analysis->forAsset($userId, $assetId),
```

L'ordre des clés compte : il fixe le hash du blob.

- [ ] **Step 4: Relancer et relever le nouveau hash**

Run: `php artisan test --compact --filter=SnapshotInvariantTest`
Expected: le nouveau test PASSE ; le test de hash ÉCHOUE en affichant le hash obtenu — c'est attendu, le blob a changé de forme.

Copier le hash obtenu dans `SNAPSHOT_VERSION` (ligne 65) et ajouter au commentaire d'en-tête, à la suite des précédents :

```
 * Modifié une quatorzième fois : chaque page actif porte ses repères d'analyse — prix de revient,
 * moyenne longue, RSI, amplitude vraie, drawdown et poids dans le portefeuille.
```

- [ ] **Step 5: Déclarer la clé côté TypeScript**

Dans `resources/js/lib/snapshotContract.ts`, ajouter à `AssetPageSnapshot`, après `valuation` :

```ts
    analysis: InstrumentAnalysis | null;
```

et l'import correspondant depuis `@/lib/instrument`.

- [ ] **Step 6: Vérifier**

Run: `php artisan test --compact --filter=SnapshotInvariantTest`
Expected: PASS.

Run: `bunx vue-tsc --noEmit` (si le projet expose un autre script de contrôle de types, l'utiliser)
Expected: aucune erreur.

- [ ] **Step 7: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php resources/js/lib/snapshotContract.ts tests/Feature/SnapshotInvariantTest.php
git commit -m "$(cat <<'EOF'
feat: emporte les repères d'analyse dans l'instantané hors-ligne

La section vit sans réseau comme le reste de la fiche. Hash de référence
déplacé en conséquence.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 10 : `lib/analysis.ts`, la mise en forme

**Files:**
- Create: `resources/js/lib/analysis.ts`
- Create: `resources/js/lib/analysis.test.ts`
- Modify: `resources/js/lib/instrument.ts` (ajout du type `InstrumentAnalysis`)

**Interfaces:**
- Consomme : `eur`, `pct`, `sharePct` de `@/lib/format`.
- Produit :
  - `InstrumentAnalysis` dans `@/lib/instrument` — jumelle TypeScript d'`InstrumentAnalysisData` : `{ pru, pruGapPct, ma200, ma200GapPct, rsi14, high52w, high52wGapPct, atr, atrPct, maxDrawdown, portfolioWeightPct }`, toutes `number | null`.
  - `IndicatorId = 'pru' | 'pruGap' | 'ma200' | 'ma200Gap' | 'rsi14' | 'high52w' | 'high52wGap' | 'atrPct' | 'maxDrawdown' | 'portfolioWeight'`
  - `AnalysisRow = { indicator: IndicatorId; label: string; value: string; gain?: number | null }`
  - `AnalysisGroup = { title: string | null; rows: AnalysisRow[] }`
  - `analysisGroups(analysis: InstrumentAnalysis): AnalysisGroup[]`

Règles de rendu, reprises de la spec : une valeur absente rend `—` (jamais de ligne masquée) ; un groupe dont **toutes** les valeurs manquent disparaît ; `gain` n'est posé que là où le signe dit un gain ou une perte — l'écart au PRU — et reste absent partout ailleurs, où le signe n'est qu'une direction.

- [ ] **Step 1: Écrire le test qui échoue**

`resources/js/lib/analysis.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { analysisGroups } from '@/lib/analysis';
import type { InstrumentAnalysis } from '@/lib/instrument';

/** Copie du helper de `instrument.test.ts` : `toLocaleString` sépare avec des espaces insécables. */
const normalizeSpaces = (value: string): string => value.replace(/[\xa0\u202f]/g, ' ');

const analysis = (overrides: Partial<InstrumentAnalysis> = {}): InstrumentAnalysis => ({
    pru: 80,
    pruGapPct: 25,
    ma200: 82.4,
    ma200GapPct: -11.2,
    rsi14: 38.4,
    high52w: 94.1,
    high52wGapPct: -22.2,
    atr: 1.9,
    atrPct: 1.9,
    maxDrawdown: 31.4,
    portfolioWeightPct: 4.2,
    ...overrides,
});

describe('analysisGroups', () => {
    it('range les repères en référence, tendance et risque', () => {
        expect(analysisGroups(analysis()).map((group) => group.title)).toEqual([
            null,
            'Tendance',
            'Risque',
        ]);
    });

    it('formate chaque repère selon son unité', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);
        const valueOf = (indicator: string): string =>
            normalizeSpaces(rows.find((row) => row.indicator === indicator)!.value);

        expect(valueOf('pru')).toBe('80,00 €');
        expect(valueOf('pruGap')).toBe('+25,0 %');
        expect(valueOf('ma200')).toBe('82,40 €');
        expect(valueOf('ma200Gap')).toBe('-11,2 %');
        expect(valueOf('rsi14')).toBe('38');
        expect(valueOf('atrPct')).toBe('1,9 %');
        expect(valueOf('portfolioWeight')).toBe('4,2 %');
    });

    it('pose un signe négatif sur le drawdown, qui arrive en positif', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);

        expect(normalizeSpaces(rows.find((row) => row.indicator === 'maxDrawdown')!.value))
            .toBe('-31,4 %');
    });

    it('ne colore que l\'écart au prix de revient', () => {
        const rows = analysisGroups(analysis()).flatMap((group) => group.rows);
        const colored = rows.filter((row) => row.gain !== undefined);

        expect(colored.map((row) => row.indicator)).toEqual(['pruGap']);
    });

    it('rend un tiret sur un repère absent plutôt que de masquer sa ligne', () => {
        const rows = analysisGroups(analysis({ rsi14: null })).flatMap((group) => group.rows);

        expect(rows.find((row) => row.indicator === 'rsi14')!.value).toBe('—');
    });

    it('efface un groupe dont tous les repères manquent', () => {
        const groups = analysisGroups(analysis({
            atr: null,
            atrPct: null,
            maxDrawdown: null,
            portfolioWeightPct: null,
        }));

        expect(groups.map((group) => group.title)).toEqual([null, 'Tendance']);
    });
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `bunx vitest run resources/js/lib/analysis.test.ts`
Expected: FAIL — module `@/lib/analysis` introuvable.

- [ ] **Step 3: Écrire le type puis la mise en forme**

Dans `resources/js/lib/instrument.ts`, à la suite des types existants :

```ts
/** Jumelle d'`InstrumentAnalysisData` : tout est nullable, un instrument jeune n'a pas de tendance longue. */
export interface InstrumentAnalysis {
    pru: number | null;
    pruGapPct: number | null;
    ma200: number | null;
    ma200GapPct: number | null;
    rsi14: number | null;
    high52w: number | null;
    high52wGapPct: number | null;
    atr: number | null;
    atrPct: number | null;
    /** Pourcentage positif, comme le rend le serveur : le signe est posé à l'affichage. */
    maxDrawdown: number | null;
    portfolioWeightPct: number | null;
}
```

`resources/js/lib/analysis.ts` :

```ts
import { eur, pct, sharePct } from '@/lib/format';
import type { InstrumentAnalysis } from '@/lib/instrument';

export type IndicatorId =
    | 'pru'
    | 'pruGap'
    | 'ma200'
    | 'ma200Gap'
    | 'rsi14'
    | 'high52w'
    | 'high52wGap'
    | 'atrPct'
    | 'maxDrawdown'
    | 'portfolioWeight';

export interface AnalysisRow {
    indicator: IndicatorId;
    label: string;
    value: string;
    /** Posé seulement là où le signe dit un gain ou une perte : ailleurs, il n'est qu'une direction. */
    gain?: number | null;
}

export interface AnalysisGroup {
    title: string | null;
    rows: AnalysisRow[];
}

/** Le RSI se lit en entier : sa décimale n'ajoute rien à une échelle de 0 à 100. */
const index = (value: number | null): string => (value === null ? '—' : String(Math.round(value)));

/** Le serveur rend une profondeur positive ; la chute se lit avec son signe. */
const drawdown = (value: number | null): string => (value === null ? '—' : pct(-value));

/**
 * Les repères d'analyse en groupes de lignes prêtes à rendre. Un repère absent garde sa ligne et
 * rend un tiret : la ligne dit ce que la fiche sait mesurer, son absence ne doit pas se lire comme
 * un oubli. Un groupe entièrement vide, lui, disparaît — il n'apprendrait rien.
 */
export const analysisGroups = (analysis: InstrumentAnalysis): AnalysisGroup[] => {
    const groups: AnalysisGroup[] = [
        {
            title: null,
            rows: [
                { indicator: 'pru', label: 'PRU', value: eur(analysis.pru) },
                {
                    indicator: 'pruGap',
                    label: 'Écart au PRU',
                    value: pct(analysis.pruGapPct),
                    gain: analysis.pruGapPct,
                },
            ],
        },
        {
            title: 'Tendance',
            rows: [
                { indicator: 'ma200', label: 'MM200', value: eur(analysis.ma200) },
                { indicator: 'ma200Gap', label: 'Cours vs MM200', value: pct(analysis.ma200GapPct) },
                { indicator: 'rsi14', label: 'RSI 14', value: index(analysis.rsi14) },
                { indicator: 'high52w', label: 'Plus-haut 52 s.', value: eur(analysis.high52w) },
                {
                    indicator: 'high52wGap',
                    label: 'Sous le plus-haut',
                    value: pct(analysis.high52wGapPct),
                },
            ],
        },
        {
            title: 'Risque',
            rows: [
                { indicator: 'atrPct', label: 'ATR 14', value: sharePct(analysis.atrPct) },
                { indicator: 'maxDrawdown', label: 'Max drawdown', value: drawdown(analysis.maxDrawdown) },
                {
                    indicator: 'portfolioWeight',
                    label: 'Poids du portefeuille',
                    value: sharePct(analysis.portfolioWeightPct),
                },
            ],
        },
    ];

    return groups.filter((group) => group.rows.some((row) => row.value !== '—'));
};
```

- [ ] **Step 4: Relancer le test**

Run: `bunx vitest run resources/js/lib/analysis.test.ts`
Expected: PASS, 6 tests.

- [ ] **Step 5: Committer**

```bash
git add resources/js/lib/analysis.ts resources/js/lib/analysis.test.ts resources/js/lib/instrument.ts
git commit -m "$(cat <<'EOF'
feat: met en forme les repères d'analyse d'un instrument

Trois groupes de lignes, un tiret sur le repère absent, et un groupe vide
qui s'efface.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 11 : `lib/indicatorHelp.ts`, le texte des aides

**Files:**
- Create: `resources/js/lib/indicatorHelp.ts`
- Create: `resources/js/lib/indicatorHelp.test.ts`

**Interfaces:**
- Consomme : `IndicatorId` de `@/lib/analysis` (tâche 10).
- Produit : `IndicatorHelp = { title: string; subtitle: string; body: string[]; formula?: string; caveat?: string }` et `indicatorHelp: Record<IndicatorId, IndicatorHelp>`.

Le test garantit qu'aucun bouton d'aide n'ouvre du vide : chaque `IndicatorId` a son entrée, avec un titre et au moins un paragraphe.

- [ ] **Step 1: Écrire le test qui échoue**

```ts
import { describe, expect, it } from 'vitest';
import { analysisGroups } from '@/lib/analysis';
import { indicatorHelp } from '@/lib/indicatorHelp';
import type { InstrumentAnalysis } from '@/lib/instrument';

const full: InstrumentAnalysis = {
    pru: 80,
    pruGapPct: 25,
    ma200: 82.4,
    ma200GapPct: -11.2,
    rsi14: 38,
    high52w: 94.1,
    high52wGapPct: -22.2,
    atr: 1.9,
    atrPct: 1.9,
    maxDrawdown: 31.4,
    portfolioWeightPct: 4.2,
};

describe('indicatorHelp', () => {
    it('couvre chaque repère que la section peut afficher', () => {
        const shown = analysisGroups(full).flatMap((group) => group.rows.map((row) => row.indicator));

        shown.forEach((indicator) => {
            expect(indicatorHelp[indicator], indicator).toBeDefined();
        });
    });

    it('donne à chaque aide un titre et au moins un paragraphe', () => {
        Object.entries(indicatorHelp).forEach(([indicator, help]) => {
            expect(help.title.length, indicator).toBeGreaterThan(0);
            expect(help.subtitle.length, indicator).toBeGreaterThan(0);
            expect(help.body.length, indicator).toBeGreaterThan(0);
            help.body.forEach((paragraph) => expect(paragraph.trim().length).toBeGreaterThan(0));
        });
    });

    it('nomme chaque aide « Comment lire… », comme l\'aide des performances', () => {
        Object.values(indicatorHelp).forEach((help) => {
            expect(help.title.startsWith('Comment lire')).toBe(true);
        });
    });
});
```

- [ ] **Step 2: Lancer le test et vérifier qu'il échoue**

Run: `bunx vitest run resources/js/lib/indicatorHelp.test.ts`
Expected: FAIL — module introuvable.

- [ ] **Step 3: Écrire les aides**

`resources/js/lib/indicatorHelp.ts` :

```ts
import type { IndicatorId } from '@/lib/analysis';

export interface IndicatorHelp {
    title: string;
    subtitle: string;
    body: string[];
    /** Encart en chasse fixe, comme dans l'aide des performances. */
    formula?: string;
    /** La limite de lecture, posée en dernier et en retrait : ce que le chiffre ne dit pas. */
    caveat?: string;
}

/**
 * Le texte des aides de la section Analyse, en données plutôt qu'en gabarit : dix indicateurs
 * feraient d'un composant à `v-if` un mur illisible, et un test peut vérifier ici qu'aucun bouton
 * n'ouvre du vide.
 */
export const indicatorHelp: Record<IndicatorId, IndicatorHelp> = {
    pru: {
        title: 'Comment lire le PRU',
        subtitle: 'Prix de revient unitaire',
        body: [
            'C\'est le prix moyen que tu as payé par part, tous tes achats confondus : le total sorti de ton compte divisé par le nombre de parts détenues.',
            'Il sert de référence à tout le reste de la fiche — le gain latent, l\'écart au cours — mais ce n\'est pas un signal : le marché ne sait pas à quel prix tu es entré.',
        ],
        formula: 'somme des achats (frais compris) ÷ quantité détenue',
    },
    pruGap: {
        title: 'Comment lire l\'écart au PRU',
        subtitle: 'La distance entre le cours du jour et ton prix de revient',
        body: [
            'La part de gain ou de perte latente sur chaque part détenue : où en est ta position par rapport à ce qu\'elle t\'a coûté.',
        ],
        formula: '(cours − PRU) ÷ PRU',
        caveat: 'Ne dit rien du bon moment pour agir : une ligne à +80 % peut rester une bonne affaire, une ligne à −20 % un piège. Le marché ignore ton prix d\'entrée.',
    },
    ma200: {
        title: 'Comment lire la MM200',
        subtitle: 'La moyenne mobile à 200 séances',
        body: [
            'La moyenne des 200 dernières clôtures, recalculée chaque jour. Elle lisse le bruit quotidien pour ne garder que la tendance de fond — environ dix mois de cotation.',
        ],
        formula: 'moyenne des 200 dernières clôtures',
    },
    ma200Gap: {
        title: 'Comment lire l\'écart à la MM200',
        subtitle: 'La position du cours face à sa tendance longue',
        body: [
            'Au-dessus de zéro, le cours se paie plus cher que sa moyenne longue : la tendance est haussière. En dessous, le marché paie moins cher que cette moyenne.',
            'À un horizon de renforts mensuels, c\'est l\'indicateur qui situe le mieux : un écart franchement négatif signale un repli par rapport à la tendance de fond.',
        ],
        caveat: 'Un titre peut rester des mois sous sa MM200 et continuer de baisser. Sous la moyenne ne veut pas dire bon marché.',
    },
    rsi14: {
        title: 'Comment lire le RSI',
        subtitle: 'L\'indice de force relative sur 14 séances',
        body: [
            'Il compare la vigueur des hausses à celle des baisses sur les 14 dernières séances, et rend un chiffre entre 0 et 100.',
            'Sous 30, on parle de « survendu » : les baisses dominent nettement. Au-dessus de 70, de « suracheté ». Entre les deux, il ne dit rien de particulier.',
        ],
        caveat: 'En tendance forte, le RSI colle à son extrême pendant des semaines. Ce n\'est pas un compte à rebours : un RSI à 25 peut descendre à 15 avant de remonter.',
    },
    high52w: {
        title: 'Comment lire le plus-haut 52 semaines',
        subtitle: 'Le sommet de l\'année boursière',
        body: [
            'La plus haute clôture des 252 dernières séances, soit environ un an de cotation. Un repère pour situer le prix du jour dans son propre historique récent.',
        ],
    },
    high52wGap: {
        title: 'Comment lire la distance au plus-haut',
        subtitle: 'De combien le cours a reculé depuis son sommet annuel',
        body: [
            'Zéro veut dire que le titre est sur son plus-haut de l\'année. −22 % qu\'il a rendu près d\'un quart depuis ce sommet.',
            'C\'est la mesure de repli la plus directe : elle dit ce qu\'un renfort achète en escompte par rapport au meilleur prix récent.',
        ],
        caveat: 'Un fort recul depuis le sommet n\'est une occasion que si la raison du recul est passagère. L\'indicateur ne la connaît pas.',
    },
    atrPct: {
        title: 'Comment lire l\'ATR',
        subtitle: 'L\'amplitude quotidienne moyenne, en pourcentage du cours',
        body: [
            'De combien bouge une journée ordinaire. « 1,9 % » veut dire que l\'écart entre le haut et le bas d\'une séance vaut en moyenne 1,9 % du cours.',
            'Exprimé en pourcentage, il se compare d\'un instrument à l\'autre : c\'est le chiffre qui dit lequel de deux titres secoue le plus.',
            'Il sert à dimensionner : à risque égal, plus l\'ATR est haut, plus la ligne doit rester petite.',
        ],
        formula: 'moyenne des amplitudes vraies sur 14 séances ÷ dernier cours',
    },
    maxDrawdown: {
        title: 'Comment lire le max drawdown',
        subtitle: 'La pire chute depuis un sommet',
        body: [
            'La plus forte baisse subie entre un plus-haut et le creux qui l\'a suivi, sur tout l\'historique connu. Le risque déjà vécu par le titre, en une mesure.',
            'Il répond à « qu\'est-ce que j\'aurais encaissé au pire moment », ce qu\'aucune moyenne de volatilité ne dit aussi clairement.',
        ],
        caveat: 'C\'est du passé, pas une borne : rien n\'empêche une chute plus profonde que celles déjà vues.',
    },
    portfolioWeight: {
        title: 'Comment lire le poids du portefeuille',
        subtitle: 'La part de cette ligne dans ta valeur totale',
        body: [
            'La valeur de marché de cette position rapportée à celle de tout le portefeuille, immobilier exclu.',
            'C\'est le chiffre qui répond vraiment à « je renforce de combien » : une ligne qui pèse déjà 15 % ne se renforce pas comme une ligne à 2 %.',
        ],
        formula: 'valeur de la position ÷ valeur de toutes les positions',
    },
};
```

- [ ] **Step 4: Relancer le test**

Run: `bunx vitest run resources/js/lib/indicatorHelp.test.ts`
Expected: PASS, 3 tests.

- [ ] **Step 5: Committer**

```bash
git add resources/js/lib/indicatorHelp.ts resources/js/lib/indicatorHelp.test.ts
git commit -m "$(cat <<'EOF'
feat: écrit l'aide de lecture de chaque repère d'analyse

Textes en données plutôt qu'en gabarit : dix indicateurs, et un test qui
garantit qu'aucun bouton n'ouvre du vide.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 12 : Les composants et le déplacement du PRU

**Files:**
- Create: `resources/js/components/IndicatorInfoDialog.vue`
- Create: `resources/js/components/instrument/AnalysisRow.vue`
- Create: `resources/js/components/instrument/AnalysisSection.vue`
- Modify: `resources/js/Pages/Asset/Show.vue`
- Modify: `resources/js/lib/instrument.ts` (`heroMeta`)
- Modify: `resources/js/lib/instrument.test.ts` (les cas de `heroMeta`)
- Modify: `tests/Browser/HeroSectionTest.php` (le test du PRU)

**Interfaces:**
- Consomme : `analysisGroups`, `AnalysisGroup`, `IndicatorId` (tâche 10) ; `indicatorHelp` (tâche 11) ; `InstrumentAnalysis` ; `aheadOfNetwork` ; `CollapsibleSection`.
- Produit : la section rendue sur `Asset/Show`.

Le PRU quitte `heroMeta()` : détenu, la fonction ne rend plus rien ; non détenu, elle garde la ligne « au 01/07/2026 ». Le prix de revient se lit désormais en tête de la section Analyse.

- [ ] **Step 1: Écrire les tests front qui échouent**

Remplacer dans `resources/js/lib/instrument.test.ts` les deux premiers cas du `describe('heroMeta')` par :

```ts
    it('ne rend plus rien pour une position : ses repères vivent dans la section Analyse', () => {
        expect(heroMeta(instrument())).toEqual([]);
    });
```

Les cas « énonce la date du dernier cours quand l'instrument n'est pas détenu » et « n'énonce rien quand l'instrument n'est ni détenu ni coté » restent tels quels. Supprimer le cas « rend un tiret sur un prix de revient absent » : il n'a plus d'objet ici, le tiret est désormais testé dans `analysis.test.ts`.

- [ ] **Step 2: Lancer et vérifier l'échec**

Run: `bunx vitest run resources/js/lib/instrument.test.ts`
Expected: FAIL — `heroMeta` rend encore la ligne PRU.

- [ ] **Step 3: Vider `heroMeta` de son PRU**

Dans `resources/js/lib/instrument.ts`, remplacer le corps de `heroMeta` et son commentaire :

```ts
/**
 * Repères du milieu de page. Une position n'en pose plus aucun : prix de revient, tendance et
 * risque se lisent dans la section Analyse, qui les explique. Il ne reste ici que le cas d'un
 * instrument seulement suivi — la date de son dernier cours, qu'aucune autre section ne porte.
 */
export const heroMeta = (instrument: Instrument): HeroMetaEntry[] => {
    if (instrument.position !== null) {
        return [];
    }

    return instrument.lastPriceDate === null
        ? []
        : [{ label: '', value: `au ${frDate(instrument.lastPriceDate)}` }];
};
```

Run: `bunx vitest run resources/js/lib/instrument.test.ts`
Expected: PASS.

- [ ] **Step 4: Écrire le dialogue d'aide**

`resources/js/components/IndicatorInfoDialog.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Info } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { IndicatorId } from '@/lib/analysis';
import { indicatorHelp } from '@/lib/indicatorHelp';

const props = defineProps<{ indicator: IndicatorId }>();

const help = computed(() => indicatorHelp[props.indicator]);
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="icon-sm"
                class="text-muted-foreground hover:text-foreground"
                :aria-label="help.title"
            >
                <Info />
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ help.title }}</DialogTitle>
                <DialogDescription>{{ help.subtitle }}</DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3 text-sm text-foreground">
                <p v-for="paragraph in help.body" :key="paragraph">{{ paragraph }}</p>

                <p v-if="help.formula" class="rounded-md bg-muted px-3 py-2 font-mono text-xs">
                    {{ help.formula }}
                </p>

                <p v-if="help.caveat" class="text-muted-foreground">{{ help.caveat }}</p>
            </div>
        </DialogContent>
    </Dialog>
</template>
```

- [ ] **Step 5: Écrire la ligne et la section**

`resources/js/components/instrument/AnalysisRow.vue` :

```vue
<script setup lang="ts">
import IndicatorInfoDialog from '@/components/IndicatorInfoDialog.vue';
import { gainClass } from '@/lib/format';
import type { AnalysisRow } from '@/lib/analysis';

const props = defineProps<{ row: AnalysisRow }>();
</script>

<template>
    <!-- Le libellé et son aide à gauche, la valeur sur le bord droit : même colonne que les repères
         de l'en-tête, pour que l'œil suive une seule verticale de chiffres. -->
    <span
        :data-analysis-row="props.row.indicator"
        class="flex items-center justify-between gap-3 whitespace-nowrap"
    >
        <span class="flex items-center gap-0.5 text-muted-foreground">
            {{ props.row.label }}
            <IndicatorInfoDialog :indicator="props.row.indicator" />
        </span>

        <strong
            class="font-semibold tabular-nums"
            :class="props.row.gain === undefined ? 'text-foreground' : gainClass(props.row.gain ?? null)"
        >
            {{ props.row.value }}
        </strong>
    </span>
</template>
```

`resources/js/components/instrument/AnalysisSection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import AnalysisRow from '@/components/instrument/AnalysisRow.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import { analysisGroups, type AnalysisGroup } from '@/lib/analysis';
import type { InstrumentAnalysis } from '@/lib/instrument';

const props = defineProps<{ analysis?: InstrumentAnalysis | null }>();

const groups = computed<AnalysisGroup[]>(() =>
    props.analysis ? analysisGroups(props.analysis) : [],
);
</script>

<template>
    <CollapsibleSection section="analysis" title="Analyse">
        <template v-if="props.analysis !== undefined && props.analysis !== null">
            <div v-for="group in groups" :key="group.title ?? 'reference'" class="flex flex-col gap-1.5 text-[13.5px]">
                <!-- Le titre de groupe se lit comme une étiquette, pas comme un second titre de
                     section : la section n'en a qu'un. -->
                <span v-if="group.title" class="text-xs font-semibold text-muted-foreground uppercase">
                    {{ group.title }}
                </span>

                <AnalysisRow v-for="row in group.rows" :key="row.indicator" :row="row" />
            </div>
        </template>

        <Deferred v-else data="analysis">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 8" :key="n" class="h-6 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
```

- [ ] **Step 6: Poser la section sur la page**

Dans `resources/js/Pages/Asset/Show.vue` : ajouter `analysis?: InstrumentAnalysis` aux props, l'import du type et du composant, le repli hors-ligne, puis la section **après** `FiguresSection` et **avant** `TransactionsSection` :

```ts
const analysis = aheadOfNetwork(
    () => props.analysis,
    () => snapshot.assetPage(String(props.instrument.id))?.analysis ?? undefined,
);
```

```vue
        <AnalysisSection v-if="props.instrument.position" :analysis="analysis" />
```

- [ ] **Step 7: Corriger le test navigateur du PRU**

Dans `tests/Browser/HeroSectionTest.php`, le test « détaille le seul prix de revient, le cours se lisant sur la courbe » n'a plus d'objet : les repères d'un titre détenu sont vides. Le remplacer par :

```php
it('ne laisse plus de repère sous le graphe d\'un titre détenu : ils vivent dans l\'analyse', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-hero-meta]').length", 0)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 8: Construire et vérifier**

```bash
bun run build
bunx vitest run resources/js/lib
php artisan test --compact --filter=HeroSectionTest
```

Expected: vitest PASS ; `HeroSectionTest` — le nouveau cas passe, et **les trois échecs préexistants « (latent) » restent**, inchangés. Aucun autre échec.

- [ ] **Step 9: Committer**

```bash
git add resources/js/components/IndicatorInfoDialog.vue resources/js/components/instrument/AnalysisRow.vue resources/js/components/instrument/AnalysisSection.vue resources/js/Pages/Asset/Show.vue resources/js/lib/instrument.ts resources/js/lib/instrument.test.ts tests/Browser/HeroSectionTest.php
git commit -m "$(cat <<'EOF'
feat: pose la section Analyse sur la fiche instrument

Prix de revient, tendance et risque en une section repliable, chaque
ligne portant son aide de lecture. Le PRU quitte les repères du graphe.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

### Task 13 : Le test navigateur de bout en bout

**Files:**
- Create: `tests/Browser/AnalysisSectionTest.php`

**Interfaces:**
- Consomme : tout ce qui précède, plus le helper global `portfolioFixture()` de `tests/Pest.php`.
- Produit : rien que d'autres tâches consomment.

Le modèle est `tests/Browser/InstrumentsTest.php:10-20`, qui ouvre déjà un dialogue d'aide par son `aria-label`.

- [ ] **Step 1: Écrire le test**

```php
<?php

use function Pest\Browser\visit;

it('déroule les repères d\'analyse d\'une position', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->assertSee('PRU')
        ->assertSee('Poids du portefeuille')
        ->assertScript("document.querySelectorAll('[data-analysis-row]').length > 0", true)
        ->assertNoJavaScriptErrors();
});

it('explique un repère à travers son dialogue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/asset/{$instrument->id}")
        ->click('[data-section="analysis"] [data-section-toggle]')
        ->click('[aria-label="Comment lire l\'écart au PRU"]')
        ->assertSee('Comment lire l\'écart au PRU')
        ->assertSee('Ne dit rien du bon moment pour agir')
        ->assertNoJavaScriptErrors();
});

it('n\'offre pas d\'analyse sur un titre qui n\'est pas détenu', function () {
    ['user' => $user] = portfolioFixture();

    $other = \App\Contexts\Market\Models\Instrument::factory()->create(['name' => 'ORPHAN', 'ticker' => 'ORP']);
    \App\Contexts\Market\Models\Price::factory()->create([
        'asset_id' => $other->id,
        'date' => '2026-07-01',
        'close' => 42,
    ]);

    $this->actingAs($user);

    visit("/asset/{$other->id}")
        ->assertScript("document.querySelectorAll('[data-section=\"analysis\"]').length", 0)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Construire puis lancer**

```bash
bun run build
php artisan test --compact --filter=AnalysisSectionTest
```

Expected: PASS, 3 tests. Si le dialogue ne s'ouvre pas, vérifier que le clic vise bien le bouton d'aide et non la couche de bascule de `CollapsibleSection` — l'aide vit **dans** le contenu déplié, pas dans le titre, donc la section doit être ouverte d'abord.

- [ ] **Step 3: Lancer la suite complète**

```bash
php artisan test --compact
bunx vitest run
```

Expected: aucun échec **hormis** les trois échecs préexistants de `HeroSectionTest` liés au libellé « (latent) ».

- [ ] **Step 4: Committer**

```bash
git add tests/Browser/AnalysisSectionTest.php
git commit -m "$(cat <<'EOF'
test: déroule la section Analyse et son aide dans le navigateur

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_018euSTu9HVMmdomdJ9515o2
EOF
)"
```

---

## Vérification finale

- [ ] `php artisan test --compact` — seuls les trois échecs préexistants de `HeroSectionTest` subsistent.
- [ ] `bunx vitest run` — tout passe.
- [ ] `vendor/bin/pint --dirty --format agent` — plus rien à corriger.
- [ ] La fiche d'un titre détenu montre « Analyse » repliée ; dépliée, elle affiche onze lignes dont chacune ouvre son dialogue.
- [ ] La fiche d'un titre non détenu ne montre pas la section, et garde sa ligne « au JJ/MM/AAAA » sous le graphe.
