# Tableau de bord patrimoine — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Réduire le tableau de bord à un résumé du patrimoine (titres + immobilier), un graphe empilé et un revenu mensuel, et déplacer tout le détail sur `/instruments` et `/properties`.

**Architecture:** Un nouveau contexte `Wealth`, sans table, qui tire par ports sur `Portfolio`, `Valuation`, `RealEstate` et `Income` et porte la définition du patrimoine net et du cash investi. `RealEstate` gagne deux actions dérivées (série hebdomadaire de valeur nette, cash sorti) et un cache par empreinte. Le front éclate en trois pages, une par niveau de lecture.

**Tech Stack:** PHP 8.5, Laravel, Inertia v3, Vue 3 `<script setup>`, Tailwind v4, ECharts, Pest, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-21-tableau-de-bord-patrimoine-design.md`

## Global Constraints

- Tout texte visible par l'utilisateur est en **français**, accents compris.
- Les tests unitaires vivent **à côté du code** dans `app/Contexts/**` (`pest()->…->in('../app/Contexts')`). Seules les pages passent par `tests/Feature` et `tests/Browser`.
- Un seul nouveau dossier de base autorisé : `app/Contexts/Wealth`. Aucun autre.
- Créer les fichiers PHP via `php artisan make:class --no-interaction` quand la commande s'applique.
- Avant **chaque** commit : `vendor/bin/pint --dirty --format agent`.
- `bun run typecheck` dès qu'un fichier `.ts` ou `.vue` change.
- Messages de commit en français, présent de l'indicatif, préfixe `feat:` / `fix:` / `refactor:` / `docs:` — comme l'historique existant (`feat: peuple la démo de trois biens locatifs du Nord achetés à crédit`).
- Les URLs restent en anglais (`/instruments`, `/properties`) ; le français est dans le texte.
- `--chart-real-estate` est la **seule** couleur ajoutée. `palette()` recopie à la main les valeurs de `resources/css/app.css` : les deux fichiers changent ensemble.
- Ratios : `PropertyMetrics` porte des fractions (0,0655 = 6,55 %), `capitalGainPctOf` des points de pourcentage. Ne pas mélanger.
- Aucune dépendance Composer ou npm ajoutée.

---

### Task 1 : filtre par origine dans `Income`

**Pourquoi :** `Income` agrège déjà `IncomeSource::Dividend` **et** `IncomeSource::Rent`. `IncomeSummaryData::$last12Months` contient donc des loyers bruts. Sans filtre, le bloc Revenus du patrimoine compterait les loyers deux fois, et la page Titres afficherait des loyers.

**Files:**
- Modify: `app/Contexts/Income/Infrastructure/IncomeSourceRegistry.php`
- Modify: `app/Contexts/Income/Actions/GetIncomeSummary.php:22`
- Modify: `app/Contexts/Income/Actions/GetAnnualIncome.php:20`
- Test: `app/Contexts/Income/Infrastructure/IncomeSourceRegistryTest.php`
- Test: `app/Contexts/Income/Actions/GetIncomeSummaryTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `IncomeSourceRegistry::receiptsFor(int $userId, ?IncomeSource $only = null): array`, `IncomeSourceRegistry::projectedAnnualFor(int $userId, ?IncomeSource $only = null): float`, `GetIncomeSummary::__invoke(int $userId, ?IncomeSource $only = null): IncomeSummaryData`, `GetAnnualIncome::__invoke(int $userId, ?IncomeSource $only = null): array`.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à la fin de `app/Contexts/Income/Infrastructure/IncomeSourceRegistryTest.php` :

```php
/** Source factice : le registre n'a besoin que du contrat, pas d'une base. */
function fakeSource(IncomeSource $source, float $amount): IncomeSourcePort
{
    return new class($source, $amount) implements IncomeSourcePort
    {
        public function __construct(private IncomeSource $source, private float $amount) {}

        public function source(): IncomeSource
        {
            return $this->source;
        }

        /** @return list<IncomeReceiptData> */
        public function receiptsFor(int $userId): array
        {
            return [new IncomeReceiptData(
                source: $this->source,
                date: Carbon::parse('2026-06-15'),
                amount: $this->amount,
                assetId: null,
                label: $this->source->getLabel(),
            )];
        }

        public function projectedAnnualFor(int $userId): float
        {
            return $this->amount * 12;
        }
    };
}

it('ne rend que les revenus de l\'origine demandée', function () {
    $registry = new IncomeSourceRegistry([
        fakeSource(IncomeSource::Dividend, 100.0),
        fakeSource(IncomeSource::Rent, 600.0),
    ]);

    $receipts = $registry->receiptsFor(1, IncomeSource::Dividend);

    expect($receipts)->toHaveCount(1)
        ->and($receipts[0]->amount)->toBe(100.0)
        ->and($receipts[0]->source)->toBe(IncomeSource::Dividend);
});

it('ne projette que l\'origine demandée', function () {
    $registry = new IncomeSourceRegistry([
        fakeSource(IncomeSource::Dividend, 100.0),
        fakeSource(IncomeSource::Rent, 600.0),
    ]);

    expect($registry->projectedAnnualFor(1, IncomeSource::Rent))->toBe(7200.0);
});

it('rend toutes les origines sans filtre', function () {
    $registry = new IncomeSourceRegistry([
        fakeSource(IncomeSource::Dividend, 100.0),
        fakeSource(IncomeSource::Rent, 600.0),
    ]);

    expect($registry->receiptsFor(1))->toHaveCount(2)
        ->and($registry->projectedAnnualFor(1))->toBe(8400.0);
});
```

Ajouter les `use` manquants en tête du fichier : `App\Contexts\Income\Datas\IncomeReceiptData`, `App\Contexts\Income\Enums\IncomeSource`, `App\Contexts\Income\Ports\IncomeSourcePort`, `Illuminate\Support\Carbon`.

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=IncomeSourceRegistry`
Expected: FAIL — `receiptsFor()` n'accepte qu'un argument (`ArgumentCountError` ou `TypeError`).

- [ ] **Step 3 : implémenter le filtre dans le registre**

Dans `app/Contexts/Income/Infrastructure/IncomeSourceRegistry.php`, remplacer les deux méthodes :

```php
    /**
     * Les revenus perçus par l'utilisateur. Sans `$only`, toutes origines confondues.
     *
     * @return list<IncomeReceiptData>
     */
    public function receiptsFor(int $userId, ?IncomeSource $only = null): array
    {
        $receipts = [];

        foreach ($this->sourcesFor($only) as $source) {
            foreach ($source->receiptsFor($userId) as $receipt) {
                $receipts[] = $receipt;
            }
        }

        return $receipts;
    }

    /** Revenu attendu sur les douze prochains mois. Sans `$only`, toutes origines confondues. */
    public function projectedAnnualFor(int $userId, ?IncomeSource $only = null): float
    {
        $projected = 0.0;

        foreach ($this->sourcesFor($only) as $source) {
            $projected += $source->projectedAnnualFor($userId);
        }

        return round($projected, 2);
    }

    /**
     * Le filtre porte sur l'origine déclarée par la source, jamais sur celle des reçus : une
     * source ne produit que des reçus de sa propre origine, l'interroger pour rien coûte ses
     * requêtes.
     *
     * @return iterable<IncomeSourcePort>
     */
    private function sourcesFor(?IncomeSource $only): iterable
    {
        foreach ($this->sources as $source) {
            if ($only === null || $source->source() === $only) {
                yield $source;
            }
        }
    }
```

Ajouter le `use App\Contexts\Income\Enums\IncomeSource;` en tête.

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=IncomeSourceRegistry`
Expected: PASS

- [ ] **Step 5 : propager le filtre aux deux actions**

Dans `GetIncomeSummary.php`, changer la signature et le premier appel :

```php
    public function __invoke(int $userId, ?IncomeSource $only = null): IncomeSummaryData
    {
        $receipts = $this->sources->receiptsFor($userId, $only);
        $estimatedAnnual = $this->sources->projectedAnnualFor($userId, $only);
```

Dans `GetAnnualIncome.php` :

```php
    public function __invoke(int $userId, ?IncomeSource $only = null): array
    {
        /** @var array<int, array<string, float>> $bySourcePerYear */
        $bySourcePerYear = [];

        foreach ($this->sources->receiptsFor($userId, $only) as $receipt) {
```

Ajouter `use App\Contexts\Income\Enums\IncomeSource;` dans les deux fichiers.

- [ ] **Step 6 : écrire le test du filtre au niveau de l'action**

Ajouter à `app/Contexts/Income/Actions/GetIncomeSummaryTest.php` :

```php
it('ne résume que les dividendes quand on filtre sur cette origine', function () {
    ['user' => $user] = dividendFixture();
    $property = propertyFixture()['property'];
    $property->update(['user_id' => $user->id]);

    $all = app(GetIncomeSummary::class)($user->id);
    $dividendsOnly = app(GetIncomeSummary::class)($user->id, IncomeSource::Dividend);

    expect($dividendsOnly->totalReceived)->toBeLessThan($all->totalReceived)
        ->and(array_keys($dividendsOnly->bySource))->toBe(['dividend']);
});
```

- [ ] **Step 7 : lancer les tests d'`Income` en entier**

Run: `php artisan test --compact --filter=Income`
Expected: PASS — les appels existants sans second argument gardent leur comportement.

- [ ] **Step 8 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Income
git commit -m "feat: filtre les revenus par origine"
```

---

### Task 2 : extraire le cash-flow mensuel d'un bien en service

**Pourquoi :** `GetPropertyDetail::monthlyCashFlows()` est privé et fenêtré sur douze mois glissants. Le cash sorti (Task 3) et la série hebdomadaire (Task 4) ont besoin du même calcul sur **tout** l'historique. Un seul calcul, trois appelants.

**Files:**
- Create: `app/Contexts/RealEstate/Services/CashFlowCalculator.php`
- Create: `app/Contexts/RealEstate/Services/CashFlowCalculatorTest.php`
- Modify: `app/Contexts/RealEstate/Actions/GetPropertyDetail.php:63-115` (remplace `monthlyCashFlows()`)

**Interfaces:**
- Consumes: `PropertyFinancialsAssembler::scheduleFor()`, `::leaseTerms()`, `::exceptions()`, `RentScheduleCalculator::months()`.
- Produces: `CashFlowCalculator::months(Property $property, Carbon $from, Carbon $until): array` — `list<MonthlyCashFlowData>`, un élément par mois de `$from` (ramené au premier du mois) au mois de `$until` inclus, ordre chronologique.

- [ ] **Step 1 : écrire le test qui échoue**

Créer `app/Contexts/RealEstate/Services/CashFlowCalculatorTest.php` :

```php
<?php

use App\Contexts\RealEstate\Services\CashFlowCalculator;
use Illuminate\Support\Carbon;

it('rend un mois par mois de la fenêtre, du plus ancien au plus récent', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-08-21'),
    );

    expect($flows)->toHaveCount(3)
        ->and($flows[0]->month)->toBe('2026-06-01')
        ->and($flows[2]->month)->toBe('2026-08-01');
});

it('compose le net d\'un mois de son loyer, de ses charges et de son échéance', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-07-01'),
        Carbon::parse('2026-07-31'),
    );

    /** 80 000 € à taux nul sur 240 mois : 333,33 € d'échéance. Loyer 600 €, aucune charge en juillet. */
    expect($flows[0]->rents)->toBe(600.0)
        ->and($flows[0]->expenses)->toBe(0.0)
        ->and($flows[0]->loanPayment)->toBe(333.33)
        ->and($flows[0]->net)->toBe(266.67);
});

it('rend un net négatif le mois où les charges dépassent le loyer', function () {
    Carbon::setTestNow('2026-08-21');
    ['property' => $property] = propertyFixture(['loan' => true]);

    $flows = app(CashFlowCalculator::class)->months(
        $property->fresh(['leases.exceptions', 'loans', 'expenses', 'valuations']),
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
    );

    /** Janvier porte les deux charges du fixture : 750 € de travaux et 250 € de taxe foncière. */
    expect($flows[0]->expenses)->toBe(1000.0)
        ->and($flows[0]->net)->toBe(-733.33);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=CashFlowCalculator`
Expected: FAIL — `Target class [App\Contexts\RealEstate\Services\CashFlowCalculator] does not exist.`

- [ ] **Step 3 : écrire le service**

```bash
php artisan make:class Contexts/RealEstate/Services/CashFlowCalculator --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use Illuminate\Support\Carbon;

/**
 * Cash-flow mois par mois d'un bien : loyer encaissé, charges payées, échéance de prêt, et le net
 * qui reste. Le calcul était privé dans `GetPropertyDetail` ; trois appelants en ont désormais
 * besoin sur des fenêtres différentes, d'où l'extraction — la fenêtre est un argument, pas une
 * règle du service.
 */
class CashFlowCalculator
{
    public function __construct(
        private PropertyFinancialsAssembler $assembler,
        private RentScheduleCalculator $rents,
    ) {}

    /**
     * Un élément par mois, de `$from` (ramené au premier du mois) au mois de `$until` inclus.
     *
     * @return list<MonthlyCashFlowData>
     */
    public function months(Property $property, Carbon $from, Carbon $until): array
    {
        $rentsByMonth = [];
        foreach ($this->rentMonths($property, $until) as $month) {
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
        $cursor = $from->copy()->startOfMonth();
        $lastMonth = $until->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
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

    /** @return list<RentMonthData> */
    public function rentMonths(Property $property, Carbon $until): array
    {
        return $this->rents->months(
            $this->assembler->leaseTerms($property),
            $this->assembler->exceptions($property),
            $until,
        );
    }
}
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=CashFlowCalculator`
Expected: PASS

- [ ] **Step 5 : brancher `GetPropertyDetail` sur le service**

Dans `app/Contexts/RealEstate/Actions/GetPropertyDetail.php` : injecter `CashFlowCalculator $cashFlows` dans le constructeur, remplacer l'appel

```php
            monthlyCashFlows: $this->monthlyCashFlows($property, $months, $today),
```

par

```php
            monthlyCashFlows: $this->cashFlows->months(
                $property,
                $this->cashFlowWindowStart($months, $today),
                $today,
            ),
```

puis **supprimer** la méthode privée `monthlyCashFlows()` et le `use` de `PropertyExpense` s'il ne sert plus. Garder `cashFlowWindowStart()` : la fenêtre est une décision de la fiche, pas du service.

- [ ] **Step 6 : vérifier qu'aucun comportement de la fiche n'a bougé**

Run: `php artisan test --compact --filter=GetPropertyDetail`
Expected: PASS, sans modifier une seule assertion du test existant.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/RealEstate
git commit -m "refactor: extrait le cash-flow mensuel d'un bien en service"
```

---

### Task 3 : cash sorti d'un bien acheté à crédit

**Files:**
- Create: `app/Contexts/RealEstate/Support/UserProperties.php`
- Create: `app/Contexts/RealEstate/Actions/GetRealEstateCashInvested.php`
- Create: `app/Contexts/RealEstate/Actions/GetRealEstateCashInvestedTest.php`
- Modify: `app/Contexts/RealEstate/Actions/GetRealEstateOverview.php:19-25` (passe par `UserProperties`)

**Interfaces:**
- Consumes: `CashFlowCalculator::months()` (Task 2).
- Produces: `UserProperties::forUser(int $userId): Collection<int, Property>` (relations `leases.exceptions`, `loans`, `expenses`, `valuations` chargées, triées par `name`) ; `GetRealEstateCashInvested::__invoke(int $userId): float` ; `GetRealEstateCashInvested::forProperty(Property $property, Carbon $today): float`.

**Formule (spec) :**

```
apport       = acquisition_price + acquisition_fees − Σ loans.principal
cash injecté = Σ_mois depuis l'acquisition  max(0, −net)
investi      = apport + cash injecté
```

- [ ] **Step 1 : écrire le test qui échoue**

Créer `app/Contexts/RealEstate/Actions/GetRealEstateCashInvestedTest.php` :

```php
<?php

use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Models\Loan;
use Illuminate\Support\Carbon;

it('compte l\'apport et les mois que le bien n\'a pas couverts', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /**
     * Apport = 100 000 + 8 000 − 80 000 = 28 000 €.
     * Un seul mois déficitaire dans la fenêtre du fixture : janvier 2026, où 1 000 € de charges
     * s'ajoutent à 333,33 € d'échéance contre 600 € de loyer, soit 733,33 € injectés.
     */
    expect(app(GetRealEstateCashInvested::class)($user->id))->toBe(28733.33);
});

it('ne compte pas comme sorti le capital remboursé par le locataire', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $investi = app(GetRealEstateCashInvested::class)($user->id);

    /**
     * Vingt mois d'échéances à 333,33 € valent 6 666,60 € de capital remboursé. S'il était compté
     * comme une mise, l'investi dépasserait 34 000 €. Il ne doit pas : ce capital est sorti du
     * loyer, pas de la poche.
     */
    expect($investi)->toBeLessThan(30000.0);
});

it('conserve un apport négatif quand l\'emprunt dépasse le coût d\'acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();

    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 120000,
        'annual_rate' => 0.0,
        'term_months' => 240,
        'start_date' => $property->acquisition_date->toDateString(),
        'monthly_insurance' => 0,
    ]);

    /** Apport = 100 000 + 8 000 − 120 000 = −12 000 €, conservé tel quel. */
    expect(app(GetRealEstateCashInvested::class)($user->id))->toBeLessThan(0.0);
});

