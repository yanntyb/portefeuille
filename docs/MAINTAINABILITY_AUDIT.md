# Maintainability Audit & Action Plan

**Date:** 2026-05-06  
**Status:** Post-refactoring S1–S7  
**Target:** 9/10 maintainability before Tier 1 features  

---

## Current State: 7/10

### Strengths ✓

| Area | Status | Evidence |
|------|--------|----------|
| **Code cleanliness** | ✓ Excellent | Zero `dd()`, `dump()`, commented code, or @todo |
| **Naming conventions** | ✓ Consistent | *Calculator, *Service, *Provider, *Aggregator, *Engine suffixes all predictable |
| **Testing structure** | ✓ Solid | Pest framework, 48 Feature tests, 10 factories, RefreshDatabase pattern |
| **Models & relationships** | ✓ Modern | PHP 8 casts(), explicit relationships, proper scopes |
| **Documentation** | ✓ Comprehensive | 12 markdown files organized by concern (Core, Services, Flows, Integrations, Reference) |
| **Seeders** | ✓ Well-designed | Multiple contexts (Demo, Nuclear, Feedback, Transactions) with proper hierarchy |
| **Dependency scoping** | ✓ Octane-safe | `app()->scoped()` pattern used for request-level caching |
| **Shared patterns** | ✓ DRY | Traits (ComputesSingleSecurityStats), shared Blade views (@isset conditionals) |
| **Service extraction** | ✓ Complete (S1–S7) | VolatilityCalculator, PriceRefreshService, SingleSecurityStatsProvider all extracted |

---

## Critical Gaps 🔴 (Before Feature Development)

### 1. Missing Database Indexes
**File:** `database/migrations/`  
**Impact:** N+1 query risk in wallet-scoped operations

```sql
-- ADD TO NEXT MIGRATION:

-- transactions table: wallet filtering without index
ALTER TABLE transactions ADD INDEX idx_wallet_id (wallet_id);

-- security_prices table: VolatilityCalculator orders by (security_id, date)
ALTER TABLE security_prices ADD INDEX idx_security_date (security_id, date);
```

**Where it hurts:**
- `accountPage->scopedSecuritiesQuery()` filters by wallet_id → table scan
- `VolatilityCalculator->forWallet()` queries 100+ prices per security → missing index on (security_id, date)
- Any dashboard query aggregating by wallet

**Fix time:** 1–2 hours (migration + test)

---

### 2. Heavy Page Classes
**Location:** 
- `app/Filament/Pages/AccountPage.php` (367 lines)
- `app/Filament/Pages/RebalancingCalculator.php` (370 lines)

**Problem:** Mixed responsibilities
```php
// AccountPage contains:
scopedSecuritiesQuery()         ← Query builder
computeSecurityVisibility()     ← State mutation
getTotalValuation()             ← Calculation
refreshPrices()                 ← External service call
toggleSecurity()                ← Event dispatch
getFormattedValuation()         ← Formatting
content() override              ← UI routing
```

**Impact:** 
- Impossible to unit test
- Hard to reuse calculations in other contexts
- New dev doesn't know where to add similar feature

**Solution:** Extract `AccountPageDataProvider` service
```php
class AccountPageDataProvider {
    public function getTotalValuation(Wallet $wallet): float
    public function getAnnualizedReturn(Wallet $wallet): float
    public function getPortfolioVolatility(Wallet $wallet): float
    public function getSecurityVisibility(Wallet $wallet): SecurityVisibility
}

// AccountPage becomes thin:
class AccountPage extends Page {
    public function __construct(private AccountPageDataProvider $provider) {}
    
    protected function getTotalValuation(): float {
        return $this->provider->getTotalValuation($this->wallet);
    }
}
```

**Fix time:** 4–5 hours (extraction + tests)  
**Also applies to:** RebalancingCalculatorPage (same pattern)

---

### 3. No Dependency Injection Interfaces

**Current pattern:**
```php
app(VolatilityCalculator::class)->forWallet($wallet)
app(PriceRefreshService::class)->refreshIfNeeded($securities)
```

