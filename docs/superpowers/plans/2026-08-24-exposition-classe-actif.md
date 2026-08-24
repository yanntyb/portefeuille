# Exposition et enveloppe — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Séparer l'exposition d'un actif (actions, obligations, matières premières, crypto) de son enveloppe (`InstrumentType`), pour que le patrimoine se lise par classe réelle et que l'or cesse d'être compté dans « Actions ».

**Architecture:** Un enum `AssetClass` et une colonne `assets.asset_class` portent l'exposition. Le contexte `Wealth` dérive ses classes de patrimoine de `AssetClass::cases()` au lieu d'écrire une classe PHP par exposition. `MarketView` sert une page liste par exposition et une fiche polymorphe unique sur `/asset/{id}`. `InstrumentType` reste, réduit à son rôle d'enveloppe : badge de ligne et disponibilité des secteurs.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest (tests colocalisés dans `app/Contexts/**`), Inertia v3 + Vue 3, Pinia, ECharts, Vitest, SQLite.

**Spec:** `docs/superpowers/specs/2026-08-24-exposition-classe-actif-design.md`

## Global Constraints

- **Français obligatoire** pour tout texte visible : libellés, titres de page, fils d'Ariane, slugs d'URL. Les valeurs d'enum stockées en base restent en anglais.
- **Docblocs en français**, comme tout le code existant. Pas de commentaires inline sauf logique exceptionnellement dense (règle projet).
- **Pint après toute modification PHP** : `vendor/bin/pint --dirty --format agent`.
- **Types de retour explicites** et types de paramètres sur toute méthode. Promotion de propriétés dans les constructeurs.
- **Tests colocalisés** : `app/Contexts/Market/Enums/AssetClassTest.php`, pas `tests/Unit/...`. `phpunit.xml` inclut `tests`, `app/Contexts` et `app/Shared`.
- **Un commit par tâche**, message conventionnel en français (`feat:`, `refactor:`, `test:`, `docs:`).
- **Branche** : `vendredi-soir`. Ne pas committer sur `main`.
- **Commandes de test** : `php artisan test --compact --filter=<nom>` pour PHP, `bun run test:js` pour Vitest, `bun run typecheck` pour vue-tsc.
- **Ordre des cas de `AssetClass` = contrat** : il fixe l'ordre des lignes du résumé et l'empilement des bandes. Ne jamais le réordonner sans intention.
- **Une exposition, une origine de revenu, au plus une fois.** Deux expositions renvoyant `IncomeSource::Dividend` compteraient les mêmes dividendes deux fois.

---

### Task 1: L'enum `AssetClass`

**Files:**
- Create: `app/Contexts/Market/Enums/AssetClass.php`
- Test: `app/Contexts/Market/Enums/AssetClassTest.php`

**Interfaces:**
- Consumes: `App\Contexts\Market\Enums\InstrumentType` (existant : cas `Stock`, `ETF`, `Crypto`, `Bond`, `Commodity`).
- Produces: `AssetClass` (enum `string`) avec `cases()`, `values(): list<string>`, `defaultForType(InstrumentType): self`, `getLabel(): string`, `slug(): string`, `getColor(): string`, `getIcon(): string`, `hasSectors(): bool`.

- [ ] **Step 1: Write the failing test**

Créer `app/Contexts/Market/Enums/AssetClassTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;

it('declares its cases in the order the wealth summary reads them', function () {
    expect(AssetClass::values())->toBe(['equity', 'bond', 'commodity', 'crypto']);
});

it('labels every exposure in French', function () {
    expect(AssetClass::Equity->getLabel())->toBe('Actions')
        ->and(AssetClass::Bond->getLabel())->toBe('Obligations')
        ->and(AssetClass::Commodity->getLabel())->toBe('Matières premières')
        ->and(AssetClass::Crypto->getLabel())->toBe('Crypto');
});

it('slugs every exposure in French', function () {
    expect(AssetClass::Equity->slug())->toBe('actions')
        ->and(AssetClass::Bond->slug())->toBe('obligations')
        ->and(AssetClass::Commodity->slug())->toBe('matieres-premieres')
        ->and(AssetClass::Crypto->slug())->toBe('crypto');
});

it('defaults an ETF to equity, and every other wrapper to its own exposure', function () {
    expect(AssetClass::defaultForType(InstrumentType::Stock))->toBe(AssetClass::Equity)
        ->and(AssetClass::defaultForType(InstrumentType::ETF))->toBe(AssetClass::Equity)
        ->and(AssetClass::defaultForType(InstrumentType::Bond))->toBe(AssetClass::Bond)
        ->and(AssetClass::defaultForType(InstrumentType::Commodity))->toBe(AssetClass::Commodity)
        ->and(AssetClass::defaultForType(InstrumentType::Crypto))->toBe(AssetClass::Crypto);
});

it('gives every exposure a distinct colour token', function () {
    $tokens = array_map(fn (AssetClass $class): string => $class->getColor(), AssetClass::cases());

    expect($tokens)->toHaveCount(count(array_unique($tokens)));
});

it('carries sectors on equity alone', function () {
    expect(AssetClass::Equity->hasSectors())->toBeTrue()
        ->and(AssetClass::Bond->hasSectors())->toBeFalse()
        ->and(AssetClass::Commodity->hasSectors())->toBeFalse()
        ->and(AssetClass::Crypto->hasSectors())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AssetClassTest`
Expected: FAIL — `Class "App\Contexts\Market\Enums\AssetClass" not found`.

- [ ] **Step 3: Write minimal implementation**

Créer `app/Contexts/Market/Enums/AssetClass.php` :

```php
<?php

namespace App\Contexts\Market\Enums;

/**
 * L'exposition d'un actif : à quoi son porteur est exposé, indépendamment de la façon dont il le
 * détient. `InstrumentType` dit l'enveloppe — titre vif, ETF, contrat à terme —, cet enum dit la
 * classe d'actif. Un ETF MSCI World est une enveloppe ETF sur une exposition actions ; l'or Xetra
 * et un contrat à terme sur l'argent sont deux enveloppes sur une même exposition.
 *
 * L'ordre des cas est un contrat : il fixe l'ordre des lignes du résumé patrimonial et
 * l'empilement des bandes de son graphe. Le réordonner change ce que le lecteur voit.
 */
enum AssetClass: string
{
    case Equity = 'equity';
    case Bond = 'bond';
    case Commodity = 'commodity';
    case Crypto = 'crypto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    /**
     * L'exposition posée à la création d'un instrument qui n'en déclare aucune. C'est un défaut,
     * jamais une dérivation : la valeur est écrite en base une fois, puis corrigible ligne à ligne.
     *
     * Un ETF est présumé actions — vrai des sept que porte le portefeuille, faux d'un futur ETF
     * obligataire ou aurifère, qu'il faudra corriger à la main. Modifier cette correspondance
     * casse un test, donc reste délibéré.
     */
    public static function defaultForType(InstrumentType $type): self
    {
        return match ($type) {
            InstrumentType::Stock, InstrumentType::ETF => self::Equity,
            InstrumentType::Bond => self::Bond,
            InstrumentType::Commodity => self::Commodity,
            InstrumentType::Crypto => self::Crypto,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Equity => 'Actions',
            self::Bond => 'Obligations',
            self::Commodity => 'Matières premières',
            self::Crypto => 'Crypto',
        };
    }

    /** Segment d'URL de la page liste. En français : une adresse est du texte vu par le lecteur. */
    public function slug(): string
    {
        return match ($this) {
            self::Equity => 'actions',
            self::Bond => 'obligations',
            self::Commodity => 'matieres-premieres',
            self::Crypto => 'crypto',
        };
    }

    /**
     * Jeton de teinte, pas une couleur : ECharts peint son SVG lui-même et ne résout pas les
     * variables CSS, donc `palette()` côté front tient un hexadécimal par jeton et par thème.
     */
    public function getColor(): string
    {
        return match ($this) {
            self::Equity => 'value',
            self::Bond => 'bond',
            self::Commodity => 'commodity',
            self::Crypto => 'crypto',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Equity => 'heroicon-o-chart-bar',
            self::Bond => 'heroicon-o-document-text',
            self::Commodity => 'heroicon-o-cube',
            self::Crypto => 'heroicon-o-currency-bitcoin',
        };
    }

    /**
     * Une exposition dont les actifs portent une répartition sectorielle. C'est l'enveloppe qui
     * décide : `YahooFinanceAdapter::supportsSectors()` ne décompose que les `Stock` et les `ETF`,
     * qui sont l'un et l'autre des expositions actions.
     */
    public function hasSectors(): bool
    {
        return $this === self::Equity;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=AssetClassTest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Market/Enums/AssetClass.php app/Contexts/Market/Enums/AssetClassTest.php
git commit -m "feat: introduit l'exposition d'un actif à côté de son enveloppe"
```

---

### Task 2: La colonne `assets.asset_class`

**Files:**
- Create: `database/migrations/2026_08_24_000000_add_asset_class_to_assets_table.php`
- Modify: `app/Contexts/Market/Models/Instrument.php`
- Modify: `app/Contexts/Market/Models/InstrumentTest.php`

**Interfaces:**
- Consumes: `AssetClass::defaultForType()` (Task 1).
- Produces: `Instrument::$asset_class` casté en `AssetClass`, jamais nul après création.

- [ ] **Step 1: Write the failing test**

Ajouter à la fin de `app/Contexts/Market/Models/InstrumentTest.php` :

```php
it('fills the exposure from the wrapper when none is given', function () {
    $etf = Instrument::factory()->create(['type' => InstrumentType::ETF]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    expect($etf->asset_class)->toBe(AssetClass::Equity)
        ->and($gold->asset_class)->toBe(AssetClass::Commodity);
});

it('never overwrites an exposure that was given explicitly', function () {
    $bondEtf = Instrument::factory()->create([
        'type' => InstrumentType::ETF,
        'asset_class' => AssetClass::Bond,
    ]);

    expect($bondEtf->fresh()->asset_class)->toBe(AssetClass::Bond);
});
```

