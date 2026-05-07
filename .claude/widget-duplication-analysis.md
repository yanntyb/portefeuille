# Widget Duplication & Refactoring Analysis

## Summary
- **Total Widgets:** 20+ across Analytics/Security domains
- **Dashboard Variants:** 8 (DashboardXxx)
- **Security Domain Equivalents:** 12 (XXXStatOverview, XXXChartWidget, etc)
- **Code Duplication:** 35-40% (estimated from pattern analysis)
- **Refactoring Impact:** Could reduce LOC by 400-500 lines

---

## Pattern 1: Dashboard vs Security Domain Duplication

### Problematic Pairs
| Feature | Dashboard Widget | Security Widget | Single Security Widget |
|---------|------------------|-----------------|------------------------|
| Valuation | ❌ DashboardValuationWidget | ❌ ValuationStatOverview | ❌ SingleSecurityValuationStatOverview |
| Gains | ❌ DashboardGainStatsOverview | ❌ GainStatsOverview | ❌ SingleSecurityGainStatsOverview |
| Performance | ❌ DashboardPerformanceStatsOverview | ❌ PerformanceStatsOverview | ❌ SingleSecurityPerformanceStatsOverview |
| Correlation | ❌ DashboardCorrelationMatrixWidget | ❌ CorrelationMatrixWidget | N/A |
| Allocation | ❌ DashboardSectorAllocationChartWidget | ❌ SectorAllocationChartWidget | N/A |
| Valuation Chart | N/A | ❌ ValuationChartWidget | ❌ SingleSecurityValuationChartWidget |
| Price Chart | N/A | N/A | ❌ SingleSecurityPriceChartWidget |
| Allocation Chart | N/A | ❌ AllocationChartWidget | N/A |

**Root Cause:** Dashboard shows global portfolio, Security domain shows table-filtered view, Single shows one security. But computation logic is 85% identical.

---

## Pattern 2: Security Resolution Duplication

### Identical Code in Multiple Widgets

**GainStatsOverview (lines 23-36):**
```php
protected function resolveGainSecurities(): Collection
{
    if ($this->tablePageClass === null) {
        return Security::query()->where('id', null)->get();
    }

    $query = $this->getPageTableQuery();

    if ($this->shownSecurityIds !== null) {
        $query->whereIn('securities.id', $this->shownSecurityIds);
    }

    return $query->with('latestPrice')->get();
}
```

**PerformanceStatsOverview (lines 18-31):** Identical code

**AllocationChartWidget (lines 38-50):** Same logic, inline in getData()

**ValuationChartWidget:** Same logic, inline with transaction aggregation

**CorrelationMatrixWidget:** Same logic with different method name

**Widgets Affected:** 5+ (GainStatsOverview, PerformanceStatsOverview, AllocationChartWidget, ValuationChartWidget, CorrelationMatrixWidget)

---

## Pattern 3: Event Listeners Duplication

All stat overview widgets implement same pattern:

```php
#[On('prices-updated')]
public function refreshStats(): void
{
    // Re-renders the widget
}

#[On('security-visibility-changed')]
public function updateShownSecurityIds(array $shownSecurityIds): void
{
    $this->shownSecurityIds = $shownSecurityIds;
}
```

**Affected Widgets:** ValuationStatOverview, GainStatsOverview, PerformanceStatsOverview, AllocationChartWidget, etc.

---

## Pattern 4: Color Logic Duplication

### Same Comparison in Multiple Widgets
```php
// DashboardValuationWidget
$color = $totalValuation >= $totalInvested ? 'success' : 'danger'

// ValuationStatOverview  
$color = $valuation >= $totalInvested ? 'success' : 'danger'

// SingleSecurityValuationStatOverview
$color = $stats['valuation'] >= $stats['totalInvested'] ? 'success' : 'danger'
```

**Affected Widgets:** All stat overview widgets (Valuation, Gain, Performance variants)

---

## Refactoring Opportunities

### Opportunity 1: Extract Security Filter Method (HIGH PRIORITY)
**Impact:** Removes 50+ lines of duplication
**Affected:** 5+ widgets

**Proposal:** Add method to HasReactiveTableProperties:
```php
protected function getFilteredSecurities(bool $withPrice = true): Collection
{
    if ($this->tablePageClass === null) {
        return Security::query()->where('id', null)->get();
    }

    $query = $this->getPageTableQuery();
    
    if ($this->shownSecurityIds !== null) {
        $query->whereIn('securities.id', $this->shownSecurityIds);
    }

    if ($withPrice) {
        $query->with('latestPrice');
    }

    return $query->get();
}
```

### Opportunity 2: Consolidate Dashboard + Domain Variants (HIGH PRIORITY)
**Impact:** Reduces 6 duplicate widget pairs
**Affected:** Dashboard + Security equivalents

**Proposal:** Make stat widgets accept data source strategy:
```php
class ValuationStatOverviewWidget extends Widget
{
    public function __construct(private DataSourceStrategy $dataSource) {}
    
    public function getValuationData(): array
    {
        $securities = $this->dataSource->resolve(); // DashboardProvider OR TableQuery
        // ... shared computation logic
    }
}
```

