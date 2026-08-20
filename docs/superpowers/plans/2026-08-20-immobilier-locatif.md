# Contexte RealEstate — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Suivre un bien locatif détenu : loyers, charges, crédit, valeur estimée, avec carte au dashboard, page dédiée et loyers agrégés dans le contexte Income. Supprime le vestige `PersonalAsset`.

**Architecture:** Nouveau bounded context `app/Contexts/RealEstate/` (modèles, calculateurs purs, actions), même patron que les contexts existants. Les loyers et mensualités ne sont jamais stockés : calculés depuis bail et prêt, seules les exceptions sont persistées. Income gagne une source `Rent` derrière un port, câblée par tag comme la source Dividend.

**Tech Stack:** Laravel 12, PHP 8.5, Pest 4 (tests colocalisés dans `app/Contexts/**`), Inertia v3 + Vue 3 + Tailwind 4, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-20-immobilier-locatif-design.md`

## Global Constraints

- Tout texte visible dans le front est en français ; identifiants de code en anglais.
- Chaque tâche se termine par `vendor/bin/pint --dirty --format agent` (si PHP modifié) puis un commit dédié, message en français préfixé `feat:`/`refactor:`/`test:`.
- Tests Pest colocalisés : `Foo.php` et `FooTest.php` côte à côte dans `app/Contexts/**` (suite phpunit couvre `app/Contexts`). `RefreshDatabase` déjà appliqué par `tests/Pest.php`.
- Lancer les tests : `php artisan test --compact --filter=NomDuTest`. Vitest : `bun run test:js`.
- Montants `decimal(12,2)` ; taux du prêt stocké en **fraction** (`0.024` = 2,4 %/an), `decimal(8,5)`.
- Les modèles suivent le style existant : `#[UseFactory]`, `$fillable` en liste, `casts()` méthode, PHPDoc `@property-read`.
- Les Datas sont des `readonly class` maison (pas de package), `JsonSerializable` uniquement si elles traversent Inertia.
- Aucune route d'écriture, aucun formulaire. Pas de nouveau provider : RealEstate n'a aucun binding (les actions lisent les modèles du contexte).
- Ne jamais utiliser `env()` hors config. Eager loading systématique (pas de N+1).

---

### Task 1: Suppression de PersonalAsset

**Files:**
- Delete: `app/Contexts/Portfolio/Models/PersonalAsset.php`
- Delete: `app/Contexts/Portfolio/Models/PersonalAssetTest.php`
- Delete: `app/Contexts/Portfolio/Enums/PersonalAssetType.php`
- Delete: `app/Contexts/Portfolio/Enums/PersonalAssetTypeTest.php`
- Delete: `app/Contexts/Portfolio/Factories/PersonalAssetFactory.php`
- Modify: `app/Contexts/Market/Models/InstrumentTest.php:7,21-28`
- Modify: `app/Contexts/Portfolio/PortfolioProvider.php:11`
- Modify: `database/migrations/2026_08_19_000000_create_assets_table.php:11`

**Interfaces:**
- Consumes: rien.
- Produces: la valeur `'real_estate'` dans la colonne `assets.type` n'est plus adossée à un enum ; le test d'isolation du scope `market` reste vert.

- [ ] **Step 1: Adapter le test d'isolation du scope**

Dans `app/Contexts/Market/Models/InstrumentTest.php`, supprimer l'import `use App\Contexts\Portfolio\Models\PersonalAsset;` (ligne 7), ajouter `use Illuminate\Support\Facades\DB;`, et remplacer le test :

```php
it('filters out non-market assets via the global scope', function () {
    Instrument::factory()->create();
    DB::table('assets')->insert([
        'name' => 'Appartement témoin',
        'type' => 'real_estate',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Instrument::query()->count())->toBe(1)
        ->and(Instrument::query()->get()->pluck('type'))
        ->each->toBeInstanceOf(InstrumentType::class);
});
```

- [ ] **Step 2: Vérifier que le test passe encore**

Run: `php artisan test --compact --filter=InstrumentTest`
Expected: PASS (le scope filtre par `InstrumentType::values()`, indépendant de l'enum supprimé).

- [ ] **Step 3: Supprimer les cinq fichiers**

```bash
git rm app/Contexts/Portfolio/Models/PersonalAsset.php \
       app/Contexts/Portfolio/Models/PersonalAssetTest.php \
       app/Contexts/Portfolio/Enums/PersonalAssetType.php \
       app/Contexts/Portfolio/Enums/PersonalAssetTypeTest.php \
       app/Contexts/Portfolio/Factories/PersonalAssetFactory.php
```

- [ ] **Step 4: Nettoyer les commentaires**

`app/Contexts/Portfolio/PortfolioProvider.php:11` : remplacer le commentaire par `// Pas de binding pour l'instant.`

`database/migrations/2026_08_19_000000_create_assets_table.php` : dans le PHPDoc de `up()`, remplacer la mention de `Portfolio\Models\PersonalAsset` par « et d'éventuels types hors marché, discriminés par la colonne `type` ».

- [ ] **Step 5: Suite complète + commit**

Run: `php artisan test --compact`
Expected: PASS, aucune référence restante (`grep -rn "PersonalAsset" app/ database/ tests/` ne rend rien).

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "refactor: supprime le vestige PersonalAsset"
```

---

### Task 2: Migrations, modèles et factories du contexte RealEstate

**Files:**
- Create: `database/migrations/2026_08_20_000000_create_properties_table.php`
- Create: `database/migrations/2026_08_20_000001_create_property_valuations_table.php`
- Create: `database/migrations/2026_08_20_000002_create_leases_table.php`
- Create: `database/migrations/2026_08_20_000003_create_rent_exceptions_table.php`
- Create: `database/migrations/2026_08_20_000004_create_loans_table.php`
- Create: `database/migrations/2026_08_20_000005_create_property_expenses_table.php`
- Create: `app/Contexts/RealEstate/Enums/ExpenseCategory.php` + `ExpenseCategoryTest.php`
- Create: `app/Contexts/RealEstate/Models/Property.php` + `PropertyTest.php`
- Create: `app/Contexts/RealEstate/Models/PropertyValuation.php`
- Create: `app/Contexts/RealEstate/Models/Lease.php`
- Create: `app/Contexts/RealEstate/Models/RentException.php`
- Create: `app/Contexts/RealEstate/Models/Loan.php`
- Create: `app/Contexts/RealEstate/Models/PropertyExpense.php`
- Create: `app/Contexts/RealEstate/Factories/PropertyFactory.php`, `PropertyValuationFactory.php`, `LeaseFactory.php`, `RentExceptionFactory.php`, `LoanFactory.php`, `PropertyExpenseFactory.php`

**Interfaces:**
- Consumes: table `users` existante.
- Produces: modèles Eloquent `Property` (relations `valuations()`, `leases()`, `loans()`, `expenses()`), `Lease` (relation `exceptions()`), factories utilisables par toutes les tâches suivantes. `ExpenseCategory` avec `getLabel(): string` et `values(): list<string>`.

- [ ] **Step 1: Écrire les six migrations**

`2026_08_20_000000_create_properties_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biens immobiliers détenus. `acquisition_fees` : notaire, agence, dossier — tout ce qui
     * s'ajoute au prix pour former le coût d'acquisition total.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->date('acquisition_date');
            $table->decimal('acquisition_price', 12, 2);
            $table->decimal('acquisition_fees', 12, 2)->default(0);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
```

`2026_08_20_000001_create_property_valuations_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Estimations datées de la valeur d'un bien, saisies à la main. La dernière fait foi. */
    public function up(): void
    {
        Schema::create('property_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('value', 12, 2);
            $table->timestamps();

            $table->unique(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_valuations');
    }
};
```

`2026_08_20_000002_create_leases_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baux à loyer constant. `end_date` nulle = bail en cours. Une révision de loyer clôt le
     * bail et en ouvre un autre ; la vacance locative est un trou entre deux baux, pas une ligne.
     */
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('monthly_rent', 8, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
```

`2026_08_20_000003_create_rent_exceptions_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Loyer effectif d'un mois qui dévie du bail : `0` = impayé total, un montant partiel est
     * possible. `month` porte le premier jour du mois. Absence de ligne = loyer plein.
     */
    public function up(): void
    {
        Schema::create('rent_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('amount_override', 8, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_exceptions');
    }
};
```

`2026_08_20_000004_create_loans_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prêts amortissables à la française. `annual_rate` en fraction (`0.024` = 2,4 %/an).
     * L'échéancier n'est jamais stocké : recalculé depuis ces paramètres.
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('principal', 12, 2);
            $table->decimal('annual_rate', 8, 5);
            $table->unsignedInteger('term_months');
            $table->date('start_date');
            $table->decimal('monthly_insurance', 8, 2)->default(0);
            $table->timestamps();

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
```

`2026_08_20_000005_create_property_expenses_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Charges ponctuelles datées (taxe foncière, copro, assurance, gestion, travaux, autre). */
    public function up(): void
    {
        Schema::create('property_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 12, 2);
            $table->string('category');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_expenses');
    }
};
```

- [ ] **Step 2: Écrire l'enum et son test**

`app/Contexts/RealEstate/Enums/ExpenseCategory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Enums;

enum ExpenseCategory: string
{
    case PropertyTax = 'property_tax';
    case CoOwnership = 'co_ownership';
    case Insurance = 'insurance';
    case Management = 'management';
    case Works = 'works';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::PropertyTax => 'Taxe foncière',
            self::CoOwnership => 'Copropriété',
            self::Insurance => 'Assurance',
            self::Management => 'Gestion',
            self::Works => 'Travaux',
            self::Other => 'Autre',
        };
    }
}
```

`ExpenseCategoryTest.php` :

```php
<?php

use App\Contexts\RealEstate\Enums\ExpenseCategory;

it('exposes its values as strings', function () {
    expect(ExpenseCategory::values())->toContain('property_tax', 'works')
        ->and(ExpenseCategory::values())->toHaveCount(6);
});

it('labels every case in French', function () {
    foreach (ExpenseCategory::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBeEmpty();
    }
});
```

- [ ] **Step 3: Écrire les six modèles**

`app/Contexts/RealEstate/Models/Property.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $user_id
 * @property-read string $name
 * @property-read ?string $address
 * @property-read Carbon $acquisition_date
 * @property-read string $acquisition_price
 * @property-read string $acquisition_fees
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
#[UseFactory(PropertyFactory::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'name', 'address', 'acquisition_date', 'acquisition_price', 'acquisition_fees'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_price' => 'decimal:2',
            'acquisition_fees' => 'decimal:2',
        ];
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(PropertyValuation::class)->orderBy('date');
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class)->orderBy('start_date');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(PropertyExpense::class)->orderBy('date');
    }
}
```

`PropertyValuation.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\PropertyValuationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read Carbon $date
 * @property-read string $value
 */
