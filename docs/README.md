# Documentation - Argent

Portfolio management system. Complete architectural & data flow documentation.

---

## 🗺️ Navigation Map

### 📘 Core Data Model
Understand data structures, types, contracts.

- **[1-models.md](CORE/1-models.md)** — User, Wallet, Transaction, Security, SecurityPrice, WalletFee, AllocationProfile, SecuritySector, Invitation
- **[2-contracts.md](CORE/2-contracts.md)** — All data schemas, purposes, when created, why exists
- **[3-enums.md](CORE/3-enums.md)** — TransactionType, Role, CurrencyModificationUnit, FeeScope, FrequencyUnit, Sector, PerformancePeriod

### ⚙️ Services & Calculators
Business logic implementations.

- **[4-aggregators.md](SERVICES/4-aggregators.md)** — TransactionAggregator (time series), SectorAggregator (breakdown)
- **[5-calculators.md](SERVICES/5-calculators.md)** — PortfolioPerformanceCalculator (returns), RebalancingCalculator (allocation), CorrelationCalculator (matrix)
- **[6-simulation.md](SERVICES/6-simulation.md)** — SimulationEngine (scenarios), MonteCarloEngine (portfolio projections)

### 🔄 Data Flows
How data moves through system.

- **[7-dashboard-flow.md](FLOWS/7-dashboard-flow.md)** — User login → Load wallets → Calculate metrics → Display
- **[8-transaction-lifecycle.md](FLOWS/8-transaction-lifecycle.md)** — Create form → Validate → Insert → Observer → Compute gain
- **[9-refresh-flow.md](FLOWS/9-refresh-flow.md)** — Scheduled daily + Manual SecurityPrice refresh, bulk vs sequential
- **[10-rebalancing-flow.md](FLOWS/10-rebalancing-flow.md)** — AllocationProfile → Calculator → Buy suggestions

### 🔗 Integrations
External systems & bridges.

- **[11-python-bridge.md](INTEGRATIONS/11-python-bridge.md)** — PHP↔Python IPC, search_ticker.py, fetch_prices.py, fetch_sectors.py
- **[12-filament-pages.md](INTEGRATIONS/12-filament-pages.md)** — Dashboard, WalletPage, RebalancingCalculator, SimulationBoard pages

### 📚 Reference
Type systems, glossary, formulas.

- **[13-type-reference.md](REFERENCE/13-type-reference.md)** — JSON types, PHP types, SQL types, mappings
- **[14-glossary.md](REFERENCE/14-glossary.md)** — Abbreviations, formulas, terminology

---

## 🎯 Quick Start by Use Case

**Understand portfolio valuation:**
1. Read [1-models.md](CORE/1-models.md) (Transaction, Security, SecurityPrice)
2. Read [7-dashboard-flow.md](FLOWS/7-dashboard-flow.md) (load → calculate → display)
3. Read [5-calculators.md](SERVICES/5-calculators.md) (PortfolioPerformanceCalculator)

**Understand transaction lifecycle:**
1. Read [2-contracts.md](CORE/2-contracts.md) (Transaction contract)
2. Read [8-transaction-lifecycle.md](FLOWS/8-transaction-lifecycle.md) (create → observer → gain)

**Understand price refresh:**
1. Read [1-models.md](CORE/1-models.md) (SecurityPrice model)
2. Read [9-refresh-flow.md](FLOWS/9-refresh-flow.md) (scheduled + manual)
3. Read [11-python-bridge.md](INTEGRATIONS/11-python-bridge.md) (Python scripts)

**Understand rebalancing:**
1. Read [1-models.md](CORE/1-models.md) (AllocationProfile, AllocationProfileItem)
2. Read [10-rebalancing-flow.md](FLOWS/10-rebalancing-flow.md) (profile → calculator)
3. Read [5-calculators.md](SERVICES/5-calculators.md) (RebalancingCalculator)

**Understand simulations:**
1. Read [6-simulation.md](SERVICES/6-simulation.md) (engines overview)
2. Read [12-filament-pages.md](INTEGRATIONS/12-filament-pages.md) (SimulationBoard UI)

---

## 📊 Coverage Summary

| Area | Coverage | Files |
|------|----------|-------|
| Core data model | 100% | 1-models, 2-contracts, 3-enums |
| Services & calculations | 100% | 4-aggregators, 5-calculators, 6-simulation |
| User flows | 95% | 7-dashboard, 8-transaction, 9-refresh, 10-rebalancing |
| Integrations | 90% | 11-python-bridge, 12-filament-pages |
| Reference | 100% | 13-type-reference, 14-glossary |