Ajouter l'import `use App\Contexts\Market\Enums\AssetClass;` en tête du fichier de test s'il n'y est pas.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=InstrumentTest`
Expected: FAIL — colonne `asset_class` inconnue (`SQLSTATE[HY000]: no such column`).

- [ ] **Step 3a: Create the migration**

```bash
php artisan make:migration add_asset_class_to_assets_table --no-interaction
```

Renommer le fichier généré en `2026_08_24_000000_add_asset_class_to_assets_table.php` si l'horodatage diffère, puis écrire :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'exposition de l'actif, à côté de son enveloppe. Le défaut de colonne ne sert qu'à donner
     * une valeur aux lignes existantes le temps du rétro-remplissage qui suit : la correspondance
     * qui fait foi est `AssetClass::defaultForType()`, la même que le hook `creating` du modèle.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('asset_class')->default(AssetClass::Equity->value);
        });

        foreach (InstrumentType::cases() as $type) {
            DB::table('assets')
                ->where('type', $type->value)
                ->update(['asset_class' => AssetClass::defaultForType($type)->value]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('asset_class');
        });
    }
};
```

- [ ] **Step 3b: Cast and default on the model**

Dans `app/Contexts/Market/Models/Instrument.php` :

1. Ajouter `use App\Contexts\Market\Enums\AssetClass;` aux imports.
2. Ajouter `@property AssetClass $asset_class` au bloc `@property` de la classe.
3. Dans `casts()`, ajouter la ligne `'asset_class' => AssetClass::class,`.
4. Remplacer le hook `creating` existant par :

```php
        static::creating(function (Instrument $instrument): void {
            if ($instrument->type === null) {
                $instrument->type = InstrumentType::Stock;
            }

            /**
             * Défaut posé une fois puis stocké : une exposition donnée explicitement n'est jamais
             * écrasée. C'est ce qui sépare ce défaut d'une dérivation permanente depuis le type.
             */
            if ($instrument->asset_class === null) {
                $instrument->asset_class = AssetClass::defaultForType($instrument->type);
            }
        });
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=InstrumentTest`
Expected: PASS.

Puis vérifier le rétro-remplissage sur la vraie base :

Run: `php artisan migrate --no-interaction && php artisan tinker --execute 'foreach (\App\Contexts\Market\Models\Instrument::query()->selectRaw("asset_class, count(*) as n")->groupBy("asset_class")->get() as $row) { echo $row->asset_class->value." ".$row->n."\n"; }'`
Expected: `equity 31`, `commodity 2`, `crypto 3`.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Contexts/Market/Models/Instrument.php app/Contexts/Market/Models/InstrumentTest.php
git commit -m "feat: stocke l'exposition de chaque instrument en base"
```

---

### Task 3: Le répertoire filtre par exposition

**Files:**
- Modify: `app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php`
- Modify: `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php`
- Modify: `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php`

**Interfaces:**
- Consumes: `AssetClass` (Task 1), colonne `asset_class` (Task 2).
- Produces: `InstrumentDirectoryPort::idsOfClasses(array $classes): list<int>` remplaçant `idsOfTypes()`. `namesFor()` inchangé.

- [ ] **Step 1: Write the failing test**

Dans `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectoryTest.php`, remplacer les cas qui appellent `idsOfTypes()` par :

```php
it('serves the ids of the exposures it is given', function () {
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $bitcoin = Instrument::factory()->create(['type' => InstrumentType::Crypto]);

    $ids = (new MarketInstrumentDirectory())->idsOfClasses([AssetClass::Commodity, AssetClass::Crypto]);

    expect($ids)->toContain($gold->id, $bitcoin->id)
        ->not->toContain($stock->id);
});

it('serves nothing when no exposure is given', function () {
    Instrument::factory()->create(['type' => InstrumentType::Stock]);

    expect((new MarketInstrumentDirectory())->idsOfClasses([]))->toBe([]);
});
```