#[UseFactory(PropertyValuationFactory::class)]
class PropertyValuation extends Model
{
    /** @use HasFactory<PropertyValuationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'date', 'value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
```

`Lease.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\LeaseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read string $monthly_rent
 * @property-read Carbon $start_date
 * @property-read ?Carbon $end_date
 */
#[UseFactory(LeaseFactory::class)]
class Lease extends Model
{
    /** @use HasFactory<LeaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'monthly_rent', 'start_date', 'end_date'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_rent' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(RentException::class)->orderBy('month');
    }
}
```

`RentException.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\RentExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $lease_id
 * @property-read Carbon $month
 * @property-read string $amount_override
 * @property-read ?string $note
 */
#[UseFactory(RentExceptionFactory::class)]
class RentException extends Model
{
    /** @use HasFactory<RentExceptionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['lease_id', 'month', 'amount_override', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'date',
            'amount_override' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
```

`Loan.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read string $principal
 * @property-read string $annual_rate fraction : `0.024` = 2,4 %/an
 * @property-read int $term_months
 * @property-read Carbon $start_date
 * @property-read string $monthly_insurance
 */
#[UseFactory(LoanFactory::class)]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'principal', 'annual_rate', 'term_months', 'start_date', 'monthly_insurance'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'annual_rate' => 'decimal:5',
            'term_months' => 'integer',
            'start_date' => 'date',
            'monthly_insurance' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
```

`PropertyExpense.php` :

```php
<?php

namespace App\Contexts\RealEstate\Models;

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Factories\PropertyExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property-read int $id
 * @property-read int $property_id
 * @property-read Carbon $date
 * @property-read string $amount
 * @property-read ExpenseCategory $category
 * @property-read ?string $label
 */
#[UseFactory(PropertyExpenseFactory::class)]
class PropertyExpense extends Model
{
    /** @use HasFactory<PropertyExpenseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['property_id', 'date', 'amount', 'category', 'label'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'category' => ExpenseCategory::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
```

- [ ] **Step 4: Écrire les six factories**

`PropertyFactory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->streetName(),
            'address' => fake()->address(),
            'acquisition_date' => fake()->date(),
            'acquisition_price' => fake()->randomFloat(2, 80000, 300000),
            'acquisition_fees' => fake()->randomFloat(2, 5000, 25000),
        ];
    }
}
```

`PropertyValuationFactory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyValuation> */
class PropertyValuationFactory extends Factory
{
    protected $model = PropertyValuation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'date' => fake()->date(),
            'value' => fake()->randomFloat(2, 80000, 350000),
        ];
    }
}
```

`LeaseFactory.php` (état `ongoing()` inclus) :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lease> */
class LeaseFactory extends Factory
{
    protected $model = Lease::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'monthly_rent' => fake()->randomFloat(2, 400, 1200),
            'start_date' => fake()->date(),
            'end_date' => null,
        ];
    }

    /** Bail en cours, démarré il y a un an. */
    public function ongoing(): static
    {
        return $this->state(fn (): array => [
            'start_date' => now()->subYear()->startOfMonth()->toDateString(),
            'end_date' => null,
        ]);
    }
}
```

`RentExceptionFactory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RentException> */
class RentExceptionFactory extends Factory
{
    protected $model = RentException::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'lease_id' => Lease::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'amount_override' => 0,
            'note' => null,
        ];
    }
}
```

`LoanFactory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Loan> */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'principal' => fake()->randomFloat(2, 50000, 250000),
            'annual_rate' => fake()->randomFloat(5, 0.01, 0.045),
            'term_months' => fake()->randomElement([180, 240, 300]),
            'start_date' => fake()->date(),
            'monthly_insurance' => fake()->randomFloat(2, 10, 60),
        ];
    }
}
```

`PropertyExpenseFactory.php` :

```php
<?php

namespace App\Contexts\RealEstate\Factories;

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PropertyExpense> */
class PropertyExpenseFactory extends Factory
{
    protected $model = PropertyExpense::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'date' => fake()->date(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'category' => fake()->randomElement(ExpenseCategory::cases()),
            'label' => null,
        ];
    }
}
```

- [ ] **Step 5: Test des relations et casts sur Property**

`app/Contexts/RealEstate/Models/PropertyTest.php` :

```php
<?php

use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;

it('has valuations, leases, loans and expenses relations', function () {
    $property = Property::factory()->create();
    PropertyValuation::factory()->create(['property_id' => $property->id]);
    $lease = Lease::factory()->create(['property_id' => $property->id]);
    RentException::factory()->create(['lease_id' => $lease->id]);
    Loan::factory()->create(['property_id' => $property->id]);
    PropertyExpense::factory()->create(['property_id' => $property->id]);

    expect($property->valuations)->toHaveCount(1)
        ->and($property->leases)->toHaveCount(1)
        ->and($property->leases->first()->exceptions)->toHaveCount(1)
        ->and($property->loans)->toHaveCount(1)
        ->and($property->expenses)->toHaveCount(1);
});

it('casts the expense category to an enum', function () {
    $expense = PropertyExpense::factory()->create(['category' => ExpenseCategory::PropertyTax]);

    expect($expense->refresh()->category)->toBe(ExpenseCategory::PropertyTax);
});

it('orders valuations by date so the last one is the current value', function () {
    $property = Property::factory()->create();
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-06-01', 'value' => 120000]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2025-01-01', 'value' => 100000]);

    expect((float) $property->valuations->last()->value)->toBe(120000.0);
});
```

- [ ] **Step 6: Migrer et tester**

Run: `php artisan migrate && php artisan test --compact --filter="PropertyTest|ExpenseCategoryTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: modèles et migrations du contexte RealEstate"
```

---

### Task 3: LoanAmortizationCalculator

**Files:**
- Create: `app/Contexts/RealEstate/Datas/AmortizationLineData.php`
- Create: `app/Contexts/RealEstate/Services/LoanAmortizationCalculator.php`
- Test: `app/Contexts/RealEstate/Services/LoanAmortizationCalculatorTest.php`

**Interfaces:**
- Consumes: rien (calculateur pur).
- Produces:
  - `AmortizationLineData { public string $month /* 'Y-m-d', 1er du mois d'échéance */, public float $payment, public float $interest, public float $principal, public float $insurance, public float $remaining }`, `JsonSerializable` (part au front dans le tableau d'amortissement).
  - `LoanAmortizationCalculator::schedule(float $principal, float $annualRate, int $termMonths, Carbon $startDate, float $monthlyInsurance = 0.0): list<AmortizationLineData>` — première échéance un mois après `$startDate`.
  - `LoanAmortizationCalculator::remainingAt(array $schedule, Carbon $date): float` — capital restant dû à la date : `principal + intérêts` non encore échus ne comptent pas ; avant la première échéance, rend le principal ; après la dernière, `0.0`.

- [ ] **Step 1: Écrire les tests (rouges)**

`LoanAmortizationCalculatorTest.php` — le cas 1 000 € / 12 %/an / 2 mois se vérifie à la main : r = 0,01 ; M = 10 ÷ (1 − 1,01⁻²) = 507,51 ; mois 1 : intérêts 10,00, capital 497,51, restant 502,49 ; mois 2 : intérêts 5,02, capital 502,49, mensualité 507,51, restant 0.

```php
<?php

use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new LoanAmortizationCalculator;
});

it('builds a French amortization schedule with constant payments', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'));

    expect($schedule)->toHaveCount(2)
        ->and($schedule[0]->month)->toBe('2026-02-01')
        ->and($schedule[0]->payment)->toBe(507.51)
        ->and($schedule[0]->interest)->toBe(10.0)
        ->and($schedule[0]->principal)->toBe(497.51)
        ->and($schedule[0]->remaining)->toBe(502.49)
        ->and($schedule[1]->interest)->toBe(5.02)
        ->and($schedule[1]->principal)->toBe(502.49)
        ->and($schedule[1]->payment)->toBe(507.51)
        ->and($schedule[1]->remaining)->toBe(0.0);
});

it('sums repaid principal back to the borrowed amount', function () {
    $schedule = $this->calculator->schedule(150000.0, 0.024, 240, Carbon::parse('2026-01-01'));

    $repaid = array_sum(array_map(fn ($line): float => $line->principal, $schedule));

    expect($schedule)->toHaveCount(240)
        ->and(round($repaid, 2))->toBe(150000.0)
        ->and($schedule[239]->remaining)->toBe(0.0);
});

it('handles a zero interest rate as straight-line repayment', function () {
    $schedule = $this->calculator->schedule(1200.0, 0.0, 12, Carbon::parse('2026-01-01'));

    expect($schedule[0]->payment)->toBe(100.0)
        ->and($schedule[0]->interest)->toBe(0.0)
        ->and($schedule[11]->remaining)->toBe(0.0);
});

it('adds the insurance on top of the payment', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'), 20.0);

    expect($schedule[0]->insurance)->toBe(20.0)
        ->and($schedule[0]->payment)->toBe(527.51);
});

it('reads the remaining principal at any date', function () {
    $schedule = $this->calculator->schedule(1000.0, 0.12, 2, Carbon::parse('2026-01-15'));

    expect($this->calculator->remainingAt($schedule, Carbon::parse('2026-01-20')))->toBe(1000.0)
        ->and($this->calculator->remainingAt($schedule, Carbon::parse('2026-02-10')))->toBe(502.49)
        ->and($this->calculator->remainingAt($schedule, Carbon::parse('2026-12-31')))->toBe(0.0);
});

it('remaining before any line needs the borrowed principal, so an empty schedule yields zero', function () {
    expect($this->calculator->remainingAt([], Carbon::parse('2026-01-01')))->toBe(0.0);
});
```

Note : `remainingAt` sur planning vide rend `0.0` (aucun prêt = rien à devoir). Pour « avant la première échéance = principal », la ligne 0 n'existe pas : le calculateur infère le principal de `remaining + principal` de la première ligne.

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=LoanAmortizationCalculatorTest`
Expected: FAIL — classe inexistante.

- [ ] **Step 3: Implémenter**

`AmortizationLineData.php` :

```php
<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Une échéance de prêt. `month` porte le premier jour du mois d'échéance. */
readonly class AmortizationLineData implements JsonSerializable
{
    public function __construct(
        public string $month,
        public float $payment,
        public float $interest,
        public float $principal,
        public float $insurance,
        public float $remaining,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'month' => $this->month,
            'payment' => $this->payment,
            'interest' => $this->interest,
            'principal' => $this->principal,
            'insurance' => $this->insurance,
            'remaining' => $this->remaining,
        ];
    }
}
```

`LoanAmortizationCalculator.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use Illuminate\Support\Carbon;

/**
 * Échéancier français à mensualité constante : M = P·r ÷ (1 − (1 + r)⁻ⁿ), r = taux annuel ÷ 12.
 * Chaque ligne arrondit à deux décimales ; la dernière solde le capital exactement, si bien que
 * sa mensualité peut dévier de quelques centimes.
 */
class LoanAmortizationCalculator
{
    /** @return list<AmortizationLineData> */
    public function schedule(
        float $principal,
        float $annualRate,
        int $termMonths,
        Carbon $startDate,
        float $monthlyInsurance = 0.0,
    ): array {
        $monthlyRate = $annualRate / 12;

        $basePayment = $monthlyRate === 0.0
            ? round($principal / $termMonths, 2)
            : round($principal * $monthlyRate / (1 - (1 + $monthlyRate) ** -$termMonths), 2);

        $lines = [];
        $remaining = $principal;

        for ($index = 1; $index <= $termMonths; $index++) {
            $interest = round($remaining * $monthlyRate, 2);

            $repaid = $index === $termMonths
                ? round($remaining, 2)
                : round($basePayment - $interest, 2);

            $remaining = round($remaining - $repaid, 2);

            $lines[] = new AmortizationLineData(
                month: $startDate->copy()->addMonthsNoOverflow($index)->startOfMonth()->toDateString(),
                payment: round($repaid + $interest + $monthlyInsurance, 2),
                interest: $interest,
                principal: $repaid,
                insurance: $monthlyInsurance,
                remaining: $remaining,
            );
        }

        return $lines;
    }