it('vaut le coût d\'acquisition pour un bien détenu sans prêt et sans charge', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();
    $property->expenses()->delete();

    expect(app(GetRealEstateCashInvested::class)($user->id))->toBe(108000.0);
});

it('vaut zéro sans aucun bien', function () {
    expect(app(GetRealEstateCashInvested::class)(999))->toBe(0.0);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=GetRealEstateCashInvested`
Expected: FAIL — `Target class [...GetRealEstateCashInvested] does not exist.`

- [ ] **Step 3 : écrire le chargeur de biens**

```bash
php artisan make:class Contexts/RealEstate/Support/UserProperties --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Support;

use App\Contexts\RealEstate\Models\Property;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les biens d'un utilisateur avec tout ce dont les calculs dérivés ont besoin. Trois actions
 * chargeaient les mêmes relations : une divergence entre elles se paierait en N+1 silencieux.
 */
class UserProperties
{
    /** @return Collection<int, Property> */
    public function forUser(int $userId): Collection
    {
        return Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->orderBy('name')
            ->get();
    }
}
```

- [ ] **Step 4 : écrire l'action**

```bash
php artisan make:class Contexts/RealEstate/Actions/GetRealEstateCashInvested --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\MonthlyCashFlowData;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Le cash réellement sorti de la poche pour l'immobilier : l'apport, plus chaque mois que le bien
 * n'a pas couvert de lui-même.
 *
 * Ce n'est pas `apport + capital remboursé`. Cette formule-là compte le capital deux fois dès
 * qu'un mois est déficitaire — l'échéance qui sort de la poche le contient déjà — et elle compte
 * comme une mise le capital remboursé par le locataire, qui n'a rien coûté. C'est ce capital-là
 * qui doit apparaître en gain : c'est le levier.
 */
class GetRealEstateCashInvested
{
    public function __construct(
        private UserProperties $properties,
        private CashFlowCalculator $cashFlows,
    ) {}

    public function __invoke(int $userId): float
    {
        $today = Carbon::now();
        $total = 0.0;

        foreach ($this->properties->forUser($userId) as $property) {
            $total += $this->forProperty($property, $today);
        }

        return round($total, 2);
    }

    /** Relations `loans`, `leases.exceptions` et `expenses` attendues chargées. */
    public function forProperty(Property $property, Carbon $today): float
    {
        return round($this->downPaymentFor($property) + $this->injected($property, $today), 2);
    }

    /**
     * Négatif quand l'emprunt dépasse le coût d'acquisition : un financement à plus de 100 %.
     *
     * Publique parce que `BuildRealEstateSeries` en a besoin seule : elle cumule les injections
     * label par label et ne peut pas se servir de `forProperty()`, qui les cumule déjà.
     */
    public function downPaymentFor(Property $property): float
    {
        $borrowed = $property->loans->sum(fn (Loan $loan): float => (float) $loan->principal);

        return (float) $property->acquisition_price + (float) $property->acquisition_fees - $borrowed;
    }

    /** Seuls les mois déficitaires injectent : un mois excédentaire rend du cash, il ne le prend pas. */
    private function injected(Property $property, Carbon $today): float
    {
        $from = $property->acquisition_date->copy()->startOfMonth();

        if ($from > $today) {
            return 0.0;
        }

        $injected = 0.0;

        foreach ($this->cashFlows->months($property, $from, $today) as $flow) {
            $injected += max(0.0, -$flow->net);
        }

        return $injected;
    }
}
```

- [ ] **Step 5 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=GetRealEstateCashInvested`
Expected: PASS

- [ ] **Step 6 : brancher `GetRealEstateOverview` sur `UserProperties`**

Remplacer dans `GetRealEstateOverview::__invoke()` le bloc

```php
        $properties = Property::query()
            ->where('user_id', $userId)
            ->with(['leases.exceptions', 'loans', 'expenses', 'valuations'])
            ->orderBy('name')
            ->get();
```

par

```php
        $properties = $this->properties->forUser($userId);
```

et ajouter `private UserProperties $properties` au constructeur. Retirer le `use` de `Property` s'il ne sert plus.

- [ ] **Step 7 : vérifier que le résumé immobilier n'a pas bougé**

Run: `php artisan test --compact --filter=GetRealEstateOverview`
Expected: PASS, sans modifier une assertion.

- [ ] **Step 8 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/RealEstate
git commit -m "feat: chiffre le cash sorti d'un bien acheté à crédit"
```

---

### Task 4 : série hebdomadaire de valeur nette immobilière

**Files:**
- Create: `app/Contexts/RealEstate/Datas/RealEstateSeriesData.php`
- Create: `app/Contexts/RealEstate/Actions/BuildRealEstateSeries.php`
- Create: `app/Contexts/RealEstate/Actions/BuildRealEstateSeriesTest.php`

**Interfaces:**
- Consumes: `UserProperties::forUser()`, `CashFlowCalculator::months()`, `PropertyFinancialsAssembler::scheduleFor()`, `LoanAmortizationCalculator::remainingAt()`.
- Produces: `BuildRealEstateSeries::__invoke(int $userId): RealEstateSeriesData` avec `RealEstateSeriesData { list<string> $labels; list<float> $netWorth; list<float> $invested; }` et `RealEstateSeriesData::empty(): self`.

**Règles (spec) :**
- Grille hebdomadaire, tous les lundis depuis le premier lundi ≤ la plus ancienne acquisition, plus aujourd'hui en dernier point.
- Valeur estimée **en escalier** : la dernière `PropertyValuation` de date ≤ au label. Aucune interpolation.
- Un bien non encore acquis contribue 0 à `netWorth` **et** à `invested`.
- `netWorth(label)` = Σ (valeur escalier − capital restant dû au label).
- `invested(label)` = Σ (apport + Σ des mois ≤ label de `max(0, −net)`).

- [ ] **Step 1 : écrire le test qui échoue**

Créer `app/Contexts/RealEstate/Actions/BuildRealEstateSeriesTest.php` :

```php
<?php

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

it('rend une série vide sans aucun bien', function () {
    $series = app(BuildRealEstateSeries::class)(999);

    expect($series->labels)->toBe([])
        ->and($series->netWorth)->toBe([])
        ->and($series->invested)->toBe([]);
});

it('termine sur aujourd\'hui et commence avant la plus ancienne acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);

    expect($series->labels[count($series->labels) - 1])->toBe('2026-08-21')
        ->and($series->labels[0])->toBeLessThanOrEqual($property->acquisition_date->toDateString())
        ->and($series->netWorth)->toHaveCount(count($series->labels))
        ->and($series->invested)->toHaveCount(count($series->labels));
});

it('vaut zéro avant la date d\'acquisition', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);

    expect($series->netWorth[0])->toBe(0.0)
        ->and($series->invested[0])->toBe(0.0);
});

it('lit la valeur estimée en escalier, sans interpoler', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user, 'property' => $property] = propertyFixture();
    $property->valuations()->delete();

    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => '2026-01-01',
        'value' => 100000,
    ]);
    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => '2026-07-01',
        'value' => 200000,
    ]);

    $series = app(BuildRealEstateSeries::class)($user->id);
    $byLabel = array_combine($series->labels, $series->netWorth);

    /** Sans prêt, la valeur nette vaut la valeur estimée. Avril tient la valeur de janvier. */
    $april = collect($series->labels)->first(fn (string $label): bool => str_starts_with($label, '2026-04'));

    expect($byLabel[$april])->toBe(100000.0);
});

it('fait décroître la valeur nette du capital remboursé', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $series = app(BuildRealEstateSeries::class)($user->id);
    $last = count($series->labels) - 1;

    /** Vingt mois d'échéances à taux nul : le restant dû a baissé, donc la valeur nette a monté. */
    expect($series->netWorth[$last])->toBeGreaterThan(0.0);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=BuildRealEstateSeries`
Expected: FAIL — `Target class [...BuildRealEstateSeries] does not exist.`

- [ ] **Step 3 : écrire le DTO**

```bash
php artisan make:class Contexts/RealEstate/Datas/RealEstateSeriesData --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Datas;

use JsonSerializable;

/** Valeur nette et cash investi du parc immobilier, semaine par semaine. */
readonly class RealEstateSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $netWorth
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $netWorth,
        public array $invested,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'labels' => $this->labels,
            'netWorth' => $this->netWorth,
            'invested' => $this->invested,
        ];
    }
}
```

- [ ] **Step 4 : écrire l'action**

```bash
php artisan make:class Contexts/RealEstate/Actions/BuildRealEstateSeries --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Datas\RealEstateSeriesData;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Services\CashFlowCalculator;
use App\Contexts\RealEstate\Services\LoanAmortizationCalculator;
use App\Contexts\RealEstate\Support\PropertyFinancialsAssembler;
use App\Contexts\RealEstate\Support\UserProperties;
use Illuminate\Support\Carbon;

/**
 * Patrimoine net immobilier semaine par semaine, et le cash investi en regard.
 *
 * La grille n'est pas alignée sur celle des titres : c'est `Wealth\Services\SeriesAligner` qui
 * réconcilie les deux. Un bien acheté avant la première transaction du portefeuille aurait sinon
 * été tronqué, et un utilisateur sans aucune transaction n'aurait pas eu un seul label.
 */
class BuildRealEstateSeries
{
    public function __construct(
        private UserProperties $properties,
        private CashFlowCalculator $cashFlows,
        private PropertyFinancialsAssembler $assembler,
        private LoanAmortizationCalculator $amortization,
        private GetRealEstateCashInvested $cashInvested,
    ) {}

    public function __invoke(int $userId): RealEstateSeriesData
    {
        $properties = $this->properties->forUser($userId);

        if ($properties->isEmpty()) {
            return RealEstateSeriesData::empty();
        }

        $today = Carbon::now();
        $labels = $this->weeklyLabels($properties->min('acquisition_date'), $today);

        $netWorth = array_fill(0, count($labels), 0.0);
        $invested = array_fill(0, count($labels), 0.0);

        foreach ($properties as $property) {
            $acquisition = $property->acquisition_date->toDateString();
            $valuations = $this->valuationPoints($property);
            $schedules = $this->schedulesFor($property);
            $injections = $this->injectionsByMonth($property, $today);
            $downPayment = $this->cashInvested->downPaymentFor($property);

            foreach ($labels as $index => $label) {
                if ($label < $acquisition) {
                    continue;
                }

                $netWorth[$index] += $this->valueAt($valuations, $label) - $this->remainingAt($schedules, $label);
                $invested[$index] += $downPayment + $this->injectedUpTo($injections, $label);
            }
        }

        return new RealEstateSeriesData(
            labels: $labels,
            netWorth: array_map(fn (float $amount): float => round($amount, 2), $netWorth),
            invested: array_map(fn (float $amount): float => round($amount, 2), $invested),
        );
    }

