# Domain Boundaries & Cross-Domain Coupling

## Bounded Contexts Overview

```mermaid
graph TD
    subgraph Shared["Shared (Infrastructure)"]
        DomainEvent
        MarketCalendar
    end

    subgraph User["User Domain"]
        UserModel["User"]
    end

    subgraph Security["Security Domain"]
        SecurityModel["Security"]
        SecurityPrice["SecurityPrice"]
        SecuritySector["SecuritySector"]
        UpdateSecurityJob["UpdateSecurityJob"]
        YahooFinanceService["YahooFinanceService"]
        SecurityContracts["Contracts: SecurityPriceRepositoryInterface"]
    end

    subgraph Portfolio["Portfolio Domain"]
        Transaction["Transaction"]
        Wallet["Wallet"]
        TransactionObserver["TransactionObserver"]
        CalculateRealizedGainListener["CalculateRealizedGainListener"]
        RealizedGainCalculator["RealizedGainCalculator"]
        PortfolioPerformanceService["PortfolioPerformanceService"]
        SectorAggregator["SectorAggregator"]
        DashboardDataProvider["DashboardDataProvider"]
        AllocationProfile["AllocationProfile"]
    end

    subgraph Analytics["Analytics Domain"]
        VolatilityCalculator["VolatilityCalculator"]
        VolatilityCalculating["Contract: VolatilityCalculating"]
        RebalancingCalculatorOrchestrator["RebalancingCalculatorOrchestrator"]
        CorrelationCalculator["CorrelationCalculator"]
        SimulationEngine["SimulationEngine"]
        PerformancePeriod["PerformancePeriod (enum)"]
    end

    User --> Portfolio
    User --> Security
    Portfolio --> Security
    Analytics --> Portfolio
    Analytics --> Security
    Portfolio --> Analytics
```

## Dependency Direction Map

```mermaid
graph LR
    P(Portfolio) -->|"imports Security models\ndirect ORM queries"| S(Security)
    P -->|"imports VolatilityCalculator concrete\nnot the contract"| A(Analytics)
    A -->|"queries Transaction directly\nRebalancingCalculatorOrchestrator"| P
    A -->|"queries SecurityPrice directly\nVolatilityCalculator + CorrelationCalculator"| S
    A -->|"VolatilityCalculating contract\ntype-hints Wallet + Security"| P
    A -->|"VolatilityCalculating contract\ntype-hints Wallet + Security"| S

    style P fill:#3b82f6,color:#fff
    style S fill:#10b981,color:#fff
    style A fill:#f59e0b,color:#fff
```

## Detailed Coupling: Who Imports What

### Portfolio → Security (heavy coupling)

| Caller | Imported | Operation | Severity |
|--------|----------|-----------|----------|
| `PortfolioPerformanceService` | `Security`, `SecurityPrice` | `Security::forWallet()` + `SecurityPrice` visibility check | ⚠️ Read-only, synchronous |
| `PortfolioPerformanceCalculator` | `Security`, `SecurityPrice` | TWR calculation, price time-series | ⚠️ Read-only, synchronous |
| `SectorAggregator` | `Security`, `SecuritySector`, `Sector` enum | Sector label formatting | ⚠️ `Sector` enum in wrong domain |
| `DashboardDataProvider` | `Security` | Dashboard securities query | ⚠️ Read-only, synchronous |
| `Transaction` model | `Security` | FK `belongsTo` relation | ✅ Schema-level, acceptable |
| `AccountPage` (UI) | `Security`, `PriceRefreshService`, `UpdateSecuritiesJob` | Dispatch update job, embed widgets | ⚠️ Job dispatch = event candidate |

### Portfolio → Analytics (wrong direction)

| Caller | Imported | Operation | Severity |
|--------|----------|-----------|----------|
| `PortfolioPerformanceService` | `VolatilityCalculator` (concrete) | `computePortfolioVolatility()` | 🔴 Should use `VolatilityCalculating` contract |
| `WalletPage` (UI) | `SimulationSectionWidget` | Embed simulation widget | ⚠️ UI composition |

### Analytics → Portfolio (most harmful coupling)