    /**
     * Capital restant dû à une date : celui de la dernière échéance passée, le total emprunté
     * avant la première, zéro après la dernière.
     *
     * @param  list<AmortizationLineData>  $schedule
     */
    public function remainingAt(array $schedule, Carbon $date): float
    {
        if ($schedule === []) {
            return 0.0;
        }

        $remaining = round($schedule[0]->remaining + $schedule[0]->principal, 2);

        foreach ($schedule as $line) {
            if ($line->month > $date->toDateString()) {
                break;
            }

            $remaining = $line->remaining;
        }

        return $remaining;
    }
}
```

- [ ] **Step 4: Vérifier le vert**

Run: `php artisan test --compact --filter=LoanAmortizationCalculatorTest`
Expected: PASS. Si le test « 507.51 » diverge d'un centime, la faute est dans l'arrondi de `basePayment` — ne pas retoucher les valeurs attendues, elles sont vérifiées à la main.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: échéancier d'amortissement du prêt immobilier"
```

---

### Task 4: RentScheduleCalculator

**Files:**
- Create: `app/Contexts/RealEstate/Datas/LeaseTermData.php`
- Create: `app/Contexts/RealEstate/Datas/RentExceptionData.php`
- Create: `app/Contexts/RealEstate/Datas/RentMonthData.php`
- Create: `app/Contexts/RealEstate/Services/RentScheduleCalculator.php`
- Test: `app/Contexts/RealEstate/Services/RentScheduleCalculatorTest.php`

**Interfaces:**
- Consumes: rien (calculateur pur).
- Produces:
  - `LeaseTermData { public string $start /* 'Y-m-d' */, public ?string $end, public float $monthlyRent }`
  - `RentExceptionData { public string $month /* 'Y-m-d', 1er du mois */, public float $amountOverride }`
  - `RentMonthData { public string $month /* 'Y-m-d', 1er du mois */, public float $expected, public float $effective }`, `JsonSerializable` — `expected = 0` marque la vacance, `effective < expected` un impayé.
  - `RentScheduleCalculator::months(array $leases, array $exceptions, Carbon $until): list<RentMonthData>` — un élément par mois, du mois du premier `start` au mois de `$until` inclus ; liste vide si aucun bail.
  - `RentScheduleCalculator::projectedAnnual(array $leases, Carbon $today): float` — loyer du bail couvrant `$today` × 12, `0.0` sinon.

- [ ] **Step 1: Écrire les tests (rouges)**

`RentScheduleCalculatorTest.php` :

```php
<?php

use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new RentScheduleCalculator;
});

it('yields one line per month from the first lease, vacancy included', function () {
    $leases = [
        new LeaseTermData(start: '2026-01-01', end: '2026-03-31', monthlyRent: 500.0),
        new LeaseTermData(start: '2026-05-01', end: null, monthlyRent: 600.0),
    ];
    $exceptions = [new RentExceptionData(month: '2026-02-01', amountOverride: 250.0)];

    $months = $this->calculator->months($leases, $exceptions, Carbon::parse('2026-06-15'));

    expect(array_map(fn ($m): array => [$m->month, $m->expected, $m->effective], $months))->toBe([
        ['2026-01-01', 500.0, 500.0],
        ['2026-02-01', 500.0, 250.0],
        ['2026-03-01', 500.0, 500.0],
        ['2026-04-01', 0.0, 0.0],
        ['2026-05-01', 600.0, 600.0],
        ['2026-06-01', 600.0, 600.0],
    ]);
});

it('yields nothing without any lease', function () {
    expect($this->calculator->months([], [], Carbon::parse('2026-06-15')))->toBe([]);
});

it('treats a lease ending mid-month as covering that month', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: '2026-02-10', monthlyRent: 500.0)];

    $months = $this->calculator->months($leases, [], Carbon::parse('2026-02-20'));

    expect($months[1]->expected)->toBe(500.0);
});

it('projects the active lease over twelve months', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: null, monthlyRent: 600.0)];

    expect($this->calculator->projectedAnnual($leases, Carbon::parse('2026-06-15')))->toBe(7200.0);
});

it('projects zero without an active lease', function () {
    $leases = [new LeaseTermData(start: '2026-01-01', end: '2026-03-31', monthlyRent: 600.0)];

    expect($this->calculator->projectedAnnual($leases, Carbon::parse('2026-06-15')))->toBe(0.0);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=RentScheduleCalculatorTest`
Expected: FAIL — classes inexistantes.

- [ ] **Step 3: Implémenter**

Les trois Datas, sur le modèle de `AmortizationLineData` (constructeur promu readonly ; seul `RentMonthData` implémente `JsonSerializable`, avec les clés `month`, `expected`, `effective`).

`RentScheduleCalculator.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use Illuminate\Support\Carbon;

/**
 * Loyers mois par mois depuis les baux : mois couvert = loyer plein sauf exception, mois sans
 * bail = vacance à zéro. Un bail couvre chaque mois que son intervalle touche, même
 * partiellement — un départ le 10 laisse le loyer du mois dû.
 */
class RentScheduleCalculator
{
    /**
     * @param  list<LeaseTermData>  $leases
     * @param  list<RentExceptionData>  $exceptions
     * @return list<RentMonthData>
     */
    public function months(array $leases, array $exceptions, Carbon $until): array
    {
        if ($leases === []) {
            return [];
        }

        $overrides = [];
        foreach ($exceptions as $exception) {
            $overrides[$exception->month] = $exception->amountOverride;
        }

        $starts = array_map(fn (LeaseTermData $lease): string => $lease->start, $leases);
        $cursor = Carbon::parse(min($starts))->startOfMonth();
        $lastMonth = $until->copy()->startOfMonth();

        $months = [];

        while ($cursor <= $lastMonth) {
            $key = $cursor->toDateString();
            $expected = $this->rentFor($leases, $cursor);
            $effective = $expected > 0 ? ($overrides[$key] ?? $expected) : 0.0;

            $months[] = new RentMonthData(month: $key, expected: $expected, effective: $effective);

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /** @param list<LeaseTermData> $leases */
    public function projectedAnnual(array $leases, Carbon $today): float
    {
        $active = $this->leaseCovering($leases, $today->copy()->startOfMonth());

        return $active === null ? 0.0 : round($active->monthlyRent * 12, 2);
    }

    /** @param list<LeaseTermData> $leases */
    private function rentFor(array $leases, Carbon $month): float
    {
        $lease = $this->leaseCovering($leases, $month);

        return $lease?->monthlyRent ?? 0.0;
    }

    /** Bail dont l'intervalle touche le mois donné (comparé au premier jour du mois). */
    private function leaseCovering(array $leases, Carbon $month): ?LeaseTermData
    {
        foreach ($leases as $lease) {
            $startMonth = Carbon::parse($lease->start)->startOfMonth();
            $endMonth = $lease->end === null ? null : Carbon::parse($lease->end)->startOfMonth();

            if ($startMonth <= $month && ($endMonth === null || $month <= $endMonth)) {
                return $lease;
            }
        }

        return null;
    }
}
```

- [ ] **Step 4: Vérifier le vert**

Run: `php artisan test --compact --filter=RentScheduleCalculatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: calendrier des loyers depuis les baux et leurs exceptions"
```

---

### Task 5: PropertyMetricsCalculator

**Files:**
- Create: `app/Contexts/RealEstate/Datas/PropertyFinancialsData.php`
- Create: `app/Contexts/RealEstate/Datas/PropertyMetricsData.php`
- Create: `app/Contexts/RealEstate/Services/PropertyMetricsCalculator.php`
- Test: `app/Contexts/RealEstate/Services/PropertyMetricsCalculatorTest.php`

**Interfaces:**
- Consumes: rien (calculateur pur).
- Produces:
  - `PropertyFinancialsData { public float $acquisitionPrice, public float $acquisitionFees, public float $currentMonthlyRent, public float $rents12m, public float $expenses12m, public float $loanPayments12m, public float $borrowedPrincipal, public float $remainingPrincipal, public float $currentValue }` — `currentValue = 0.0` si aucune estimation, `borrowedPrincipal = 0.0` sans prêt.
  - `PropertyMetricsData { public float $grossYield, public float $netYield, public float $annualCashFlow, public ?float $cashOnCash, public ?float $ltv }`, `JsonSerializable` — ratios en fraction arrondis à 4 décimales, `cashOnCash` nul si apport ≤ 0, `ltv` nul si `currentValue ≤ 0`.
  - `PropertyMetricsCalculator::metrics(PropertyFinancialsData $financials): PropertyMetricsData`.

- [ ] **Step 1: Écrire les tests (rouges)**

Cas vérifié à la main : prix 100 000 + frais 10 000 ; loyer courant 600 ; loyers 12 m 6 600 ; charges 1 200 ; mensualités 4 800 ; emprunté 90 000 ; restant 85 000 ; valeur 120 000. Brut = 7 200/110 000 = 0,0655 ; net = 5 400/110 000 = 0,0491 ; cash-flow = 600 ; apport = 20 000 → CoC = 0,03 ; LTV = 85 000/120 000 = 0,7083.

```php
<?php

use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Services\PropertyMetricsCalculator;

beforeEach(function () {
    $this->calculator = new PropertyMetricsCalculator;
});

it('computes every pre-tax metric', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 10000.0,
        currentMonthlyRent: 600.0,
        rents12m: 6600.0,
        expenses12m: 1200.0,
        loanPayments12m: 4800.0,
        borrowedPrincipal: 90000.0,
        remainingPrincipal: 85000.0,
        currentValue: 120000.0,
    ));

    expect($metrics->grossYield)->toBe(0.0655)
        ->and($metrics->netYield)->toBe(0.0491)
        ->and($metrics->annualCashFlow)->toBe(600.0)
        ->and($metrics->cashOnCash)->toBe(0.03)
        ->and($metrics->ltv)->toBe(0.7083);
});

it('leaves cash-on-cash out when there is no down payment', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 0.0,
        currentMonthlyRent: 600.0,
        rents12m: 7200.0,
        expenses12m: 0.0,
        loanPayments12m: 6000.0,
        borrowedPrincipal: 100000.0,
        remainingPrincipal: 95000.0,
        currentValue: 100000.0,
    ));

    expect($metrics->cashOnCash)->toBeNull();
});

it('leaves ltv out without a current value', function () {
    $metrics = $this->calculator->metrics(new PropertyFinancialsData(
        acquisitionPrice: 100000.0,
        acquisitionFees: 10000.0,
        currentMonthlyRent: 600.0,
        rents12m: 6600.0,
        expenses12m: 1200.0,
        loanPayments12m: 4800.0,
        borrowedPrincipal: 90000.0,
        remainingPrincipal: 85000.0,
        currentValue: 0.0,
    ));

    expect($metrics->ltv)->toBeNull();
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=PropertyMetricsCalculatorTest`
Expected: FAIL.

- [ ] **Step 3: Implémenter**

`PropertyFinancialsData.php` : readonly, promotion de constructeur, pas de `JsonSerializable` (interne). `PropertyMetricsData.php` : readonly + `JsonSerializable` (clés `grossYield`, `netYield`, `annualCashFlow`, `cashOnCash`, `ltv`).

`PropertyMetricsCalculator.php` :

```php
<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Datas\PropertyMetricsData;

/** Indicateurs de rentabilité, tous avant impôt. Ratios en fraction, arrondis à 4 décimales. */
class PropertyMetricsCalculator
{
    public function metrics(PropertyFinancialsData $financials): PropertyMetricsData
    {
        $totalCost = $financials->acquisitionPrice + $financials->acquisitionFees;
        $downPayment = $totalCost - $financials->borrowedPrincipal;
        $annualCashFlow = round($financials->rents12m - $financials->expenses12m - $financials->loanPayments12m, 2);

        return new PropertyMetricsData(
            grossYield: round($financials->currentMonthlyRent * 12 / $totalCost, 4),
            netYield: round(($financials->rents12m - $financials->expenses12m) / $totalCost, 4),
            annualCashFlow: $annualCashFlow,
            cashOnCash: $downPayment > 0 ? round($annualCashFlow / $downPayment, 4) : null,
            ltv: $financials->currentValue > 0
                ? round($financials->remainingPrincipal / $financials->currentValue, 4)
                : null,
        );
    }
}
```

- [ ] **Step 4: Vérifier le vert**

Run: `php artisan test --compact --filter=PropertyMetricsCalculatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: indicateurs de rentabilité du bien locatif"
```

---

### Task 6: GetRealEstateOverview (carte dashboard, côté serveur)

**Files:**
- Create: `app/Contexts/RealEstate/Datas/PropertyOverviewData.php`
- Create: `app/Contexts/RealEstate/Datas/RealEstateOverviewData.php`
- Create: `app/Contexts/RealEstate/Actions/GetRealEstateOverview.php`
- Test: `app/Contexts/RealEstate/Actions/GetRealEstateOverviewTest.php`
- Create: `app/Contexts/RealEstate/Support/PropertyFinancialsAssembler.php`
- Test: `app/Contexts/RealEstate/Support/PropertyFinancialsAssemblerTest.php`

**Interfaces:**
- Consumes: modèles Task 2, calculateurs Tasks 3-5 (`schedule`, `remainingAt`, `months`, `projectedAnnual`, `metrics`).
- Produces:
  - `PropertyOverviewData { public int $id, public string $name, public float $currentValue, public float $remainingPrincipal, public float $netWorth, public float $monthlyCashFlow }`, `JsonSerializable` (clés homonymes).
  - `RealEstateOverviewData { public array $properties /* list<PropertyOverviewData> */, public float $totalValue, public float $totalRemaining, public float $totalNetWorth }`, `JsonSerializable`, + `public static empty(): self`.
  - `GetRealEstateOverview::__invoke(int $userId): RealEstateOverviewData`.
  - `PropertyFinancialsAssembler::financialsFor(Property $property, Carbon $today): PropertyFinancialsData` — charge `leases.exceptions`, `loans`, `expenses`, `valuations` (le bien doit être passé avec ces relations chargées), assemble les fenêtres 12 mois glissants. Réutilisé par Task 7.

- [ ] **Step 1: Écrire le test de l'assembleur (rouge)**

`PropertyFinancialsAssemblerTest.php` :

```php
<?php

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

it('assembles the twelve month sliding windows', function () {
    Carbon::setTestNow('2026-08-20');

    $property = Property::factory()->create([
        'acquisition_price' => 100000,
        'acquisition_fees' => 10000,
    ]);
    Lease::factory()->create([
        'property_id' => $property->id,
        'monthly_rent' => 600,
        'start_date' => '2025-01-01',
        'end_date' => null,
    ]);
    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 90000,
        'annual_rate' => 0.0,
        'term_months' => 300,
        'start_date' => '2025-01-01',
        'monthly_insurance' => 0,
    ]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2026-03-10', 'amount' => 900]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2024-01-10', 'amount' => 500]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $financials = app(PropertyFinancialsAssembler::class)->financialsFor(
        $property->load(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::now(),
    );

    // 12 mois glissants = 2025-09 à 2026-08 : 12 loyers de 600, la charge 2024 est hors fenêtre.
    // Prêt à taux zéro : 90000/300 = 300 par mois, 12 × 300 = 3600.
    expect($financials->acquisitionPrice)->toBe(100000.0)
        ->and($financials->acquisitionFees)->toBe(10000.0)
        ->and($financials->currentMonthlyRent)->toBe(600.0)
        ->and($financials->rents12m)->toBe(7200.0)
        ->and($financials->expenses12m)->toBe(900.0)
        ->and($financials->loanPayments12m)->toBe(3600.0)
        ->and($financials->borrowedPrincipal)->toBe(90000.0)
        ->and($financials->currentValue)->toBe(120000.0)
        ->and($financials->remainingPrincipal)->toBeLessThan(90000.0);

    Carbon::setTestNow();
});
```

- [ ] **Step 2: Vérifier l'échec puis implémenter l'assembleur**

Run: `php artisan test --compact --filter=PropertyFinancialsAssemblerTest` → FAIL.

`app/Contexts/RealEstate/Support/PropertyFinancialsAssembler.php` :

```php
<?php

namespace App\Contexts\RealEstate\Support;

use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\PropertyFinancialsData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\RentException;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use Illuminate\Support\Carbon;

/**
 * Traduit un bien (relations chargées) en données financières sur douze mois glissants. Les
 * fenêtres se comptent en mois d'échéance : de `today - 11 mois` (début de mois) à `today`.
 */
class PropertyFinancialsAssembler
{
    public function __construct(
        private RentScheduleCalculator $rents,
        private LoanAmortizationCalculator $amortization,
    ) {}

    public function financialsFor(Property $property, Carbon $today): PropertyFinancialsData
    {
        $windowStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();
        $todayKey = $today->toDateString();

        $leases = $this->leaseTerms($property);
        $months = $this->rents->months($leases, $this->exceptions($property), $today);

        $rents12m = 0.0;
        foreach ($months as $month) {
            if ($month->month >= $windowStart) {
                $rents12m += $month->effective;
            }
        }

        $expenses12m = $property->expenses
            ->filter(fn (PropertyExpense $expense): bool => $expense->date->toDateString() >= $windowStart
                && $expense->date->toDateString() <= $todayKey)
            ->sum(fn (PropertyExpense $expense): float => (float) $expense->amount);

        $loanPayments12m = 0.0;
        $remaining = 0.0;
        $borrowed = 0.0;

        foreach ($property->loans as $loan) {
            $schedule = $this->scheduleFor($loan);
            $borrowed += (float) $loan->principal;
            $remaining += $this->amortization->remainingAt($schedule, $today);

            foreach ($schedule as $line) {
                if ($line->month >= $windowStart && $line->month <= $todayKey) {
                    $loanPayments12m += $line->payment;
                }
            }
        }

        $currentMonthlyRent = $this->rents->projectedAnnual($leases, $today) / 12;

        return new PropertyFinancialsData(
            acquisitionPrice: (float) $property->acquisition_price,
            acquisitionFees: (float) $property->acquisition_fees,
            currentMonthlyRent: round($currentMonthlyRent, 2),
            rents12m: round($rents12m, 2),
            expenses12m: round((float) $expenses12m, 2),
            loanPayments12m: round($loanPayments12m, 2),
            borrowedPrincipal: round($borrowed, 2),
            remainingPrincipal: round($remaining, 2),
            currentValue: (float) ($property->valuations->last()?->value ?? 0.0),
        );
    }

    /** @return list<\App\Contexts\RealEstate\Datas\AmortizationLineData> */
    public function scheduleFor(Loan $loan): array
    {
        return $this->amortization->schedule(
            (float) $loan->principal,
            (float) $loan->annual_rate,
            $loan->term_months,
            $loan->start_date->copy(),
            (float) $loan->monthly_insurance,
        );
    }

    /** @return list<LeaseTermData> */
    public function leaseTerms(Property $property): array
    {
        return $property->leases
            ->map(fn (Lease $lease): LeaseTermData => new LeaseTermData(
                start: $lease->start_date->toDateString(),
                end: $lease->end_date?->toDateString(),
                monthlyRent: (float) $lease->monthly_rent,
            ))
            ->values()
            ->all();
    }

    /** @return list<RentExceptionData> */
    public function exceptions(Property $property): array
    {
        return $property->leases
            ->flatMap(fn (Lease $lease) => $lease->exceptions)
            ->map(fn (RentException $exception): RentExceptionData => new RentExceptionData(
                month: $exception->month->toDateString(),
                amountOverride: (float) $exception->amount_override,
            ))
            ->values()
            ->all();
    }
}
```

Run: `php artisan test --compact --filter=PropertyFinancialsAssemblerTest` → PASS.

- [ ] **Step 3: Écrire le test de l'action (rouge)**

`GetRealEstateOverviewTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('sums net worth over the user properties', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $overview = app(GetRealEstateOverview::class)($user->id);

    // 19 échéances passées (2025-02 à 2026-08) × 300 = 5700 remboursés, restant 84300.
    expect($overview->properties)->toHaveCount(1)
        ->and($overview->properties[0]->name)->toBe('T2 Lyon 7e')
        ->and($overview->properties[0]->currentValue)->toBe(120000.0)
        ->and($overview->properties[0]->remainingPrincipal)->toBe(84300.0)
        ->and($overview->properties[0]->netWorth)->toBe(35700.0)
        ->and($overview->totalNetWorth)->toBe(35700.0);

    Carbon::setTestNow();
});

it('ignores properties of other users and yields an empty overview', function () {
    $user = User::factory()->create();
    Property::factory()->create();

    $overview = app(GetRealEstateOverview::class)($user->id);

    expect($overview->properties)->toBe([])
        ->and($overview->totalNetWorth)->toBe(0.0);
});
```

- [ ] **Step 4: Implémenter Datas + action**

`PropertyOverviewData.php` et `RealEstateOverviewData.php` : readonly + `JsonSerializable`, clés identiques aux propriétés ; `RealEstateOverviewData::empty()` rend `new self([], 0.0, 0.0, 0.0)`.

`GetRealEstateOverview.php` :

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\PropertyOverviewData;
use App\Contexts\RealEstate\Datas\RealEstateOverviewData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/** Carte immobilière du tableau de bord : patrimoine net par bien et totaux. */
class GetRealEstateOverview
{
    public function __construct(private PropertyFinancialsAssembler $assembler) {}

