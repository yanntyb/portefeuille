# Glossary - Argent

Terms, formulas, abbreviations. Financial + technical.

---

## 📊 Financial Terms

### Buy

**Definition:** Purchase shares at unit_price.

**Impact on portfolio:**
- Increase quantity
- Add to invested basis
- realized_gain = null

**Example:**
```
Buy 10 AAPL @ 150€
qty = 10, invested += 1500€
```

---

### Sell

**Definition:** Liquidate shares, realize gain/loss.

**Impact on portfolio:**
- Decrease quantity
- Realize gain = (sale_price - cost_price) × qty - fees
- Update portfolio value

**Formula:**
```
realized_gain = qty × (unit_price - PRU) - fees
```

**Example:**
```
Sell 3 AAPL @ 160€ (PRU=151.67€)
realized_gain = 3 × (160 - 151.67) - 2 = 23€
```

---

### PRU (Previous Realized Unit Price)

**Definition:** Average cost of shares held in portfolio.

**Formula:**
```
PRU = Total Invested / Total Quantity
```

**Example:**
```
Holdings:
- 10 AAPL @ 150€ = 1500€
- 5 AAPL @ 155€ = 775€

PRU = (1500 + 775) / (10 + 5) = 151.67€
```

**Used for:** Computing realized_gain on Sell

---

### Portfolio Value

**Definition:** Current market value of all holdings.

**Formula:**
```
portfolio_value = Σ(quantity × current_price)
```

**Example:**
```
10 AAPL @ 185.64€ = 1856.40€
5 MSFT @ 421.30€ = 2106.50€
Total = 3962.90€
```

---

### Unrealized Gain

**Definition:** Profit/loss on held positions (not sold).

**Formula:**
```
unrealized_gain = (portfolio_value - invested)
unrealized_gain% = unrealized_gain / invested × 100
```

**Example:**
```
Invested: 2275€
Current value: 2400€
Unrealized gain: 125€ (+5.5%)
```

**Note:** Becomes realized when sold

---

### Realized Gain

**Definition:** Profit/loss on sold positions.

**Computed by:** TransactionObserver on Sell

**Formula:**
```
realized_gain = qty × (sale_price - PRU) - fees
```

**Impact:** Deducted from total return calculation

---

### Return %

**Definition:** Percentage profit/loss on invested capital.

**Formula:**
```
return% = ((current_value + realized_gains) - invested) / invested × 100
```

**Examples:**
```
Scenario 1:
Invested: 1000€, Current: 1100€, Realized: 0€
return% = (1100 - 1000) / 1000 × 100 = +10%

Scenario 2:
Invested: 1000€, Current: 950€, Realized: 50€ (from sales)
return% = ((950 + 50) - 1000) / 1000 × 100 = 0%
```

---

### Fee

**Definition:** Cost that reduces portfolio value.

**Types:**
- Fixed (€): Same amount regardless of portfolio value
- Percentage (%): Scales with portfolio value

**Deduction timing:**
- Included in return% calculation
- Reduces net invested amount

**Example:**
```
Fee = 50€ annual (fixed)
Invested = 10000€
Fee% = 50 / 10000 × 100 = 0.5%
Return (before fee) = 7.5%
Return (after fee) = 7.0%
```

---

### DCA (Dollar Cost Averaging)

**Definition:** Invest fixed amount regularly (e.g., monthly).

**Purpose:** Reduce impact of market timing, cost average

**Formula (in Monte Carlo):**
```
portfolio(t+1) = (portfolio(t) + monthly_dca) × (1 + r)
```

**Example (10 years, 500€/month):**
```
Year 1: 12 × 500€ = 6,000€ contributed
Year 10: 12 × 500€ = 6,000€ contributed
Total: 6,000€ × 10 = 60,000€ contributed
```

---

### Allocation Profile

**Definition:** Target percentage per security.

**Example:**
```
60/40 Equities/Bonds:
- SPY: 60%
- BND: 40%
Total: 100% (or less if keeping cash)
```

**Usage:** Input to RebalancingCalculator

---

### Rebalancing

**Definition:** Adjust holdings to match allocation targets.

**Trigger:** Manual (user discretion)

**Result:** Buy/sell suggestions from RebalancingCalculator

**Example:**
```
Target: SPY 60%, currently 22%
Action: Buy more SPY until 60%
```

---

### Correlation

**Definition:** Measure of how two assets move together.

**Range:** -1 to +1

**Meaning:**
| Value | Interpretation |
|-------|-----------------|
| +1.0 | Perfect positive (move exactly together) |
| +0.5 | Moderate positive |
| 0.0 | No correlation |
| -0.5 | Moderate negative (offset) |
| -1.0 | Perfect negative (perfect hedge) |

**Use:** Diversification analysis (find uncorrelated assets)

---

### Volatility

**Definition:** Standard deviation of returns. Measure of risk.

**Formula (annualized):**
```
volatility = std_dev(daily_returns) × √252
(252 = trading days/year)
```