Ajouter `use App\Contexts\Market\Enums\AssetClass;` aux imports du test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MarketInstrumentDirectoryTest`
Expected: FAIL — `Call to undefined method ...::idsOfClasses()`.

- [ ] **Step 3: Rename the port method**

Dans `app/Contexts/Valuation/Ports/InstrumentDirectoryPort.php`, remplacer `idsOfTypes` :

```php
    /**
     * Les actifs d'une ou plusieurs expositions. Ce qui permet de ne garder d'une série que les
     * actions, ou que les matières premières, sans que la valorisation ait à connaître le partage.
     *
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $classes): array;
```

Remplacer l'import `InstrumentType` par `AssetClass`.

Dans `app/Contexts/Valuation/Infrastructure/MarketInstrumentDirectory.php` :

```php
    /**
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $classes): array
    {
        if ($classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
```

Remplacer l'import `InstrumentType` par `AssetClass`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=MarketInstrumentDirectoryTest`
Expected: PASS. Les autres suites échouent encore (appels à `idsOfTypes` ailleurs) — c'est attendu, la Task 4 les rattrape.

- [ ] **Step 5: Do not commit yet**

Cette tâche laisse volontairement l'application rouge : `BuildEvolutionSeries` et `BuildPortfolioPerformances` appellent encore `idsOfTypes()`. Enchaîner directement sur la Task 4 et committer les deux ensemble.

---

### Task 4: Les trois actions filtrent par exposition

**Files:**
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`
- Modify: `app/Contexts/Portfolio/Datas/HoldingLineData.php`
- Modify: `app/Contexts/Valuation/Actions/BuildEvolutionSeries.php`
- Modify: `app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php`
- Modify: `app/Contexts/Market/Enums/InstrumentType.php` (suppression de `securities()`)
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioAssetClass.php`, `SecuritiesClass.php`, `CryptoClass.php`
- Modify: `app/Contexts/MarketView/Http/InstrumentsController.php`, `CryptoController.php`
- Modify: `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php`
- Modify: `resources/js/lib/portfolio.ts`
- Tests à adapter : `GetPortfolioOverviewTest.php`, `BuildEvolutionSeriesTest.php`, `BuildPortfolioPerformancesTest.php`, `InstrumentTypeTest.php`

**Interfaces:**
- Consumes: `AssetClass` (Task 1), `idsOfClasses()` (Task 3).
- Produces:
  - `GetPortfolioOverview::__invoke(User $user, ?array $classes = null): PortfolioOverviewData` où `$classes` est `?list<AssetClass>`.
  - `BuildEvolutionSeries::__invoke(int $userId, ?int $months = null, ValuationGranularity $granularity = ValuationGranularity::Month, ?array $classes = null): EvolutionSeriesData`.
  - `BuildPortfolioPerformances::__invoke(int $userId, ?array $classes = null): list<PerformanceData>`.
  - `HoldingLineData::$assetClass` (type `AssetClass`), sérialisé en `assetClass` et `assetClassLabel`.

- [ ] **Step 1: Write the failing test**

Ajouter à `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php` :

```php
it('keeps only the holdings of the exposures it is given', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Price::factory()->create(['asset_id' => $stock->id, 'close' => 50.0]);

    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 2, 'avg_cost' => 80.0,
    ]);
    Holding::factory()->create([
        'asset_id' => $stock->id, 'wallet_id' => $wallet->id, 'user_id' => $user->id,
        'quantity' => 4, 'avg_cost' => 40.0,
    ]);

    $overview = app(GetPortfolioOverview::class)($user, [AssetClass::Commodity]);

    expect($overview->holdings)->toHaveCount(1)
        ->and($overview->holdings[0]->assetId)->toBe($gold->id)
        ->and($overview->holdings[0]->assetClass)->toBe(AssetClass::Commodity)
        ->and($overview->totalValue)->toBe(200.0);
});
```

Adapter les imports du fichier (`AssetClass`, `Price`, `Holding`, `Wallet`, `Instrument`, `InstrumentType`, `User`) selon ce qui y est déjà présent.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetPortfolioOverviewTest`
Expected: FAIL — `assetClass` inconnu sur `HoldingLineData`.

- [ ] **Step 3a: `HoldingLineData` porte l'exposition**

Dans `app/Contexts/Portfolio/Datas/HoldingLineData.php`, ajouter le paramètre après `type` et l'exposer :

```php
        public InstrumentType $type,
        public AssetClass $assetClass,
```

et dans `jsonSerialize()`, après `'typeLabel'` :

```php
            'assetClass' => $this->assetClass->value,
            'assetClassLabel' => $this->assetClass->getLabel(),
```

Ajouter l'import `use App\Contexts\Market\Enums\AssetClass;`.

- [ ] **Step 3b: `GetPortfolioOverview` filtre sur `asset_class`**

Dans `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` :

```php
    /**
     * Sans `$classes`, tout le portefeuille. Avec, une ou plusieurs expositions : chacune a sa
     * page et sa ligne au patrimoine, et le partage se lit dans `AssetClass`, nulle part ailleurs.
     *
     * @param  ?list<AssetClass>  $classes
     */
    public function __invoke(User $user, ?array $classes = null): PortfolioOverviewData
    {
        $holdings = Holding::query()
            ->with('asset')
            ->where('user_id', $user->id)
            ->when($classes !== null, fn (Builder $query) => $query->whereHas(
                'asset',
                fn (Builder $asset) => $asset->whereIn(
                    'asset_class',
                    array_map(fn (AssetClass $class): string => $class->value, $classes),
                ),
            ))
            ->get();
```

Et dans la construction de `HoldingLineData`, ajouter après `type: $holding->asset->type,` :

```php
                assetClass: $holding->asset->asset_class,
```

Remplacer l'import `InstrumentType` par `AssetClass`.

- [ ] **Step 3c: `BuildEvolutionSeries` et `BuildPortfolioPerformances`**

Dans `BuildEvolutionSeries.php` : renommer le paramètre `$types` en `$classes`, son typedoc en `?list<AssetClass>`, la méthode privée `onlyTypes()` en `onlyClasses()`, et l'appel `idsOfTypes($types)` en `idsOfClasses($classes)`. Remplacer l'import `InstrumentType` par `AssetClass`. **Ne pas toucher** au commentaire qui explique que le filtre s'applique après le cache — il reste vrai.

Dans `BuildPortfolioPerformances.php` : mêmes renommages ; `nameOf(array $classes)` mappe désormais `fn (AssetClass $class): string => $class->value`. **Ne pas toucher** au commentaire qui explique que le filtre entre dans le nom du cache — il reste vrai.

- [ ] **Step 3d: Mettre à jour les appelants et supprimer `securities()`**

- `app/Contexts/Wealth/Infrastructure/PortfolioAssetClass.php` : `abstract protected function types(): ?array;` devient `abstract protected function classes(): ?array;` (typedoc `?list<AssetClass>`), et les trois appels `$this->types()` deviennent `$this->classes()`.
- `SecuritiesClass.php` : `classes()` renvoie `[AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity]`.
- `CryptoClass.php` : `classes()` renvoie `[AssetClass::Crypto]`.
- `InstrumentsController.php` : `$types = InstrumentType::securities();` devient
  `$classes = [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity];`, et les trois usages suivent.
- `CryptoController.php` : `$types = [InstrumentType::Crypto];` devient `$classes = [AssetClass::Crypto];`.
- `BuildMarketViewSnapshot.php` : `$securities = InstrumentType::securities();` devient
  `$securities = [AssetClass::Equity, AssetClass::Bond, AssetClass::Commodity];` et
  `$crypto = [InstrumentType::Crypto];` devient `$crypto = [AssetClass::Crypto];`.
- `app/Contexts/Market/Enums/InstrumentType.php` : supprimer la méthode `securities()` et son docbloc. **Garder `isCrypto()`** — les deux contrôleurs de fiche et `detailsByClass()` s'en servent encore ; il partira en Task 9 et Task 10.
- `app/Contexts/Market/Enums/InstrumentTypeTest.php` : supprimer le ou les cas qui testent `securities()`.
- `resources/js/lib/portfolio.ts` : ajouter `assetClass: string;` et `assetClassLabel: string;` au type de ligne de position, à côté de `type` et `typeLabel`.

- [ ] **Step 4: Run the affected tests**

Run: `php artisan test --compact --filter="GetPortfolioOverviewTest|BuildEvolutionSeriesTest|BuildPortfolioPerformancesTest|MarketInstrumentDirectoryTest|InstrumentTypeTest"`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS — suite entière verte.

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: filtre le portefeuille par exposition plutôt que par enveloppe"
```

---

### Task 5: L'origine de revenu d'une exposition

**Files:**
- Modify: `app/Contexts/Income/Enums/IncomeSource.php`
- Create: `app/Contexts/Income/Enums/IncomeSourceTest.php` (ou compléter s'il existe)

**Interfaces:**
- Consumes: `AssetClass` (Task 1).
- Produces: `IncomeSource::forAssetClass(AssetClass $class): ?self`.

- [ ] **Step 1: Write the failing test**

Créer `app/Contexts/Income/Enums/IncomeSourceTest.php` :

```php
<?php

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;

it('pays dividends on equity alone', function () {
    expect(IncomeSource::forAssetClass(AssetClass::Equity))->toBe(IncomeSource::Dividend)
        ->and(IncomeSource::forAssetClass(AssetClass::Bond))->toBeNull()
        ->and(IncomeSource::forAssetClass(AssetClass::Commodity))->toBeNull()
        ->and(IncomeSource::forAssetClass(AssetClass::Crypto))->toBeNull();
});

it('never maps two exposures onto the same source', function () {
    $sources = array_filter(array_map(
        fn (AssetClass $class): ?IncomeSource => IncomeSource::forAssetClass($class),
        AssetClass::cases(),
    ));

    expect($sources)->toHaveCount(count(array_unique($sources, SORT_REGULAR)));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=IncomeSourceTest`
Expected: FAIL — `Call to undefined method ...IncomeSource::forAssetClass()`.

- [ ] **Step 3: Write the implementation**

Dans `app/Contexts/Income/Enums/IncomeSource.php`, ajouter l'import `use App\Contexts\Market\Enums\AssetClass;` et la méthode :

```php
    /**
     * L'origine de revenu d'une exposition, ou `null` quand elle n'en produit aucune.
     *
     * Au plus une exposition par origine : le revenu du patrimoine se filtre par origine, jamais
     * par exposition, donc deux expositions renvoyant `Dividend` compteraient deux fois les mêmes
     * dividendes. Les obligations n'ont pas de ligne pour cette raison, et parce que rien ne les
     * alimente — `YahooFinanceAdapter::covers()` ne les couvre pas.
     */
    public static function forAssetClass(AssetClass $class): ?self
    {
        return match ($class) {
            AssetClass::Equity => self::Dividend,
            AssetClass::Bond, AssetClass::Commodity, AssetClass::Crypto => null,
        };
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=IncomeSourceTest`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Income/Enums/IncomeSource.php app/Contexts/Income/Enums/IncomeSourceTest.php
git commit -m "feat: rattache une origine de revenu à chaque exposition"
```

---

### Task 6: Les tendances se filtrent par exposition

**Files:**
- Modify: `app/Contexts/MarketView/Actions/GetHoldingTrends.php`
- Modify: `app/Contexts/MarketView/Ports/MarketDataPort.php`, `app/Contexts/MarketView/Infrastructure/MarketData.php`, `MarketDataTest.php`
- Test: `app/Contexts/MarketView/Actions/GetHoldingTrendsTest.php`

**Interfaces:**
- Consumes: `AssetClass` (Task 1), `HoldingSnapshotData` (existant).
- Produces: `GetHoldingTrends::__invoke(int $userId, ValuationRange $range = ValuationRange::Max, ?array $classes = null): list<HoldingTrendData>`.

- [ ] **Step 1: Write the failing test**

Ajouter à `app/Contexts/MarketView/Actions/GetHoldingTrendsTest.php` (le créer sur le modèle des tests voisins s'il n'existe pas) :

```php
it('keeps only the trends of the exposures it is given', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    foreach ([$gold, $stock] as $instrument) {
        Price::factory()->create(['asset_id' => $instrument->id, 'close' => 10.0]);
        Holding::factory()->create([
            'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
            'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 5.0,
        ]);
    }

    $trends = app(GetHoldingTrends::class)($user->id, ValuationRange::Max, [AssetClass::Commodity]);

    expect(array_map(fn ($trend): int => $trend->assetId, $trends))->toBe([$gold->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetHoldingTrendsTest`
Expected: FAIL — `__invoke()` n'accepte que deux arguments.

- [ ] **Step 3: Write the implementation**

Le filtre descend dans le port : dans `MarketView`, seul `Infrastructure` importe les modèles de `Market` (`MarketData.php`), les actions passent toutes par un port. Ajouter donc à `app/Contexts/MarketView/Ports/MarketDataPort.php` :

```php
    /**
     * Parmi ces actifs, ceux qui portent l'une des expositions données. Le filtre descend ici et
     * non dans l'action : `MarketView` lit le marché par ses ports, jamais par ses modèles.
     *
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $assetIds, array $classes): array;
```

et son implémentation dans `app/Contexts/MarketView/Infrastructure/MarketData.php` :

```php
    /**
     * @param  list<int>  $assetIds
     * @param  list<AssetClass>  $classes
     * @return list<int>
     */
    public function idsOfClasses(array $assetIds, array $classes): array
    {
        if ($assetIds === [] || $classes === []) {
            return [];
        }

        return Instrument::query()
            ->whereIn('id', $assetIds)
            ->whereIn('asset_class', array_map(fn (AssetClass $class): string => $class->value, $classes))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
```

Puis dans `app/Contexts/MarketView/Actions/GetHoldingTrends.php` :

```php
    /**
     * Les tendances des instruments détenus. Sans `$classes`, tout le portefeuille ; avec, une
     * exposition — sinon l'instantané hors-ligne porterait le portefeuille entier une fois par
     * classe, pour un rendu qui n'en montre qu'une part.
     *
     * @param  ?list<AssetClass>  $classes
     * @return list<HoldingTrendData>
     */
    public function __invoke(
        int $userId,
        ValuationRange $range = ValuationRange::Max,
        ?array $classes = null,
    ): array {
        $since = $this->windowStart($range);
        $assetIds = array_map(
            fn (HoldingSnapshotData $snapshot): int => $snapshot->assetId,
            $this->holdings->holdingsFor($userId),
        );

        if ($classes !== null) {
            $kept = array_flip($this->market->idsOfClasses($assetIds, $classes));
            $assetIds = array_values(array_filter($assetIds, fn (int $id): bool => isset($kept[$id])));
        }

        $closes = $this->market->closeSeriesSince($assetIds, $since);
        ...
    }
```

Ajouter l'import `AssetClass` là où il manque. Le reste du corps de l'action ne bouge pas.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GetHoldingTrendsTest`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "perf: borne les tendances à l'exposition demandée"
```

---

### Task 7: L'instantané hors-ligne suit le nouvel axe

**Files:**
- Modify: `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php`, `BuildMarketViewSnapshotTest.php`
- Modify: `app/Shared/Pwa/Http/SnapshotController.php`, `SnapshotControllerTest.php`
- Modify: `resources/js/lib/snapshotContract.ts`
- Modify: `resources/js/stores/snapshot.ts`, `resources/js/stores/snapshot.test.ts`
- Modify: `app/Contexts/MarketView/Datas/InstrumentDetailData.php`, `app/Contexts/MarketView/Datas/InstrumentMetaData.php`
- Modify: `app/Contexts/MarketView/Actions/GetInstrumentDetail.php`, `app/Contexts/MarketView/Infrastructure/MarketData.php`, `MarketDataTest.php`
- Modify: `resources/js/lib/instrument.ts`

**Interfaces:**
- Consumes: `AssetClass` et `hasSectors()` (Task 1), `IncomeSource::forAssetClass()` (Task 5), `GetHoldingTrends` filtré (Task 6).
- Produces:
  - `InstrumentDetailData::$assetClass` sérialisé en `assetClass`, `assetClassLabel`, `assetClassHref` — la fiche de la Task 9 s'en sert pour son fil d'Ariane.
  - PHP : `BuildMarketViewSnapshot::__invoke(int $userId): array{classes: array<string, array<string, mixed>>, assets: array<int, array<string, mixed>>}`.
  - TS : `AssetClassListSnapshot`, `AssetPageSnapshot`, `Snapshot.classes`, `Snapshot.assets`.
  - Store : `classList(key: string): AssetClassListSnapshot | null`, `assetPage(id: string): AssetPageSnapshot | null`, plus quatre accès transitoires (`instrumentsList`, `cryptoList`, `instrumentPage`, `cryptoPage`) que la Task 9 supprime.

- [ ] **Step 1: Write the failing test**

Dans `app/Contexts/MarketView/Actions/BuildMarketViewSnapshotTest.php`, remplacer les cas qui lisent `instruments` / `crypto` par :

```php
it('carries one list per exposure and every held asset once', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    Price::factory()->create(['asset_id' => $gold->id, 'close' => 100.0]);
    Holding::factory()->create([
        'asset_id' => $gold->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 80.0,
    ]);

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect(array_keys($snapshot['classes']))->toBe(AssetClass::values())
        ->and($snapshot['assets'])->toHaveKey($gold->id);
});

it('withholds sectors and income from the exposures that have none', function () {
    $user = User::factory()->create();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['classes']['equity'])->toHaveKeys(['sectorBreakdown', 'income', 'annualIncome'])
        ->and($snapshot['classes']['crypto'])->not->toHaveKey('sectorBreakdown')
        ->and($snapshot['classes']['crypto'])->not->toHaveKey('income');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=BuildMarketViewSnapshotTest`
Expected: FAIL — clé `classes` absente.

- [ ] **Step 3a: `InstrumentDetailData` porte son exposition**

Le gate des détachements se lit désormais sur l'exposition de l'actif, pas sur son enveloppe.

Dans `app/Contexts/MarketView/Datas/InstrumentDetailData.php` : ajouter `public AssetClass $assetClass,` juste après `public InstrumentType $type,`, l'import correspondant, et dans `jsonSerialize()` après `'typeLabel'` :

```php
            'assetClass' => $this->assetClass->value,
            'assetClassLabel' => $this->assetClass->getLabel(),
            'assetClassHref' => '/'.$this->assetClass->slug(),
```

`GetInstrumentDetail` ne lit pas le modèle : il construit `InstrumentDetailData` depuis le DTO `InstrumentMetaData` rendu par le port (`type: $meta->type`, `GetInstrumentDetail.php:36`). L'exposition doit donc traverser ce DTO.

Dans `app/Contexts/MarketView/Datas/InstrumentMetaData.php`, ajouter `public AssetClass $assetClass,` juste après `public InstrumentType $type,` et l'import.

Dans `app/Contexts/MarketView/Infrastructure/MarketData.php`, méthode `findInstrument()`, renseigner `assetClass: $instrument->asset_class,` après `type: $instrument->type,`.

Dans `app/Contexts/MarketView/Actions/GetInstrumentDetail.php`, passer `assetClass: $meta->assetClass,` après `type: $meta->type,`.

Couvrir la nouvelle donnée dans `app/Contexts/MarketView/Infrastructure/MarketDataTest.php`, sur le modèle des cas voisins.

Dans `resources/js/lib/instrument.ts`, ajouter au type `Instrument` :

```ts
    assetClass: string;
    assetClassLabel: string;
    assetClassHref: string;
```

- [ ] **Step 3b: Rewrite the snapshot builder**

Dans `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php` :

```php
    /**
     * @return array{
     *     classes: array<string, array<string, mixed>>,
     *     assets: array<int, array<string, mixed>>,
     * }
     */
    public function __invoke(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ['classes' => $this->emptyClasses(), 'assets' => []];
        }

        $classes = [];

        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->listFor($user, $userId, $exposure);
        }

        return ['classes' => $classes, 'assets' => $this->pagesFor($userId)];
    }

    /**
     * La composition d'une page liste, gates comprises. Les mêmes que celles d'`AssetClassController` :
     * l'instantané doit porter ce que la page affiche, jamais une composition parallèle.
     *
     * @return array<string, mixed>
     */
    private function listFor(User $user, int $userId, AssetClass $exposure): array
    {
        $classes = [$exposure];

        $list = [
            'overview' => ($this->getOverview)($user, $classes),
            'trends' => ($this->getTrends)($userId, ValuationRange::Max, $classes),
            'performances' => app(BuildPortfolioPerformances::class)($userId, $classes),
            'evolutionSeries' => app(BuildEvolutionSeries::class)(
                $userId,
                null,
                ValuationGranularity::Week,
                $classes,
            ),
        ];

        if ($exposure->hasSectors()) {
            $list['sectorBreakdown'] = app(GetSectorBreakdown::class)($user);
        }

        $source = IncomeSource::forAssetClass($exposure);

        if ($source !== null) {
            $list['income'] = app(GetIncomeSummary::class)($userId, $source);
            $list['annualIncome'] = app(GetAnnualIncome::class)($userId, $source);
        }

        return $list;
    }

    /**
     * Les fiches, une par position détenue. Plus de partage par classe : `/asset/{id}` sert le
     * même contenu à tout actif, quelle que soit son exposition.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pagesFor(int $userId): array
    {
        $pages = [];

        foreach ($this->holdings->holdingsFor($userId) as $holding) {
            /** @var HoldingSnapshotData $holding */
            $detail = ($this->getDetail)($userId, $holding->assetId);

            if ($detail === null) {
                continue;
            }

            $pages[$holding->assetId] = $this->page($userId, $holding->assetId, $detail);
        }

        return $pages;
    }
```

Remplacer `detailsByClass()` par `pagesFor()`, et `emptyList(bool $forSecurities)` par :

```php
    /**
     * Les quatre listes d'une base sans utilisateur : les mêmes `Data::empty()` que sert le
     * contrôleur, sous les mêmes gates.
     *
     * @return array<string, array<string, mixed>>
     */
    private function emptyClasses(): array
    {
        $classes = [];

        foreach (AssetClass::cases() as $exposure) {
            $list = [
                'overview' => PortfolioOverviewData::empty(),
                'trends' => [],
                'performances' => [],
                'evolutionSeries' => EvolutionSeriesData::empty(),
            ];

            if ($exposure->hasSectors()) {
                $list['sectorBreakdown'] = [];
            }

            if (IncomeSource::forAssetClass($exposure) !== null) {
                $list['income'] = IncomeSummaryData::empty();
                $list['annualIncome'] = [];
            }

            $classes[$exposure->value] = $list;
        }

        return $classes;
    }
```

Dans `page()`, remplacer le gate `if (! $detail->type->isCrypto())` par :

```php
        /** Une exposition qui ne distribue rien n'a pas de détachements : les porter gonflerait le blob pour rien. */
        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $page['dividends'] = app(GetAssetDividendHistory::class)($userId, $assetId);
        }
```

`isCrypto()` reste pour l'instant : les deux contrôleurs de fiche s'en servent encore. Elle part en Task 9.

- [ ] **Step 3c: `SnapshotController`**

Dans `app/Shared/Pwa/Http/SnapshotController.php`, remplacer les deux lignes du corps :

```php
            'classes' => $market['classes'],
            'assets' => $market['assets'],
```

- [ ] **Step 3d: Contrat TS et store**

Dans `resources/js/lib/snapshotContract.ts` :

```ts
/** Une page liste d'exposition. Le trio secteurs/revenus n'est servi que par les expositions qui en ont. */
export interface AssetClassListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    performances: Performance[];
    evolutionSeries: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}