    public function __invoke(int $userId): RealEstateOverviewData
    {
        $properties = Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->orderBy('name')
            ->get();

        $today = Carbon::now();
        $lines = [];

        foreach ($properties as $property) {
            $financials = $this->assembler->financialsFor($property, $today);

            $lines[] = new PropertyOverviewData(
                id: $property->id,
                name: $property->name,
                currentValue: $financials->currentValue,
                remainingPrincipal: $financials->remainingPrincipal,
                netWorth: round($financials->currentValue - $financials->remainingPrincipal, 2),
                monthlyCashFlow: round(
                    ($financials->rents12m - $financials->expenses12m - $financials->loanPayments12m) / 12,
                    2,
                ),
            );
        }

        return new RealEstateOverviewData(
            properties: $lines,
            totalValue: round(array_sum(array_map(fn ($l): float => $l->currentValue, $lines)), 2),
            totalRemaining: round(array_sum(array_map(fn ($l): float => $l->remainingPrincipal, $lines)), 2),
            totalNetWorth: round(array_sum(array_map(fn ($l): float => $l->netWorth, $lines)), 2),
        );
    }
}
```

- [ ] **Step 5: Vérifier le vert + commit**

Run: `php artisan test --compact --filter="GetRealEstateOverviewTest|PropertyFinancialsAssemblerTest"`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: vue d'ensemble immobilière pour le tableau de bord"
```

---

### Task 7: GetPropertyDetail + GetLoanSchedule

**Files:**
- Create: `app/Contexts/RealEstate/Datas/MonthlyCashFlowData.php`
- Create: `app/Contexts/RealEstate/Datas/ExpenseYearData.php`
- Create: `app/Contexts/RealEstate/Datas/LoanSummaryData.php`
- Create: `app/Contexts/RealEstate/Datas/PropertyDetailData.php`
- Create: `app/Contexts/RealEstate/Actions/GetPropertyDetail.php`
- Create: `app/Contexts/RealEstate/Actions/GetLoanSchedule.php`
- Test: `app/Contexts/RealEstate/Actions/GetPropertyDetailTest.php`
- Test: `app/Contexts/RealEstate/Actions/GetLoanScheduleTest.php`

**Interfaces:**
- Consumes: Tasks 2-6 (`PropertyFinancialsAssembler::financialsFor/scheduleFor/leaseTerms/exceptions`, `PropertyMetricsCalculator::metrics`, `RentScheduleCalculator::months`).
- Produces (tous `JsonSerializable`, clés homonymes) :
  - `MonthlyCashFlowData { public string $month, public float $rents, public float $expenses, public float $loanPayment, public float $net }`
  - `ExpenseYearData { public int $year, public array $byCategory /* array<string, float>, clé = valeur d'`ExpenseCategory` */, public float $total }`
  - `LoanSummaryData { public float $principal, public float $annualRate, public int $termMonths, public string $startDate, public float $monthlyInsurance, public float $monthlyPayment, public float $remainingPrincipal, public float $totalCost /* intérêts + assurance sur la durée */ }`
  - `PropertyDetailData { public int $id, public string $name, public ?string $address, public string $acquisitionDate, public float $acquisitionPrice, public float $acquisitionFees, public float $currentValue, public float $netWorth, public PropertyMetricsData $metrics, public array $monthlyCashFlows /* 12 derniers mois, list<MonthlyCashFlowData> */, public array $rentHistory /* list<RentMonthData>, plus récent d'abord */, public array $expenseYears /* list<ExpenseYearData>, plus récent d'abord */, public ?LoanSummaryData $loan }`
  - `GetPropertyDetail::__invoke(int $userId, int $propertyId): ?PropertyDetailData` — `null` si le bien n'existe pas ou appartient à un autre utilisateur.
  - `GetLoanSchedule::__invoke(int $userId, int $propertyId): array /* list<AmortizationLineData> */` — vide sans prêt ; premier prêt du bien.

- [ ] **Step 1: Écrire les tests (rouges)**

`GetPropertyDetailTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetPropertyDetail;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Support\Carbon;

it('assembles the whole property sheet', function () {
    Carbon::setTestNow('2026-08-20');

    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'name' => 'T2 Lyon 7e',
        'acquisition_price' => 100000,
        'acquisition_fees' => 10000,
    ]);
    $lease = Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 600, 'start_date' => '2025-01-01', 'end_date' => null]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-02-01', 'amount_override' => 0, 'note' => 'Impayé']);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 90000, 'annual_rate' => 0.0, 'term_months' => 300, 'start_date' => '2025-01-01', 'monthly_insurance' => 0]);
    PropertyExpense::factory()->create(['property_id' => $property->id, 'date' => '2026-03-10', 'amount' => 900, 'category' => ExpenseCategory::PropertyTax]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'date' => '2026-01-01', 'value' => 120000]);

    $detail = app(GetPropertyDetail::class)($user->id, $property->id);

    expect($detail->name)->toBe('T2 Lyon 7e')
        ->and($detail->currentValue)->toBe(120000.0)
        ->and($detail->monthlyCashFlows)->toHaveCount(12)
        ->and($detail->rentHistory[0]->month)->toBe('2026-08-01')
        ->and($detail->loan->monthlyPayment)->toBe(300.0)
        ->and($detail->expenseYears[0]->year)->toBe(2026)
        ->and($detail->expenseYears[0]->byCategory['property_tax'])->toBe(900.0)
        ->and($detail->metrics->grossYield)->toBe(0.0655);

    Carbon::setTestNow();
});

it('yields null for a property of another user', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create();

    expect(app(GetPropertyDetail::class)($user->id, $property->id))->toBeNull();
});
```

`GetLoanScheduleTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetLoanSchedule;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;

it('yields the amortization schedule of the property loan', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Loan::factory()->create(['property_id' => $property->id, 'principal' => 1200, 'annual_rate' => 0.0, 'term_months' => 12, 'start_date' => '2026-01-01', 'monthly_insurance' => 0]);

    $schedule = app(GetLoanSchedule::class)($user->id, $property->id);

    expect($schedule)->toHaveCount(12)
        ->and($schedule[0]->payment)->toBe(100.0);
});

it('yields an empty schedule without a loan or for a foreign property', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    $foreign = Property::factory()->create();

    expect(app(GetLoanSchedule::class)($user->id, $property->id))->toBe([])
        ->and(app(GetLoanSchedule::class)($user->id, $foreign->id))->toBe([]);
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter="GetPropertyDetailTest|GetLoanScheduleTest"`
Expected: FAIL.

- [ ] **Step 3: Implémenter les Datas**

Les quatre Datas : readonly + `JsonSerializable`. `PropertyDetailData::jsonSerialize()` sérialise `metrics`, `monthlyCashFlows`, `rentHistory`, `expenseYears`, `loan` tels quels (les Datas imbriqués sont eux-mêmes `JsonSerializable`, Inertia les encode en cascade).

- [ ] **Step 4: Implémenter GetPropertyDetail**

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\ExpenseYearData;
use App\Contexts\RealEstate\Datas\LoanSummaryData;
use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Datas\PropertyDetailData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Services\PropertyMetricsCalculator;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/** Fiche complète d'un bien : indicateurs, cash-flow, loyers, charges, prêt. */
class GetPropertyDetail
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private RentScheduleCalculator $rents,
        private PropertyMetricsCalculator $metrics,
    ) {}

