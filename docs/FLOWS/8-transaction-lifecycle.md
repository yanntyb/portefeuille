# Transaction Lifecycle - Argent

Create form → Validate → Insert → Observer → Compute gain.

---

## 🔄 State Machine

```mermaid
graph TB
    A["User Opens<br/>Create Form"] --> B["Enter Details<br/>Type, qty, price, date"]
    B --> C["Submit Form"]
    C --> D["Validate<br/>FormRequest"]
    
    D -->|Invalid| E["Show Errors<br/>Retry"]
    E --> B
    
    D -->|Valid| F["INSERT Transaction<br/>Database"]
    F --> G["TransactionObserver<br/>Boot Event"]
    
    G --> H{Transaction<br/>Type?}
    H -->|Buy| I["Set realized_gain = null"]
    H -->|Sell| J["Compute realized_gain<br/>qty × (unit_price - PRU) - fees"]
    
    I & J --> K["SAVE Observer<br/>Update realized_gain"]
    K --> L["Invalidate Cache<br/>Dashboard metrics"]
    L --> M["Success Message<br/>Return to dashboard"]
    
    classDef start fill:#3b82f6,color:#fff
    classDef process fill:#8b5cf6,color:#fff
    classDef decision fill:#f59e0b,color:#fff
    classDef observer fill:#10b981,color:#fff
    classDef end fill:#ef4444,color:#fff
    
    class A start
    class B,C,F process
    class D decision
    class E end
    class G,H,I,J,K,L observer
    class M end
```

---

## 📝 Form Input

**URL:** `/transactions/create`

**Fields:**
```json
{
  "wallet_id": 1,
  "security_id": 123,
  "type": "Buy|Sell",
  "date": "2024-04-30",
  "quantity": 10,
  "unit_price": 185.64,
  "fees": 5.00,
  "notes": "Optional annotation"
}
```

**Validation (FormRequest):**
```php
'wallet_id' => 'required|exists:wallets,id',
'security_id' => 'required|exists:securities,id',
'type' => 'required|in:Buy,Sell',
'date' => 'required|date|before_or_equal:today',
'quantity' => 'required|numeric|min:0.01',
'unit_price' => 'required|numeric|min:0.01',
'fees' => 'required|numeric|min:0'
```

**Scope check:** Wallet belongs to auth user
```php
'wallet_id' => 'required|exists:wallets,id,user_id,' . auth()->id()
```

---

## ✅ Validation

### Rule 1: Wallet Ownership

**Check:**
```sql
SELECT id FROM wallets 
WHERE id = ? AND user_id = ?
```

**Fail:** 403 Unauthorized (scope violation)

---

### Rule 2: Security Exists

**Check:**
```sql
SELECT id FROM securities WHERE id = ?
```

**Fail:** 422 Unprocessable (security not found)

---

### Rule 3: Date Not Future

**Check:** `date <= today`

**Fail:** 422 Validation error

---

### Rule 4: Quantity Valid

**Check:** `quantity > 0`

**Fail:** 422 Validation error

---

## 💾 Database Insert

**Executed:**
```sql
INSERT INTO transactions (
  user_id,
  wallet_id,
  security_id,
  type,
  date,
  quantity,
  unit_price,
  fees,
  realized_gain,
  created_at,
  updated_at
) VALUES (
  ?,  -- auth()->id()
  ?,  -- $request->wallet_id
  ?,  -- $request->security_id
  ?,  -- $request->type
  ?,  -- $request->date
  ?,  -- $request->quantity
  ?,  -- $request->unit_price
  ?,  -- $request->fees
  NULL,  -- Will be set by Observer if Sell
  NOW(),
  NOW()
)
```

---

## 👁️ Observer: TransactionObserver

**Triggered:** On `saved` event (after INSERT/UPDATE committed)

**Purpose:** Compute realized_gain for Sell transactions

### Logic

