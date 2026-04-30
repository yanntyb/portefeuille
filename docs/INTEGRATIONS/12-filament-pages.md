# Filament Pages - Argent

Dashboard, Wallet, Rebalancing, Simulation, Admin pages. Data dependencies.

---

## 🎯 Page Architecture (Layers)

```
📄 Pages (5)
  ├─ DashboardPage
  ├─ WalletPage
  ├─ RebalancingPage
  ├─ SimulationBoard
  └─ AdminPages

📥 Data Load (scoped by user)
  ├─ User + Wallets
  ├─ Transactions + Prices
  ├─ Allocation Profiles
  └─ Securities

🧮 Compute (business logic)
  ├─ Aggregators
  ├─ Calculators
  └─ Engines

📤 Display (render to UI)
  ├─ Metrics Cards
  ├─ Charts
  ├─ Tables
  └─ Suggestions
```

---

## 📋 Dependencies by Page

| Page | Data Load | Compute | Output |
|------|-----------|---------|--------|
| Dashboard | User, Wallets, TX, Prices | Aggregator, Calculator, SectorAgg | Metrics, Charts |
| Wallet | Wallet, TX, Prices, Fees | Aggregator, Calculator | Metrics, Holdings |
| Rebalancing | AllocationProfile, Holdings, Prices | RebalancingCalculator | Suggestions |
| Simulation | None (input-only) | MonteCarloEngine | Curves, Percentiles |
| Admin | Securities only | None | Forms, Lists |

---

## 📊 DashboardPage

**URL:** `/dashboard`

**Purpose:** Portfolio overview. All wallets, metrics, charts.

### Data Dependencies

1. **User** (`auth()->user()`)
2. **Wallets** (scoped: user_id)
3. **Transactions** (scoped: user_id)
4. **SecurityPrice** (latest date)
5. **SecuritySector**

### Computed Data

- **CumulativeData** (TransactionAggregator)
- **PerformanceMetrics** (PortfolioPerformanceCalculator)
- **SectorBreakdown** (SectorAggregator)

### Layout

```
┌─────────────────────────────────┐
│ 💰 Total Portfolio              │
│ 10,750€ (+450€ unrealized)      │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📈 Performance (All Wallets)    │
│ Day: +0.5% | Week: +2.3%        │
│ Month: +5.1% | YTD: +15.2%      │
│ All-time: -2.4%                 │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 🏢 Sector Allocation            │
│ Technology: 65%                 │
│ Utilities: 35%                  │
│ [Pie Chart]                     │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 💼 Wallets Summary              │
│ Growth: 1800€ | Safe: 420€      │
│ [Click to view details]         │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📊 Value History                │
│ [Line Chart: 90 days]           │
└─────────────────────────────────┘
```

### Performance

**Load time:** 150-300ms (aggregation + calculation)

**Caching:**
- PerformanceMetrics (1 hour)
- SectorBreakdown (1 hour)

---

## 💼 WalletPage

**URL:** `/wallets/{id}`

**Purpose:** Single wallet detail. Holdings, returns, transactions history.

### Data Dependencies

1. **Wallet** (verify ownership: user_id)
2. **Transactions** (wallet_id)
3. **SecurityPrice** (latest)
4. **WalletFee** (wallet_id)

### Computed Data

- **CumulativeData** (per wallet)
- **PerformanceMetrics** (per wallet)
- **Holdings breakdown** (current qty × price)

### Layout

```
┌─────────────────────────────────┐
│ Growth Wallet                   │
│ Created: 2024-01-15             │
│ Value: 1,800€                   │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📈 Wallet Returns               │
│ Day: +0.8% | Month: +6.2%       │
│ YTD: +18.5%                     │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📊 Holdings                     │
│ AAPL: 10 @ 185.64€ = 1856.40€  │
│ MSFT: 5 @ 421.30€ = 2106.50€   │
│ [Add Transaction] [Delete]      │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 💸 Fees (Annual)                │
│ Management: 50€ (0.3%)          │
│ Total: 50€                      │
│ [Add/Edit Fees]                 │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📈 Value History (90 days)      │
│ [Line Chart]                    │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📋 Recent Transactions          │
│ [Buy AAPL 10 @ 185.64] 2024-04-30│
│ [Sell MSFT 2 @ 415] 2024-04-28  │
│ [View All]                      │
└─────────────────────────────────┘
```

### Actions

- **Create Transaction** → Modal form
- **Edit Fee** → Inline edit or modal
- **Rename Wallet** → Inline edit
- **Delete Wallet** → Confirm modal

---

## 🔄 RebalancingPage

**URL:** `/wallets/{id}/rebalance`

**Purpose:** Portfolio rebalancing suggestions.

### Data Dependencies

1. **Wallet** (verify ownership)
2. **AllocationProfile** (wallet_id)
3. **Transactions** (current holdings)
4. **SecurityPrice** (latest)

### Computed Data

- **RebalancingResult** (RebalancingCalculator)

### Layout

```
┌─────────────────────────────────┐
│ Rebalancing: Growth Wallet      │
│ Profile: 60/40 Equities/Bonds   │
│ Current Value: 10,750€          │
│ Available Cash: 1,000€          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Allocation Profile              │
│ Create New | [60/40 ▼]          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📊 Suggestions                  │
│                                 │
│ SPY - S&P 500 ETF              │
│ Current: 22.3% | Target: 60%   │
│ → BUY 8 shares @ 480.50€       │
│   Cost: 3844.00€               │
│ New %: 58.1%                   │
│                                 │
│ BND - Bond ETF                 │
│ Current: 19.5% | Target: 40%   │
│ → BUY 26 shares @ 84.30€       │
│   Cost: 2191.80€               │
│ New %: 39.9%                   │
│                                 │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Remaining: 0.20€               │
│ Total Investment: 1,000€       │
│                                 │
│ [Execute] [Adjust] [Discard]   │
└─────────────────────────────────┘
```