**Not documented (exploratory):**
- Filament resources CRUD (68 files)
- Auth gates & policies
- Admin features
- API endpoints (if any)

### 🚀 Roadmap & Refactoring

- **[ROADMAP.md](ROADMAP.md)** — Indicateurs manquants, priorisés par tier (Tier 1→4)
- **[REFACTORING.md](REFACTORING.md)** — Simplifications de l'architecture actuelle (N+1, duplications, diagrammes Mermaid)
- **[ROADMAP/tier1-drawdown.md](ROADMAP/tier1-drawdown.md)** — Max Drawdown : algorithme, service, tables optionnelles
- **[ROADMAP/tier1-benchmark.md](ROADMAP/tier1-benchmark.md)** — Performance vs Benchmark : nouvelles tables, service, UI
- **[ROADMAP/tier1-cagr-twr-mwr.md](ROADMAP/tier1-cagr-twr-mwr.md)** — CAGR, TWR depuis ouverture, MWR/XIRR
- **[ROADMAP/tier2-risk-ratios.md](ROADMAP/tier2-risk-ratios.md)** — Sharpe, Sortino, Calmar : formules, service, affichage

---

## 🔍 Key Concepts (Quick Reference)

### Isolation
Global scopes: `where('user_id', auth()->id())` ← Each user sees only own data

### Refresh Strategy
- **Scheduled daily:** FetchSecurityPricesCommand (incremental or backfill)
- **Manual:** UpdateSecurityJob via Filament (full 5-year history)

### Portfolio Calculation
```
Transaction flow:
  User creates TX → Observer computes realized_gain (if Sell)

Valuation:
  Security.forWallet() → SUM(qty), PRU, current_value, unrealized_gain
  
Performance:
  TransactionAggregator → CumulativeData → PortfolioPerformanceCalculator → return%
```

### Rebalancing Algorithm
```
Current value per security → Target value = total × target%
→ Shares to buy = (target - current) / price
→ Greedy allocate (most underweighted first)
```

### Simulation (Monte Carlo)
```
10k paths, 12 months/year
r ~ N(return/12, volatility/√12)
portfolio = (portfolio + DCA) × (1 + r)
→ p10, p50, p90 percentiles
```

---

## 📝 File Organization

```
docs/
├── README.md                           ← You are here
├── CORE/
│   ├── 1-models.md                     (11 entities + relationships)
│   ├── 2-contracts.md                  (Purpose, when, why, types)
│   └── 3-enums.md                      (6 enums, values, usage)
├── SERVICES/
│   ├── 4-aggregators.md                (2 services, time series)
│   ├── 5-calculators.md                (3 services, math)
│   └── 6-simulation.md                 (2 engines, projections)
├── FLOWS/
│   ├── 7-dashboard-flow.md             (Diagrams + steps)
│   ├── 8-transaction-lifecycle.md      (State machine + observer)
│   ├── 9-refresh-flow.md               (Scheduled + manual, bulk vs seq)
│   └── 10-rebalancing-flow.md          (Profile → calc → suggestions)
├── INTEGRATIONS/
│   ├── 11-python-bridge.md             (PHP↔Python IPC, 4 scripts)
│   └── 12-filament-pages.md            (4 pages, data dependencies)
└── REFERENCE/
    ├── 13-type-reference.md            (JSON, PHP, SQL types)
    └── 14-glossary.md                  (Terms, formulas)
```

---

## 🔗 Architecture Layers

```
User Input
    ↓
Filament Pages (12)
    ↓
Services (6 services + calculators)
    ↓
Core Models (11 entities)
    ↓
Database / Python Bridge
```

---

## ✅ When You Need To...

| Task | Read |
|------|------|
| Add new field to Transaction | 1-models, 2-contracts |
| Fix portfolio calculation | 5-calculators, 7-dashboard-flow |
| Add SecurityPrice refresh | 9-refresh-flow, 11-python-bridge |
| Understand rebalancing | 1-models, 10-rebalancing-flow, 5-calculators |
| Add new simulation | 6-simulation, 12-filament-pages |
| Debug scope isolation | 1-models (global scopes), 7-dashboard-flow |
| Understand returns computation | 4-aggregators, 5-calculators, 8-transaction-lifecycle |

---

**Last updated:** 2026-04-30  
**Total docs:** 14 files, ~3000 lines  
**Coverage:** 90% core flows, 95% business logic