/** La fiche servie par `/asset/{id}`. `dividends` manque aux expositions qui ne distribuent rien. */
export interface AssetPageSnapshot {
    instrument: Instrument;
    performances: Performance[];
    priceHistory: PriceHistory;
    valuation: ValuationSeries;
    dividends?: AssetDividendHistory;
}

export interface Snapshot {
    version: string;
    generatedAt: number;
    dashboard: DashboardSnapshot;
    /** Indexé par la valeur de `AssetClass` : `equity`, `bond`, `commodity`, `crypto`. */
    classes: Record<string, AssetClassListSnapshot>;
    assets: Record<string, AssetPageSnapshot>;
    properties: { list: PropertiesListSnapshot; byId: Record<string, PropertyPageSnapshot> };
}
```

Supprimer `InstrumentsListSnapshot`, `CryptoListSnapshot` et `InstrumentPageSnapshot`.

Dans `resources/js/stores/snapshot.ts`, ajouter les deux accès de la nouvelle forme :

```ts
    /** L'indexation se fait en mémoire : le blob entier est déjà chargé, une requête par page n'ajouterait qu'une latence. */
    function classList(key: string): AssetClassListSnapshot | null {
        return snapshot.value?.classes[key] ?? null;
    }

    function assetPage(id: string): AssetPageSnapshot | null {
        return snapshot.value?.assets[id] ?? null;
    }
