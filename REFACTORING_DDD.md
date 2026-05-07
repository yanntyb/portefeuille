# Plan Refactoring DDD - Argent

## Status: UPDATED (basé recherches Filament + DDD 2025)

Stratégie transformation argent vers Domain-Driven Design. Cible: éliminer antipatterns critiques, établir boundaries clairs, centraliser logique métier.

**Sources**: [Filament modular architecture](https://filamentphp.com/docs/5.x/advanced/modular-architecture), [SensioLabs DDD guide](https://sensiolabs.com/blog/2025/applying-domain-driven-design-in-php-and-symfony-a-hands-on-guide), [Service Layer pattern](https://medium.com/@jorisvdaalsvoort/mastering-the-service-layer-pattern-in-php-best-practices-for-maintainable-applications-0a8ba24d2df5)

---

## TDD Workflow (MANDATORY)

Toute modification doit suivre TDD. Pas d'exceptions.

### Pattern
1. **Write test** (RED) - test échoue
2. **Write implementation** (GREEN) - test passe
3. **Refactor** (REFACTOR) - improve, test re-passe

### Par Phase

**Phase 1.1 - Value Objects**:
```bash
# Test QuantityValue validation
php artisan test --filter=QuantityValueTest

# Implement QuantityValue
# Test passes

# Refactor if needed
php artisan test --filter=QuantityValueTest
```

**Phase 1.2 - Events**:
```bash
# Test TransactionCreated dispatched
php artisan test --filter=TransactionCreatedTest

# Implement dispatch in Service
# Test passes
```

**Phase 1.3 - Filament Plugins**:
```bash
# Test plugin registers resources
php artisan test --filter=PortfolioPluginTest

# Implement plugin registration
# Test passes
```

**Phase 1.4 - Listeners**:
```bash
# Test listener updates realized_gain
php artisan test --filter=CalculateRealizedGainListenerTest

# Implement listener
# Test passes
```

### Run Tests
```bash
# Compact output, fail fast
php artisan test --compact

# Filter par phase
php artisan test --filter=Phase1 --compact

# Coverage check
php artisan test --coverage
```

### Before Commit
- [ ] `php artisan test --compact` passes
- [ ] New tests added (1+ per feature)
- [ ] Coverage maintained >80% for Domain
- [ ] No failing tests
- [ ] `vendor/bin/phpstan analyse --memory-limit=1G` passes
- [ ] No boundary violations (cross-domain imports)
- [ ] `vendor/bin/pint --dirty --format agent` (code formatting)

---

## PHPStan Configuration

Level 2 enforced. Boundary rules pour DDD.

### Run PHPStan
```bash
# Full analysis
vendor/bin/phpstan analyse --memory-limit=1G

# Specific domain
vendor/bin/phpstan analyse app/Domains/Portfolio --memory-limit=1G

# Baseline update (après intentional changes)
vendor/bin/phpstan analyse --generate-baseline
```

### Boundary Rules (phpstan.neon)

```neon
# Forbidden cross-domain imports
- type: forbiddenCall
  message: "Portfolio cannot import from Analytics"
  call: "Analytics\*"
  in: "app/Domains/Portfolio/*"

- type: forbiddenCall
  message: "Services must use Repository interface, not Model"
  call: "Model::query()"
  in: "app/Domains/*/Services/*"
```

### Before Commit
```bash
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Fail = no commit.

---

## IMPORTANT: No Auto-Commits

**NEVER commit automatically.** User commits only.

- Claude ne doit pas `git add` / `git commit` / `git push` sans approbation explicite
- Chaque commit = intentionnel, message clair, pourquoi pas juste quoi
- User décide quand committer, pas Claude
- Tests + phpstan passent avant USER commit request

### Workflow
1. Claude: write code + run tests/phpstan
2. Claude: show diff, ask "commit this?"
3. User: "yes, commit" ou "changes first"
4. Claude: commit with message only IF user approves

Respecter cette rule strictement.

---

## DDD + Filament: Essentiel vs Overkill

### ESSENTIEL
✓ **Modules autonomes par Bounded Context** (Filament support officiel)
✓ **Value Objects** (validation métier, intégrité données)
✓ **Domain Events** (audit, async, side-effects explicites)
✓ **Interface Abstraction** (decoupling, testabilité)
✓ **Filament Plugins par module** (auto-registration, conditional panels)

### OVERKILL (skip pour argent)
❌ **Services partout** - Services utiles = logique métier complexe. CRUD simple = Eloquent direct.
❌ **Repositories everywhere** - Utiles = complex queries + swap possible. Skip simple Models.
❌ **Domain Events partout** - Events = audit + async. Skip mutations simples.
❌ **Multiple abstraction layers** - Filament → Service → Repository → Event. Trop.
❌ **Service Interfaces everywhere** - Interfaces = testigabilité. Utiles = complexe seulement.

### PRAGMATIQUE ARGENT

- **Modules** ✓ OUI → Portfolio, Security, Analytics, User
- **Value Objects** ✓ OUI → Transaction (Qty, Price), Allocation
- **Domain Events** ✓ OUI → TransactionCreated, PriceUpdated, etc
- **Filament Plugins** ✓ OUI → Module par plugin (auto-registration)
- **Services** ⚠️ SELECTIVE → Portfolio metrics, Rebalancing, Volatility (skip CRUD simple)
- **Repositories** ⚠️ SELECTIVE → Transaction, Security, SecurityPrice (complexe seulement)
- **Service Interfaces** ⚠️ SOME → Complexe seulement (skip partout)
- **Query Objects** ⚠️ SOME → scopeForAuth (12 lignes), skip simples where
- **Actions/Commands** ⚠️ SOME → UseCase métier, Filament CreateRecord OK

---

## Phase 1: FONDATIONS (Semaine 1-2)

### 1.1 Value Objects pour Transaction Métier

**Objectif**: Encapsuler règles métier. Garantir validité données.

**Antipatterns fixés**:
- ❌ Validation dispersée (Form + Model + Filament)
- ❌ Possible créer vente avant achat
- ❌ Pas d'invariants garantis

**Implémentation**:
```php
app/Domains/Portfolio/ValueObjects/
  QuantityValue.php        (>= 0, integer)
  TransactionPrice.php     (>= 0, decimal 2)
  PortfolioAllocation.php  (0-100%, sum=100)

// Usage
class Transaction extends Model {
  protected function casts(): array {
    return [
      'quantity' => QuantityValue::class,
      'price' => TransactionPrice::class,
    ];
  }
}
```

**Étapes**:
1. Créer QuantityValue (validation, casting)
2. Créer TransactionPrice (validation, decimal)
3. Créer PortfolioAllocation (range, sum rules)
4. Tests: chaque VO testé isolément

**Effort**: ~2-3 jours

---

### 1.2 Event System & Domain Events

**Objectif**: Publier événements métier explicites.

**Antipatterns fixés**:
- ❌ Zéro événements métier
- ❌ dispatch() Livewire seulement

**Implémentation**:
```
app/
  Events/
    Domain/
      TransactionCreated.php
      TransactionCancelled.php
      PriceUpdated.php
      PortfolioRebalanced.php
```

**Étapes**:
1. Créer base `DomainEvent` abstract
2. Identifier 6+ événements critiques (TransactionCreated, PriceUpdated, etc)
3. Dispatcher depuis Services (pas Models)
4. Tests: chaque événement publié au moment attendu

**Fichiers à modifier**: 
- Domains/Portfolio/Services/CreateTransactionService.php
- Domains/Security/Services/UpdatePriceService.php

**Effort**: ~2-3 jours

---

### 1.3 Filament Plugins per Module

**Objectif**: Organiser Filament resources par module. Auto-registration.

**Antipatterns fixés**:
- ❌ Filament registration = centralisé
- ❌ Modules = tight couplage à Filament

**Implémentation** (Filament v5 recommandé):
```php
// app-modules/portfolio/PortfolioPlugin.php
class PortfolioPlugin extends Plugin {
  public function register(): void {
    // Auto-register resources, pages, widgets
  }
}

// bootstrap/app.php
Panel::make()
  ->registerPlugins([
    PortfolioPlugin::class,
    SecurityPlugin::class,
    AnalyticsPlugin::class,
  ])
```

**Étapes**:
1. Créer PortfolioPlugin
2. Créer SecurityPlugin
3. Créer AnalyticsPlugin
4. Enregistrer via Panel::registerPlugins()
5. Tests: resources accessible via admin panel

**Fichiers**:
- app/Domains/Portfolio/PortfolioPlugin.php (new)
- app/Domains/Security/SecurityPlugin.php (new)
- app/Domains/Analytics/AnalyticsPlugin.php (new)
- bootstrap/app.php (register)

**Effort**: ~1-2 jours

---

### 1.4 Domain Event Listeners

**Objectif**: Logique métier = Events + Listeners, pas Observers.

**Antipatterns fixés**:
- ❌ Logique métier dans TransactionObserver
- ❌ Non-idempotent side-effects
- ❌ Invisible, difficile auditer

**Implémentation**:
```php
// Listener pour realized_gain
class CalculateRealizedGainListener {
  public function handle(TransactionCreated $event): void {
    if ($event->transaction->isSell()) {
      $gain = GainCalculator::calculate($event->transaction);
      $event->transaction->update(['realized_gain' => $gain->amount]);
    }
  }
}

// Register bootstrap/app.php
->withEvents(
  CalculateRealizedGainListener::class
)
```

**Étapes**:
1. Supprimer TransactionObserver
2. Créer CalculateRealizedGainListener
3. Register listeners dans bootstrap/app.php
4. Tests: TransactionCreated → realized_gain calculé

**Fichiers**:
- Supprimer: Domains/Portfolio/Observers/TransactionObserver.php
- Créer: Domains/Portfolio/Listeners/CalculateRealizedGainListener.php

**Effort**: ~1-2 jours

---

## Phase 2: DOMAINE ISOLÉ (Semaine 2-3)

### 2.1 Retirer auth() des GlobalScopes

**Objectif**: Models = objets de domaine purs. Context externe = parameter.

**Antipatterns fixés**:
- ❌ auth()->id() dans GlobalScopes
- ❌ withoutGlobalScope() everywhere
- ❌ Models non-testables hors HTTP

**Implémentation**:
```php
// Avant
class Wallet extends Model {
  protected static function booted() {
    static::addGlobalScope('auth_user', fn($q) => 
      $q->where('user_id', auth()->id())
    );
  }
}

// Après - pas de GlobalScope
class Wallet extends Model {}

// Query explicite depuis Filament/Service
Transaction::query()->where('user_id', $userId)->get();
```

**Étapes**:
1. Retirer GlobalScopes: Wallet, Transaction
2. Passer user_id depuis Controllers/Filament
3. Vérifier tests passent sans GlobalScope

**Fichiers**:
- Domains/Portfolio/Models/Wallet.php
- Domains/Portfolio/Models/Transaction.php

**Effort**: ~1-2 jours

---

### 2.2 Selective Repositories (complexe queries)

**Objectif**: Data access = abstrait pour queries complexes.

**Antipatterns fixés**:
- ❌ 12-line SQL scopes dans Models
- ❌ Queries directes dans Filament Forms

**Implémentation** (SÉLECTIF):
```php
// Pour complexe seulement
interface TransactionRepositoryInterface {
  public function findByIdForUser(int $id, int $userId): ?Transaction;
  public function forUser(int $userId): Collection;
}

class EloquentTransactionRepository implements TransactionRepositoryInterface {
  public function forUser(int $userId): Collection {
    return Transaction::query()
      ->where('user_id', $userId)
      ->with('security')
      ->get();
  }
}

// Injection
class PortfolioService {
  public function __construct(private TransactionRepositoryInterface $txs) {}
}
```

**Étapes**:
1. Créer repos SEULEMENT pour: Transaction, Security, SecurityPrice
2. Skip repos pour: Wallet, User (simple CRUD)
3. Injecter dans Services
4. Tests: mock repositories

**Fichiers**:
- Domains/Portfolio/Contracts/TransactionRepositoryInterface.php
- Domains/Portfolio/Infrastructure/EloquentTransactionRepository.php
- Domains/Security/Contracts/SecurityRepositoryInterface.php

**Effort**: ~2-3 jours

---

## Phase 3: DOMAINE BOUNDARIES & SERVICES (Semaine 3-4)

### 3.1 Service Layer pour Logique Métier Complexe

**Objectif**: Centraliser logique métier complexe dans Services.

**Antipatterns fixés**:
- ❌ Logique dispersée (Controllers, Pages, Filament)
- ❌ 373 lignes AccountPage
- ❌ 695 lignes SimulationBoardWidget

**Implémentation** (SÉLECTIF):
```php
// Services = logique métier complexe SEULEMENT
class PortfolioPerformanceService {
  public function computeMetrics(int $userId): PortfolioMetrics {
    // CAGR, volatility, allocation, etc.
  }
}

class RebalancingCalculator {
  public function calculate(Portfolio $portfolio): RebalancingPlan {
    // Complex algorithm
  }
}

// Pages = présentation
class AccountPage extends Page {
  #[Computed]
  public function metrics(): PortfolioMetrics {
    return PortfolioPerformanceService::make()->computeMetrics($this->user->id);
  }
}
```

**Étapes**:
1. Créer PortfolioPerformanceService (extraire AccountPage logique)
2. Créer RebalancingDisplayService (extraire SimulationBoardWidget)
3. Injecter dans Pages/Resources
4. Réduire Pages à <150 lignes
5. Tests: Services independently

**Fichiers**:
- Domains/Portfolio/Services/PortfolioPerformanceService.php
- Domains/Analytics/Services/RebalancingDisplayService.php
- Refactor AccountPage, SimulationBoardWidget

**Effort**: ~2-3 jours

---

### 3.2 Domain Boundaries & Anti-Corruption

**Objectif**: Clair import rules. Pas circular dependencies.

**Antipatterns fixés**:
- ❌ Portfolio imports 32x Security
- ❌ Analytics ↔ Portfolio circular

**Implémentation**:
```
Bounded Contexts:
  User (auth, roles)
  Portfolio (transactions, positions, wallets)
  Security (tickers, prices, metadata)
  Analytics (calculations, simulations)

Communication:
  TransactionCreated event
    → Analytics listens, updates caches
    → No direct import

RULES:
  ✗ Portfolio ← Analytics (imports)
  ✗ Analytics → Portfolio\Models (imports)
  ✓ Shared: Events, Enums, Contracts
```

**Étapes**:
1. Auditer cross-domain imports
2. Créer app/Shared/Events/, Enums/, Contracts/
3. Remplacer Model imports par Events
4. phpstan.neon: forbid cross-domain imports
5. Tests: boundary violations fail

**Fichiers**:
- app/Shared/Events/DomainEvent.php
- app/Shared/Enums/TransactionType.php
- phpstan.neon (add boundary check)

**Effort**: ~2-3 jours

---

## Phase 4: TESTING & REFINEMENT (Semaine 4-5)

### 4.1 Domain Layer Tests

**Objectif**: Value Objects, Rules, Services testés. >80% coverage.

**Étapes**:
1. Tests Value Objects (QuantityValue validation)
2. Tests Services (PortfolioPerformanceService metrics)
3. Tests Events (TransactionCreated → realized_gain)
4. Tests Repositories (mock + integration)

**Effort**: ~3-4 jours

---

### 4.2 Optional: Query Objects (complexe seulement)

**Objectif**: 12-line SQL scopes → readable Query Objects.

**Seulement SI**: scopeForAuth/scopeForWallet utilisés partout.

**Fichiers**:
- Domains/Security/Queries/SecurityForAuthQuery.php
- Domains/Security/Queries/SecurityForWalletQuery.php

**Effort**: ~1-2 jours (skip si pas prioritaire)

---

## Timeline Résumé

**Phase 1**: Essentiels (Value Objects, Events, Filament Plugins, Listeners)
- 1.1 Value Objects: 2-3 jours
- 1.2 Events: 2-3 jours
- 1.3 Filament Plugins: 1-2 jours
- 1.4 Event Listeners: 1-2 jours
- **Total Phase 1: 1 semaine**

**Phase 2**: Domain Isolation
- 2.1 Remove auth() GlobalScopes: 1-2 jours
- 2.2 Selective Repositories: 2-3 jours
- **Total Phase 2: 1 semaine**

**Phase 3**: Services & Boundaries
- 3.1 Service Layer (complexe): 2-3 jours
- 3.2 Domain Boundaries: 2-3 jours
- **Total Phase 3: 1 semaine**

**Phase 4**: Testing (optional refinements)
- 4.1 Domain Tests: 3-4 jours
- 4.2 Query Objects (if needed): 1-2 jours
- **Total Phase 4: 1 semaine**

**GRAND TOTAL: ~4-5 semaines** (vs 7-8 précédent)

---

## Success Criteria

- [ ] Value Objects: TransactionPrice, QuantityValue, PortfolioAllocation
- [ ] Domain Events: TransactionCreated, PriceUpdated, etc.
- [ ] Filament Plugins: Portfolio, Security, Analytics auto-registered
- [ ] Event Listeners: realized_gain calculated from TransactionCreated
- [ ] Auth removed from GlobalScopes (forUser parameter)
- [ ] Services isolé pour logique complexe (PortfolioPerformance, Rebalancing)
- [ ] Domain tests >80% coverage
- [ ] Zero cross-domain Model imports (phpstan boundary check)
- [ ] Filament Pages <150 lignes
- [ ] No Observer pattern (Listeners only)

---

## Next Steps

**Week 1**:
- 1.1 Create Value Objects (QuantityValue, TransactionPrice, PortfolioAllocation)
- 1.2 Create Domain Events (TransactionCreated, PriceUpdated, etc)
- 1.3 Create Filament Plugins (register resources per module)
- 1.4 Create Event Listeners (CalculateRealizedGainListener)

**Sync checkpoint**: End of Week 1
- Review Phase 1 completion
- Test Event → Listener flow
- Adjust Phase 2 scope if needed