```php
// app/Observers/TransactionObserver.php

public function saved(Transaction $transaction): void
{
  if ($transaction->type === TransactionType::Sell) {
    $pru = $transaction->computePRU();  // Previous realized unit price
    $realized_gain = 
      ($transaction->quantity * ($transaction->unit_price - $pru)) 
      - $transaction->fees;
    
    $transaction->update(['realized_gain' => $realized_gain]);
  } else {
    $transaction->update(['realized_gain' => null]);
  }
  
  // Invalidate cache
  Cache::tags(['portfolio', $transaction->wallet_id])->flush();
}
```

---

## 🧮 Realized Gain Computation

**Formula:**
```
realized_gain = quantity × (unit_price - PRU) - fees
```

**Where:**
- **quantity:** Shares sold
- **unit_price:** Sale price per share
- **PRU:** Previous Realized Unit price (avg cost of held shares)
- **fees:** Commission/costs

### PRU Calculation

**Method:** Weighted average of remaining quantity

**Example:**
```
Holdings:
- 10 AAPL @ 150€ (total: 1500€)
- 5 AAPL @ 155€ (total: 775€)

Total: 15 AAPL, invested: 2275€
PRU = 2275 / 15 = 151.67€

Sell 3 @ 160€:
  realized_gain = 3 × (160 - 151.67) - 2€
               = 3 × 8.33 - 2€
               = 25€ - 2€
               = 23€ profit
```

---

## 🎯 Observer Details

### When Triggered

**CREATE:** After INSERT
```
1. Form submitted
2. Validation passes
3. INSERT executed
4. TransactionObserver::saved() called
5. realized_gain computed (if Sell)
```

**UPDATE:** After UPDATE
```
Same flow, re-compute if type changed to Sell
```

---

### Observer Exceptions

**BEFORE:** Fields set in saved() still available
```php
public function saved(Transaction $transaction): void
{
  // Can access $transaction->quantity, ->unit_price, etc.
}
```

**AFTER:** Fields read-only (fresh query if needed)
```php
public function created(Transaction $transaction): void
{
  // For post-processing (webhooks, logs)
}
```

---

## 📊 Cache Invalidation

**Tags flushed:**
```
Cache::tags(['portfolio', $transaction->wallet_id])->flush()
```

**Affects:**
- PerformanceMetrics (recalculated on next load)
- SectorBreakdown (recalculated)
- Dashboard (refreshed)

**Timing:** Synchronous (happens during request)

---

## 🔄 Complete Flow Example

**User creates Sell transaction:**

```
Input:
  wallet_id: 1
  security_id: 123 (AAPL)
  type: "Sell"
  date: "2024-04-30"
  quantity: 3
  unit_price: 160
  fees: 2

↓ Validation ✓

↓ INSERT Transaction
  user_id: 42
  realized_gain: NULL  (initially)

↓ TransactionObserver::saved()
  1. Detect type == "Sell"
  2. Compute PRU = 151.67€
  3. realized_gain = 3 × (160 - 151.67) - 2 = 23€
  4. UPDATE transaction SET realized_gain = 23

↓ Cache::tags(['portfolio', 1])->flush()

↓ Success Message
  "Transaction created. Gain: 23€"

↓ Redirect to Dashboard
  Shows updated returns with +23€ realized
```

---

## ⚡ Performance

| Step | Time |
|------|------|
| Form validation | <5ms |
| INSERT | 5-10ms |
| PRU computation | <2ms (query per security) |
| realized_gain UPDATE | 3-5ms |
| Cache flush | <1ms |
| **Total** | **15-25ms** |

---

## ✅ Edge Cases

| Case | Behavior |
|------|----------|
| Sell before any buy | PRU = 0, gain = (qty × unit_price) - fees |
| Sell entire position | Correct gain computed, qty=0 afterward |
| Sell more than held | Error (should prevent in UI validation) |
| Zero fees | Works, realized_gain = qty × (unit_price - PRU) |
| Negative realized_gain | Loss, stored as negative value |