    /**
     * Tous les lundis depuis celui qui précède la plus ancienne acquisition, puis aujourd'hui —
     * sans quoi le dernier point de la série serait vieux de six jours au plus mauvais moment.
     *
     * @return list<string>
     */
    private function weeklyLabels(Carbon $firstAcquisition, Carbon $today): array
    {
        $cursor = $firstAcquisition->copy()->startOfWeek();
        $labels = [];

        while ($cursor < $today) {
            $labels[] = $cursor->toDateString();
            $cursor = $cursor->addWeek();
        }

        $labels[] = $today->toDateString();

        return $labels;
    }

    /**
     * Valuations triées par date croissante, en paires `[date, valeur]`.
     *
     * @return list<array{0: string, 1: float}>
     */
    private function valuationPoints(Property $property): array
    {
        return $property->valuations
            ->sortBy(fn (PropertyValuation $valuation): string => $valuation->date->toDateString())
            ->map(fn (PropertyValuation $valuation): array => [
                $valuation->date->toDateString(),
                (float) $valuation->value,
            ])
            ->values()
            ->all();
    }

    /**
     * Escalier : la dernière valeur estimée de date ≤ au label, 0 avant la première. Aucune
     * interpolation — une valeur n'est connue que le jour où elle a été estimée.
     *
     * @param  list<array{0: string, 1: float}>  $points
     */
    private function valueAt(array $points, string $label): float
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

    /** @return list<list<\App\Contexts\RealEstate\Datas\AmortizationLineData>> */
    private function schedulesFor(Property $property): array
    {
        return $property->loans
            ->map(fn (Loan $loan): array => $this->assembler->scheduleFor($loan))
            ->values()
            ->all();
    }

    /** @param  list<list<\App\Contexts\RealEstate\Datas\AmortizationLineData>>  $schedules */
    private function remainingAt(array $schedules, string $label): float
    {
        $remaining = 0.0;

        foreach ($schedules as $schedule) {
            $remaining += $this->amortization->remainingAt($schedule, Carbon::parse($label));
        }

        return $remaining;
    }

    /**
     * Cash injecté par mois, indexé par premier jour du mois. Seuls les mois déficitaires y
     * figurent.
     *
     * @return array<string, float>
     */
    private function injectionsByMonth(Property $property, Carbon $today): array
    {
        $from = $property->acquisition_date->copy()->startOfMonth();

        if ($from > $today) {
            return [];
        }

        $injections = [];

        foreach ($this->cashFlows->months($property, $from, $today) as $flow) {
            $injected = max(0.0, -$flow->net);

            if ($injected > 0.0) {
                $injections[$flow->month] = $injected;
            }
        }

        return $injections;
    }

    /** @param  array<string, float>  $injections */
    private function injectedUpTo(array $injections, string $label): float
    {
        $total = 0.0;

        foreach ($injections as $month => $amount) {
            if ($month <= $label) {
                $total += $amount;
            }
        }

        return $total;
    }
}
```

- [ ] **Step 5 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=BuildRealEstateSeries`
Expected: PASS

- [ ] **Step 6 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/RealEstate
git commit -m "feat: projette la valeur nette immobilière semaine par semaine"
```

---

### Task 5 : cache immobilier par empreinte

**Pourquoi :** `BuildRealEstateSeries` déroule l'échéancier complet de chaque prêt à chaque affichage. La part dérivée ne dépend pas de l'heure, donc elle se retient. Le signal de péremption est une **empreinte lue dans les données**, pas un observer : `RealEstateDemoSeeder::purgeRelated()` supprime en masse par le query builder, aucun événement de modèle n'en part.

**Files:**
- Create: `app/Contexts/RealEstate/Ports/RealEstateCachePort.php`
- Create: `app/Contexts/RealEstate/Infrastructure/LaravelRealEstateCache.php`
- Create: `app/Contexts/RealEstate/Infrastructure/LaravelRealEstateCacheTest.php`
- Create: `app/Contexts/RealEstate/RealEstateProvider.php`
- Modify: `app/Providers/AppServiceProvider.php:33` (ajoute `RealEstateProvider::registers`)
- Modify: `app/Contexts/RealEstate/Actions/BuildRealEstateSeries.php` (enveloppe le calcul)

**Interfaces:**
- Consumes: `BuildRealEstateSeries` (Task 4).
- Produces: `RealEstateCachePort::remember(string $name, int $userId, Closure $callback): mixed`.

- [ ] **Step 1 : écrire le test qui échoue**

Créer `app/Contexts/RealEstate/Infrastructure/LaravelRealEstateCacheTest.php` :

```php
<?php

use App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache;
use App\Contexts\RealEstate\Models\PropertyValuation;
use Illuminate\Support\Carbon;

/**
 * Une instance par appel : l'empreinte n'est mémoïsée que le temps d'une requête, et c'est bien
 * d'une requête à la suivante que l'invalidation doit se voir.
 */
function rememberRealEstate(int $userId, int &$calls): string
{
    return (new LaravelRealEstateCache)->remember('serie', $userId, function () use (&$calls): string {
        $calls++;

        return 'calculé';
    });
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-21');
    ['user' => $this->user, 'property' => $this->property] = propertyFixture(['loan' => true]);
});

it('ne recalcule pas tant que rien ne bouge', function () {
    $calls = 0;

    expect(rememberRealEstate($this->user->id, $calls))->toBe('calculé');
    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(1);
});

it('recalcule après une suppression en masse par le query builder', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    /** Exactement ce que fait RealEstateDemoSeeder::purgeRelated() : aucun événement de modèle. */
    PropertyValuation::query()->where('property_id', $this->property->id)->delete();

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('recalcule dès qu\'une valeur estimée change', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    PropertyValuation::factory()->create([
        'property_id' => $this->property->id,
        'date' => '2026-08-01',
        'value' => 175000,
    ]);

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('ne resserve pas la série de la veille', function () {
    $calls = 0;
    rememberRealEstate($this->user->id, $calls);

    Carbon::setTestNow('2026-08-22');

    rememberRealEstate($this->user->id, $calls);

    expect($calls)->toBe(2);
});

it('ne mélange pas les séries de deux utilisateurs', function () {
    $calls = 0;
    ['user' => $other] = propertyFixture();

    rememberRealEstate($this->user->id, $calls);
    rememberRealEstate($other->id, $calls);

    expect($calls)->toBe(2);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=LaravelRealEstateCache`
Expected: FAIL — `Class "App\Contexts\RealEstate\Infrastructure\LaravelRealEstateCache" not found`.

- [ ] **Step 3 : écrire le port**

```bash
php artisan make:class Contexts/RealEstate/Ports/RealEstateCachePort --no-interaction
```

Le remplacer par une interface :

```php
<?php

namespace App\Contexts\RealEstate\Ports;

use Closure;

interface RealEstateCachePort
{
    /**
     * Rend la série déjà calculée pour cet utilisateur, ou la calcule et la retient.
     *
     * L'implémentation décide seule quand un résultat est périmé : les actions n'ont ni clé ni
     * durée à fournir.
     */
    public function remember(string $name, int $userId, Closure $callback): mixed;
}
```

- [ ] **Step 4 : écrire l'implémentation**

```bash
php artisan make:class Contexts/RealEstate/Infrastructure/LaravelRealEstateCache --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Infrastructure;

use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyExpense;
use App\Contexts\RealEstate\Models\PropertyValuation;
use App\Contexts\RealEstate\Models\RentException;
use App\Contexts\RealEstate\Ports\RealEstateCachePort;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Les séries immobilières ne dépendent que des six tables du contexte et du jour courant. La clé
 * porte l'empreinte de ces sept ingrédients : un import, une migration ou une suppression en masse
 * la fait changer, et le résultat périmé n'est plus jamais lu.
 *
 * Lue dans les données et non posée par un observateur, comme `Valuation\Infrastructure\
 * LaravelSeriesCache` : `RealEstateDemoSeeder::purgeRelated()` supprime par le query builder, donc
 * aucun événement de modèle n'en part. Un observateur serait périmé dès le premier `db:seed`.
 *
 * Le jour courant fait partie de l'empreinte, contrairement à celle des titres : côté titres,
 * `Price::max('date')` avance de lui-même ; ici rien ne bouge, alors que l'escalier de valuation
 * et le capital restant dû se lisent à aujourd'hui. Sans la date, la série de lundi serait encore
 * servie vendredi.
 */
class LaravelRealEstateCache implements RealEstateCachePort
{
    private const TTL_SECONDS = 86400;

    /** @var array<int, string> */
    private array $stamps = [];

    public function remember(string $name, int $userId, Closure $callback): mixed
    {
        return Cache::remember(
            sprintf('immobilier.%s.%d.%s', $name, $userId, $this->stampFor($userId)),
            self::TTL_SECONDS,
            $callback,
        );
    }

    /**
     * Les sommes sont dans l'empreinte parce qu'`updated_at` ne descend pas sous la seconde : une
     * correction saisie dans la seconde qui suit la création ne se verrait pas sans elles.
     */
    private function stampFor(int $userId): string
    {
        return $this->stamps[$userId] ??= md5(implode('|', [
            Carbon::now()->toDateString(),
            $this->digest(Property::query()->where('user_id', $userId), [
                'coalesce(sum(acquisition_price), 0) as price',
                'coalesce(sum(acquisition_fees), 0) as fees',
                'max(acquisition_date) as last_acquisition',
            ]),
            $this->digest($this->scopedTo(PropertyValuation::query(), $userId), [
                'max(date) as last_date',
                'coalesce(sum(value), 0) as value',
            ]),
            $this->digest($this->scopedTo(Loan::query(), $userId), [
                'coalesce(sum(principal), 0) as principal',
                'coalesce(sum(annual_rate), 0) as rate',
                'coalesce(sum(term_months), 0) as term',
            ]),
            $this->digest($this->scopedTo(Lease::query(), $userId), [
                'coalesce(sum(monthly_rent), 0) as rent',
            ]),
            $this->digest($this->scopedTo(PropertyExpense::query(), $userId), [
                'coalesce(sum(amount), 0) as amount',
            ]),
            $this->digest(
                RentException::query()->whereIn(
                    'lease_id',
                    Lease::query()
                        ->whereIn('property_id', Property::query()->where('user_id', $userId)->select('id'))
                        ->select('id'),
                ),
                ['coalesce(sum(amount_override), 0) as override'],
            ),
        ]));
    }

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function scopedTo(Builder $query, int $userId): Builder
    {
        return $query->whereIn(
            'property_id',
            Property::query()->where('user_id', $userId)->select('id'),
        );
    }

    /**
     * Résumé d'une table : combien de lignes, quand la dernière a bougé, et les agrégats qui
     * distinguent deux états de même cardinalité.
     *
     * @param  Builder<*>  $query
     * @param  list<string>  $aggregates
     */
    private function digest(Builder $query, array $aggregates): string
    {
        $query = $query->selectRaw('count(*) as total')->selectRaw('max(updated_at) as touched');

        foreach ($aggregates as $aggregate) {
            $query = $query->selectRaw($aggregate);
        }

        return (string) json_encode($query->toBase()->first());
    }
}
```

- [ ] **Step 5 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=LaravelRealEstateCache`
Expected: PASS

- [ ] **Step 6 : écrire le provider et le brancher**

```bash
php artisan make:class Contexts/RealEstate/RealEstateProvider --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate;

use App\Contexts\RealEstate\Ports\RealEstateCachePort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class RealEstateProvider extends ServiceProvider
{
    /** @param  class-string<RealEstateCachePort>  $cache */
    public static function registers(Application $app, string $cache): void
    {
        /** L'empreinte des données est calculée une fois par requête : `scoped()` et non `singleton()`. */
        $app->scoped(RealEstateCachePort::class, $cache);
    }
}
```

Dans `app/Providers/AppServiceProvider.php`, après le bloc `ValuationProvider::registers(...)` :

```php
        RealEstateProvider::registers(
            app: $this->app,
            cache: LaravelRealEstateCache::class,
        );
```

avec les deux `use` correspondants.

- [ ] **Step 7 : envelopper le calcul de la série**

Dans `BuildRealEstateSeries`, ajouter `private RealEstateCachePort $cache` au constructeur, renommer le corps actuel de `__invoke()` en `private function build(int $userId): RealEstateSeriesData`, et écrire :

```php
    public function __invoke(int $userId): RealEstateSeriesData
    {
        return $this->cache->remember('serie', $userId, fn (): RealEstateSeriesData => $this->build($userId));
    }
```

- [ ] **Step 8 : vérifier que la série n'a pas changé de valeur**

Run: `php artisan test --compact --filter="BuildRealEstateSeries|LaravelRealEstateCache|GetRealEstate"`
Expected: PASS

- [ ] **Step 9 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/RealEstate app/Providers/AppServiceProvider.php
git commit -m "feat: retient les séries immobilières par empreinte"
```

---

### Task 6 : contexte `Wealth`

**Pourquoi `Wealth` et non `WealthView` :** `Valuation` et `Income` sont déjà des contextes sans table, qui ne vivent que de ports et portent leurs règles dans `Services/` — aucun des deux ne porte de suffixe. `InstrumentView` porte le sien parce que `Market\Models\Instrument` occupait déjà le nom `Instrument`. « Patrimoine » n'entre en collision avec rien.

**Files:**
- Create: `app/Contexts/Wealth/Datas/ClassSnapshotData.php`
- Create: `app/Contexts/Wealth/Datas/ClassSeriesData.php`
- Create: `app/Contexts/Wealth/Datas/AssetClassData.php`
- Create: `app/Contexts/Wealth/Datas/WealthOverviewData.php`
- Create: `app/Contexts/Wealth/Datas/WealthSeriesData.php`
- Create: `app/Contexts/Wealth/Datas/WealthIncomeData.php`
- Create: `app/Contexts/Wealth/Ports/HoldingsPort.php`
- Create: `app/Contexts/Wealth/Ports/SecuritiesSeriesPort.php`
- Create: `app/Contexts/Wealth/Ports/RealEstatePort.php`
- Create: `app/Contexts/Wealth/Ports/IncomePort.php`
- Create: `app/Contexts/Wealth/Infrastructure/PortfolioHoldings.php`
- Create: `app/Contexts/Wealth/Infrastructure/ValuationSeries.php`
- Create: `app/Contexts/Wealth/Infrastructure/RealEstateFinancials.php`
- Create: `app/Contexts/Wealth/Infrastructure/DividendIncome.php`
- Create: `app/Contexts/Wealth/Services/SeriesAligner.php`
- Create: `app/Contexts/Wealth/Services/SeriesAlignerTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthOverview.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthOverviewTest.php`
- Create: `app/Contexts/Wealth/Actions/BuildWealthSeries.php`
- Create: `app/Contexts/Wealth/Actions/BuildWealthSeriesTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthIncome.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthIncomeTest.php`
- Create: `app/Contexts/Wealth/WealthProvider.php`
- Modify: `app/Providers/AppServiceProvider.php` (ajoute `WealthProvider::registers`)

**Interfaces:**
- Consumes: `GetPortfolioOverview`, `BuildEvolutionSeries`, `GetRealEstateOverview`, `GetRealEstateCashInvested` (Task 3), `BuildRealEstateSeries` (Task 4), `GetIncomeSummary` avec filtre (Task 1).
- Produces:
  - `ClassSnapshotData { float $value; float $invested; }`
  - `ClassSeriesData { list<string> $labels; list<float> $value; list<float> $invested; }`, `::empty()`
  - `AssetClassData { float $value; float $invested; float $gain; ?float $gainPct; }`, `::from(ClassSnapshotData $snapshot): self`
  - `WealthOverviewData { float $totalValue; float $totalInvested; float $totalGain; ?float $totalGainPct; AssetClassData $securities; AssetClassData $realEstate; }`, `::empty()`
  - `WealthSeriesData { list<string> $labels; list<float> $securities; list<float> $realEstate; list<float> $invested; }`, `::empty()`
  - `WealthIncomeData { float $monthlyTotal; float $monthlyDividends; float $monthlyRentalNet; }`, `::empty()`
  - `SeriesAligner::union(array $left, array $right): array`, `::onto(array $labels, array $sourceLabels, array $values): array`
  - `GetWealthOverview::__invoke(int $userId): WealthOverviewData`
  - `BuildWealthSeries::__invoke(int $userId): WealthSeriesData`
  - `GetWealthIncome::__invoke(int $userId): WealthIncomeData`

- [ ] **Step 1 : écrire le test de `SeriesAligner`, qui échoue**

Créer `app/Contexts/Wealth/Services/SeriesAlignerTest.php` :

```php
<?php

use App\Contexts\Wealth\Services\SeriesAligner;

it('fait l\'union triée de deux jeux de labels sans doublon', function () {
    $aligner = new SeriesAligner;

    expect($aligner->union(['2026-01-05', '2026-01-19'], ['2026-01-12', '2026-01-19']))
        ->toBe(['2026-01-05', '2026-01-12', '2026-01-19']);
});

it('reporte la dernière valeur connue sur les labels intercalés', function () {
    $aligner = new SeriesAligner;

    $aligned = $aligner->onto(
        ['2026-01-05', '2026-01-12', '2026-01-19'],
        ['2026-01-05', '2026-01-19'],
        [100.0, 300.0],
    );

    /** Le 12 n'existe pas dans la source : il tient la valeur du 5, il ne l'interpole pas. */
    expect($aligned)->toBe([100.0, 100.0, 300.0]);
});

it('vaut zéro avant le premier point de la source', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], ['2026-01-12'], [50.0]))
        ->toBe([0.0, 50.0]);
});