```

et **réécrire les quatre anciens sur la nouvelle forme** plutôt que les supprimer : les pages qui les lisent vivent encore jusqu'aux Tasks 8 et 9, et les supprimer ici casserait `bun run typecheck` sans qu'aucune tâche ne soit en faute.

```ts
    /** Transitoires : lus par les pages que les Tasks 8 et 9 remplacent, supprimés avec elles. */
    const instrumentsList: ComputedRef<AssetClassListSnapshot | null> = computed(
        (): AssetClassListSnapshot | null => classList('equity'),
    );

    const cryptoList: ComputedRef<AssetClassListSnapshot | null> = computed(
        (): AssetClassListSnapshot | null => classList('crypto'),
    );

    const instrumentPage = assetPage;
    const cryptoPage = assetPage;
```

Exposer les six dans l'objet retourné, et mettre à jour les imports de types (`AssetClassListSnapshot`, `AssetPageSnapshot` remplacent `InstrumentsListSnapshot`, `CryptoListSnapshot`, `InstrumentPageSnapshot`).

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter="BuildMarketViewSnapshotTest|SnapshotControllerTest"`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS.

Run: `bun run test:js`
Expected: PASS (adapter `resources/js/stores/snapshot.test.ts` aux nouveaux noms si nécessaire).

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "refactor: indexe l'instantané par exposition et par actif"
```

---

### Task 8: La page liste, une par exposition

**Files:**
- Create: `app/Contexts/MarketView/Http/AssetClassController.php`
- Create: `app/Contexts/MarketView/Http/AssetClassControllerTest.php`
- Create: `resources/js/Pages/AssetClass/Index.vue`
- Delete: `app/Contexts/MarketView/Http/InstrumentsController.php`, `InstrumentsControllerTest.php` (s'il existe), `CryptoController.php`, `CryptoControllerTest.php`
- Delete: `resources/js/Pages/Instruments/Index.vue`, `resources/js/Pages/Crypto/Index.vue`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `AssetClass` et `hasSectors()` (Task 1), les filtres en `AssetClass` (Task 4), `IncomeSource::forAssetClass()` (Task 5), `GetHoldingTrends` filtré (Task 6), `snapshot.classList()` (Task 7).
- Produces: routes nommées `classes.equity`, `classes.bond`, `classes.commodity`, `classes.crypto` ; page Inertia `AssetClass/Index` avec la prop `assetClass: {key: string, label: string}` en plus des props existantes de la page Actions.

- [ ] **Step 1: Write the failing test**

Créer `app/Contexts/MarketView/Http/AssetClassControllerTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;