**Problem:** 
- No contract/interface for callers
- Concrete class coupling (harder to mock in tests)
- AppServiceProvider not used as single source of truth

**Where it matters:**
- 8+ services instantiated via `app()` instead of via interfaces
- Page/Widget constructors can't DI because Livewire doesn't support PHP constructor injection
- Tests must use real classes or manually mock via `app()->instance()`

**Solution:**
```php
// app/Contracts/VolatilityCalculating.php
interface VolatilityCalculating {
    public function forWallet(Wallet $wallet): float;
    public function forSecurity(Security $security): ?float;
}

// app/Providers/AppServiceProvider.php
public function register(): void {
    $this->app->bind(VolatilityCalculating::class, VolatilityCalculator::class);
    // ... repeat for all services
}

// Usage:
inject VolatilityCalculating $volatility instead of app(VolatilityCalculator::class)
```

**Why now:** Services are stable post-refactoring; interfaces won't change during feature development  
**Fix time:** 2–3 hours (8 interfaces + bindings)

---

### 4. Generic Exception Handling

**Current:**
```php
throw new RuntimeException("Price data insufficient");
```

**Problem:** Callers can't distinguish error types; generic catch-all loses intent

**Solution:**
```php
// app/Exceptions/
class InsufficientPriceDataException extends \Exception {}
class InvalidWalletException extends \Exception {}
class InvalidSecurityException extends \Exception {}
class TransactionProcessingException extends \Exception {}

// Usage:
try {
    $volatility = $calculator->forSecurity($security);
} catch (InsufficientPriceDataException $e) {
    return null; // graceful fallback
} catch (InvalidSecurityException $e) {
    log()->error($e);
    return 0.0;
}
```

**Fix time:** 1 hour

---

## Important Gaps 🟡 (Before Tier 1 Features)

### 5. Large Test Methods (200–439 lines)

**Files:** `tests/Feature/TransactionSellTest.php` (439 lines), `ValuationChartWidgetTest.php` (371 lines)

**Problem:** Tests mix multiple scenarios; hard to isolate failures

**Solution:** Use Pest `describe()` blocks + shared factories
```php
describe('TransactionSell', function () {
    describe('when quantity is exact', function () {
        test('reduces remaining holdings to zero', fn () => ...);
    });
    
    describe('when partial sell', function () {
        test('reduces quantity correctly', fn () => ...);
    });
});
```

**Fix time:** 2–3 hours (split 5–6 largest tests)

---

### 6. Triplet Widgets (9 classes, 3 views)

**Status:** Documented (REFACTORING.md) but not yet consolidated  
**Classes:**
- GainStatsOverview, DashboardGainStatsOverview, SingleSecurityGainStatsOverview
- PerformanceStatsOverview, DashboardPerformanceStatsOverview, SingleSecurityPerformanceStatsOverview
- ValuationStatOverview, DashboardValuationWidget, SingleSecurityValuationStatOverview

**Current state:** Each calculates own data (before S5 fix, now SingleSecurity use `SingleSecurityStatsProvider`)

**Future improvement:** Base Widget class with template method for data calculation?
```php
abstract class BaseStatsWidget extends Widget {
    abstract protected function getStats(): array;
    protected string $view = 'shared.stats-view';
}
```

**Not critical for now** — already using traits to share logic  
**Priority:** Low (after Phase 1-2)

---

## Minor Gaps 🟢 (Nice to have)

- **API documentation:** No OpenAPI/Swagger; consider `docs/ROUTES.md`
- **Service reference map:** Create `docs/SERVICE_MATRIX.md` (which service, where used, cost)
- **Onboarding:** README good; add link to CODEBASE_STRUCTURE.md for architecture overview

---

## What Works Well (Keep as Is)