    public function __invoke(int $userId, int $propertyId): ?PropertyDetailData
    {
        $property = Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->find($propertyId);

        if ($property === null) {
            return null;
        }

        $today = Carbon::now();
        $financials = $this->assembler->financialsFor($property, $today);
        $months = $this->rents->months(
            $this->assembler->leaseTerms($property),
            $this->assembler->exceptions($property),
            $today,
        );

        return new PropertyDetailData(
            id: $property->id,
            name: $property->name,
            address: $property->address,
            acquisitionDate: $property->acquisition_date->toDateString(),
            acquisitionPrice: (float) $property->acquisition_price,
            acquisitionFees: (float) $property->acquisition_fees,
            currentValue: $financials->currentValue,
            netWorth: round($financials->currentValue - $financials->remainingPrincipal, 2),
            metrics: $this->metrics->metrics($financials),
            monthlyCashFlows: $this->monthlyCashFlows($property, $months, $today),
            rentHistory: array_reverse($months),
            expenseYears: $this->expenseYears($property),
            loan: $this->loanSummary($property, $today, $financials->remainingPrincipal),
        );
    }

    /**
     * @param  list<RentMonthData>  $months
     * @return list<MonthlyCashFlowData>
     */
    private function monthlyCashFlows(Property $property, array $months, Carbon $today): array
    {
        $windowStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString();

        $rentsByMonth = [];
        foreach ($months as $month) {
            $rentsByMonth[$month->month] = $month->effective;
        }

        $expensesByMonth = [];
        foreach ($property->expenses as $expense) {
            $key = $expense->date->copy()->startOfMonth()->toDateString();
            $expensesByMonth[$key] = ($expensesByMonth[$key] ?? 0.0) + (float) $expense->amount;
        }

        $paymentsByMonth = [];
        foreach ($property->loans as $loan) {
            foreach ($this->assembler->scheduleFor($loan) as $line) {
                $paymentsByMonth[$line->month] = ($paymentsByMonth[$line->month] ?? 0.0) + $line->payment;
            }
        }

        $flows = [];
        $cursor = Carbon::parse($windowStart);

        for ($index = 0; $index < 12; $index++) {
            $key = $cursor->toDateString();
            $rents = $rentsByMonth[$key] ?? 0.0;
            $expenses = $expensesByMonth[$key] ?? 0.0;
            $payment = $paymentsByMonth[$key] ?? 0.0;

            $flows[] = new MonthlyCashFlowData(
                month: $key,
                rents: round($rents, 2),
                expenses: round($expenses, 2),
                loanPayment: round($payment, 2),
                net: round($rents - $expenses - $payment, 2),
            );

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $flows;
    }

    /** @return list<ExpenseYearData> */
    private function expenseYears(Property $property): array
    {
        $years = [];

        foreach ($property->expenses as $expense) {
            $year = $expense->date->year;
            $category = $expense->category->value;
            $years[$year][$category] = ($years[$year][$category] ?? 0.0) + (float) $expense->amount;
        }

        krsort($years);

        return array_map(
            fn (int $year): ExpenseYearData => new ExpenseYearData(
                year: $year,
                byCategory: array_map(fn (float $amount): float => round($amount, 2), $years[$year]),
                total: round(array_sum($years[$year]), 2),
            ),
            array_keys($years),
        );
    }

    private function loanSummary(Property $property, Carbon $today, float $remaining): ?LoanSummaryData
    {
        $loan = $property->loans->first();

        if ($loan === null) {
            return null;
        }

        $schedule = $this->assembler->scheduleFor($loan);
        $totalPaid = array_sum(array_map(fn ($line): float => $line->payment, $schedule));

        return new LoanSummaryData(
            principal: (float) $loan->principal,
            annualRate: (float) $loan->annual_rate,
            termMonths: $loan->term_months,
            startDate: $loan->start_date->toDateString(),
            monthlyInsurance: (float) $loan->monthly_insurance,
            monthlyPayment: $schedule[0]->payment,
            remainingPrincipal: $remaining,
            totalCost: round($totalPaid - (float) $loan->principal, 2),
        );
    }
}
```

- [ ] **Step 5: Implémenter GetLoanSchedule**

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\AmortizationLineData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;

/** Tableau d'amortissement complet, servi en prop différée sur la fiche du bien. */
class GetLoanSchedule
{
    public function __construct(private PropertyFinancialsAssembler $assembler) {}

    /** @return list<AmortizationLineData> */
    public function __invoke(int $userId, int $propertyId): array
    {
        $loan = Property::query()
            ->where('user_id', $userId)
            ->with('loans')
            ->find($propertyId)
            ?->loans
            ->first();

        return $loan === null ? [] : $this->assembler->scheduleFor($loan);
    }
}
```

- [ ] **Step 6: Vérifier le vert + commit**

Run: `php artisan test --compact --filter="GetPropertyDetailTest|GetLoanScheduleTest"`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: fiche détaillée du bien et tableau d'amortissement"
```

---

### Task 8: Source de revenu Rent dans Income

**Files:**
- Modify: `app/Contexts/Income/Enums/IncomeSource.php`
- Modify: `app/Contexts/Income/Enums/IncomeSourceTest.php`
- Create: `app/Contexts/Income/Sources/Rent/Ports/RentSchedulePort.php`
- Create: `app/Contexts/Income/Sources/Rent/Datas/RentReceiptData.php`
- Create: `app/Contexts/Income/Sources/Rent/Infrastructure/RealEstateRentSchedule.php`
- Test: `app/Contexts/Income/Sources/Rent/Infrastructure/RealEstateRentScheduleTest.php`
- Create: `app/Contexts/Income/Sources/Rent/RentIncomeSource.php`
- Test: `app/Contexts/Income/Sources/Rent/RentIncomeSourceTest.php`
- Modify: `app/Contexts/Income/IncomeProvider.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Interfaces:**
- Consumes: modèles et calculateurs RealEstate (Tasks 2, 4, 6 : `PropertyFinancialsAssembler::leaseTerms/exceptions`, `RentScheduleCalculator::months/projectedAnnual`), noyau Income existant (`IncomeSourcePort`, `IncomeReceiptData`).
- Produces:
  - Cas d'enum `IncomeSource::Rent = 'rent'`, label « Loyers ».
  - `RentReceiptData { public string $month /* 'Y-m-d' */, public float $amount, public string $propertyName }` (Data interne à la source, pas de `JsonSerializable`).
  - `RentSchedulePort { /** @return list<RentReceiptData> */ public function receiptsFor(int $userId): array; public function projectedAnnualFor(int $userId): float; }`
  - `RealEstateRentSchedule` : adaptateur du port sur le contexte RealEstate.
  - `RentIncomeSource implements IncomeSourcePort`.
  - `IncomeProvider::registers` accepte `string $rentSchedule` et le binde sur `RentSchedulePort`.

- [ ] **Step 1: Ajouter le cas d'enum et son test**

Dans `IncomeSource.php` : ajouter `case Rent = 'rent';` et `self::Rent => 'Loyers',` dans `getLabel()`. Dans `IncomeSourceTest.php`, compléter le test existant des valeurs/labels pour couvrir `Rent` (suivre la forme des assertions déjà présentes).

Run: `php artisan test --compact --filter=IncomeSourceTest` → PASS.

- [ ] **Step 2: Écrire le test de l'adaptateur (rouge)**

`RealEstateRentScheduleTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Sources\Rent\Infrastructure\RealEstateRentSchedule;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Support\Carbon;

it('yields one receipt per collected month, skipping vacancy and full defaults', function () {
    Carbon::setTestNow('2026-04-15');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    $lease = Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 500, 'start_date' => '2026-01-01', 'end_date' => null]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-02-01', 'amount_override' => 0]);
    RentException::factory()->create(['lease_id' => $lease->id, 'month' => '2026-03-01', 'amount_override' => 250]);

    $receipts = app(RealEstateRentSchedule::class)->receiptsFor($user->id);

    expect(array_map(fn ($r): array => [$r->month, $r->amount, $r->propertyName], $receipts))->toBe([
        ['2026-01-01', 500.0, 'T2 Lyon 7e'],
        ['2026-03-01', 250.0, 'T2 Lyon 7e'],
        ['2026-04-01', 500.0, 'T2 Lyon 7e'],
    ]);

    Carbon::setTestNow();
});

it('projects the yearly rent of active leases', function () {
    Carbon::setTestNow('2026-04-15');

    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id]);
    Lease::factory()->create(['property_id' => $property->id, 'monthly_rent' => 500, 'start_date' => '2026-01-01', 'end_date' => null]);

    expect(app(RealEstateRentSchedule::class)->projectedAnnualFor($user->id))->toBe(6000.0);

    Carbon::setTestNow();
});
```

- [ ] **Step 3: Implémenter port, Data et adaptateur**

`RentSchedulePort.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Rent\Ports;

use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;

/** Loyers encaissés et loyer projeté, vus depuis le contexte immobilier. */
interface RentSchedulePort
{
    /** @return list<RentReceiptData> */
    public function receiptsFor(int $userId): array;

    public function projectedAnnualFor(int $userId): float;
}
```

`RentReceiptData.php` : readonly `{ public string $month, public float $amount, public string $propertyName }`.

`RealEstateRentSchedule.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Rent\Infrastructure;

use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\RentScheduleCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/**
 * Adaptateur du contexte RealEstate : seuls les mois réellement encaissés (`effective > 0`)
 * deviennent des reçus — la vacance et l'impayé total n'ont rien produit.
 */
class RealEstateRentSchedule implements RentSchedulePort
{
    public function __construct(
        private RentScheduleCalculator $calculator,
        private PropertyFinancialsAssembler $assembler,
    ) {}

    /** @return list<RentReceiptData> */
    public function receiptsFor(int $userId): array
    {
        $receipts = [];
        $today = Carbon::now();

        foreach ($this->propertiesFor($userId) as $property) {
            $months = $this->calculator->months(
                $this->assembler->leaseTerms($property),
                $this->assembler->exceptions($property),
                $today,
            );

            foreach ($months as $month) {
                if ($month->effective > 0) {
                    $receipts[] = new RentReceiptData(
                        month: $month->month,
                        amount: $month->effective,
                        propertyName: $property->name,
                    );
                }
            }
        }

        return $receipts;
    }

    public function projectedAnnualFor(int $userId): float
    {
        $projected = 0.0;
        $today = Carbon::now();

        foreach ($this->propertiesFor($userId) as $property) {
            $projected += $this->calculator->projectedAnnual($this->assembler->leaseTerms($property), $today);
        }

        return round($projected, 2);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Property> */
    private function propertiesFor(int $userId)
    {
        return Property::query()
            ->where('user_id', $userId)
            ->with('leases.exceptions')
            ->get();
    }
}
```

Run: `php artisan test --compact --filter=RealEstateRentScheduleTest` → PASS.

- [ ] **Step 4: Écrire le test de la source (rouge) puis l'implémenter**

`RentIncomeSourceTest.php` :

```php
<?php

use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use App\Contexts\Income\Sources\Rent\RentIncomeSource;

it('maps schedule receipts to income receipts labeled by property', function () {
    $port = new class implements RentSchedulePort
    {
        public function receiptsFor(int $userId): array
        {
            return [new RentReceiptData(month: '2026-03-01', amount: 500.0, propertyName: 'T2 Lyon 7e')];
        }

        public function projectedAnnualFor(int $userId): float
        {
            return 6000.0;
        }
    };

    $source = new RentIncomeSource($port);
    $receipts = $source->receiptsFor(1);

    expect($source->source())->toBe(IncomeSource::Rent)
        ->and($receipts)->toHaveCount(1)
        ->and($receipts[0]->source)->toBe(IncomeSource::Rent)
        ->and($receipts[0]->date->toDateString())->toBe('2026-03-01')
        ->and($receipts[0]->amount)->toBe(500.0)
        ->and($receipts[0]->assetId)->toBeNull()
        ->and($receipts[0]->label)->toBe('T2 Lyon 7e')
        ->and($source->projectedAnnualFor(1))->toBe(6000.0);
});
```

`RentIncomeSource.php` :

```php
<?php

namespace App\Contexts\Income\Sources\Rent;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use Illuminate\Support\Carbon;

class RentIncomeSource implements IncomeSourcePort
{
    public function __construct(private RentSchedulePort $schedule) {}

    public function source(): IncomeSource
    {
        return IncomeSource::Rent;
    }

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array
    {
        return array_map(
            fn (RentReceiptData $receipt): IncomeReceiptData => new IncomeReceiptData(
                source: IncomeSource::Rent,
                date: Carbon::parse($receipt->month),
                amount: $receipt->amount,
                assetId: null,
                label: $receipt->propertyName,
            ),
            $this->schedule->receiptsFor($userId),
        );
    }

    public function projectedAnnualFor(int $userId): float
    {
        return $this->schedule->projectedAnnualFor($userId);
    }
}
```

Run: `php artisan test --compact --filter=RentIncomeSourceTest` → PASS.

- [ ] **Step 5: Câbler**

`IncomeProvider::registers` : ajouter le paramètre `string $rentSchedule` (class-string de `RentSchedulePort`) et la ligne `$app->bind(RentSchedulePort::class, $rentSchedule);` (import du port en tête de fichier, PHPDoc du paramètre aligné sur les voisins).

`AppServiceProvider` :

```php
IncomeProvider::registers(
    app: $this->app,
    sources: [DividendIncomeSource::class, RentIncomeSource::class],
    dividendHistory: MarketDividendHistory::class,
    positionHistory: PortfolioPositionHistory::class,
    rentSchedule: RealEstateRentSchedule::class,
);
```

(imports : `use App\Contexts\Income\Sources\Rent\RentIncomeSource;` et `use App\Contexts\Income\Sources\Rent\Infrastructure\RealEstateRentSchedule;`)

- [ ] **Step 6: Test d'intégration du câblage + suite Income**

`IncomeSourceRegistryTest` prouve déjà l'agrégation source-agnostique — ne pas y toucher. Ce qui manque : la preuve que le conteneur tague bien les deux sources réelles. Ajouter à la fin de `RentIncomeSourceTest.php` :

```php
it('is tagged into the income source registry', function () {
    $registry = app(\App\Contexts\Income\Infrastructure\IncomeSourceRegistry::class);

    $reflection = new ReflectionProperty($registry, 'sources');
    $sources = collect($reflection->getValue($registry))
        ->map(fn ($source): string => $source::class);

    expect($sources->all())->toContain(
        \App\Contexts\Income\Sources\Dividend\DividendIncomeSource::class,
        \App\Contexts\Income\Sources\Rent\RentIncomeSource::class,
    );
});
```

Run: `php artisan test --compact --filter="Income"`
Expected: PASS — toute la suite Income, sources réelles résolues par le conteneur comprises.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: les loyers rejoignent les revenus via la source Rent"
```

---

### Task 9: Routes et contrôleurs (PropertyDetailController + prop dashboard)

**Files:**
- Create: `app/Contexts/RealEstate/Http/PropertyDetailController.php`
- Test: `app/Contexts/RealEstate/Http/PropertyDetailControllerTest.php`
- Modify: `routes/web.php`
- Modify: `app/Contexts/Portfolio/Http/DashboardController.php`
- Test: existing `tests/Feature/` dashboard test if present, sinon assertions dans `PropertyDetailControllerTest.php`

**Interfaces:**
- Consumes: `GetPropertyDetail`, `GetLoanSchedule`, `GetRealEstateOverview` (Tasks 6-7).
- Produces: route nommée `properties.show` (`GET /properties/{id}`) rendant `Properties/Detail` ; prop Inertia différée `realEstate` (groupe `immobilier`) sur `Dashboard`.

- [ ] **Step 1: Écrire le test du contrôleur (rouge)**

`PropertyDetailControllerTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Inertia\Testing\AssertableInertia;

it('renders the property page with its deferred amortization', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'name' => 'T2 Lyon 7e']);
    Lease::factory()->ongoing()->create(['property_id' => $property->id, 'monthly_rent' => 600]);
    PropertyValuation::factory()->create(['property_id' => $property->id, 'value' => 120000]);

    $this->get(route('properties.show', $property->id))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Properties/Detail')
            ->where('property.name', 'T2 Lyon 7e')
            ->has('property.metrics'));
});

