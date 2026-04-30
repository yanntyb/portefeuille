# Enums - Argent

Enumerated types. All constants, valid values, usage context.

---

## 📋 Enum Template

Every enum defines:
- **Values** — Valid options
- **Purpose** — Why exists
- **Usage** — Where referenced
- **Default** — If applicable

---

## 🔵 TransactionType

**Purpose:** Direction of portfolio movement.

| Value | Meaning | Usage |
|-------|---------|-------|
| `Buy` | Purchase security | Transaction.type |
| `Sell` | Sell security | Transaction.type |

**Constraint:** Immutable after creation
**Observer:** On Sell → compute realized_gain

---

## 👤 Role

**Purpose:** Access control. Filament gates.

| Value | Meaning | Permissions |
|-------|---------|-------------|
| `User` | Regular user | View/edit own data (scoped) |
| `Admin` | Administrator | Full access (bypass scopes) |

**Implementation:** `->gate('filament')` checks `auth()->user()->role`
**Used in:** User.role

---

## 💶 CurrencyModificationUnit

**Purpose:** Fee interpretation. Fixed vs percentage.

| Value | Meaning | Example |
|-------|---------|---------|
| `€` | Fixed currency amount | Fee = 50€ |
| `%` | Percentage of portfolio | Fee = 1% of value |

**Context:** WalletFee.unit
**Calculation:** (unit=€) → fixed; (unit=%) → portfolio_value × percentage

---

## 📊 FeeScope

**Purpose:** Basis for fee application.

| Value | Meaning | Calculation |
|-------|---------|-------------|
| `All` | Apply to full portfolio | fee_amount (fixed) |
| `PercentageOfValue` | Apply % to current value | portfolio_value × fee% |

**Context:** WalletFee.scope
**Implementation:** PortfolioPerformanceCalculator deducts fees before return calc

---

## ⏰ FrequencyUnit

**Purpose:** Fee repetition period.

| Value | Meaning | When Applied |
|-------|---------|---------------|
| `Monthly` | Every month | 12 times/year |
| `Yearly` | Once per year | Annual deduction |
| `OneTime` | Single charge | On creation date |

**Context:** WalletFee.frequency
**Deduction:** Spread across period or lump at period end (configurable)

---

## 🏢 Sector

**Purpose:** Security classification. Risk breakdown.

| Sector | Type | Example |
|--------|------|---------|
| `Technology` | Information Technology | AAPL, MSFT |
| `Healthcare` | Healthcare | JNJ, PFE |
| `Financials` | Banks, Insurance | JPM, BLK |
| `Industrials` | Manufacturing | BA, CAT |
| `ConsumerDiscretionary` | Retail, Luxury | AMZN, LVMH |
| `ConsumerStaples` | Food, Household | PG, KO |
| `Energy` | Oil, Gas, Renewable | XOM, TTE |
| `Materials` | Metals, Chemicals | FCX, LIN |
| `RealEstate` | REIT | VICI, PLD |
| `Utilities` | Electric, Water | NEE, DUK |
| `CommunicationServices` | Telecom, Media | META, GOOGL |

**Context:** SecuritySector.sector
**Source:** FetchSecuritySectorsCommand (Python bridge)
**Used by:** SectorAggregator → Dashboard breakdown chart

---

## 📈 PerformancePeriod

**Purpose:** Time buckets for return calculation.

| Period | Meaning | Calculation |
|--------|---------|-------------|
| `day` | Last 24 hours | (current - 1d ago) / invested |
| `week` | Last 7 days | (current - 7d ago) / invested |
| `month` | Last 30 days | (current - 30d ago) / invested |
| `quarter` | Last 90 days | (current - 90d ago) / invested |
| `year` | Last 365 days | (current - 365d ago) / invested |
| `ytd` | Year to date | (current - Jan 1) / invested |
| `all_time` | Entire portfolio history | (current - inception) / invested |

**Context:** PerformanceMetrics output model
**Formula:** `return% = (current_value - invested) / invested × 100`
**Used by:** Dashboard, WalletPage (display returns)

---

## 🔍 Complete Reference

| Enum | Values | Cardinality | Mutability |
|------|--------|-------------|-----------|
| TransactionType | 2 | Fixed | Immutable |
| Role | 2 | Fixed | Mutable (admin only) |
| CurrencyModificationUnit | 2 | Fixed | Immutable |
| FeeScope | 2 | Fixed | Immutable |
| FrequencyUnit | 3 | Fixed | Immutable |
| Sector | 11 | Semi-fixed | Managed by UpdateSecurityJob |
| PerformancePeriod | 7 | Fixed | Immutable |

---

## ✅ Usage Checklist

When creating enum:
- ✅ Define all valid values
- ✅ Document purpose
- ✅ Show where used (model field, calculator, page)
- ✅ Explain calculation/impact
- ✅ Note mutability (can change after creation?)
- ✅ Show examples if applicable