**Typical ranges:**
- Bonds: 5-10%
- Stocks: 12-20%
- Tech: 20-40%

**Use:** Input to Monte Carlo simulation

---

## 📈 Technical Terms

### Query Scope

**Definition:** Global WHERE clause applied to all queries.

**Example:**
```sql
-- Global scope on Wallet model
SELECT * FROM wallets 
WHERE user_id = ? AND ...

-- Applied automatically in queries
Wallet::where('user_id', auth()->id())->get()
```

**Purpose:** User isolation (each user sees only own data)

---

### Observer

**Definition:** Event listener triggered on model save.

**Example (TransactionObserver):**
```php
// Triggers on Transaction::save()
public function saved(Transaction $transaction)
{
  if ($transaction->type === 'Sell') {
    // Compute realized_gain
  }
}
```

**Used for:** Automatic calculations, cache invalidation

---

### Caching

**Definition:** Store computed results to avoid recalculation.

**Strategy:**
- TTL: 1 hour
- Tags: 'portfolio', 'wallet_id'
- Invalidate: On transaction change

**Example:**
```
Compute PerformanceMetrics (expensive)
→ Cache for 1 hour
→ On transaction create, flush cache
→ Next load recalculates
```

---

### N+1 Query Problem

**Definition:** 1 query loads records, then N more queries load related data.

**Bad (N+1):**
```php
$users = User::all();  // 1 query
foreach ($users as $user) {
  $user->wallets;      // N more queries (1 per user)
}
```

**Good (eager loading):**
```php
$users = User::with('wallets')->get();  // 2 queries total
```

---

### Upsert

**Definition:** Insert if not exists, update if exists.

**SQL:**
```sql
INSERT INTO security_prices (security_id, date, close)
VALUES (123, '2024-04-30', 185.64)
ON CONFLICT (security_id, date) DO UPDATE SET close = 185.64
```

**Used for:** SecurityPrice refresh (idempotent)

---

### GBM (Geometric Brownian Motion)

**Definition:** Stochastic model for asset price movement.

**Formula:**
```
dS/S = μ dt + σ dW

Simplified:
S(t+1) = S(t) × exp(μ - σ²/2 + σ × z)
where z ~ N(0,1)
```

**Used in:** Monte Carlo simulation

---

### Box-Muller

**Definition:** Algorithm to transform uniform RNG → normal distribution.

**Formula:**
```
u1, u2 ~ Uniform(0, 1)
z = √(-2 ln u1) × cos(2π u2)
r ~ N(0, 1)
```

**Used in:** Monte Carlo (generate random returns)

---

## 🏛️ Abbreviations

| Abbr | Term | Context |
|------|------|---------|
| OHLCV | Open, High, Low, Close, Volume | Price data |
| ISIN | Intl Securities ID Number | Security identifier |
| PRU | Previous Realized Unit Price | Cost basis |
| DCA | Dollar Cost Averaging | Monthly contributions |
| YTD | Year To Date | Return period |
| API | Application Programming Interface | Yahoo Finance |
| IPC | Inter-Process Communication | PHP ↔ Python bridge |
| TTL | Time To Live | Cache duration |
| ETF | Exchange Traded Fund | Security type |
| GBM | Geometric Brownian Motion | Simulation model |
| Box-Muller | Transform algorithm | RNG → normal distribution |

---

## 📐 Key Formulas

### Portfolio Return

```
return% = ((current_value + realized_gains) - invested) / invested × 100
```

### Realized Gain (Sell)

```
realized_gain = quantity × (sale_price - PRU) - fees
```

### PRU (Cost Basis)

```
PRU = total_invested / total_quantity
```

### Rebalancing Need

```
target_value = portfolio_value × target%
shares_to_buy = floor((target_value - current_value) / price)
```

### Fee Impact

```
annual_fee (fixed) = value × frequency
annual_fee (%) = portfolio_value × (value / 100)
```

### Monte Carlo Return (monthly)

```
r ~ N(annual_return / 12, annual_volatility / √12)
portfolio(t+1) = (portfolio(t) + dca) × (1 + r)
```

### Correlation

```
correlation(X, Y) = cov(X, Y) / (std(X) × std(Y))
```

---

## 🎯 Glossary Index by Context

### Portfolio Management
- Buy, Sell, PRU, Realized Gain, Unrealized Gain
- Return %, Fee, DCA, Allocation Profile, Rebalancing

### Risk Analysis
- Volatility, Correlation, Diversification

### Technical
- Query Scope, Observer, Caching, N+1 Problem, Upsert
- GBM, Box-Muller, IPC, TTL

### Data
- OHLCV, ISIN, ISIN, Volume

---

## ✅ Formula Reference

**When computing returns:** Use formula in "Portfolio Return"
**When selling:** Use formula in "Realized Gain (Sell)"
**When determining cost basis:** Use formula in "PRU"
**When rebalancing:** Use formula in "Rebalancing Need"
**When simulating:** Use formula in "Monte Carlo Return"