it('rend une série de zéros quand la source est vide', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], [], []))->toBe([0.0, 0.0]);
});

it('garde la dernière valeur au-delà du dernier point de la source', function () {
    $aligner = new SeriesAligner;

    expect($aligner->onto(['2026-01-05', '2026-01-12'], ['2026-01-05'], [80.0]))
        ->toBe([80.0, 80.0]);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=SeriesAligner`
Expected: FAIL — `Class "App\Contexts\Wealth\Services\SeriesAligner" not found`.

- [ ] **Step 3 : écrire `SeriesAligner`**

```bash
php artisan make:class Contexts/Wealth/Services/SeriesAligner --no-interaction
```

```php
<?php

namespace App\Contexts\Wealth\Services;

/**
 * Réconcilie deux séries qui n'ont pas la même grille. Les titres se comptent depuis la première
 * transaction, l'immobilier depuis la plus ancienne acquisition : ni l'une ni l'autre ne peut
 * servir de grille commune sans tronquer l'autre.
 */
class SeriesAligner
{
    /**
     * @param  list<string>  $left
     * @param  list<string>  $right
     * @return list<string>
     */
    public function union(array $left, array $right): array
    {
        $labels = array_values(array_unique([...$left, ...$right]));
        sort($labels);

        return $labels;
    }

    /**
     * Report d'une série sur une autre grille : chaque label prend la dernière valeur connue de
     * date ≤ à lui, 0 avant le premier point. Jamais d'interpolation — inventer une valeur
     * intermédiaire ferait mentir un tracé dont la donnée est ponctuelle.
     *
     * @param  list<string>  $labels
     * @param  list<string>  $sourceLabels
     * @param  list<float>  $values
     * @return list<float>
     */
    public function onto(array $labels, array $sourceLabels, array $values): array
    {
        $aligned = [];
        $cursor = 0;
        $current = 0.0;

        foreach ($labels as $label) {
            while ($cursor < count($sourceLabels) && $sourceLabels[$cursor] <= $label) {
                $current = $values[$cursor] ?? $current;
                $cursor++;
            }

            $aligned[] = $current;
        }

        return $aligned;
    }
}
```

- [ ] **Step 4 : lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=SeriesAligner`
Expected: PASS

- [ ] **Step 5 : écrire les six DTO**

`app/Contexts/Wealth/Datas/ClassSnapshotData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

/** Ce qu'une classe d'actif vaut, et ce qu'elle a coûté en cash. */
readonly class ClassSnapshotData
{
    public function __construct(
        public float $value,
        public float $invested,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0);
    }
}
```

`app/Contexts/Wealth/Datas/ClassSeriesData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

/** Une classe d'actif dans le temps, sur sa propre grille de labels. */
readonly class ClassSeriesData
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $value
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $value,
        public array $invested,
    ) {}

    public static function empty(): self
    {
        return new self([], [], []);
    }
}
```

`app/Contexts/Wealth/Datas/AssetClassData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Une ligne de classe d'actif du résumé : sa valeur, sa mise, et l'écart entre les deux. */
readonly class AssetClassData implements JsonSerializable
{
    public function __construct(
        public float $value,
        public float $invested,
        public float $gain,
        public ?float $gainPct,
    ) {}

    /**
     * Le pourcentage est nul, et non zéro, quand la mise est nulle ou négative : un bien financé
     * à plus de 100 % n'a pas de mise à laquelle rapporter son gain, et « 0 % » mentirait.
     */
    public static function from(ClassSnapshotData $snapshot): self
    {
        $gain = round($snapshot->value - $snapshot->invested, 2);

        return new self(
            value: round($snapshot->value, 2),
            invested: round($snapshot->invested, 2),
            gain: $gain,
            gainPct: $snapshot->invested <= 0.0 ? null : round($gain / $snapshot->invested * 100, 2),
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'invested' => $this->invested,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
        ];
    }
}
```

`app/Contexts/Wealth/Datas/WealthOverviewData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Le patrimoine d'un utilisateur : son total, et ses deux classes d'actif. */
readonly class WealthOverviewData implements JsonSerializable
{
    public function __construct(
        public float $totalValue,
        public float $totalInvested,
        public float $totalGain,
        public ?float $totalGainPct,
        public AssetClassData $securities,
        public AssetClassData $realEstate,
    ) {}

    public static function empty(): self
    {
        $empty = AssetClassData::from(ClassSnapshotData::empty());

        return new self(0.0, 0.0, 0.0, null, $empty, $empty);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'totalValue' => $this->totalValue,
            'totalInvested' => $this->totalInvested,
            'totalGain' => $this->totalGain,
            'totalGainPct' => $this->totalGainPct,
            'securities' => $this->securities->jsonSerialize(),
            'realEstate' => $this->realEstate->jsonSerialize(),
        ];
    }
}
```

`app/Contexts/Wealth/Datas/WealthSeriesData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Le patrimoine dans le temps, les deux classes sur une grille commune : le graphe les empile,
 * donc leurs indices doivent se correspondre un à un.
 */
readonly class WealthSeriesData implements JsonSerializable
{
    /**
     * @param  list<string>  $labels
     * @param  list<float>  $securities
     * @param  list<float>  $realEstate
     * @param  list<float>  $invested
     */
    public function __construct(
        public array $labels,
        public array $securities,
        public array $realEstate,
        public array $invested,
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
            'securities' => $this->securities,
            'realEstate' => $this->realEstate,
            'invested' => $this->invested,
        ];
    }
}
```

`app/Contexts/Wealth/Datas/WealthIncomeData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/** Ce que le patrimoine laisse chaque mois, et d'où ça vient. */
readonly class WealthIncomeData implements JsonSerializable
{
    public function __construct(
        public float $monthlyTotal,
        public float $monthlyDividends,
        public float $monthlyRentalNet,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'monthlyTotal' => $this->monthlyTotal,
            'monthlyDividends' => $this->monthlyDividends,
            'monthlyRentalNet' => $this->monthlyRentalNet,
        ];
    }
}
```

- [ ] **Step 6 : écrire les quatre ports**

`app/Contexts/Wealth/Ports/HoldingsPort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSnapshotData;

interface HoldingsPort
{
    /** Valeur et prix de revient du portefeuille de titres. */
    public function snapshotFor(int $userId): ClassSnapshotData;
}
```

`app/Contexts/Wealth/Ports/SecuritiesSeriesPort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;

interface SecuritiesSeriesPort
{
    /** Valeur et investi des titres dans le temps, sur la grille du fournisseur. */
    public function seriesFor(int $userId): ClassSeriesData;
}
```

`app/Contexts/Wealth/Ports/RealEstatePort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;

interface RealEstatePort
{
    /** Patrimoine net et cash sorti du parc immobilier. */
    public function snapshotFor(int $userId): ClassSnapshotData;

    /** Patrimoine net et cash sorti dans le temps, sur la grille du fournisseur. */
    public function seriesFor(int $userId): ClassSeriesData;

    /** Cash-flow locatif net mensuel, après charges et échéances. */
    public function monthlyNetFor(int $userId): float;
}
```

`app/Contexts/Wealth/Ports/IncomePort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

interface IncomePort
{
    /**
     * Dividendes des douze derniers mois, mensualisés. Les loyers en sont exclus : ils arrivent
     * nets par `RealEstatePort`, et `Income` les compte bruts.
     */
    public function monthlyDividendsFor(int $userId): float;
}
```

- [ ] **Step 7 : écrire les quatre adaptateurs**

`app/Contexts/Wealth/Infrastructure/PortfolioHoldings.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\HoldingsPort;

class PortfolioHoldings implements HoldingsPort
{
    public function __construct(private GetPortfolioOverview $overview) {}

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return ClassSnapshotData::empty();
        }

        $overview = ($this->overview)($user);

        return new ClassSnapshotData(value: $overview->totalValue, invested: $overview->totalCost);
    }
}
```

`app/Contexts/Wealth/Infrastructure/ValuationSeries.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Datas\AssetSeriesData;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;

class ValuationSeries implements SecuritiesSeriesPort
{
    public function __construct(private BuildEvolutionSeries $evolution) {}

    public function seriesFor(int $userId): ClassSeriesData
    {
        /** Historique complet au pas hebdomadaire, comme le graphe du tableau de bord l'utilisait déjà. */
        $series = ($this->evolution)($userId, null, ValuationGranularity::Week);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->value, count($series->labels)),
            invested: $this->sum($series->perAsset, fn (AssetSeriesData $asset): array => $asset->invested, count($series->labels)),
        );
    }

    /**
     * @param  list<AssetSeriesData>  $perAsset
     * @param  callable(AssetSeriesData): list<float>  $pick
     * @return list<float>
     */
    private function sum(array $perAsset, callable $pick, int $length): array
    {
        $totals = array_fill(0, $length, 0.0);

        foreach ($perAsset as $asset) {
            foreach ($pick($asset) as $index => $amount) {
                $totals[$index] = ($totals[$index] ?? 0.0) + $amount;
            }
        }

        return array_map(fn (float $amount): float => round($amount, 2), $totals);
    }
}
```

`app/Contexts/Wealth/Infrastructure/RealEstateFinancials.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Actions\GetRealEstateCashInvested;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Datas\PropertyOverviewData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\RealEstatePort;

class RealEstateFinancials implements RealEstatePort
{
    public function __construct(
        private GetRealEstateOverview $overview,
        private GetRealEstateCashInvested $cashInvested,
        private BuildRealEstateSeries $series,
    ) {}

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        return new ClassSnapshotData(
            value: ($this->overview)($userId)->totalNetWorth,
            invested: ($this->cashInvested)($userId),
        );
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        $series = ($this->series)($userId);

        return new ClassSeriesData(
            labels: $series->labels,
            value: $series->netWorth,
            invested: $series->invested,
        );
    }

    public function monthlyNetFor(int $userId): float
    {
        $properties = ($this->overview)($userId)->properties;

        return round(array_sum(array_map(
            fn (PropertyOverviewData $property): float => $property->monthlyCashFlow,
            $properties,
        )), 2);
    }
}
```

`app/Contexts/Wealth/Infrastructure/DividendIncome.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Wealth\Ports\IncomePort;

class DividendIncome implements IncomePort
{
    public function __construct(private GetIncomeSummary $summary) {}

    public function monthlyDividendsFor(int $userId): float
    {
        /**
         * Filtré sur les dividendes : `Income` agrège aussi `IncomeSource::Rent`, et les loyers
         * arrivent nets par `RealEstatePort`. Sans le filtre, ils seraient comptés deux fois.
         */
        $summary = ($this->summary)($userId, IncomeSource::Dividend);

        return round($summary->last12Months / 12, 2);
    }
}
```

- [ ] **Step 8 : écrire le test de `GetWealthOverview`, qui échoue**

Créer `app/Contexts/Wealth/Actions/GetWealthOverviewTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\HoldingsPort;

function fakeHoldings(float $value, float $invested): void
{
    app()->bind(HoldingsPort::class, fn (): HoldingsPort => new class($value, $invested) implements HoldingsPort
    {
        public function __construct(private float $value, private float $invested) {}

        public function snapshotFor(int $userId): ClassSnapshotData
        {
            return new ClassSnapshotData($this->value, $this->invested);
        }
    });
}

it('additionne les deux classes d\'actif', function () {
    fakeHoldings(184200.0, 160000.0);
    ['user' => $user] = propertyFixture();

    $overview = app(GetWealthOverview::class)($user->id);

    expect($overview->totalValue)->toBe(round($overview->securities->value + $overview->realEstate->value, 2))
        ->and($overview->totalInvested)->toBe(round($overview->securities->invested + $overview->realEstate->invested, 2))
        ->and($overview->totalGain)->toBe(round($overview->totalValue - $overview->totalInvested, 2));
});

it('rend un pourcentage de gain nul quand rien n\'a été investi', function () {
    fakeHoldings(0.0, 0.0);

    $overview = app(GetWealthOverview::class)(999);

    expect($overview->totalInvested)->toBe(0.0)
        ->and($overview->totalGainPct)->toBeNull();
});

it('rend le patrimoine des titres seuls quand aucun bien n\'existe', function () {
    fakeHoldings(1000.0, 800.0);

    $overview = app(GetWealthOverview::class)(999);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->realEstate->value)->toBe(0.0)
        ->and($overview->securities->gainPct)->toBe(25.0);
});
```

- [ ] **Step 9 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=GetWealthOverview`
Expected: FAIL — `Target class [App\Contexts\Wealth\Ports\HoldingsPort] is not instantiable` ou classe absente.

- [ ] **Step 10 : écrire les trois actions**

`app/Contexts/Wealth/Actions/GetWealthOverview.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\AssetClassData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Ports\HoldingsPort;
use App\Contexts\Wealth\Ports\RealEstatePort;

/** Le patrimoine d'un utilisateur, toutes classes d'actif confondues. */
class GetWealthOverview
{
    public function __construct(
        private HoldingsPort $holdings,
        private RealEstatePort $realEstate,
    ) {}

    public function __invoke(int $userId): WealthOverviewData
    {
        $securities = $this->holdings->snapshotFor($userId);
        $realEstate = $this->realEstate->snapshotFor($userId);

        $total = new ClassSnapshotData(
            value: $securities->value + $realEstate->value,
            invested: $securities->invested + $realEstate->invested,
        );

        $totals = AssetClassData::from($total);

        return new WealthOverviewData(
            totalValue: $totals->value,
            totalInvested: $totals->invested,
            totalGain: $totals->gain,
            totalGainPct: $totals->gainPct,
            securities: AssetClassData::from($securities),
            realEstate: AssetClassData::from($realEstate),
        );
    }
}
```

`app/Contexts/Wealth/Actions/BuildWealthSeries.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthSeriesData;
use App\Contexts\Wealth\Ports\RealEstatePort;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use App\Contexts\Wealth\Services\SeriesAligner;

/** Le patrimoine dans le temps, les deux classes empilables sur une grille commune. */
class BuildWealthSeries
{
    public function __construct(
        private SecuritiesSeriesPort $securities,
        private RealEstatePort $realEstate,
        private SeriesAligner $aligner,
    ) {}

    public function __invoke(int $userId): WealthSeriesData
    {
        $securities = $this->securities->seriesFor($userId);
        $realEstate = $this->realEstate->seriesFor($userId);

        $labels = $this->aligner->union($securities->labels, $realEstate->labels);

        if ($labels === []) {
            return WealthSeriesData::empty();
        }

        $securitiesValue = $this->aligner->onto($labels, $securities->labels, $securities->value);
        $realEstateValue = $this->aligner->onto($labels, $realEstate->labels, $realEstate->value);
        $securitiesInvested = $this->aligner->onto($labels, $securities->labels, $securities->invested);
        $realEstateInvested = $this->aligner->onto($labels, $realEstate->labels, $realEstate->invested);

        return new WealthSeriesData(
            labels: $labels,
            securities: $securitiesValue,
            realEstate: $realEstateValue,
            invested: array_map(
                fn (float $left, float $right): float => round($left + $right, 2),
                $securitiesInvested,
                $realEstateInvested,
            ),
        );
    }
}
```

`app/Contexts/Wealth/Actions/GetWealthIncome.php` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Ports\IncomePort;
use App\Contexts\Wealth\Ports\RealEstatePort;

/**
 * Ce que le patrimoine laisse chaque mois. Les dividendes sont mensualisés sur douze mois
 * glissants, le locatif est déjà net de charges et d'échéances.
 */
class GetWealthIncome
{
    public function __construct(
        private IncomePort $income,
        private RealEstatePort $realEstate,
    ) {}

    public function __invoke(int $userId): WealthIncomeData
    {
        $dividends = $this->income->monthlyDividendsFor($userId);
        $rentalNet = $this->realEstate->monthlyNetFor($userId);

        return new WealthIncomeData(
            monthlyTotal: round($dividends + $rentalNet, 2),
            monthlyDividends: $dividends,
            monthlyRentalNet: $rentalNet,
        );
    }
}
```

- [ ] **Step 11 : écrire le provider et le brancher**

`app/Contexts/Wealth/WealthProvider.php` :

```php
<?php

namespace App\Contexts\Wealth;

use App\Contexts\Wealth\Ports\HoldingsPort;
use App\Contexts\Wealth\Ports\IncomePort;
use App\Contexts\Wealth\Ports\RealEstatePort;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WealthProvider extends ServiceProvider
{
    /**
     * @param  class-string<HoldingsPort>  $holdings
     * @param  class-string<SecuritiesSeriesPort>  $securitiesSeries
     * @param  class-string<RealEstatePort>  $realEstate
     * @param  class-string<IncomePort>  $income
     */
    public static function registers(
        Application $app,
        string $holdings,
        string $securitiesSeries,
        string $realEstate,
        string $income,
    ): void {
        $app->bind(HoldingsPort::class, $holdings);
        $app->bind(SecuritiesSeriesPort::class, $securitiesSeries);
        $app->bind(IncomePort::class, $income);

        /**
         * Le résumé, la série et les revenus interrogent tous les trois le parc immobilier dans la
         * même requête : `scoped()` évite de refaire trois fois les mêmes lectures.
         */
        $app->scoped(RealEstatePort::class, $realEstate);
    }
}
```

Dans `AppServiceProvider::register()`, après `IncomeProvider::registers(...)` :

```php
        WealthProvider::registers(
            app: $this->app,
            holdings: WealthPortfolioHoldings::class,
            securitiesSeries: ValuationSeries::class,
            realEstate: RealEstateFinancials::class,
            income: DividendIncome::class,
        );
```

Le `use` de `Wealth\Infrastructure\PortfolioHoldings` entre en collision avec celui de `InstrumentView\Infrastructure\PortfolioHoldings` déjà importé : l'aliaser à l'import.

```php
use App\Contexts\Wealth\Infrastructure\PortfolioHoldings as WealthPortfolioHoldings;
```

- [ ] **Step 12 : lancer le test de `GetWealthOverview`**

Run: `php artisan test --compact --filter=GetWealthOverview`
Expected: PASS

- [ ] **Step 13 : écrire les tests de `BuildWealthSeries` et `GetWealthIncome`**

`app/Contexts/Wealth/Actions/BuildWealthSeriesTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Ports\SecuritiesSeriesPort;
use Illuminate\Support\Carbon;

function fakeSecuritiesSeries(ClassSeriesData $series): void
{
    app()->bind(SecuritiesSeriesPort::class, fn (): SecuritiesSeriesPort => new class($series) implements SecuritiesSeriesPort
    {
        public function __construct(private ClassSeriesData $series) {}

        public function seriesFor(int $userId): ClassSeriesData
        {
            return $this->series;
        }
    });
}

it('rend une série vide quand ni les titres ni l\'immobilier n\'ont d\'historique', function () {
    fakeSecuritiesSeries(ClassSeriesData::empty());

    $series = app(BuildWealthSeries::class)(999);

    expect($series->labels)->toBe([]);
});

it('étend la grille aux labels de l\'immobilier quand un bien précède la première transaction', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    /** Les titres ne commencent qu'en août ; le bien a été acquis vingt mois plus tôt. */
    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03', '2026-08-10'],
        value: [1000.0, 1100.0],
        invested: [900.0, 900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);

    expect($series->labels[0])->toBeLessThan('2026-08-03')
        ->and($series->securities[0])->toBe(0.0)
        ->and($series->realEstate[0])->toBe(0.0);
});

it('reporte la dernière valeur des titres sur les labels de l\'immobilier', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $last = count($series->labels) - 1;

    expect($series->securities[$last])->toBe(1000.0);
});

it('donne aux trois séries la longueur de la grille', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    fakeSecuritiesSeries(new ClassSeriesData(
        labels: ['2026-08-03'],
        value: [1000.0],
        invested: [900.0],
    ));

    $series = app(BuildWealthSeries::class)($user->id);
    $length = count($series->labels);

    expect($series->securities)->toHaveCount($length)
        ->and($series->realEstate)->toHaveCount($length)
        ->and($series->invested)->toHaveCount($length);
});
```

`app/Contexts/Wealth/Actions/GetWealthIncomeTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Ports\IncomePort;
use Illuminate\Support\Carbon;

function fakeMonthlyDividends(float $amount): void
{
    app()->bind(IncomePort::class, fn (): IncomePort => new class($amount) implements IncomePort
    {
        public function __construct(private float $amount) {}

        public function monthlyDividendsFor(int $userId): float
        {
            return $this->amount;
        }
    });
}

it('additionne les dividendes mensualisés et le locatif net', function () {
    Carbon::setTestNow('2026-08-21');
    fakeMonthlyDividends(102.0);
    ['user' => $user] = propertyFixture(['loan' => true]);

    $income = app(GetWealthIncome::class)($user->id);

    expect($income->monthlyDividends)->toBe(102.0)
        ->and($income->monthlyTotal)->toBe(round(102.0 + $income->monthlyRentalNet, 2));
});

it('vaut zéro sans dividende ni bien', function () {
    fakeMonthlyDividends(0.0);

    $income = app(GetWealthIncome::class)(999);

    expect($income->monthlyTotal)->toBe(0.0)
        ->and($income->monthlyRentalNet)->toBe(0.0);
});
```

- [ ] **Step 14 : lancer tous les tests du contexte**

Run: `php artisan test --compact --filter=Wealth`
Expected: PASS

- [ ] **Step 15 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth app/Providers/AppServiceProvider.php
git commit -m "feat: réunit titres et immobilier en un patrimoine"
```

---

### Task 7 : page dédiée aux titres

**Files:**
- Create: `app/Contexts/InstrumentView/Http/InstrumentsController.php` (copie de `Portfolio\Http\DashboardController`)
- Create: `resources/js/Pages/Instruments/Index.vue`
- Move: `resources/js/components/dashboard/{Valuation,Evolution,Instruments,Performances,Income,Sectors}Section.vue` → `resources/js/components/instruments/`
- Modify: `resources/js/Pages/Dashboard.vue` (imports vers `components/instruments/`)
- Modify: `routes/web.php` (ajoute `/instruments`, laisse `/` intact)
- Create: `tests/Feature/InstrumentsPageTest.php`

**Interfaces:**
- Consumes: `GetPortfolioOverview`, `GetHoldingTrends`, `BuildPortfolioPerformances`, `BuildEvolutionSeries`, `GetSectorBreakdown`, `GetIncomeSummary`, `GetAnnualIncome` — tous déjà appelés par le contrôleur actuel.
- Produces: route nommée `instruments.index` servant le composant Inertia `Instruments/Index`.

**Pourquoi une copie et non un déplacement.** `/` doit continuer de rendre `Dashboard` jusqu'à Task 10, sinon l'assertion ordonnée des sections dans `tests/Browser/SmokeTest.php` casse pendant trois commits. `Portfolio\Http\DashboardController` reste donc en place et intact ; Task 10 le supprime en même temps qu'elle rend `/` à `Wealth`. Deux contrôleurs identiques le temps de trois commits, contre une application cassée : le choix est fait.

- [ ] **Step 1 : copier le contrôleur**

```bash
cp app/Contexts/Portfolio/Http/DashboardController.php \
   app/Contexts/InstrumentView/Http/InstrumentsController.php
```

Dans la copie : `namespace App\Contexts\InstrumentView\Http;`, `class InstrumentsController`, et `Inertia::render('Instruments/Index', [...])`. Filtrer les deux props de revenus sur les dividendes — la page s'intitule « Titres », y afficher des loyers serait faux :

```php
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetIncomeSummary::class)($user->id, IncomeSource::Dividend)
                : IncomeSummaryData::empty(), 'revenus'),
            'annualIncome' => Inertia::defer(fn () => $user !== null
                ? app(GetAnnualIncome::class)($user->id, IncomeSource::Dividend)
                : [], 'revenus'),