### Actions

- **Create Profile** → Modal form (add items)
- **Edit Profile** → Modal form
- **Execute** → Pre-fill transactions
- **Adjust Cash** → Change available amount, recalculate

---

## 🎲 SimulationBoard

**URL:** `/wallets/{id}/simulate`

**Purpose:** Monte Carlo portfolio projections.

### Data Dependencies

1. **Wallet** (verify ownership)
2. **Current Holdings** (for context)
3. **Historical Returns** (estimate volatility)

### Computed Data

- **MonteCarloResult** (MonteCarloEngine)
- Multiple scenarios (base, conservative, optimistic)

### Layout

```
┌─────────────────────────────────┐
│ Portfolio Simulation            │
│ Wallet: Growth                  │
│ Current Value: 10,750€          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Base Case Configuration         │
│ Initial Capital: 100,000€       │
│ Monthly DCA: 500€               │
│ Expected Return: 7%             │
│ Volatility: 12%                 │
│ Duration: 10 years              │
│ Simulations: 10,000             │
│ [Recalculate]                   │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Conservative Scenario           │
│ Return: 5% | Volatility: 15%    │
│ DCA: 500€                       │
│ [Recalculate]                   │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Optimistic Scenario             │
│ Return: 9% | Volatility: 10%    │
│ DCA: 1000€                      │
│ [Recalculate]                   │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ Results (10 years)              │
│ Base Case:                      │
│ p10: 1.1M | p50: 1.95M |        │
│ p90: 3.2M                       │
│                                 │
│ Conservative:                   │
│ p10: 0.9M | p50: 1.5M |        │
│ p90: 2.3M                       │
│                                 │
│ Optimistic:                     │
│ p10: 1.5M | p50: 2.8M |        │
│ p90: 4.5M                       │
│                                 │
│ Probability of reaching         │
│ 2M€ target: 52% (base)          │
└─────────────────────────────────┘

┌─────────────────────────────────┐
│ 📊 Projection Chart             │
│ [Line Chart: 3 curves, p10/50/90]│
└─────────────────────────────────┘
```

### Actions

- **Update params** → Recalculate on blur
- **Switch scenario** → Tab switch
- **Add scenario** → Custom scenario form
- **Export results** → CSV/PDF download

---

## 👨‍💼 Admin Pages

**Location:** Filament admin panel

**Access:** role == 'Admin' only

### Security List

**URL:** `/admin/securities`

- List all securities (no scoping)
- Create → search ISIN + resolve ticker
- Edit → Button "Refresh Historical Data (5 years)"
- Delete → Confirm (cascade: SecurityPrice, SecuritySector)

### Invitation Management

**URL:** `/admin/invitations`

- Create invite → send email token
- Mark used → on registration
- Expire → after 30 days

### User Management

**URL:** `/admin/users`

- List users (no scoping)
- Edit role (User ↔ Admin)
- Impersonate (debug)
- Delete user (cascade)

---

## 🔐 Security & Scope

### Ownership Check

```php
// In page boot
$wallet = Wallet::findOrFail($id);
$this->authorize('view', $wallet);  // Policy check
```

### Scope Filter

```php
// Global scope on queries
Transaction::whereUserId(auth()->id())
  ->whereWalletId($wallet_id)
  ->get();
```

### Admin Bypass

```php
if (auth()->user()->role === Role::ADMIN) {
  // No scope filtering
  return Security::all();
}
```

---

## ⚡ Performance Targets

| Page | Load Time | Metrics |
|------|-----------|---------|
| Dashboard | 150-300ms | 5+ metrics, 3+ charts |
| WalletPage | 100-200ms | Holdings, returns, history |
| RebalancingPage | 50-150ms | Suggestions only |
| SimulationBoard | 200-500ms | 10k simulations |
| Admin pages | <100ms | Simple CRUD |

---

## 💾 Caching Strategy

**Query cache (1 hour):**
- PerformanceMetrics
- SectorBreakdown
- Holdings aggregations

**Invalidate on:**
- Transaction create/edit
- SecurityPrice refresh
- WalletFee change

---

## ✅ Testing

**Test file:** `tests/Feature/FilamentPagesTest.php`

```php
public function test_dashboard_loads_for_authenticated_user()
{
  $this->actingAs($user = User::factory()->create());
  
  $response = $this->get('/dashboard');
  $response->assertSuccessful();
  $response->assertSeeLivewire(DashboardPage::class);
}

public function test_wallet_page_shows_only_user_wallets()
{
  $this->actingAs($user1 = User::factory()->create());
  $user2 = User::factory()->create();
  
  $wallet1 = Wallet::factory()->for($user1)->create();
  $wallet2 = Wallet::factory()->for($user2)->create();
  
  $this->get("/wallets/{$wallet1->id}")->assertSuccessful();
  $this->get("/wallets/{$wallet2->id}")->assertForbidden();
}
```

---

## 📚 Quick Reference

| Page | Route | Dependencies | Refresh | Cache TTL |
|------|-------|--------------|---------|-----------|
| Dashboard | `/dashboard` | Wallets, TX, Prices | Auto | 1h |
| Wallet | `/wallets/{id}` | Wallet, TX, Prices | Auto | 1h |
| Rebalancing | `/wallets/{id}/rebalance` | Profile, Holdings | Manual | None |
| Simulation | `/wallets/{id}/simulate` | None (input-only) | Manual | None |
| Admin Security | `/admin/securities` | Securities | Manual | None |