it('renders 404 for an unknown property', function () {
    User::factory()->create();

    $this->get(route('properties.show', 999))->assertNotFound();
});

it('defers the real estate overview on the dashboard', function () {
    User::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard'));
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `php artisan test --compact --filter=PropertyDetailControllerTest`
Expected: FAIL — route inconnue.

- [ ] **Step 3: Implémenter le contrôleur**

`PropertyDetailController.php` — même convention d'utilisateur que les contrôleurs existants (`auth()->user() ?? User::query()->first()`) :

```php
<?php

namespace App\Contexts\RealEstate\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetLoanSchedule;
use App\Contexts\RealEstate\Actions\GetPropertyDetail;
use Inertia\Inertia;
use Inertia\Response;

class PropertyDetailController
{
    public function __construct(private GetPropertyDetail $getDetail) {}

    public function __invoke(int $id): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;

        $detail = ($this->getDetail)($userId, $id);

        if ($detail === null) {
            abort(404);
        }

        return Inertia::render('Properties/Detail', [
            'property' => $detail,
            /** Long et rarement lu en premier : le tableau d'amortissement arrive après la page. */
            'amortization' => Inertia::defer(fn () => app(GetLoanSchedule::class)($userId, $id)),
        ]);
    }
}
```

- [ ] **Step 4: Ajouter la route et la prop dashboard**

`routes/web.php` :

```php
use App\Contexts\RealEstate\Http\PropertyDetailController;

Route::get('/properties/{id}', PropertyDetailController::class)->name('properties.show');
```

`DashboardController.php` — ajouter à la liste des props :

```php
'realEstate' => Inertia::defer(fn () => $user !== null
    ? app(GetRealEstateOverview::class)($user->id)
    : RealEstateOverviewData::empty(), 'immobilier'),
```

(imports : `use App\Contexts\RealEstate\Actions\GetRealEstateOverview;` et `use App\Contexts\RealEstate\Datas\RealEstateOverviewData;`)

- [ ] **Step 5: Vérifier le vert + commit**

Run: `php artisan test --compact --filter=PropertyDetailControllerTest`
Expected: PASS.

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: route de la fiche du bien et prop immobilière du dashboard"
```

---

### Task 10: Front — types et carte immobilière du dashboard

**Files:**
- Create: `resources/js/lib/realEstate.ts`
- Create: `resources/js/lib/realEstate.test.ts`
- Create: `resources/js/components/dashboard/RealEstateSection.vue`
- Modify: `resources/js/Pages/Dashboard.vue`

**Interfaces:**
- Consumes: prop `realEstate` (Task 9), composants existants (`Deferred` d'Inertia, `eur` de `@/lib/format`).
- Produces: types TS `PropertyOverview`, `RealEstateOverview`, `PropertyDetail`, `PropertyMetrics`, `MonthlyCashFlow`, `RentMonth`, `ExpenseYear`, `LoanSummary`, `AmortizationLine` ; helper `percent(ratio: number): string` si absent de `format.ts` (vérifier avant : réutiliser l'existant si `format.ts` expose déjà un pourcentage).

- [ ] **Step 1: Écrire les types et le test du helper**

`resources/js/lib/realEstate.ts` :

```ts
export interface PropertyOverview {
    id: number;
    name: string;
    currentValue: number;
    remainingPrincipal: number;
    netWorth: number;
    monthlyCashFlow: number;
}

export interface RealEstateOverview {
    properties: PropertyOverview[];
    totalValue: number;
    totalRemaining: number;
    totalNetWorth: number;
}

export interface PropertyMetrics {
    grossYield: number;
    netYield: number;
    annualCashFlow: number;
    cashOnCash: number | null;
    ltv: number | null;
}

export interface MonthlyCashFlow {
    month: string;
    rents: number;
    expenses: number;
    loanPayment: number;
    net: number;
}

export interface RentMonth {
    month: string;
    expected: number;
    effective: number;
}

export interface ExpenseYear {
    year: number;
    byCategory: Record<string, number>;
    total: number;
}

export interface LoanSummary {
    principal: number;
    annualRate: number;
    termMonths: number;
    startDate: string;
    monthlyInsurance: number;
    monthlyPayment: number;
    remainingPrincipal: number;
    totalCost: number;
}

export interface AmortizationLine {
    month: string;
    payment: number;
    interest: number;
    principal: number;
    insurance: number;
    remaining: number;
}

export interface PropertyDetail {
    id: number;
    name: string;
    address: string | null;
    acquisitionDate: string;
    acquisitionPrice: number;
    acquisitionFees: number;
    currentValue: number;
    netWorth: number;
    metrics: PropertyMetrics;
    monthlyCashFlows: MonthlyCashFlow[];
    rentHistory: RentMonth[];
    expenseYears: ExpenseYear[];
    loan: LoanSummary | null;
}

/** Étiquette d'un mois de loyer : plein, partiel, impayé ou vacance. */
export type RentMonthStatus = 'plein' | 'partiel' | 'impayé' | 'vacance';

export const rentMonthStatus = (month: RentMonth): RentMonthStatus => {
    if (month.expected === 0) {
        return 'vacance';
    }
    if (month.effective === 0) {
        return 'impayé';
    }
    return month.effective < month.expected ? 'partiel' : 'plein';
};
```

`resources/js/lib/realEstate.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { rentMonthStatus } from '@/lib/realEstate';

describe('rentMonthStatus', () => {
    it('labels a full rent', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 500 })).toBe('plein');
    });

    it('labels a partial payment', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 250 })).toBe('partiel');
    });

    it('labels a default', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 500, effective: 0 })).toBe('impayé');
    });

    it('labels vacancy', () => {
        expect(rentMonthStatus({ month: '2026-01-01', expected: 0, effective: 0 })).toBe('vacance');
    });
});
```

Run: `bun run test:js` → PASS.

- [ ] **Step 2: Écrire la section dashboard**

`resources/js/components/dashboard/RealEstateSection.vue` — même squelette que `IncomeSection.vue` (Deferred + fallback pulsant + rescue hors-ligne) :

```vue
<script setup lang="ts">
import { Deferred, Link } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate?: RealEstateOverview }>();
</script>