| Caller | Imported | Operation | Severity |
|--------|----------|-----------|----------|
| `RebalancingCalculatorOrchestrator` | `Transaction` | `Transaction::query()->groupBy('security_id')` to get holdings quantities | 🔴 Analytics queryies Portfolio ORM directly |
| `RebalancingCalculatorOrchestrator` | `Wallet` | `?Wallet $wallet` param for scoping | 🟡 Replace with `?int $walletId` |
| `VolatilityCalculator` | `Wallet` | `forWallet(Wallet $wallet)` param | 🟡 Replace with `int $walletId` |
| `VolatilityCalculating` (contract) | `Wallet`, `Security` | Interface type-hints foreign models | 🔴 Contract leaks coupling |
| `RebalancingCalculator` (page) | `AllocationProfile`, `Wallet` | Load allocation profiles + wallet list | ⚠️ UI read, acceptable |

### Analytics → Security (medium coupling)

| Caller | Imported | Operation | Severity |
|--------|----------|-----------|----------|
| `VolatilityCalculator` | `Security`, `SecurityPrice` | `Security::forWallet()` + historical closes bulk query | 🟡 `SecurityPriceRepositoryInterface` exists but unused |
| `CorrelationCalculator` | `SecurityPrice` | Historical closes for log-return correlation | 🟡 Same: use existing repository contract |
| `RebalancingCalculatorOrchestrator` | `Security` | `Security::with('latestPrice')->whereIn()` | 🟡 Use repository contract |

## Current Event System (Production = Nothing)

```mermaid
graph LR
    subgraph Events["Domain Events (defined)"]
        TC["TransactionCreated"]
        PU["PriceUpdated"]
        PR["PortfolioRebalanced"]
    end

    subgraph Listeners["Listeners"]
        CRGL["CalculateRealizedGainListener"]
    end

    subgraph Production["Production dispatch"]
        NEVER["❌ Never dispatched"]
    end

    subgraph Tests["Test dispatch only"]
        TD["TransactionCreated::dispatch()"]
    end

    TC -->|registered| CRGL
    PU -->|no listener| NEVER
    PR -->|no listener| NEVER
    TD -->|test only| TC
```

## Ideal State: Event-Driven Decoupling

```mermaid
sequenceDiagram
    participant UI as Filament UI
    participant TO as TransactionObserver
    participant EB as Event Bus
    participant CRGL as CalculateRealizedGainListener
    participant HP as HoldingsProjection (read model)
    participant RCO as RebalancingCalculatorOrchestrator

    UI->>TO: Transaction created/updated
    TO->>EB: TransactionCreated::dispatch()
    EB->>CRGL: handle(TransactionCreated)
    CRGL->>CRGL: RealizedGainCalculator::calculate()
    CRGL->>Transaction: withoutObservers → update realized_gain
    EB->>HP: handle(TransactionCreated) [future]
    HP->>HP: Update [security_id → qty] projection
    RCO->>HP: Read holdings (no Transaction query)
    note over RCO: Analytics no longer touches Portfolio ORM
```

## Decoupling Roadmap

### Via Events (side-effect delegation)

| Event | Where to dispatch | Who listens | Benefit |
|-------|-------------------|-------------|---------|
| `TransactionCreated` | `TransactionObserver::created/updated` | `CalculateRealizedGainListener` (existing) | Observer becomes thin, business logic isolated |
| `TransactionCreated` | same | `UpdateHoldingsProjectionListener` (future) | Eliminates `Analytics → Portfolio.Transaction` ORM query |
| `PriceUpdated` | `UpdateSecurityJob` after fetch | *(none yet)* | Correct lifecycle signal for future listeners |
| `PortfolioRebalanced` | `RebalancingCalculator::calculate()` | *(none yet)* | Audit/notification hook for future |

### Via Contract/Interface (synchronous reads)

| Coupling | Fix | Contract exists? |
|----------|-----|-----------------|
| `PortfolioPerformanceService` → `VolatilityCalculator` concrete | Use `VolatilityCalculating` interface | ✅ Yes |
| `VolatilityCalculator` param `Wallet` | Replace with `int $walletId` | N/A (primitive) |
| `RebalancingCalculatorOrchestrator` param `Wallet` | Replace with `?int $walletId` | N/A (primitive) |
| `VolatilityCalculator` queries `SecurityPrice` direct | Use `SecurityPriceRepositoryInterface` | ✅ Yes |
| `CorrelationCalculator` queries `SecurityPrice` direct | Use `SecurityPriceRepositoryInterface` | ✅ Yes |
| `VolatilityCalculating` contract type-hints `Wallet`/`Security` | Refactor to primitives | — |

### Enum in wrong domain

| Enum | Current location | Move to |
|------|-----------------|---------|
| `PerformancePeriod` | `Analytics/Enums` (imported by Portfolio) | `Shared/Enums` or `Portfolio/Enums` |
| `Sector` | `Security/Enums` (imported by Portfolio) | `Shared/Enums` |