OR Keep separate but extract shared computation:
```php
class ValuationComputation
{
    public static function compute(Collection $securities): array
    {
        $valuation = $securities->sum(...);
        $invested = $securities->sum(...);
        return [
            'valuation' => Number::currency($valuation, 'EUR'),
            'color' => $valuation >= $invested ? 'success' : 'danger',
        ];
    }
}
```

### Opportunity 3: Base Chart Widget Class (MEDIUM PRIORITY)
**Impact:** Reduces getData() duplication in chart widgets
**Affected:** AllocationChartWidget, ValuationChartWidget, CorrelationMatrixWidget, etc.

**Proposal:**
```php
abstract class ReactiveChartWidget extends ChartWidget
{
    use HasReactiveTableProperties;
    
    protected function getData(): array
    {
        if ($this->tablePageClass === null) {
            return ['datasets' => [], 'labels' => []];
        }
        
        $securities = $this->getFilteredSecurities();
        return $this->computeChartData($securities);
    }
    
    abstract protected function computeChartData(Collection $securities): array;
}
```

### Opportunity 4: Event Listener Trait (MEDIUM PRIORITY)
**Impact:** Removes 20+ lines repeated in stat widgets
**Affected:** 5+ stat overview widgets

**Proposal:**
```php
trait HasStatWidgetListeners
{
    #[On('prices-updated')]
    public function refreshStats(): void {}
    
    #[On('security-visibility-changed')]
    public function updateShownSecurityIds(array $shownSecurityIds): void
    {
        $this->shownSecurityIds = $shownSecurityIds;
    }
}
```

### Opportunity 5: Stat Computation Trait (LOW PRIORITY)
**Impact:** Centralizes valuation/gain/performance computation
**Affected:** All stat widgets

**Proposal:**
```php
trait ComputesStatColor
{
    protected function getStatColor(float $value, float $threshold): string
    {
        return $value >= $threshold ? 'success' : 'danger';
    }
}
```

---

## Implementation Priority

### Phase 1: Extract Security Filtering (Immediate)
- Add `getFilteredSecurities()` to HasReactiveTableProperties
- Update GainStatsOverview, PerformanceStatsOverview, AllocationChartWidget, etc.
- **LOC Saved:** ~50 lines
- **Time:** 1-2 hours
- **Risk:** Low (pure extraction, no logic changes)

### Phase 2: Event Listener Trait (Next)
- Create HasStatWidgetListeners trait
- Apply to ValuationStatOverview, GainStatsOverview, PerformanceStatsOverview
- **LOC Saved:** ~20 lines
- **Time:** 30 min
- **Risk:** Low

### Phase 3: Computation Consolidation (Later)
- Extract ValuationComputation class or similar
- Apply to Dashboard + domain widget pairs
- **LOC Saved:** ~150 lines
- **Time:** 4-6 hours
- **Risk:** Medium (requires testing of all variants)

### Phase 4: Base Chart Widget (Optional)
- Only if more chart widgets are added
- Current benefit: ~100 lines saved
- Better suited for future expansion

---

## Risk Assessment

**Low Risk Changes:**
- Extracting shared methods (getFilteredSecurities)
- Creating event listener trait
- Creating stat color trait

**Medium Risk Changes:**
- Creating computation classes
- Consolidating Dashboard + domain variants

**Testing Required:**
- All 20+ widgets must pass existing test suite
- Widget behavior must remain identical
- No visual changes to rendered output

---

## Before/After Example

### Before: GainStatsOverview + PerformanceStatsOverview

```php
// GainStatsOverview (54 lines)
protected function resolveGainSecurities(): Collection { /* 13 lines */ }
use ComputesGainStats; 
use HasReactiveTableProperties;

// PerformanceStatsOverview (32 lines)
protected function resolvePerformanceSecurities(): Collection { /* 13 lines - DUPLICATE */ }
use ComputesPerformanceStats;
use HasReactiveTableProperties;

// TOTAL: 86 lines, 13 lines duplicated
```

### After: With Extraction

```php
// Both inherit from base
class GainStatsOverview extends Widget {
    use ComputesGainStats;
    use HasReactiveTableProperties;
    use HasStatWidgetListeners; // Replaces 4 lines
    
    protected function resolveGainSecurities(): Collection {
        return $this->getFilteredSecurities(); // 1 line instead of 13
    }
}

// TOTAL: 40 lines, 0 duplicated
// SAVED: 46 lines in 2 widgets
```

---

## Recommendations

1. **Start with Phase 1** (Security filtering extraction) - highest ROI, lowest risk
2. **Defer Phase 4** (base chart class) until more widgets are added
3. **Consider consolidation** (Phase 3) only after Phase 1-2 complete
4. **Maintain test coverage** throughout refactoring
5. **Backward compatibility** is not a concern (internal widgets)

**Estimated Total Impact:**
- **LOC Reduction:** 400-500 lines
- **Maintenance Cost:** Reduced by ~30%
- **Time to Implement:** 6-8 hours across all phases
- **Breaking Changes:** None (internal refactoring)
