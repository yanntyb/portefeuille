# Data Contracts - Argent

Centralized schemas, purposes, when created, why exists.

---

## 📋 Contract Template

Every data contract answers:
- **What?** Field name + type
- **Why?** Purpose  
- **When?** Created/triggered
- **Who?** Used by (relationships)
- **How?** Validated (constraints)

---

## 🔵 Core Models - Full Contracts

### User
**Purpose:** Authentication + root entity for isolation.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| email | string | unique | Register | Login identifier |
| role | Admin\|User | enum | Register | Filament access control |
| password | string | hashed | Register | Security |

**Used by:** Wallet.user_id, Transaction.user_id, AllocationProfile.user_id

---

### Wallet
**Purpose:** Container for portfolio. Logical grouping.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| user_id | int | FK, scope | Create Wallet | Ownership + isolation |
| name | string | - | Create | Label ("Growth", "Safe") |

**Used by:** Transaction.wallet_id, WalletFee.wallet_id

---

### Transaction
**Purpose:** Buy/sell record. Position source of truth.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| type | Buy\|Sell | enum | Create TX | Direction |
| date | date | - | Create | Settlement (valuation ref) |
| quantity | decimal(4) | > 0 | Create | Number shares |
| unit_price | decimal(4) | > 0 | Create | Price/share |
| fees | decimal(2) | >= 0 | Create | Commission |
| realized_gain | decimal(2) | nullable | Observer (Sell only) | Gain/loss computation |

**Observer:** On save → If Sell: compute realized_gain = qty × (unit_price - PRU) - fees

---

### Security
**Purpose:** Global catalogue. Shared across users.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| isin | string | unique | Admin import | Standard identifier |
| ticker | string | - | UpdateSecurityJob | Yahoo Finance symbol |

**Used by:** Transaction.security_id, SecurityPrice.security_id

---

### SecurityPrice
**Purpose:** OHLCV history. Valuation source.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| date | date | UK(security, date) | Refresh command | Trading date |
| close | decimal(4) | - | Refresh | **Portfolio valuation** |
| open, high, low | decimal(4) | - | Refresh | Charts, analysis |
| volume | int | - | Refresh | Trading volume |

**Refresh:** Daily scheduled (incremental) OR Manual UpdateSecurityJob (backfill 5y)

---

### WalletFee
**Purpose:** Recurring/fixed fees. Impact net performance.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| value | decimal(4) | - | Create fee | Amount or % |
| unit | €\|% | enum | Create | Interpretation |
| scope | All\|PercentageOfValue | enum | Create | Application basis |
| frequency | Monthly\|Yearly\|OneTime | enum | Create | Periodicity |

**Calculation:** (unit=€) → fixed; (unit=%) → percentage of portfolio value

---

### AllocationProfile
**Purpose:** Target allocation rules. Guide rebalancing.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| wallet_id | int | FK | Create profile | Which wallet to manage |
| name | string | - | Create | Label ("60/40") |

**Relationships:** items() → AllocationProfileItem[]

---

### AllocationProfileItem
**Purpose:** Single line in allocation (1 security = X%).

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| target_percentage | decimal(2) | 0-100 | Add item | % of portfolio |

**Constraint:** Sum of target_percentage per profile ≤ 100%

---

### SecuritySector
**Purpose:** Security classification. Risk analysis.

| Field | Type | Constraint | When | Why |
|-------|------|-----------|------|-----|
| sector | enum | [enum values] | Refresh | Category |
| weight | decimal | nullable | Refresh | Sector composition % |

**Refresh:** Daily scheduled (if outdated >7d) OR Manual

---

## 🟣 Computed Models (Not persisted)

### TimeSeriesPoint
**Purpose:** (date, value) tuple for charts.

```json
{"date": "2024-04-30", "value": 45000.50}
```

---

### CumulativeData
**Purpose:** Aggregated transaction history.

```json
{
  "quantities": {security_id: [TimeSeriesPoint]},
  "invested": [TimeSeriesPoint],
  "fees": [TimeSeriesPoint],
  "realizedGains": float
}
```

---

### PortfolioContext
**Purpose:** Snapshot of portfolio state.

```json
{
  "securities": [...],
  "transactions": [...],
  "priceMap": {security_id: {date: {open, close, ...}}},
  "cumulativeQuantities": {...}
}
```

---

## 📤 Output Models (UI/API Responses)

### PerformanceMetrics
**Purpose:** Returns by period.

```json
{
  "day": 0.5,
  "week": 2.3,
  "month": 5.1,
  "quarter": 12.4,
  "year": 25.6,
  "ytd": 15.2,
  "all_time": 45.8
}
```

**Type:** {period: float(%)}

---

### RebalancingResult
**Purpose:** Buy/sell suggestions.

```json
{
  "items": [{
    "security_id": 123,
    "quantity_held": 10,
    "current_percentage": 15.2,
    "target_percentage": 20.0,
    "shares_to_buy": 2,
    "buy_cost": 371.28
  }],
  "remainder": 500.00,
  "total_invested": 9500.00
}
```

---

### MonteCarloResult
**Purpose:** Portfolio projections.

```json
{
  "duree": 10,
  "p10": [100000, 105000, ...],
  "p50": [100000, 110000, ...],
  "p90": [100000, 115000, ...],
  "capitalInvesti": [100000, 106000, ...]
}
```

---

## 📡 Python Bridge Contracts

### search_ticker Input/Output

**Input:**
```json
{"query": "FR0011871110", "fallback_query": "TotalEnergies"}
```

**Output:**
```json
{
  "status": "ok",
  "data": [{
    "symbol": "TTEF",
    "name": "TotalEnergies",
    "exchange": "EURONEXT PARIS",
    "type": "Equity"
  }]
}
```

---

### fetch_prices Input/Output

**Input:**
```json
{"ticker": "AAPL", "start_date": "2024-01-01", "end_date": "2024-12-31"}
```

**Output:**
```json
{
  "status": "ok",
  "data": [{
    "date": "2024-01-02",
    "open": 185.64,
    "high": 186.39,
    "low": 184.85,
    "close": 185.64,
    "volume": 52000000
  }]
}
```

---

### fetch_prices_bulk Input/Output

**Input:**
```json
{
  "tickers": [
    {"ticker": "AAPL", "start_date": "2024-01-01", "end_date": "2024-12-31"},
    {"ticker": "MSFT", "start_date": "2024-01-01", "end_date": "2024-12-31"}
  ]
}
```

**Output:**
```json
{
  "status": "ok",
  "data": {
    "AAPL": [{...OHLCV...}],
    "MSFT": [{...OHLCV...}]
  }
}
```

---

### fetch_sectors Input/Output

**Input:**
```json
{"ticker": "AAPL"}
```

**Output:**
```json
{
  "status": "ok",
  "data": {
    "software": 0.45,
    "consumer_discretionary": 0.25,
    "hardware": 0.30
  }
}
```

---

## ✅ Clarity Checklist

Every contract:
- ✅ **What** (field + type)
- ✅ **Why** (purpose)
- ✅ **When** (created/triggered)
- ✅ **Who** (relationships)
- ✅ **How** (constraints)