✓ **Scoped bindings** (`app()->scoped()`) — Octane-safe caching  
✓ **Service pattern** — Coherent *Calculator/*Service/*Provider naming  
✓ **Trait reuse** — Avoid deep hierarchies, use composition  
✓ **Shared Blade views** — DRY + conditional @isset blocks  
✓ **Factory coverage** — All models have factories  
✓ **Test organization** — describe/it structure is clean  
✓ **Documentation** — Well-organized by concern, easy to navigate  

---

## Action Plan

### Phase 1: Foundation (This week — 8–10 hours)

```
[ ] Add missing indexes (1–2 hours)
    └─ wallet_id on transactions
    └─ (security_id, date) composite on security_prices
    └─ Verify with EXPLAIN ANALYZE

[ ] Create DI interfaces (2–3 hours)
    └─ VolatilityCalculating
    └─ PriceRefreshService (no interface yet)
    └─ PortfolioPerformanceCalculating
    └─ RebalancingCalculating
    └─ MonteCarloSimulating
    └─ Bind all in AppServiceProvider
    └─ Update all call sites (8+ files)

[ ] Create exception hierarchy (1 hour)
    └─ InsufficientPriceDataException
    └─ InvalidWalletException
    └─ InvalidSecurityException
    └─ TransactionProcessingException
    └─ Replace generic RuntimeException

[ ] Extract RebalancingCalculatorPage logic (4–5 hours)
    └─ Move calculations to RebalancingCalculator service
    └─ Page becomes thin orchestrator
    └─ Add unit tests for service
```

**Commit message:**
```
refactor: add indexes, create service interfaces, extract exceptions

- Add missing database indexes for wallet_id and (security_id, date)
- Create DI interfaces for all major services (VolatilityCalculating, etc.)
- Bind interfaces in AppServiceProvider
- Create exception hierarchy for domain-specific errors
- Extract RebalancingCalculatorPage heavy logic to service
- All tests passing (343+)
```

---

### Phase 2: Pages & Tests (Before Tier 1 — 5–7 hours)

```
[ ] Extract AccountPageDataProvider (3–4 hours)
    └─ Move getTotalValuation, getAnnualizedReturn, computePortfolioVolatility
    └─ Add unit tests
    └─ AccountPage now thin

[ ] Consolidate test helpers (2–3 hours)
    └─ Split large test files (TransactionSellTest, ValuationChartWidgetTest)
    └─ Create describe() blocks for scenarios
    └─ Add shared fixture builders
```

---

### Phase 3: Development Standard (During Tier 1+)

**Rule for new features:**
- ✓ Domain logic always in Service, never in Page/Widget
- ✓ Pages max 150 lines (routing + delegation to services)
- ✓ Services max 200 lines per method
- ✓ Use interfaces from day 1
- ✓ Create exceptions, don't throw generic RuntimeException
- ✓ Tests describe intent via blocks, not monolithic methods

---

## Effort Summary

| Task | Hours | Complexity | Risk |
|------|-------|-----------|------|
| Add indexes | 1–2 | Low | Low (non-breaking) |
| DI interfaces | 2–3 | Medium | Low (refactor only) |
| Exceptions | 1 | Low | Low (new, don't break existing) |
| RebalancingCalculatorPage | 4–5 | High | Medium (extract, need tests) |
| **Phase 1 Total** | **8–11** | — | — |
| AccountPageDataProvider | 3–4 | Medium | Medium (widely used) |
| Test consolidation | 2–3 | Low | Low (test-only changes) |
| **Phase 2 Total** | **5–7** | — | — |
| **Grand Total** | **13–18 hours** | — | — |

---

## Success Metrics

After Phase 1-2:
- ✓ All Services have interfaces + bindings in AppServiceProvider
- ✓ All Pages < 150 lines (code only, not Blade)
- ✓ All domain exceptions are specific (no RuntimeException in domain)
- ✓ Tests split into describe() blocks (no 300+ line test methods)
- ✓ Database queries have proper indexes (verified with EXPLAIN)
- ✓ New Tier 1 features follow patterns above

**Maintainability target: 9/10**

---

## References

- **CODEBASE_STRUCTURE.md** — Architecture observations, naming, patterns
- **REFACTORING.md** — Completed simplifications S1–S7
- **ROADMAP.md** — Tier 1-4 feature roadmap
- **docs/** — Service references, flows, integrations

---

*Audit by Claude after refactoring S1–S7. Pre-feature checklist for Tier 1 work.*