<template>
    <section data-section="real-estate" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Immobilier</h2>

        <Deferred data="realEstate">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 2" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">Données indisponibles hors-ligne.</p>
            </template>

            <template v-if="props.realEstate && props.realEstate.properties.length">
                <p class="text-sm text-muted-foreground">
                    <span data-real-estate-net class="font-semibold text-foreground">
                        {{ eur(props.realEstate.totalNetWorth) }}
                    </span>
                    de patrimoine net ·
                    {{ eur(props.realEstate.totalValue) }} estimés,
                    {{ eur(props.realEstate.totalRemaining) }} restant dus
                </p>

                <ul class="flex flex-col gap-2">
                    <li v-for="property in props.realEstate.properties" :key="property.id">
                        <Link
                            :href="route('properties.show', property.id)"
                            class="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                        >
                            <span class="truncate font-medium">{{ property.name }}</span>
                            <span class="flex shrink-0 items-center gap-3 tabular-nums">
                                <span class="text-muted-foreground">
                                    {{ eur(property.monthlyCashFlow) }}/mois
                                </span>
                                <span class="font-semibold">{{ eur(property.netWorth) }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </template>
        </Deferred>
    </section>
</template>
```

Note : si `route()` n'est pas disponible côté client dans ce projet, construire l'URL en dur `` `/properties/${property.id}` `` — vérifier l'usage dans les composants existants (`InstrumentList.vue`) et suivre la même convention.

- [ ] **Step 3: Brancher dans Dashboard.vue**

Dans `resources/js/Pages/Dashboard.vue` : importer le type et la section, ajouter la prop, poser la section entre `IncomeSection` et `SectorsSection` :

```ts
import RealEstateSection from '@/components/dashboard/RealEstateSection.vue';
import type { RealEstateOverview } from '@/lib/realEstate';
// dans defineProps :
realEstate?: RealEstateOverview;
```

```html
<RealEstateSection :real-estate="realEstate" />
```

- [ ] **Step 4: Vérifier types + tests + build**

Run: `bun run typecheck && bun run test:js && bun run build`
Expected: PASS (typecheck sans erreur, vitest vert, build OK).

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat: carte immobilière au tableau de bord"
```

---

### Task 11: Front — page du bien

**Files:**
- Create: `resources/js/Pages/Properties/Detail.vue`
- Create: `resources/js/components/property/PropertyMetricsGrid.vue`
- Create: `resources/js/components/property/PropertyCashFlowTable.vue`
- Create: `resources/js/components/property/PropertyRentHistory.vue`
- Create: `resources/js/components/property/PropertyExpenseYears.vue`
- Create: `resources/js/components/property/PropertyAmortizationTable.vue`

**Interfaces:**
- Consumes: props `property: PropertyDetail` et `amortization?: AmortizationLine[]` (Task 9), types de `@/lib/realEstate` (Task 10), `eur`/`frDayMonth` de `@/lib/format`, `AppPage`, `AppBreadcrumb`.
- Produces: page Inertia `Properties/Detail`.

- [ ] **Step 1: Écrire la page**

`resources/js/Pages/Properties/Detail.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyAmortizationTable from '@/components/property/PropertyAmortizationTable.vue';
import PropertyCashFlowTable from '@/components/property/PropertyCashFlowTable.vue';
import PropertyExpenseYears from '@/components/property/PropertyExpenseYears.vue';
import PropertyMetricsGrid from '@/components/property/PropertyMetricsGrid.vue';
import PropertyRentHistory from '@/components/property/PropertyRentHistory.vue';
import { eur } from '@/lib/format';
import type { AmortizationLine, PropertyDetail } from '@/lib/realEstate';

const props = defineProps<{ property: PropertyDetail; amortization?: AmortizationLine[] }>();

const frDate = (iso: string): string =>
    new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
</script>

<template>
    <Head :title="props.property.name" />

    <AppPage>
        <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: props.property.name }]" />

        <header class="flex flex-col gap-1 px-6">
            <h1 class="text-xl font-bold">{{ props.property.name }}</h1>
            <p v-if="props.property.address" class="text-sm text-muted-foreground">{{ props.property.address }}</p>
            <p class="text-sm text-muted-foreground">
                Acquis le {{ frDate(props.property.acquisitionDate) }} pour
                {{ eur(props.property.acquisitionPrice) }}
                <template v-if="props.property.acquisitionFees > 0">
                    (+ {{ eur(props.property.acquisitionFees) }} de frais)
                </template>
                · estimé {{ eur(props.property.currentValue) }} ·
                <span class="font-semibold text-foreground">{{ eur(props.property.netWorth) }}</span> net
            </p>
        </header>

        <PropertyMetricsGrid :metrics="props.property.metrics" :loan="props.property.loan" />

        <PropertyCashFlowTable :flows="props.property.monthlyCashFlows" />

        <PropertyRentHistory :months="props.property.rentHistory" />

        <PropertyExpenseYears :years="props.property.expenseYears" />

        <PropertyAmortizationTable v-if="props.property.loan" :loan="props.property.loan" :lines="amortization" />
    </AppPage>
</template>
```

Note : vérifier la signature réelle de `AppBreadcrumb` (props `items` supposée) dans `resources/js/components/AppBreadcrumb.vue` et s'aligner. Si `frDayMonth`/`format.ts` expose déjà un format long, l'utiliser au lieu du helper local.

- [ ] **Step 2: Écrire les cinq composants**

`PropertyMetricsGrid.vue` :

```vue
<script setup lang="ts">
import { eur } from '@/lib/format';
import type { LoanSummary, PropertyMetrics } from '@/lib/realEstate';

const props = defineProps<{ metrics: PropertyMetrics; loan: LoanSummary | null }>();

const pct = (ratio: number): string => `${(ratio * 100).toFixed(2).replace('.', ',')} %`;
</script>

<template>
    <section data-section="metrics" class="grid grid-cols-2 gap-4 px-6 md:grid-cols-5">
        <div class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Rendement brut</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.grossYield) }}</span>
        </div>
        <div class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Rendement net</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.netYield) }}</span>
        </div>
        <div class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Cash-flow annuel</span>
            <span class="font-semibold tabular-nums">{{ eur(props.metrics.annualCashFlow) }}</span>
        </div>
        <div v-if="props.metrics.cashOnCash !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">Cash-on-cash</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.cashOnCash) }}</span>
        </div>
        <div v-if="props.metrics.ltv !== null" class="flex flex-col gap-1">
            <span class="text-xs text-muted-foreground">LTV</span>
            <span class="font-semibold tabular-nums">{{ pct(props.metrics.ltv) }}</span>
        </div>
    </section>
</template>
```

`PropertyCashFlowTable.vue` :

```vue
<script setup lang="ts">
import { eur } from '@/lib/format';
import type { MonthlyCashFlow } from '@/lib/realEstate';

const props = defineProps<{ flows: MonthlyCashFlow[] }>();

const frMonth = (iso: string): string =>
    new Date(iso).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
</script>

<template>
    <section data-section="cash-flow" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Cash-flow mensuel</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted-foreground">
                        <th class="py-1 pr-4 font-normal">Mois</th>
                        <th class="py-1 pr-4 text-right font-normal">Loyers</th>
                        <th class="py-1 pr-4 text-right font-normal">Charges</th>
                        <th class="py-1 pr-4 text-right font-normal">Crédit</th>
                        <th class="py-1 text-right font-normal">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="flow in props.flows" :key="flow.month" class="border-t border-separator">
                        <td class="py-1.5 pr-4">{{ frMonth(flow.month) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.rents) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.expenses) }}</td>
                        <td class="py-1.5 pr-4 text-right tabular-nums">{{ eur(flow.loanPayment) }}</td>
                        <td
                            class="py-1.5 text-right font-semibold tabular-nums"
                            :class="flow.net < 0 ? 'text-red-600 dark:text-red-400' : ''"
                        >
                            {{ eur(flow.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
```

`PropertyRentHistory.vue` :

```vue
<script setup lang="ts">
import { eur } from '@/lib/format';
import { rentMonthStatus, type RentMonth } from '@/lib/realEstate';

const props = defineProps<{ months: RentMonth[] }>();

const frMonth = (iso: string): string =>
    new Date(iso).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });

const statusClass = (month: RentMonth): string => {
    const status = rentMonthStatus(month);
    if (status === 'impayé') {
        return 'text-red-600 dark:text-red-400';
    }
    if (status === 'partiel') {
        return 'text-amber-600 dark:text-amber-400';
    }
    if (status === 'vacance') {
        return 'text-muted-foreground';
    }
    return '';
};
</script>

<template>
    <section data-section="rents" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Loyers</h2>

        <ul class="flex flex-col">
            <li
                v-for="month in props.months"
                :key="month.month"
                class="flex items-center justify-between border-t border-separator py-1.5 text-sm first:border-t-0"
            >
                <span>{{ frMonth(month.month) }}</span>
                <span class="flex items-center gap-2 tabular-nums" :class="statusClass(month)">
                    <span class="text-xs">{{ rentMonthStatus(month) !== 'plein' ? rentMonthStatus(month) : '' }}</span>
                    <span class="font-medium">{{ eur(month.effective) }}</span>
                </span>
            </li>
        </ul>
    </section>
</template>
```

`PropertyExpenseYears.vue` :

```vue
<script setup lang="ts">
import { eur } from '@/lib/format';
import type { ExpenseYear } from '@/lib/realEstate';

const props = defineProps<{ years: ExpenseYear[] }>();

const categoryLabels: Record<string, string> = {
    property_tax: 'Taxe foncière',
    co_ownership: 'Copropriété',
    insurance: 'Assurance',
    management: 'Gestion',
    works: 'Travaux',
    other: 'Autre',
};
</script>

<template>
    <section data-section="expenses" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Charges</h2>

        <p v-if="!props.years.length" class="text-sm text-muted-foreground">Aucune charge enregistrée.</p>

        <div v-for="year in props.years" :key="year.year" class="flex flex-col gap-1">
            <div class="flex items-center justify-between text-sm">
                <span class="font-semibold">{{ year.year }}</span>
                <span class="font-semibold tabular-nums">{{ eur(year.total) }}</span>
            </div>
            <ul class="flex flex-col">
                <li
                    v-for="(amount, category) in year.byCategory"
                    :key="category"
                    class="flex items-center justify-between py-1 text-sm text-muted-foreground"
                >
                    <span>{{ categoryLabels[category] ?? category }}</span>
                    <span class="tabular-nums">{{ eur(amount) }}</span>
                </li>
            </ul>
        </div>
    </section>
</template>
```

`PropertyAmortizationTable.vue` (Deferred + skeleton, règle Inertia sur les props différées) :

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { AmortizationLine, LoanSummary } from '@/lib/realEstate';

const props = defineProps<{ loan: LoanSummary; lines?: AmortizationLine[] }>();

const frMonth = (iso: string): string =>
    new Date(iso).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });

const pct = (ratio: number): string => `${(ratio * 100).toFixed(2).replace('.', ',')} %`;
</script>

<template>
    <section data-section="amortization" class="flex flex-col gap-4 px-6">
        <h2 class="text-[17px] leading-none font-bold">Crédit</h2>

        <p class="text-sm text-muted-foreground">
            {{ eur(props.loan.principal) }} à {{ pct(props.loan.annualRate) }} sur
            {{ props.loan.termMonths }} mois ·
            {{ eur(props.loan.monthlyPayment) }}/mois ·
            {{ eur(props.loan.remainingPrincipal) }} restant dus ·
            coût total {{ eur(props.loan.totalCost) }}
        </p>

        <Deferred data="amortization">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 6" :key="n" class="h-6 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">Données indisponibles hors-ligne.</p>
            </template>

            <div class="max-h-96 overflow-y-auto overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-background">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="py-1 pr-4 font-normal">Mois</th>
                            <th class="py-1 pr-4 text-right font-normal">Mensualité</th>
                            <th class="py-1 pr-4 text-right font-normal">Intérêts</th>
                            <th class="py-1 pr-4 text-right font-normal">Capital</th>
                            <th class="py-1 text-right font-normal">Restant dû</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in props.lines ?? []" :key="line.month" class="border-t border-separator">
                            <td class="py-1 pr-4">{{ frMonth(line.month) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.payment) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.interest) }}</td>
                            <td class="py-1 pr-4 text-right tabular-nums">{{ eur(line.principal) }}</td>
                            <td class="py-1 text-right tabular-nums">{{ eur(line.remaining) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Deferred>
    </section>
</template>
```

Note : les classes de couleur (`text-red-600`, `bg-muted`, `border-separator`…) doivent être alignées sur celles réellement utilisées dans les composants existants — vérifier `GainPill.vue` et `IncomeSection.vue` et reprendre leurs conventions si elles diffèrent.

- [ ] **Step 3: Vérifier types + build + navigateur**

Run: `bun run typecheck && bun run test:js && bun run build`
Expected: PASS.

Vérification manuelle rapide : `php artisan db:seed` n'a pas encore de bien (Task 12) — contrôler seulement que le dashboard rend sans erreur console (`php artisan test --compact --filter=PropertyDetailControllerTest` déjà vert côté serveur).

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat: page dédiée du bien locatif"
```

---

### Task 12: Seeder de démonstration + suite complète

**Files:**
- Create: `database/seeders/RealEstateDemoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: modèles Task 2.
- Produces: un bien complet en base de démo.

- [ ] **Step 1: Écrire le seeder**

`RealEstateDemoSeeder.php` — suivre la forme de `DividendDemoSeeder` (résolution du premier utilisateur, idempotence par `firstOrCreate`/`updateOrCreate`) :

```php
<?php

namespace Database\Seeders;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Enums\ExpenseCategory;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use Illuminate\Database\Seeder;

/** Un bien de démonstration : T2 loué, crédit en cours, un impayé, charges annuelles. */
class RealEstateDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        $property = Property::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => 'T2 Croix-Rousse'],
            [
                'address' => '12 rue des Tables Claudiennes, 69001 Lyon',
                'acquisition_date' => '2024-03-15',
                'acquisition_price' => 165000,
                'acquisition_fees' => 13500,
            ],
        );

        $lease = Lease::query()->firstOrCreate(
            ['property_id' => $property->id, 'start_date' => '2024-05-01'],
            ['monthly_rent' => 680, 'end_date' => null],
        );

        RentException::query()->firstOrCreate(
            ['lease_id' => $lease->id, 'month' => '2025-11-01'],
            ['amount_override' => 0, 'note' => 'Impayé, régularisé en décembre'],
        );

        Loan::query()->firstOrCreate(
            ['property_id' => $property->id, 'start_date' => '2024-03-15'],
            [
                'principal' => 145000,
                'annual_rate' => 0.0385,
                'term_months' => 240,
                'monthly_insurance' => 32,
            ],
        );

        foreach (['2024-10-12' => 780, '2025-10-10' => 810] as $date => $amount) {
            PropertyExpense::query()->firstOrCreate(
                ['property_id' => $property->id, 'date' => $date, 'category' => ExpenseCategory::PropertyTax->value],
                ['amount' => $amount, 'label' => 'Taxe foncière'],
            );
        }

        PropertyExpense::query()->firstOrCreate(
            ['property_id' => $property->id, 'date' => '2026-01-15', 'category' => ExpenseCategory::CoOwnership->value],
            ['amount' => 420, 'label' => 'Charges de copropriété T1'],
        );

        foreach (['2024-03-15' => 165000, '2026-02-01' => 178000] as $date => $value) {
            PropertyValuation::query()->firstOrCreate(
                ['property_id' => $property->id, 'date' => $date],
                ['value' => $value],
            );
        }
    }
}
```

Dans `DatabaseSeeder.php`, ajouter `$this->call(RealEstateDemoSeeder::class);` après `DividendDemoSeeder`.

- [ ] **Step 2: Vérifier le seed et l'app**

Run: `php artisan db:seed --class=Database\\Seeders\\RealEstateDemoSeeder && php artisan tinker --execute 'echo \App\Contexts\RealEstate\Models\Property::query()->count();'`
Expected: `1`. Ouvrir le dashboard (Herd) : carte « Immobilier » remplie, lien vers la fiche fonctionnel, section « Revenus » incluant les loyers.

- [ ] **Step 3: Suite complète + PHPStan**

Run: `php artisan test --compact && vendor/bin/phpstan analyse --memory-limit=1G`
Expected: PASS des deux. Corriger tout ce qui sort avant de committer.

- [ ] **Step 4: Commit final**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: seeder de démonstration du bien locatif"
```