it('serves a list page for every exposure', function (AssetClass $class) {
    $this->get(route("classes.{$class->value}"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AssetClass/Index')
            ->where('assetClass.key', $class->value)
            ->where('assetClass.label', $class->getLabel())
            ->where('assetClass.hasSectors', $class->hasSectors())
            ->where('assetClass.hasIncome', $class === AssetClass::Equity));
})->with(AssetClass::cases());

it('offers sectors and income on equity alone', function () {
    $this->get(route('classes.equity'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sectorBreakdown')->has('income'));

    $this->get(route('classes.crypto'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->missing('sectorBreakdown')->missing('income'));
});
```

Vérifier la forme exacte des assertions Inertia utilisées par `CryptoControllerTest.php` avant d'écrire, et s'y conformer.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AssetClassControllerTest`
Expected: FAIL — route `classes.equity` inconnue.

- [ ] **Step 3a: Write the controller**

Créer `app/Contexts/MarketView/Http/AssetClassController.php` :

```php
<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\MarketView\Actions\GetHoldingTrends;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page liste d'une exposition. Une seule composition sert les quatre : elles ne diffèrent que
 * par deux sections, et les recopier une fois par classe les ferait diverger à la première
 * correction. L'exposition arrive par le défaut de route, posé à la déclaration.
 */
class AssetClassController
{
    public function __construct(
        private GetPortfolioOverview $getPortfolioOverview,
        private GetHoldingTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));
        $range = ValuationRange::fromRequest(request()->query('range'));
        $classes = [$exposure];

        $overview = $user !== null
            ? ($this->getPortfolioOverview)($user, $classes)
            : PortfolioOverviewData::empty();

        $source = IncomeSource::forAssetClass($exposure);

        /**
         * Les deux drapeaux voyagent avec la classe : la page ne peut pas déduire d'une valeur
         * absente qu'une section n'existe pas. `aheadOfNetwork` rend `null`, jamais `undefined`,
         * donc tester la valeur ferait apparaître les sections sur toutes les expositions.
         */
        $props = [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'hasSectors' => $exposure->hasSectors(),
                'hasIncome' => $source !== null,
            ],
            'overview' => $overview,
            /** Un groupe par section : chaque squelette se remplit à son rythme. */
            'trends' => Inertia::defer(fn () => ($this->getTrends)($userId, $range, $classes), 'tendances'),
            'performances' => Inertia::defer(fn () => $user !== null
                ? app(BuildPortfolioPerformances::class)($userId, $classes)
                : [], 'performances'),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'evolutionSeries' => Inertia::defer(fn () => $user !== null
                ? app(BuildEvolutionSeries::class)($userId, null, ValuationGranularity::Week, $classes)
                : EvolutionSeriesData::empty(), 'evolution'),
        ];

        if ($exposure->hasSectors()) {
            $props['sectorBreakdown'] = Inertia::defer(fn () => $user !== null
                ? app(GetSectorBreakdown::class)($user)
                : [], 'secteurs');
        }

        if ($source !== null) {
            /** Un seul groupe pour les deux : la section les affiche ensemble. */
            $props['income'] = Inertia::defer(fn () => $user !== null
                ? app(GetIncomeSummary::class)($userId, $source)
                : \App\Contexts\Income\Datas\IncomeSummaryData::empty(), 'revenus');
            $props['annualIncome'] = Inertia::defer(fn () => $user !== null
                ? app(GetAnnualIncome::class)($userId, $source)
                : [], 'revenus');
        }

        return Inertia::render('AssetClass/Index', $props);
    }
}
```

Comparer avec `InstrumentsController.php` avant suppression pour reprendre exactement les noms de groupes différés et la gestion du cas `$user === null`.

- [ ] **Step 3b: Declare the routes**

Dans `routes/web.php`, remplacer les lignes `/instruments` et `/crypto` par :

```php
/**
 * Une route par exposition, engendrée depuis l'enum : ajouter une classe d'actif n'est jamais
 * ajouter une route à la main. Routes explicites plutôt qu'un `/{exposition}` attrape-tout — la
 * racine porte déjà `/asset`, `/properties`, `/hors-ligne`, `/instantane`, `/manifest.json`.
 */
foreach (AssetClass::cases() as $assetClass) {
    Route::get($assetClass->slug(), AssetClassController::class)
        ->defaults('exposure', $assetClass->value)
        ->name("classes.{$assetClass->value}");
}
```

Ajouter les imports `App\Contexts\Market\Enums\AssetClass` et `App\Contexts\MarketView\Http\AssetClassController` ; retirer ceux de `InstrumentsController` et `CryptoController`.

- [ ] **Step 3c: Write the Vue page**

Créer `resources/js/Pages/AssetClass/Index.vue` en fusionnant `Instruments/Index.vue` et `Crypto/Index.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import IncomeSection from '@/components/instruments/IncomeSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { CatalogTrend } from '@/lib/catalog';
import type { AnnualIncome, IncomeSummary } from '@/lib/income';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    assetClass: { key: string; label: string; hasSectors: boolean; hasIncome: boolean };
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    /** Absentes des expositions sans secteur ni revenu : le serveur ne les envoie pas. */
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();

const snapshot = useSnapshotStore();

const cached = () => snapshot.classList(props.assetClass.key);

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const trends = aheadOfNetwork(() => props.trends, () => cached()?.trends);
const performances = aheadOfNetwork(() => props.performances, () => cached()?.performances);
const evolutionSeries = aheadOfNetwork(() => props.evolutionSeries, () => cached()?.evolutionSeries);
const sectorBreakdown = aheadOfNetwork(() => props.sectorBreakdown, () => cached()?.sectorBreakdown);
const income = aheadOfNetwork(() => props.income, () => cached()?.income);
const annualIncome = aheadOfNetwork(() => props.annualIncome, () => cached()?.annualIncome);
</script>

<template>
    <Head :title="props.assetClass.label" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <!--
            Les sections se décident sur la classe, jamais sur la valeur : `aheadOfNetwork` rend
            `null` en attendant, et un `null` ne distingue pas « pas encore » de « jamais ».
        -->
        <IncomeSection
            v-if="overview.holdings.length && props.assetClass.hasIncome"
            :income="income"
            :annual-income="annualIncome"
        />

        <SectorsSection
            v-if="overview.holdings.length && props.assetClass.hasSectors"
            :slices="sectorBreakdown"
        />
    </AppPage>

    <AppBottomBar
        :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.assetClass.label }]"
    />
</template>
```

- [ ] **Step 3d: Delete what it replaces**

```bash
git rm app/Contexts/MarketView/Http/InstrumentsController.php \
       app/Contexts/MarketView/Http/CryptoController.php \
       app/Contexts/MarketView/Http/CryptoControllerTest.php \
       resources/js/Pages/Instruments/Index.vue \
       resources/js/Pages/Crypto/Index.vue
```

(`InstrumentsControllerTest.php` n'existe pas dans l'arbre actuel ; ne pas s'en étonner.)

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=AssetClassControllerTest`
Expected: PASS, 5 cas (4 du dataset + 1).

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: une page liste par exposition, servie par un contrôleur unique"
```

---

### Task 9: La fiche polymorphe sur `/asset/{id}`

**Files:**
- Create: `app/Contexts/MarketView/Http/AssetController.php`
- Create: `app/Contexts/MarketView/Http/AssetControllerTest.php`
- Create: `resources/js/Pages/Asset/Show.vue`
- Delete: `app/Contexts/MarketView/Http/InstrumentDetailController.php`, `InstrumentDetailControllerTest.php`, `CryptoDetailController.php`, `CryptoDetailControllerTest.php`
- Delete: `resources/js/Pages/Instruments/Show.vue`, `resources/js/Pages/Crypto/Show.vue`
- Modify: `resources/js/components/InstrumentList.vue`, `resources/js/components/instruments/InstrumentsSection.vue`
- Modify: `routes/web.php`
- Modify: `app/Contexts/Market/Enums/InstrumentType.php` (suppression de `isCrypto()`), `InstrumentTypeTest.php`
- Modify: `resources/js/stores/snapshot.ts` (suppression des accès transitoires), `resources/js/stores/snapshot.test.ts`

**Interfaces:**
- Consumes: `IncomeSource::forAssetClass()` (Task 5), `InstrumentDetailData::$assetClass` et `snapshot.assetPage()` (Task 7).
- Produces: route `assets.show` sur `/asset/{id}` ; page Inertia `Asset/Show`.

- [ ] **Step 1: Write the failing test**

Créer `app/Contexts/MarketView/Http/AssetControllerTest.php` :

```php
<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;

it('serves one address for every asset, whatever its exposure', function (InstrumentType $type) {
    $instrument = Instrument::factory()->create(['type' => $type]);

    $this->get(route('assets.show', $instrument->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Asset/Show'));
})->with([InstrumentType::Stock, InstrumentType::ETF, InstrumentType::Commodity, InstrumentType::Crypto]);

it('carries dividends on equity and withholds them elsewhere', function () {
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);

    $this->get(route('assets.show', $stock->id))
        ->assertInertia(fn ($page) => $page->has('dividends'));

    $this->get(route('assets.show', $gold->id))
        ->assertInertia(fn ($page) => $page->missing('dividends'));
});

it('answers 404 on an unknown asset', function () {
    $this->get(route('assets.show', 999999))->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AssetControllerTest`
Expected: FAIL — route `assets.show` inconnue.

- [ ] **Step 3a: Write the controller**

Créer `app/Contexts/MarketView/Http/AssetController.php` :

```php
<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\MarketView\Actions\GetInstrumentDetail;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La fiche d'un actif, quelle que soit son exposition. Une seule adresse par actif : deux
 * contrôleurs se renvoyaient autrefois 404 l'un l'autre pour éviter qu'un même actif réponde à
 * deux fils d'Ariane contradictoires. Le fil se déduit maintenant de l'exposition portée par
 * l'actif lui-même.
 */
class AssetController
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

        $props = [
            'instrument' => $detail,
            'performances' => app(BuildAssetPerformances::class)($userId, $id),
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'valuation' => Inertia::defer(
                fn () => app(BuildAssetValuationSeries::class)(
                    $userId,
                    $id,
                    ValuationRange::Max,
                    ValuationGranularity::Week,
                )
            ),
        ];

        /**
         * Non différée : la visibilité de la section dépend de la donnée elle-même, et un
         * squelette qui disparaît sur chaque actif capitalisant coûterait plus qu'il ne rapporte.
         */
        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $props['dividends'] = app(GetAssetDividendHistory::class)($userId, $id);
        }

        return Inertia::render('Asset/Show', $props);
    }
}
```

- [ ] **Step 3b: Route and links**

Dans `routes/web.php`, remplacer les deux routes de fiche par :

```php
Route::get('/asset/{id}', AssetController::class)->name('assets.show');
```

La prop traverse **deux** composants, pas un : `InstrumentsSection.vue` la reçoit et la transmet à `InstrumentList.vue`.

Dans `resources/js/components/InstrumentList.vue` : supprimer la prop `basePath` et son `withDefaults` (le `defineProps` redevient un simple `defineProps<{...}>()` si `basePath` était le seul défaut), et remplacer le `:href` par `` `/asset/${row.id}` ``.

Dans `resources/js/components/instruments/InstrumentsSection.vue` : supprimer la prop `basePath`, son `withDefaults` et l'attribut `:base-path` transmis à `InstrumentList`.

Vérifier ensuite : `grep -rn "base-path\|basePath" resources/js` ne doit plus rien rendre.

- [ ] **Step 3c: Write the Vue page**

Créer `resources/js/Pages/Asset/Show.vue` en fusionnant les deux fiches. `dividends` devient optionnelle et toutes ses lectures passent par un défaut :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppBottomBar from '@/components/AppBottomBar.vue';
import AppPage from '@/components/AppPage.vue';
import DividendsSection from '@/components/instrument/DividendsSection.vue';
import HeroSection from '@/components/instrument/HeroSection.vue';
import PerformanceSection from '@/components/instrument/PerformanceSection.vue';
import PriceHistorySection from '@/components/instrument/PriceHistorySection.vue';
import SectorsSection from '@/components/instrument/SectorsSection.vue';
import TransactionsSection from '@/components/instrument/TransactionsSection.vue';
import ValuationSection from '@/components/instrument/ValuationSection.vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import type { AssetDividendHistory } from '@/lib/income';
import type { Instrument, PriceHistory, ValuationSeries } from '@/lib/instrument';
import type { Performance } from '@/lib/performance';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    instrument: Instrument;
    performances: Performance[];
    priceHistory?: PriceHistory;
    valuation?: ValuationSeries;
    /** Absente des expositions qui ne distribuent rien : le serveur ne l'envoie pas. */
    dividends?: AssetDividendHistory;
}>();

const snapshot = useSnapshotStore();

/** Une exposition sans distribution n'a pas de détachements à annoter : la liste vide le dit. */
const receipts = computed(() => props.dividends?.receipts ?? []);

/** `instrument` et `performances` sont synchrones côté serveur : rien à combler. */
const priceHistory = aheadOfNetwork(
    () => props.priceHistory,
    () => snapshot.assetPage(String(props.instrument.id))?.priceHistory,
);
const valuation = aheadOfNetwork(
    () => props.valuation,
    () => snapshot.assetPage(String(props.instrument.id))?.valuation,
);
</script>

<template>
    <Head :title="props.instrument.name" />

    <AppPage>
        <HeroSection :instrument="props.instrument" />

        <ValuationSection
            v-if="props.instrument.position"
            :valuation="valuation"
            :dividends="receipts"
        />

        <PriceHistorySection v-else :price-history="priceHistory" />

        <PerformanceSection
            v-if="props.instrument.position && props.performances.length"
            :performances="props.performances"
        />

        <DividendsSection v-if="props.dividends && receipts.length" :dividends="props.dividends" />

        <SectorsSection
            v-if="props.instrument.sectors.length"
            :sectors="props.instrument.sectors"
            :market-value="props.instrument.position?.marketValue ?? null"
        />

        <TransactionsSection :transactions="props.instrument.transactions" />
    </AppPage>

    <AppBottomBar
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: props.instrument.assetClassLabel, href: props.instrument.assetClassHref },
            { label: props.instrument.name },
        ]"
    />
</template>
```

- [ ] **Step 3d: Delete what it replaces**

```bash
git rm app/Contexts/MarketView/Http/InstrumentDetailController.php \
       app/Contexts/MarketView/Http/InstrumentDetailControllerTest.php \
       app/Contexts/MarketView/Http/CryptoDetailController.php \
       app/Contexts/MarketView/Http/CryptoDetailControllerTest.php \
       resources/js/Pages/Instruments/Show.vue \
       resources/js/Pages/Crypto/Show.vue
```

Suppression validée avec l'utilisateur : ces deux tests couvrent le 404 croisé entre les deux fiches, comportement qui n'existe plus. `AssetControllerTest` les remplace.

Supprimer alors `isCrypto()` de `app/Contexts/Market/Enums/InstrumentType.php` et le cas qui la teste dans `InstrumentTypeTest.php` : les deux contrôleurs qui l'appelaient viennent de disparaître. Vérifier d'abord : `grep -rn "isCrypto" app resources` ne doit plus rien rendre.

Supprimer enfin les quatre accès transitoires du store — `instrumentsList`, `cryptoList`, `instrumentPage`, `cryptoPage` — dans `resources/js/stores/snapshot.ts` et dans l'objet qu'il retourne : plus aucune page ne les lit. Vérifier : `grep -rn "instrumentsList\|cryptoList\|instrumentPage\|cryptoPage" resources/js` ne doit plus rendre que le fichier de test, à adapter.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=AssetControllerTest`
Expected: PASS, 6 cas.

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: une seule adresse par actif, avec une fiche polymorphe"
```

---

### Task 10: `Wealth` dérive ses classes de l'enum

**Files:**
- Modify: `app/Contexts/Wealth/Ports/AssetClassPort.php` (ajout de `color()`)
- Modify: `app/Contexts/Wealth/Infrastructure/PortfolioAssetClass.php` (devient concrète)
- Modify: `app/Contexts/Wealth/Infrastructure/RealEstateClass.php` (ajout de `color()`)
- Modify: `app/Contexts/Wealth/WealthProvider.php`, `app/Providers/AppServiceProvider.php`
- Modify: `app/Contexts/Wealth/Datas/AssetClassData.php`, `ClassValuesData.php`
- Modify: `app/Contexts/Wealth/Actions/BuildWealthSeries.php`
- Modify: `app/Contexts/Wealth/Infrastructure/AssetClassRegistryTest.php`
- Delete: `app/Contexts/Wealth/Infrastructure/SecuritiesClass.php`, `CryptoClass.php`
- Create: `app/Contexts/Wealth/Infrastructure/PortfolioAssetClassTest.php`
- Modify: `resources/js/lib/wealth.ts`

**Interfaces:**
- Consumes: `AssetClass` (Task 1), `IncomeSource::forAssetClass()` (Task 5), filtres en `AssetClass` (Task 4).
- Produces:
  - `AssetClassPort::color(): string` (jeton de teinte).
  - `PortfolioAssetClass::__construct(AssetClass $exposure, GetPortfolioOverview $overview, BuildEvolutionSeries $evolution, GetIncomeSummary $income)`.
  - `WealthProvider::registers(Application $app, array $extra): void` où `$extra` est `list<class-string<AssetClassPort>>`.
  - `AssetClassData` et `ClassValuesData` sérialisent `color`.

- [ ] **Step 1: Write the failing test**

Remplacer le premier cas de `app/Contexts/Wealth/Infrastructure/AssetClassRegistryTest.php` par un test du registre réel, et ajouter `color()` à la classe anonyme `fakeAssetClass()` :

```php
        public function color(): string
        {
            return 'value';
        }
```

Puis ajouter :

```php
it('derives one class per exposure, then the hand-written ones', function () {
    $keys = array_map(
        fn (AssetClassPort $class): string => $class->key(),
        app(AssetClassRegistry::class)->all(),
    );

    expect($keys)->toBe(['equity', 'bond', 'commodity', 'crypto', 'realEstate']);
});
```

Créer `app/Contexts/Wealth/Infrastructure/PortfolioAssetClassTest.php` :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Wealth\Infrastructure\PortfolioAssetClass;

it('describes itself from its exposure', function () {
    $commodity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Commodity]);

    expect($commodity->key())->toBe('commodity')
        ->and($commodity->label())->toBe('Matières premières')
        ->and($commodity->href())->toBe('/matieres-premieres')
        ->and($commodity->color())->toBe('commodity')
        ->and($commodity->incomeLabel())->toBeNull();
});

it('labels the income of an exposure that distributes', function () {
    $equity = app()->makeWith(PortfolioAssetClass::class, ['exposure' => AssetClass::Equity]);

    expect($equity->incomeLabel())->toBe('Dividendes');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="AssetClassRegistryTest|PortfolioAssetClassTest"`
Expected: FAIL — `PortfolioAssetClass` est abstraite, `color()` inexistante.

- [ ] **Step 3a: Le port porte sa teinte**

Dans `app/Contexts/Wealth/Ports/AssetClassPort.php`, ajouter après `href()` :

```php
    /**
     * Jeton de teinte de la classe, résolu par thème côté front. Porté par la classe et non déduit
     * de son rang : un rang décidait autrefois de la couleur, et la quatrième classe reprenait
     * celle de la première.
     */
    public function color(): string;
```

- [ ] **Step 3b: `PortfolioAssetClass` devient concrète**

Réécrire `app/Contexts/Wealth/Infrastructure/PortfolioAssetClass.php` : retirer `abstract` de la classe et la méthode `classes()`, ajouter `private AssetClass $exposure` en tête du constructeur, et :

```php
    public function key(): string
    {
        return $this->exposure->value;
    }

    public function label(): string
    {
        return $this->exposure->getLabel();
    }

    public function href(): string
    {
        return '/'.$this->exposure->slug();
    }

    public function color(): string
    {
        return $this->exposure->getColor();
    }

    public function incomeLabel(): ?string
    {
        return IncomeSource::forAssetClass($this->exposure)?->getLabel();
    }
```

Remplacer les appels `$this->classes()` par `[$this->exposure]`, et dans `monthlyIncomeFor()` remplacer `$this->incomeSource()` par `IncomeSource::forAssetClass($this->exposure)` (conserver le docbloc qui explique le filtre par origine). Supprimer la méthode `incomeSource()`.

Dans `RealEstateClass.php`, ajouter :

```php
    public function color(): string
    {
        return 'realEstate';
    }
```

```bash
git rm app/Contexts/Wealth/Infrastructure/SecuritiesClass.php \
       app/Contexts/Wealth/Infrastructure/CryptoClass.php
```

- [ ] **Step 3c: Le registre se construit**

Réécrire `app/Contexts/Wealth/WealthProvider.php` :

```php
    /**
     * L'ordre des classes est un contrat : celui des lignes du résumé et des bandes du graphe. Les
     * expositions viennent d'abord, dans l'ordre des cas de `AssetClass` ; les classes écrites à la
     * main suivent, dans l'ordre où on les passe.
     *
     * Le conteneur ne sait pas résoudre un `AssetClass` en paramètre de constructeur : c'est donc
     * le registre qui instancie, et non un `tag()` de noms de classes.
     *
     * @param  list<class-string<AssetClassPort>>  $extra  classes écrites à la main
     */
    public static function registers(Application $app, array $extra): void
    {
        $app->scoped(
            AssetClassRegistry::class,
            function (Application $app) use ($extra): AssetClassRegistry {
                $exposures = array_map(
                    fn (AssetClass $exposure): AssetClassPort => new PortfolioAssetClass(
                        $exposure,
                        $app->make(GetPortfolioOverview::class),
                        $app->make(BuildEvolutionSeries::class),
                        $app->make(GetIncomeSummary::class),
                    ),
                    AssetClass::cases(),
                );

                return new AssetClassRegistry([
                    ...$exposures,
                    ...array_map(fn (string $class): AssetClassPort => $app->make($class), $extra),
                ]);
            },
        );
    }
```

Dans `app/Providers/AppServiceProvider.php` :

```php
        /** L'ordre décide de celui des lignes du tableau de bord et des bandes de son graphe. */
        WealthProvider::registers(app: $this->app, extra: [RealEstateClass::class]);
```

Retirer les imports de `SecuritiesClass` et `CryptoClass`.

- [ ] **Step 3d: La teinte descend dans le payload**

Dans `AssetClassData.php` : ajouter `public string $color,` au constructeur, `color: $class->color(),` dans `from()`, et `'color' => $this->color,` dans `jsonSerialize()`.

Dans `ClassValuesData.php` : ajouter `public string $color,` et `'color' => $this->color,`.

Dans `BuildWealthSeries.php` : passer `color: $class->color(),` à la construction de `ClassValuesData`.

Dans `resources/js/lib/wealth.ts` : ajouter `color: string;` aux interfaces `AssetClass` et `ClassValues`.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter="AssetClassRegistryTest|PortfolioAssetClassTest|GetWealthOverviewTest|BuildWealthSeriesTest|GetWealthIncomeTest"`
Expected: PASS (adapter les trois derniers aux nouvelles clés `equity` / `commodity` / `crypto`).

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat: dérive les classes de patrimoine de l'exposition des actifs"
```

---

### Task 11: L'invariant du patrimoine, testé pour lui-même

**Files:**
- Create: `app/Contexts/Wealth/Actions/WealthInvariantTest.php`

**Interfaces:**
- Consumes: `GetWealthOverview`, `GetWealthIncome`, `AssetClassRegistry` (Task 10).
- Produces: rien — c'est un garde-fou.

- [ ] **Step 1: Write the test**

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Datas\AssetClassData;
use App\Contexts\Income\Enums\IncomeSource;

it('totals the wealth as the exact sum of its classes', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    foreach ([InstrumentType::Stock, InstrumentType::Commodity, InstrumentType::Crypto] as $type) {
        $instrument = Instrument::factory()->create(['type' => $type]);
        Price::factory()->create(['asset_id' => $instrument->id, 'close' => 100.0]);
        Holding::factory()->create([
            'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
            'user_id' => $user->id, 'quantity' => 3, 'avg_cost' => 60.0,
        ]);
    }

    $overview = app(GetWealthOverview::class)($user->id);

    $sum = array_sum(array_map(fn (AssetClassData $line): float => $line->value, $overview->classes));

    expect(round($sum, 2))->toBe($overview->totalValue);
});

/**
 * Le revenu du patrimoine se filtre par origine et non par exposition : deux expositions
 * partageant une origine compteraient deux fois les mêmes encaissements.
 */
it('never maps two exposures onto one income source', function () {
    $sources = array_filter(array_map(
        fn (AssetClass $class): ?IncomeSource => IncomeSource::forAssetClass($class),
        AssetClass::cases(),
    ));

    expect(array_unique($sources, SORT_REGULAR))->toHaveCount(count($sources));
});
```

- [ ] **Step 2: Run test**

Run: `php artisan test --compact --filter=WealthInvariantTest`
Expected: PASS immédiatement — c'est un filet, pas une spécification à faire passer. S'il échoue, une tâche précédente est fautive : corriger avant de continuer.

- [ ] **Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth/Actions/WealthInvariantTest.php
git commit -m "test: fige la somme des classes et l'unicité des origines de revenu"
```

---

### Task 12: Une seule lecture du portefeuille par requête

**Files:**
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php`

**Interfaces:**
- Consumes: `HoldingLineData::$assetClass` (Task 4).
- Produces: signature inchangée. `GetPortfolioOverview` lit toutes les positions une fois par utilisateur et par requête, puis découpe en mémoire.

- [ ] **Step 1: Write the failing test**

```php
it('reads the holdings once, however many exposures ask for them', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $instrument = Instrument::factory()->create(['type' => InstrumentType::Stock]);
    Price::factory()->create(['asset_id' => $instrument->id, 'close' => 10.0]);
    Holding::factory()->create([
        'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
        'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 5.0,
    ]);

    $overview = app(GetPortfolioOverview::class);
    $overview($user, [AssetClass::Equity]);

    DB::enableQueryLog();

    foreach (AssetClass::cases() as $class) {
        $overview($user, [$class]);
    }

    expect(DB::getQueryLog())->toBeEmpty();
});
```

Ajouter `use Illuminate\Support\Facades\DB;` aux imports du test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GetPortfolioOverviewTest`
Expected: FAIL — le journal de requêtes n'est pas vide.