```

avec `use App\Contexts\Income\Enums\IncomeSource;`.

- [ ] **Step 2 : déplacer les six sections**

```bash
mkdir -p resources/js/components/instruments
git mv resources/js/components/dashboard/ValuationSection.vue    resources/js/components/instruments/
git mv resources/js/components/dashboard/EvolutionSection.vue    resources/js/components/instruments/
git mv resources/js/components/dashboard/InstrumentsSection.vue  resources/js/components/instruments/
git mv resources/js/components/dashboard/PerformancesSection.vue resources/js/components/instruments/
git mv resources/js/components/dashboard/IncomeSection.vue       resources/js/components/instruments/
git mv resources/js/components/dashboard/SectorsSection.vue      resources/js/components/instruments/
```

Aucun corps de composant ne change : les attributs `data-section` et `data-*` restent identiques, donc les sélecteurs des tests navigateur survivent.

- [ ] **Step 3 : écrire la page Titres**

`resources/js/Pages/Instruments/Index.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import EvolutionSection from '@/components/instruments/EvolutionSection.vue';
import IncomeSection from '@/components/instruments/IncomeSection.vue';
import InstrumentsSection from '@/components/instruments/InstrumentsSection.vue';
import PerformancesSection from '@/components/instruments/PerformancesSection.vue';
import SectorsSection from '@/components/instruments/SectorsSection.vue';
import ValuationSection from '@/components/instruments/ValuationSection.vue';
import type { CatalogTrend } from '@/lib/catalog';
import type { AnnualIncome, IncomeSummary } from '@/lib/income';
import type { Performance } from '@/lib/performance';
import type { EvolutionSeries, PortfolioOverview } from '@/lib/portfolio';
import type { SectorSlice } from '@/lib/sector';