- [ ] **Step 3: Write the implementation**

Réécrire `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` : lire tout le portefeuille une fois par utilisateur, retenir les lignes, puis filtrer et totaliser en mémoire.

```php
class GetPortfolioOverview
{
    /**
     * Les lignes de chaque utilisateur, pour la durée de la requête. Cinq classes d'actif
     * lisaient autrefois cinq fois les mêmes positions et les mêmes prix ; le portefeuille tient
     * en quelques dizaines de lignes, le découpage se fait donc en mémoire.
     *
     * @var array<int, list<HoldingLineData>>
     */
    private array $linesByUser = [];

    public function __construct(private PriceRepositoryContract $prices) {}

    /**
     * Sans `$classes`, tout le portefeuille. Avec, une ou plusieurs expositions.
     *
     * @param  ?list<AssetClass>  $classes
     */
    public function __invoke(User $user, ?array $classes = null): PortfolioOverviewData
    {
        $lines = $this->linesByUser[$user->id] ??= $this->readLines($user);

        if ($classes !== null) {
            $kept = array_flip(array_map(fn (AssetClass $class): string => $class->value, $classes));
            $lines = array_values(array_filter(
                $lines,
                fn (HoldingLineData $line): bool => isset($kept[$line->assetClass->value]),
            ));
        }

        return $this->summarize($lines);
    }
```