defineProps<{
    overview: PortfolioOverview;
    trends?: CatalogTrend[];
    performances?: Performance[];
    evolutionSeries?: EvolutionSeries;
    sectorBreakdown?: SectorSlice[];
    income?: IncomeSummary;
    annualIncome?: AnnualIncome[];
}>();
</script>

<template>
    <Head title="Titres" />

    <AppPage>
        <ValuationSection v-if="overview.holdings.length" :overview="overview" />

        <EvolutionSection :series="evolutionSeries" />

        <InstrumentsSection :holdings="overview.holdings" :trends="trends" />

        <PerformancesSection v-if="overview.holdings.length" :performances="performances" />

        <IncomeSection v-if="overview.holdings.length" :income="income" :annual-income="annualIncome" />

        <SectorsSection v-if="overview.holdings.length" :slices="sectorBreakdown" />
    </AppPage>

    <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Titres' }]" />
</template>
```

- [ ] **Step 4 : router `/instruments`, sans toucher à `/`**

Dans `routes/web.php`, ajouter l'import `use App\Contexts\InstrumentView\Http\InstrumentsController;` et la route, **après** la ligne de `/` qui ne change pas :

```php
Route::get('/instruments', InstrumentsController::class)->name('instruments.index');
```

Dans `resources/js/Pages/Dashboard.vue`, faire pointer les six imports vers `@/components/instruments/…`. Le corps du template ne change pas : `/` rend toujours les mêmes sections dans le même ordre.

- [ ] **Step 5 : copier les cas titres dans le test de la nouvelle page**

Créer `tests/Feature/InstrumentsPageTest.php` en y **copiant**, depuis `tests/Feature/DashboardPageTest.php`, tous les cas qui portent sur les titres — valorisation, positions, tendances, performances, évolution, secteurs, revenus. Y remplacer `$this->get('/')` par `$this->get('/instruments')` et `->component('Dashboard')` par `->component('Instruments/Index')`. Ne réécrire **aucune** assertion.

Copier et non déplacer : `/` sert encore `Dashboard`, donc les cas d'origine doivent continuer de passer. Task 10 les supprime de `DashboardPageTest.php` en même temps qu'elle réécrit la page.

- [ ] **Step 6 : lancer les tests de page et le typecheck**

Run: `php artisan test --compact --filter="InstrumentsPage|DashboardPage"`
Expected: PASS

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes tests
git commit -m "feat: ouvre une page dédiée aux titres"
```

---

### Task 8 : page `/properties` et fils d'Ariane à trois crans

**Files:**
- Create: `app/Contexts/RealEstate/Http/PropertiesController.php`
- Create: `resources/js/Pages/Properties/Index.vue`
- Create: `resources/js/components/properties/RealEstateSummarySection.vue`
- Create: `resources/js/components/properties/PropertyList.vue`
- Modify: `resources/js/Pages/Properties/Detail.vue:58-63` (fil à trois crans)
- Modify: `resources/js/Pages/Instruments/Show.vue:58-63` (fil à trois crans)
- Modify: `routes/web.php`
- Create: `tests/Feature/PropertiesPageTest.php`
- Modify: `tests/Browser/BreadcrumbTest.php`
- Modify: `tests/Browser/SmokeTest.php`

**Interfaces:**
- Consumes: `GetRealEstateOverview`.
- Produces: route nommée `properties.index` servant le composant Inertia `Properties/Index` avec la prop `realEstate: RealEstateOverviewData`.

- [ ] **Step 1 : écrire le test de page, qui échoue**

Créer `tests/Feature/PropertiesPageTest.php` :

```php
<?php

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('rend la page Immobilier avec les biens de l\'utilisateur', function () {
    Carbon::setTestNow('2026-08-21');
    propertyFixture(['loan' => true]);

    $this->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('realEstate.properties', 1)
            ->where('realEstate.properties.0.name', 'T2 Lyon 7e')
        );
});

it('rend la page Immobilier vide sans aucun bien', function () {
    App\Contexts\Identity\Models\User::factory()->create();

    $this->get('/properties')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Properties/Index')
            ->has('realEstate.properties', 0)
        );
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=PropertiesPage`
Expected: FAIL — 404 sur `/properties`.

- [ ] **Step 3 : écrire le contrôleur**

```bash
php artisan make:class Contexts/RealEstate/Http/PropertiesController --no-interaction
```

```php
<?php

namespace App\Contexts\RealEstate\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Datas\RealEstateOverviewData;
use Inertia\Inertia;
use Inertia\Response;

class PropertiesController
{
    public function __construct(private GetRealEstateOverview $overview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        /**
         * Synchrone et non différé : c'est le grand chiffre de la page, et le différer le ferait
         * sauter à l'arrivée.
         */
        return Inertia::render('Properties/Index', [
            'realEstate' => $user !== null
                ? ($this->overview)($user->id)
                : RealEstateOverviewData::empty(),
        ]);
    }
}
```

Dans `routes/web.php` :

```php
Route::get('/properties', PropertiesController::class)->name('properties.index');
```

placée **avant** `/properties/{id}`.

- [ ] **Step 4 : découper `RealEstateSection.vue` en deux composants**

`resources/js/components/properties/RealEstateSummarySection.vue` — le haut de l'ancien composant :

```vue
<script setup lang="ts">
import { eur } from '@/lib/format';
import type { RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview }>();
</script>

<template>
    <section data-section="real-estate-summary" class="flex shrink-0 flex-col gap-1.5 px-6">
        <p data-real-estate-net class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
            {{ eur(props.realEstate.totalNetWorth, 0) }}
        </p>

        <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
            <span class="whitespace-nowrap">
                Estimé
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalValue, 0) }}
                </strong>
            </span>
            <span class="whitespace-nowrap">
                Restant dû
                <strong class="font-semibold text-foreground tabular-nums">
                    {{ eur(props.realEstate.totalRemaining, 0) }}
                </strong>
            </span>
        </p>
    </section>
</template>
```

`resources/js/components/properties/PropertyList.vue` — le bas, la liste :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { eur } from '@/lib/format';
import type { PropertyOverview } from '@/lib/realEstate';

const props = defineProps<{ properties: PropertyOverview[] }>();

const hasProperties = computed<boolean>(() => props.properties.length > 0);
</script>

<template>
    <section data-section="real-estate" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Biens</h2>

        <ul v-if="hasProperties" class="flex flex-col gap-2">
            <li v-for="property in props.properties" :key="property.id" data-property-row>
                <Link
                    :href="`/properties/${property.id}`"
                    prefetch
                    class="flex items-center justify-between gap-3 rounded-md px-2 py-2 text-sm hover:bg-muted"
                >
                    <span class="truncate font-medium">{{ property.name }}</span>
                    <span class="flex shrink-0 items-center gap-3 tabular-nums">
                        <span class="text-muted-foreground">{{ eur(property.monthlyCashFlow) }}/mois</span>
                        <span class="font-semibold">{{ eur(property.netWorth) }}</span>
                    </span>
                </Link>
            </li>
        </ul>

        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Aucun bien immobilier pour l'instant.
        </p>
    </section>
</template>
```

**Ne pas** supprimer `resources/js/components/dashboard/RealEstateSection.vue` ici, et **ne pas** toucher `Dashboard.vue`. L'ancien composant vit un commit de plus : Task 10 réécrit la page et l'emporte avec elle. L'y remplacer maintenant ajouterait une section `real-estate-summary` à `/`, ce qui casserait l'assertion ordonnée du smoke test pour un état qui meurt au commit suivant.

- [ ] **Step 5 : écrire la page Immobilier**

`resources/js/Pages/Properties/Index.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppBreadcrumb from '@/components/AppBreadcrumb.vue';
import AppPage from '@/components/AppPage.vue';
import PropertyList from '@/components/properties/PropertyList.vue';
import RealEstateSummarySection from '@/components/properties/RealEstateSummarySection.vue';
import type { RealEstateOverview } from '@/lib/realEstate';

const props = defineProps<{ realEstate: RealEstateOverview }>();
</script>

<template>
    <Head title="Immobilier" />

    <AppPage>
        <RealEstateSummarySection :real-estate="props.realEstate" />

        <PropertyList :properties="props.realEstate.properties" />
    </AppPage>

    <AppBreadcrumb :items="[{ label: 'Tableau de bord', href: '/' }, { label: 'Immobilier' }]" />
</template>
```

- [ ] **Step 6 : poser les fils d'Ariane à trois crans**

`resources/js/Pages/Properties/Detail.vue` :

```vue
    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Immobilier', href: '/properties' },
            { label: props.property.name },
        ]"
    />
```

`resources/js/Pages/Instruments/Show.vue` :

```vue
    <AppBreadcrumb
        :items="[
            { label: 'Tableau de bord', href: '/' },
            { label: 'Titres', href: '/instruments' },
            { label: props.instrument.name },
        ]"
    />
```

- [ ] **Step 7 : étendre les tests navigateur**

Dans `tests/Browser/BreadcrumbTest.php`, le dernier cas — « affiche le fil d'Ariane complet sur une fiche instrument » — devient faux : il vérifie que le fil ne contient que deux crans. Le remplacer, et ajouter son pendant immobilier :

```php
it('affiche le fil d\'Ariane complet sur une fiche instrument', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Tableau de bord')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Titres')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'ACME')
        ->assertNoJavaScriptErrors();
});

it('affiche le fil d\'Ariane complet sur une fiche de bien', function () {
    ['user' => $user, 'property' => $property] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit("/properties/{$property->id}")
        ->assertVisible('nav[aria-label="Fil d\'Ariane"]')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Tableau de bord')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'Immobilier')
        ->assertSeeIn('nav[aria-label="Fil d\'Ariane"]', 'T2 Lyon 7e')
        ->assertNoJavaScriptErrors();
});
```

Dans `tests/Browser/SmokeTest.php`, ajouter les deux pages de liste :

```php
it('charge la page Titres, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|instruments|performances|income|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la page Immobilier et ses sections, sans erreur', function () {
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->actingAs($user);

    visit('/properties')
        ->assertSee('Biens')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'real-estate-summary|real-estate',
        )
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 8 : lancer les tests et le typecheck**

Run: `php artisan test --compact --filter="PropertiesPage|Breadcrumb|Smoke"`
Expected: PASS

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 9 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes tests
git commit -m "feat: ouvre une page par classe d'actif"
```

---

### Task 9 : graphe empilé du patrimoine

**Files:**
- Modify: `resources/css/app.css:89` (ajoute `--chart-real-estate` dans les deux thèmes) et `:51` (expose `--color-chart-real-estate`)
- Modify: `resources/js/lib/chart.ts:28-62` (`palette()` gagne `realEstate`), `:504` (nouveau constructeur), et le commentaire de `buildValueVsInvestedOption` qui affirme être seul
- Modify: `resources/js/lib/chart.test.ts`
- Modify: `resources/js/lib/chart.dark.test.ts`
- Create: `resources/js/lib/wealth.ts`

**Interfaces:**
- Consumes: `chartFrame`, `chartTooltip`, `tooltipTitle`, `tooltipRow`, `pointIndex`, `lastYearWindow`, `palette` — tous déjà dans `lib/chart.ts`.
- Produces: `buildWealthStackOption(input: WealthStackInput): ChartOption` où `WealthStackInput = { labels: string[]; securities: number[]; realEstate: number[]; invested: number[]; valueFormatter: ValueFormatter; window: ZoomWindow | null; description: string }` ; et les types de `lib/wealth.ts` : `AssetClass`, `WealthOverview`, `WealthSeries`, `WealthIncome`.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `resources/js/lib/chart.test.ts` :

```ts
describe('buildWealthStackOption', () => {
    const input = {
        labels: ['2026-01-05', '2026-01-12'],
        securities: [1000, 1100],
        realEstate: [500, 520],
        invested: [1400, 1400],
        valueFormatter: (amount: number): string => `${amount} €`,
        window: null,
        description: 'Patrimoine total.',
    };

    it('empile les deux classes sur la même clé', () => {
        const option = buildWealthStackOption(input);
        const series = option.series as { name: string; stack?: string }[];

        expect(series).toHaveLength(2);
        expect(series[0].stack).toBe(series[1].stack);
        expect(series.map((serie) => serie.name)).toEqual(['Titres', 'Immobilier']);
    });

    it('donne au sommet de la pile le patrimoine total', () => {
        const option = buildWealthStackOption(input);
        const series = option.series as { data: [string, number][] }[];

        const top = series[0].data[1][1] + series[1].data[1][1];

        expect(top).toBe(1620);
    });

    it('garde la mini-timeline de zoom', () => {
        const option = buildWealthStackOption(input);

        expect(option.dataZoom).toHaveLength(1);
    });
});
```

Ajouter `buildWealthStackOption` à l'import de `@/lib/chart` en tête du fichier.

Ajouter à `resources/js/lib/chart.dark.test.ts` — le fichier existe précisément pour vérifier la branche sombre de `palette()` :

```ts
it('donne aux deux bandes du patrimoine des couleurs distinctes en thème sombre', () => {
    const option = buildWealthStackOption({
        labels: ['2026-01-05', '2026-01-12'],
        securities: [1000, 1100],
        realEstate: [500, 520],
        invested: [1400, 1400],
        valueFormatter: (amount: number): string => `${amount} €`,
        window: null,
        description: 'Patrimoine total.',
    });

    const series = option.series as { areaStyle: { color: string } }[];

    expect(series[0].areaStyle.color).not.toBe(series[1].areaStyle.color);
    expect(series[1].areaStyle.color).toBe('#e0a75f');
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `bun run test:js -- chart`
Expected: FAIL — `buildWealthStackOption is not a function`.

- [ ] **Step 3 : ajouter la couleur**

Dans `resources/css/app.css`, à côté de `--sector-bar` dans **chacun** des deux blocs de thème :

```css
/* thème clair */
--chart-real-estate: #b3701a;
/* thème sombre */
--chart-real-estate: #e0a75f;
```

Un ocre : distinct de l'indigo des titres (`value`), du vert du gain, du rouge de la perte et du gris de l'investi. Un vert ou un cyan aurait disputé la couleur du gain, qui apparaît dans la même infobulle.

Dans le bloc `@theme`, à côté de `--color-sector-bar` :

```css
    --color-chart-real-estate: var(--chart-real-estate);
```

Dans `resources/js/lib/chart.ts`, ajouter `realEstate: string` au type `ChartPalette`, puis `realEstate: '#e0a75f'` dans la branche sombre et `realEstate: '#b3701a'` dans la branche claire de `palette()` — la recopie à la main est la convention déjà commentée au-dessus de la fonction.

- [ ] **Step 4 : écrire le constructeur d'option**

Dans `resources/js/lib/chart.ts`, après `buildValueVsInvestedOption` :

```ts
export type WealthStackInput = {
    labels: string[];
    securities: number[];
    realEstate: number[];
    invested: number[];
    valueFormatter: ValueFormatter;
    window: ZoomWindow | null;
    description: string;
};

/** Une bande par classe d'actif, empilées : le sommet de la pile est le patrimoine total. */
function wealthStackSeries(labels: string[], securities: number[], realEstate: number[]): LineSeriesOption[] {
    const colors = palette();

    const band = (name: string, values: number[], color: string): LineSeriesOption => ({
        name,
        type: 'line',
        stack: 'patrimoine',
        smooth: true,
        symbol: 'none',
        sampling: 'lttb',
        lineStyle: { width: 1.5, color },
        areaStyle: { color, opacity: 0.35 },
        data: datedPoints(labels, values),
    });

    return [
        band('Titres', securities, colors.value),
        band('Immobilier', realEstate, colors.realEstate),
    ];
}

function wealthStackTooltip(
    labels: string[],
    securities: number[],
    realEstate: number[],
    invested: number[],
    valueFormatter: ValueFormatter,
): TooltipComponentOption {
    const colors = palette();

    return {
        ...chartTooltip(),
        formatter: (params: unknown): string => {
            const index = pointIndex(params);
            if (index === null) {
                return '';
            }

            const securitiesValue = securities[index] ?? 0;
            const realEstateValue = realEstate[index] ?? 0;
            const totalValue = securitiesValue + realEstateValue;
            const totalInvested = invested[index] ?? 0;
            const gain = totalValue - totalInvested;

            return tooltipTitle(labels[index] ?? '')
                + tooltipRow(colors.value, 'Patrimoine', valueFormatter(totalValue))
                + tooltipRow(colors.value, 'Titres', valueFormatter(securitiesValue))
                + tooltipRow(colors.realEstate, 'Immobilier', valueFormatter(realEstateValue))
                + tooltipRow(colors.invested, 'Investi', valueFormatter(totalInvested))
                + tooltipRow(
                    gain >= 0 ? colors.gain : colors.loss,
                    gain >= 0 ? 'Gain' : 'Perte',
                    `${gain >= 0 ? '+' : '−'} ${valueFormatter(Math.abs(gain))}`,
                );
        },
    };
}

/**
 * Le graphe du tableau de bord : deux aires empilées, une par classe d'actif. Forme distincte de
 * `buildValueVsInvestedOption`, qui ne trace qu'une courbe — le patrimoine se lit par sa
 * composition, un instrument par sa trajectoire.
 */
export function buildWealthStackOption(
    { labels, securities, realEstate, invested, valueFormatter, window, description }: WealthStackInput,
): ChartOption {
    const visible = window ?? lastYearWindow(labels);
    const totals = labels.map((_, index: number): number => (securities[index] ?? 0) + (realEstate[index] ?? 0));

    return {
        ...chartFrame({ valueFormatter, values: totals, bottom: ZOOM_SLIDER_HEIGHT + TIME_AXIS_LABEL_HEIGHT, description }),
        series: wealthStackSeries(labels, securities, realEstate),
        tooltip: wealthStackTooltip(labels, securities, realEstate, invested, valueFormatter),
        dataZoom: [wealthZoomSlider(visible)],
    };
}
```

`wealthZoomSlider` n'est pas à écrire : c'est l'objet littéral `{ type: 'slider', … }` déjà présent dans le tableau `dataZoom` de `buildValueVsInvestedOption` (`lib/chart.ts:530-556`), déplacé **verbatim** dans une fonction, commentaires compris :

```ts
/** La mini-timeline est la seule commande de zoom, partagée par les deux formes de graphe. */
function wealthZoomSlider(visible: ZoomWindow): NonNullable<ChartOption['dataZoom']>[number] {
    // ← le littéral existant, déplacé tel quel, `start: visible.start` / `end: visible.end` inclus
}
```

Puis remplacer, dans `buildValueVsInvestedOption`, `dataZoom: [ { …le littéral… } ]` par `dataZoom: [wealthZoomSlider(visible)]`. La dupliquer ferait diverger deux zooms qui doivent se comporter pareil.

Corriger le commentaire de `buildValueVsInvestedOption` : il affirme être « seul et même pour le tableau de bord et la fiche instrument », ce qui devient faux. Le remplacer par une phrase disant qu'il sert `/instruments` et les fiches, et que le tableau de bord empile par `buildWealthStackOption`.

- [ ] **Step 5 : lancer les tests front**

Run: `bun run test:js -- chart`
Expected: PASS

- [ ] **Step 6 : écrire les types du patrimoine**

`resources/js/lib/wealth.ts` :

```ts
/** Une classe d'actif du résumé. `gainPct` est nul quand rien n'a été investi. */
export interface AssetClass {
    value: number;
    invested: number;
    gain: number;
    gainPct: number | null;
}

export interface WealthOverview {
    totalValue: number;
    totalInvested: number;
    totalGain: number;
    totalGainPct: number | null;
    securities: AssetClass;
    realEstate: AssetClass;
}

/** Les trois séries partagent `labels` : le graphe empile leurs indices un à un. */
export interface WealthSeries {
    labels: string[];
    securities: number[];
    realEstate: number[];
    invested: number[];
}

export interface WealthIncome {
    monthlyTotal: number;
    monthlyDividends: number;
    monthlyRentalNet: number;
}
```

- [ ] **Step 7 : typecheck puis commit**

```bash
bun run typecheck
git add resources
git commit -m "feat: empile titres et immobilier sur le graphe du patrimoine"
```

---

### Task 10 : résumé du patrimoine en tête du tableau de bord

**Files:**
- Create: `app/Contexts/Wealth/Http/DashboardController.php`
- Delete: `app/Contexts/Portfolio/Http/DashboardController.php`
- Create: `resources/js/components/dashboard/WealthSummarySection.vue`
- Create: `resources/js/components/dashboard/WealthEvolutionSection.vue`
- Delete: `resources/js/components/dashboard/RealEstateSection.vue`
- Modify: `resources/js/Pages/Dashboard.vue` (réécrit)
- Modify: `routes/web.php` (`/` passe à `Wealth`)
- Modify: `tests/Feature/DashboardPageTest.php`
- Modify: `tests/Browser/DashboardTest.php`

**Interfaces:**
- Consumes: `GetWealthOverview`, `BuildWealthSeries` (Task 6) ; `buildWealthStackOption`, types de `lib/wealth.ts` (Task 9).
- Produces: route `dashboard` servant le composant `Dashboard` avec `overview: WealthOverviewData` (synchrone) et `series: WealthSeriesData` (différée, groupe `evolution`).

- [ ] **Step 1 : écrire le test de page, qui échoue**

Réécrire `tests/Feature/DashboardPageTest.php` autour du résumé — les cas titres qu'il contenait vivent depuis Task 7 dans `InstrumentsPageTest.php`, et les cas immobilier dans `PropertiesPageTest.php` depuis Task 8 :

```php
<?php

use App\Contexts\Identity\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('rend un patrimoine vide sans aucune donnée', function () {
    User::factory()->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('overview.totalValue', 0.0)
            ->where('overview.totalGainPct', null)
        );
});

it('additionne les titres et l\'immobilier dans le grand chiffre', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('overview.securities')
            ->has('overview.realEstate')
            ->where('overview.totalValue', fn (float $total): bool => $total > 0.0)
        );
});

it('diffère la série du patrimoine', function () {
    Carbon::setTestNow('2026-08-21');
    propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->missing('series')
        );
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DashboardPage`
Expected: FAIL — `/` sert encore l'ancien `Dashboard`, dont les props sont `overview.holdings` et non `overview.securities`.

- [ ] **Step 3 : écrire le contrôleur**

```bash
php artisan make:class Contexts/Wealth/Http/DashboardController --no-interaction
```

```php
<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSeriesData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(private GetWealthOverview $overview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        return Inertia::render('Dashboard', [
            /**
             * Synchrone : c'est le grand chiffre, et le différer le ferait sauter à l'arrivée.
             * C'est aussi ce qui le place dans le document initial, donc dans le cache du service
             * worker, donc lisible hors-ligne.
             */
            'overview' => $user !== null
                ? ($this->overview)($user->id)
                : WealthOverviewData::empty(),
            'series' => Inertia::defer(fn () => $user !== null
                ? app(BuildWealthSeries::class)($user->id)
                : WealthSeriesData::empty(), 'evolution'),
        ]);
    }
}
```

Dans `routes/web.php`, rendre `/` à `Wealth` :

```php
use App\Contexts\Wealth\Http\DashboardController;

Route::get('/', DashboardController::class)->name('dashboard');
```

L'ancien import `use App\Contexts\Portfolio\Http\DashboardController;` disparaît en même temps que son fichier :

```bash
git rm app/Contexts/Portfolio/Http/DashboardController.php
```

`Portfolio` n'a alors plus de dossier `Http/` — c'est le but : il redevient un contexte de domaine pur.

- [ ] **Step 4 : écrire la section du résumé**

`resources/js/components/dashboard/WealthSummarySection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import GainPill from '@/components/GainPill.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import type { AssetClass, WealthOverview } from '@/lib/wealth';

const props = defineProps<{ overview: WealthOverview }>();

const eur = (value: number | null): string => formatEur(value, 0);

interface ClassLine {
    label: string;
    href: string;
    entry: AssetClass;
}

/** Une classe sans valeur ne montre pas sa ligne : une ligne à zéro n'apprend rien. */
const lines = computed<ClassLine[]>(() => [
    { label: 'Titres', href: '/instruments', entry: props.overview.securities },
    { label: 'Immobilier', href: '/properties', entry: props.overview.realEstate },
].filter((line: ClassLine): boolean => line.entry.value !== 0));
</script>

<template>
    <section data-section="wealth-summary" class="flex shrink-0 flex-col gap-4">
        <div class="flex flex-col gap-1.5 px-6">
            <div class="flex flex-wrap items-baseline gap-3">
                <p data-wealth-value class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                    {{ eur(props.overview.totalValue) }}
                </p>
                <GainPill
                    v-if="props.overview.totalGainPct !== null"
                    data-wealth-gain-pct
                    :value="props.overview.totalGain"
                    :label="pct(props.overview.totalGainPct)"
                />
            </div>

            <p class="flex flex-wrap gap-x-5 gap-y-1 text-[13.5px] text-muted-foreground">
                <span class="whitespace-nowrap">
                    Investi
                    <strong class="font-semibold text-foreground tabular-nums">
                        {{ eur(props.overview.totalInvested) }}
                    </strong>
                </span>
                <span class="whitespace-nowrap">
                    Gain
                    <strong class="font-semibold tabular-nums" :class="gainClass(props.overview.totalGain)">
                        {{ signedEur(props.overview.totalGain, 0) }}
                    </strong>
                </span>
            </p>
        </div>

        <ul v-if="lines.length" class="flex flex-col gap-1 px-3">
            <li v-for="line in lines" :key="line.label" data-wealth-class>
                <Link
                    :href="line.href"
                    prefetch
                    class="flex items-center justify-between gap-3 rounded-md px-3 py-2.5 text-sm hover:bg-muted"
                >
                    <span class="font-medium">{{ line.label }}</span>
                    <span class="flex shrink-0 items-center gap-3 tabular-nums">
                        <span class="font-semibold">{{ eur(line.entry.value) }}</span>
                        <ChevronRight class="size-4 text-muted-foreground" />
                    </span>
                </Link>
            </li>
        </ul>

        <p v-else class="px-6 py-8 text-center text-sm text-muted-foreground">
            Aucun patrimoine pour l'instant.
        </p>
    </section>
</template>
```

- [ ] **Step 5 : écrire la section du graphe**

`resources/js/components/dashboard/WealthEvolutionSection.vue` :

```vue
<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import ChartSkeleton from '@/components/ChartSkeleton.vue';
import { buildWealthStackOption, type ZoomWindow } from '@/lib/chart';
import { eur } from '@/lib/format';
import type { ChartOption } from '@/lib/echarts';
import type { WealthSeries } from '@/lib/wealth';

const props = defineProps<{ series?: WealthSeries }>();

/** Echarts pèse les deux tiers du JS : il n'est demandé qu'au montage réel d'un graphe. */
const BaseChart = defineAsyncComponent({
    loader: () => import('@/components/BaseChart.vue'),
    loadingComponent: ChartSkeleton,
    delay: 0,
});

/** Non réactive, comme sur la fiche instrument : la rendre réactive repeindrait à chaque pixel. */
let lastZoom: ZoomWindow | null = null;

const rememberZoom = (window: ZoomWindow): void => {
    lastZoom = window;
};

const labels = computed<string[]>(() => props.series?.labels ?? []);

const hasHistory = computed<boolean>(() => labels.value.length > 0);

const option = computed<ChartOption>(() => buildWealthStackOption({
    labels: labels.value,
    securities: props.series?.securities ?? [],
    realEstate: props.series?.realEstate ?? [],
    invested: props.series?.invested ?? [],
    valueFormatter: (amount: number): string => eur(amount, 0),
    window: lastZoom,
    description: 'Patrimoine total, titres et immobilier empilés, comparé au montant investi.',
}));
</script>

<template>
    <section data-section="wealth-evolution" class="flex shrink-0 flex-col gap-4">
        <h2 class="px-6 text-[17px] leading-none font-bold">Évolution</h2>

        <Deferred data="series">
            <template #fallback>
                <div class="px-6">
                    <ChartSkeleton />
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <div v-if="hasHistory" class="px-6">
                <BaseChart :option="option" @zoom="rememberZoom" />
            </div>
            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Pas encore d'historique de valorisation.
            </p>
        </Deferred>
    </section>
</template>
```

- [ ] **Step 6 : réécrire la page**

`resources/js/Pages/Dashboard.vue` :

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppPage from '@/components/AppPage.vue';
import WealthEvolutionSection from '@/components/dashboard/WealthEvolutionSection.vue';
import WealthSummarySection from '@/components/dashboard/WealthSummarySection.vue';
import type { WealthOverview, WealthSeries } from '@/lib/wealth';

const props = defineProps<{
    overview: WealthOverview;
    series?: WealthSeries;
}>();
</script>

<template>
    <Head title="Tableau de bord" />

    <!-- Pas de fil d'Ariane : le tableau de bord est la racine, son fil n'aurait qu'un seul cran. -->
    <AppPage>
        <WealthSummarySection :overview="props.overview" />

        <WealthEvolutionSection :series="props.series" />
    </AppPage>
</template>
```

Supprimer `resources/js/components/dashboard/RealEstateSection.vue`, désormais sans appelant : `PropertyList.vue` et `RealEstateSummarySection.vue` l'ont remplacé sur `/properties` en Task 8.

- [ ] **Step 7 : réduire les tests navigateur du tableau de bord**

Déplacer vers un nouveau `tests/Browser/InstrumentsTest.php` tous les cas de `tests/Browser/DashboardTest.php` qui portent sur les sections titres, en y remplaçant `visit('/')` par `visit('/instruments')`. Ne réécrire aucune assertion.

Dans `tests/Browser/SmokeTest.php`, le premier cas assertait l'ordre exact des sections de `/` : `'valuation|evolution|instruments|performances|income|real-estate|sectors'`. Le corriger :

```php
it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Investi')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'wealth-summary|wealth-evolution',
        )
        ->assertNoJavaScriptErrors();
});
```

Ce cas gagne `wealth-income` en Task 11 : `'wealth-summary|wealth-evolution|wealth-income'`.

Le premier cas de `tests/Browser/BreadcrumbTest.php` — « se passe de fil d'Ariane sur le tableau de bord » — assertait `assertSee('Performances')`, qui a quitté `/`. Remplacer par `assertSee('Investi')`.

Dans `tests/Browser/DashboardTest.php`, ne garder que le résumé, et y ajouter le cas des deux liens de classe :

```php
it('mène de chaque classe d\'actif à sa page', function () {
    ['user' => $user] = portfolioFixture();

    /** `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du portefeuille. */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/')
        ->assertVisible('[data-wealth-value]')
        ->assertSeeIn('[data-section="wealth-summary"]', 'Titres')
        ->click('a[href="/instruments"]')
        ->assertSee('Performances')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 8 : lancer les tests et le typecheck**

Run: `php artisan test --compact --filter="DashboardPage|Dashboard|Instruments"`
Expected: PASS

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 9 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources routes tests
git commit -m "feat: pose le résumé du patrimoine en tête du tableau de bord"
```

---

### Task 11 : revenu mensuel combiné

**Files:**
- Create: `resources/js/components/dashboard/WealthIncomeSection.vue`
- Modify: `app/Contexts/Wealth/Http/DashboardController.php` (prop `income` différée)
- Modify: `resources/js/Pages/Dashboard.vue`
- Modify: `tests/Feature/DashboardPageTest.php`

**Interfaces:**
- Consumes: `GetWealthIncome` (Task 6), type `WealthIncome` (Task 9).
- Produces: prop `income: WealthIncomeData`, différée dans le groupe `revenus`.

- [ ] **Step 1 : écrire le test qui échoue**

Ajouter à `tests/Feature/DashboardPageTest.php` :

```php
it('diffère le revenu mensuel et n\'y compte les loyers qu\'une fois', function () {
    Carbon::setTestNow('2026-08-21');
    ['user' => $user] = propertyFixture(['loan' => true]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('income'));

    $income = app(App\Contexts\Wealth\Actions\GetWealthIncome::class)($user->id);

    /**
     * `Income` agrège aussi `IncomeSource::Rent`, brut. Le total du patrimoine additionne les
     * dividendes filtrés et le locatif **net** : il ne peut donc pas atteindre le loyer brut.
     */
    expect($income->monthlyTotal)->toBe(round($income->monthlyDividends + $income->monthlyRentalNet, 2))
        ->and($income->monthlyRentalNet)->toBeLessThan(600.0);
});
```

- [ ] **Step 2 : lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DashboardPage`
Expected: FAIL — la prop `income` n'existe pas, `missing('income')` passe mais l'action n'est pas câblée à la page.

- [ ] **Step 3 : ajouter la prop différée**

Dans `Wealth\Http\DashboardController::__invoke()`, après `series` :

```php
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetWealthIncome::class)($user->id)
                : WealthIncomeData::empty(), 'revenus'),
```

avec les deux `use` correspondants.

- [ ] **Step 4 : écrire la section**

`resources/js/components/dashboard/WealthIncomeSection.vue` :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import { eur, gainClass, signedEur } from '@/lib/format';
import type { WealthIncome } from '@/lib/wealth';

const props = defineProps<{ income?: WealthIncome }>();

const hasIncome = computed<boolean>(() => (props.income?.monthlyTotal ?? 0) !== 0);
</script>

<template>
    <!--
        Un seul chiffre net : les dividendes des douze derniers mois mensualisés, plus le locatif
        déjà net de charges et d'échéances. Le détail par origine se lit sur /instruments et
        /properties.
    -->
    <section data-section="wealth-income" class="flex min-h-0 flex-1 flex-col gap-4 px-6 md:flex-none">
        <h2 class="shrink-0 text-[17px] leading-none font-bold">Revenus</h2>

        <Deferred data="income">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 3" :key="n" class="h-8 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <template v-if="hasIncome">
                <p class="flex flex-wrap items-baseline gap-2">
                    <span
                        data-income-monthly
                        class="text-2xl font-bold tabular-nums"
                        :class="gainClass(props.income?.monthlyTotal ?? 0)"
                    >
                        {{ signedEur(props.income?.monthlyTotal ?? 0) }}
                    </span>
                    <span class="text-[13.5px] text-muted-foreground">par mois</span>
                </p>

                <ul class="flex flex-col gap-2 text-sm">
                    <li data-income-origin class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Dividendes</span>
                        <span class="font-semibold tabular-nums">
                            {{ eur(props.income?.monthlyDividends ?? 0) }}
                        </span>
                    </li>
                    <li data-income-origin class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Locatif net</span>
                        <span
                            class="font-semibold tabular-nums"
                            :class="gainClass(props.income?.monthlyRentalNet ?? 0)"
                        >
                            {{ signedEur(props.income?.monthlyRentalNet ?? 0) }}
                        </span>
                    </li>
                </ul>
            </template>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucun revenu pour l'instant.
            </p>
        </Deferred>
    </section>
</template>
```

- [ ] **Step 5 : brancher la section sur la page**

Dans `resources/js/Pages/Dashboard.vue`, importer `WealthIncomeSection` et l'ajouter après `WealthEvolutionSection`, et ajouter `income?: WealthIncome` aux props.

Dans `tests/Browser/SmokeTest.php`, la liste ordonnée des sections de `/` gagne son troisième élément :

```php
            'wealth-summary|wealth-evolution|wealth-income',
```

- [ ] **Step 6 : lancer les tests et le typecheck**

Run: `php artisan test --compact --filter="DashboardPage|Wealth|Smoke"`
Expected: PASS

Run: `bun run typecheck`
Expected: aucune erreur.

- [ ] **Step 7 : Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources tests
git commit -m "feat: réunit dividendes et loyers nets en un revenu mensuel"
```

---

### Task 12 : documentation et règles

**Files:**
- Modify: `docs/architecture.md` (carte des contextes : `Wealth`)
- Modify: `docs/page-data.md` (props par page pour les trois pages)

**Interfaces:**
- Consumes: tout ce qui précède.
- Produces: rien de code.

- [ ] **Step 1 : décrire le contexte dans `docs/architecture.md`**

Ajouter `Wealth` à la carte des contextes, en suivant la forme des entrées existantes : ses quatre ports, ses trois actions, l'absence de `Models/`, et la raison du nom sans suffixe (`Valuation` et `Income` sont déjà des contextes dérivés sans suffixe ; `InstrumentView` porte le sien parce que `Market\Models\Instrument` occupait le nom).

- [ ] **Step 2 : décrire les props dans `docs/page-data.md`**

Trois entrées à mettre à jour ou créer, en suivant la forme du fichier :

| Page | Synchrone | Différé |
| --- | --- | --- |
| `/` | `overview` | `series` (`evolution`), `income` (`revenus`) |
| `/instruments` | `overview` | `trends` (`tendances`), `performances`, `evolutionSeries` (`evolution`), `sectorBreakdown` (`secteurs`), `income` + `annualIncome` (`revenus`) |
| `/properties` | `realEstate` | — |

Y noter que `/instruments` filtre ses revenus sur `IncomeSource::Dividend`, et pourquoi.

- [ ] **Step 3 : commit**

```bash
git add docs
git commit -m "docs: décrit le contexte patrimoine et ses pages"
```

- [ ] **Step 4 : enregistrer les deux règles**

Via l'outil MCP `record-rule` de Boost, deux appels :

Glob `app/Contexts/RealEstate/**`, titre « L'investi d'un bien à crédit est le cash sorti » :

> L'investi d'un bien acheté à crédit vaut `apport + cash injecté`, où `apport = prix + frais − capital emprunté` et `cash injecté = Σ_mois max(0, −net)`. Jamais `apport + capital remboursé` : l'échéance qui sort de la poche contient déjà ce capital, donc la seconde formule le compte deux fois dès qu'un mois est déficitaire, et elle compte comme une mise le capital remboursé par le locataire — qui doit apparaître en gain, c'est le levier. `GetRealEstateCashInvested` porte la formule.

Glob `app/Contexts/Wealth/**`, titre « Péremption par empreinte, jamais par observer » :

> La péremption des séries dérivées se lit dans les données, jamais par un observateur Eloquent. `RealEstateDemoSeeder::purgeRelated()` supprime en masse par le query builder, donc aucun événement de modèle n'en part, et le dépôt contient déjà des `upsert()` et `DB::table()->insert()` ailleurs. Voir `LaravelRealEstateCache` et son aîné `Valuation\Infrastructure\LaravelSeriesCache`. L'empreinte immobilière porte le jour courant, contrairement à celle des titres : `Price::max('date')` avance de lui-même, le parc immobilier non.

- [ ] **Step 5 : passe finale sur toute la suite**

```bash
vendor/bin/pint --format agent
php artisan test --compact
bun run test:js
bun run typecheck
```

Expected: tout vert. Si `tests/Browser` échoue sur un asset manquant, lancer `bun run build` d'abord.

---

## Notes d'exécution

**Ordre non négociable.** Task 1 avant Task 6 (le filtre par source), Task 2 avant Tasks 3 et 4 (le service de cash-flow), Task 4 avant Task 5 (le cache enveloppe la série), Task 6 avant Tasks 10 et 11 (les actions avant leurs pages), Task 7 avant Task 10 (`/` reste servi pendant le déplacement). Tasks 8 et 9 sont indépendantes l'une de l'autre.

**L'état transitoire de `/`.** Task 7 **copie** le contrôleur des titres au lieu de le déplacer, et laisse `/` rendre l'ancien `Dashboard` intact. Task 10 supprime l'original et rend `/` à `Wealth`. Ce doublon dure trois commits ; sans lui, l'assertion ordonnée des sections dans `tests/Browser/SmokeTest.php` et les cas titres de `DashboardPageTest.php` casseraient pendant tout l'intervalle. Même raison pour les assertions dupliquées entre `DashboardPageTest.php` et `InstrumentsPageTest.php` sur cet intervalle, et pour `components/dashboard/RealEstateSection.vue` qui survit jusqu'à Task 10.

Le tableau des commits du spec décrit l'état final, pas ces échafaudages : c'est ce plan qui fait foi sur l'ordre et le contenu de chaque commit.

**Ce que le plan ne fait pas.** Aucune table de projection immobilière — le cache par empreinte suffit tant qu'aucun lecteur ne requête *à travers* les biens. Aucun onglet de navigation. Aucune modification du calcul des performances ni de la répartition sectorielle. La fiche d'un bien continue d'afficher `Investi = coût d'acquisition` : c'est une autre notion que le cash sorti, et la changer n'est pas dans ce périmètre.