`readLines(User $user): list<HoldingLineData>` reprend le corps actuel jusqu'à la construction des lignes (sans le `when()` de filtrage, désormais inutile) ; `summarize(array $lines): PortfolioOverviewData` reprend l'accumulation de `totalValue`, `totalCost`, `totalGain`, `totalGainPct`. Une seule implémentation du total, partagée par tous les appels.

Enregistrer l'action en `scoped` dans `AppServiceProvider::register()` :

```php
        /** Une lecture du portefeuille par requête : les classes d'actif la partagent. */
        $this->app->scoped(GetPortfolioOverview::class);
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=GetPortfolioOverviewTest`
Expected: PASS.

Run: `php artisan test --compact`
Expected: PASS.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "perf: lit le portefeuille une seule fois par requête"
```

---

### Task 13: Les teintes cessent d'être positionnelles

**Files:**
- Modify: `resources/js/lib/chart.ts`
- Modify: `resources/js/components/dashboard/WealthEvolutionSection.vue`
- Modify: `resources/js/lib/chart.test.ts`, `resources/js/lib/chart.dark.test.ts`, `resources/js/lib/wealth.test.ts`

**Interfaces:**
- Consumes: `ClassValues.color` et `AssetClass.color` du payload (Task 10).
- Produces: `WealthStackClass = { label: string; values: number[]; color: string }` ; `palette()` gagne les jetons `bond` et `commodity` ; `classColors()` et `classColorAt()` disparaissent.

- [ ] **Step 1: Write the failing test**

Dans `resources/js/lib/chart.test.ts` :

```ts
it('paints each wealth band with the colour its class carries', () => {
    const option = buildWealthStackOption({
        labels: ['2026-01-01', '2026-02-01'],
        classes: [
            { label: 'Actions', values: [1, 2], color: 'value' },
            { label: 'Matières premières', values: [3, 4], color: 'commodity' },
            { label: 'Crypto', values: [5, 6], color: 'crypto' },
            { label: 'Immobilier', values: [7, 8], color: 'realEstate' },
        ],
        invested: [1, 1],
        valueFormatter: (amount: number): string => String(amount),
        window: null,
        description: '',
    });

    const strokes = (option.series as { lineStyle: { color: string } }[])
        .map((one) => one.lineStyle.color);

    expect(new Set(strokes).size).toBe(4);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `bun run test:js`
Expected: FAIL — quatre bandes, trois teintes ; `color` inconnu sur `WealthStackClass`.

- [ ] **Step 3: Write the implementation**

Dans `resources/js/lib/chart.ts` :

1. Ajouter `bond` et `commodity` au type `ChartPalette` et aux deux jeux de `palette()` :
   - sombre : `bond: '#2dd4bf'`, `commodity: '#fbbf24'`
   - clair : `bond: '#0d9488'`, `commodity: '#a16207'`
2. Ajouter `color: string;` à `WealthStackClass`, avec le docbloc : `/** Jeton de teinte porté par la classe : la couleur ne se déduit plus de son rang. */`
3. Supprimer `classColors()` et `classColorAt()` et leur docbloc, les remplacer par :

```ts
/**
 * La teinte d'une classe d'actif, depuis le jeton qu'elle porte. Le rang ne décide plus : il
 * décidait autrefois, et la quatrième classe reprenait la teinte de la première.
 */
function classColor(token: string): string {
    const colors = palette();

    return (colors as unknown as Record<string, string>)[token] ?? colors.value;
}
```

4. Dans `wealthStackSeries()`, remplacer `const color = classColorAt(index);` par `const color = classColor(one.color);` et retirer le paramètre `index` désormais inutile.
5. Dans `wealthStackTooltip()`, remplacer `classColorAt(at)` par `classColor(one.color)` et retirer `at`.

Dans `WealthEvolutionSection.vue`, rien à changer : `props.series.classes` porte déjà `color` depuis le serveur (Task 10). Vérifier que `WealthSeries['classes']` de `wealth.ts` inclut bien `color`.

- [ ] **Step 4: Run tests**

Run: `bun run test:js`
Expected: PASS.

Run: `bun run typecheck`
Expected: aucune erreur.

Run: `bun run build`
Expected: build réussi.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "fix: colore chaque classe par son jeton plutôt que par son rang"
```

---

### Task 14: Les règles du dépôt suivent le nouvel axe

**Files:**
- Modify: `.ai/rules/contexts.md` (via l'outil MCP `record-rule`)
- Create: `.ai/rules/market.md` (via `record-rule`)
- Modify: `.ai/rules/wealth.md` (via `record-rule`)
- Modify: `.ai/rules/index.md`

**Interfaces:**
- Consumes: l'état final du code après les tâches 1 à 13.
- Produces: des règles que le prochain agent lira avant d'éditer ces chemins.

- [ ] **Step 1: Vérifier que les anciennes règles sont bien caduques**

Run: `grep -rn "securities()\|isCrypto\|404" .ai/rules`
Expected: la règle `contexts.md` mentionne encore `InstrumentType::securities()`, `isCrypto()` et le 404 croisé — trois choses qui n'existent plus.

- [ ] **Step 2: Enregistrer la règle `Market`**

Utiliser l'outil `record-rule` avec :
- glob : `app/Contexts/Market/**`
- titre : `Deux axes : enveloppe et exposition`
- note :

```
`InstrumentType` dit COMMENT l'actif est détenu — titre vif, ETF, contrat à terme. Il décide du
badge `typeLabel` sur chaque ligne et de `YahooFinanceAdapter::supportsSectors()`. `AssetClass` dit
À QUOI le porteur est exposé — actions, obligations, matières premières, crypto. Il décide de la
classe de patrimoine, des pages liste et de tout filtre par classe.

Ne jamais partager le portefeuille par `InstrumentType` : ce filtre passe par `AssetClass`, et par
elle seule. `AssetClass::defaultForType()` est un défaut posé au `creating` puis stocké, pas une
dérivation : la valeur en base fait foi, et un ETF obligataire se corrige à la main.

L'ordre des cas de `AssetClass` est un contrat : il fixe l'ordre des lignes du résumé patrimonial
et l'empilement des bandes de son graphe.
```

- [ ] **Step 3: Réécrire la règle `Contexts`**

`record-rule` avec le glob `app/Contexts/**`, en conservant le piège des caches et en retirant celui du 404 croisé :

```
Le partage par classe d'actif se lit dans `AssetClass`, nulle part ailleurs. Tout filtre —
`GetPortfolioOverview($user, $classes)`, `BuildEvolutionSeries(..., $classes)`,
`BuildPortfolioPerformances($userId, $classes)`, `GetHoldingTrends($userId, $range, $classes)`,
`InstrumentDirectoryPort::idsOfClasses()` — passe par elle.

Piège toujours valable : la série d'évolution se filtre APRÈS son cache (elle porte `assetId` par
actif), les performances AVANT et sous un nom de cache distinct — une fenêtre glissante agrège les
transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'une
classe à l'autre.

Le piège du 404 croisé entre fiches n'existe plus : `/asset/{id}` sert tout actif, et son fil
d'Ariane se déduit de l'exposition que l'actif porte.
```

- [ ] **Step 4: Compléter la règle `Wealth`**

`record-rule` avec le glob `app/Contexts/Wealth/**`, en ajoutant à la règle existante :

```
Une exposition, une origine de revenu, au plus une fois. `monthlyIncomeFor()` filtre par
`IncomeSource` et non par exposition : deux expositions renvoyant la même origine compteraient
deux fois les mêmes encaissements. `IncomeSource::forAssetClass()` est la seule définition de
cette correspondance, et `WealthInvariantTest` la garde.

Les classes de patrimoine ne s'écrivent plus une par une : `PortfolioAssetClass` est paramétrée par
`AssetClass` et le registre boucle sur ses cas. Seule une classe qui n'est pas un portefeuille —
`RealEstateClass` — s'écrit à la main.
```

- [ ] **Step 5: Mettre à jour l'index et committer**

Ajouter à `.ai/rules/index.md` la ligne :

```
| app/Contexts/Market/** | .ai/rules/market.md |
```

en respectant l'ordre alphabétique des chemins du tableau existant.

```bash
git add .ai/rules
git commit -m "docs: réécrit les règles autour de l'exposition"
```

- [ ] **Step 6: Vérification finale**

Run: `php artisan test --compact`
Expected: suite entière verte.

Run: `bun run test:js && bun run typecheck && bun run build`
Expected: tout vert.

Run: `php artisan route:list --except-vendor`
Expected: `/`, `/actions`, `/obligations`, `/matieres-premieres`, `/crypto`, `/asset/{id}`, `/properties`, `/properties/{id}`, plus les routes PWA.

Ouvrir `https://argent.test` et vérifier de visu : le résumé du tableau de bord porte Actions, Matières premières, Crypto et Immobilier (les obligations, à zéro, sont écartées), et la part d'Actions est passée d'environ 94 % à environ 88 %.
