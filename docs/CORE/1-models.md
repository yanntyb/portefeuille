# Core Models - Argent

11 entities. User → Wallet → Transaction → Security → Price.

---

## 📊 Entity Relationship Diagram

```mermaid
erDiagram
    USER ||--o{ WALLET : "1:N"
    USER ||--o{ TRANSACTION : "1:N"
    USER ||--o{ ALLOCATION_PROFILE : "1:N"
    WALLET ||--o{ TRANSACTION : "1:N"
    WALLET ||--o{ WALLET_FEE : "1:N"
    WALLET ||--o{ ALLOCATION_PROFILE : "1:N"
    TRANSACTION }o--|| SECURITY : "N:1"
    SECURITY ||--o{ SECURITY_PRICE : "1:N"
    SECURITY ||--o{ SECURITY_SECTOR : "1:N"
    ALLOCATION_PROFILE ||--o{ ALLOCATION_PROFILE_ITEM : "1:N"
    ALLOCATION_PROFILE_ITEM }o--|| SECURITY : "N:1"
```

---

## 🔵 User
**Root entity. Auth + identity.**

| Field | Type | Scope |
|-------|------|-------|
| id | int | PK |
| email | string | unique, login |
| name | string | display |
| password | string | hashed |
| role | Admin\|User | Filament gates |

**Global scope:** All queries filtered by `auth()->id()`

---

## 💼 Wallet
**Container for transactions. Logical grouping.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| user_id | int | FK (scope) |
| name | string | Label (e.g., "Growth") |

**Scope:** `where('user_id', auth()->id())`

---

## 📍 Transaction
**Buy/Sell record. Position source of truth.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| user_id | int | Scope |
| wallet_id | int | Container |
| security_id | int | Which security |
| type | Buy\|Sell | Direction |
| date | date | Settlement |
| quantity | decimal(4) | Shares |
| unit_price | decimal(4) | Price/share |
| fees | decimal(2) | Commission |
| realized_gain | decimal(2) | Sell only (computed) |

**Observer:** `TransactionObserver` ← On save
- If type=Sell: compute realized_gain
- If type=Buy: realized_gain = null

**Scope:** `where('user_id', auth()->id())`

---

## 🔐 Security
**Global catalogue. Shared across users.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| isin | string | unique, identifier |
| name | string | Full name |
| ticker | string | Trading symbol |

**Scopes (compute-time):**
- `forAuth()` → User positions + metrics
- `forWallet()` → Wallet positions + metrics

---

## 💹 SecurityPrice
**OHLCV history. Valuation source.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| security_id | int | FK |
| date | date | UK + index |
| open | decimal(4) | OHLC |
| high | decimal(4) | OHLC |
| low | decimal(4) | OHLC |
| close | decimal(4) | **Valuation ref** |
| volume | int | Trading vol |

**Refresh:** Daily scheduled (incremental) OR Manual (5-year backfill)

---

## 💰 WalletFee
**Recurring/fixed fees. Impact on net performance.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| wallet_id | int | FK |
| name | string | Label |
| value | decimal(4) | Amount or % |
| unit | €\|% | Interpretation |
| scope | All\|PercentageOfValue | Application |
| frequency | Monthly\|Yearly\|OneTime | Period |

---

## 🎯 AllocationProfile
**Target allocation. Guide rebalancing.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| user_id | int | Ownership |
| wallet_id | int | Which wallet |
| name | string | Label |

**Items:** AllocationProfileItem[] (1 security = X%)

---

## 📊 AllocationProfileItem
**Single line in allocation.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| allocation_profile_id | int | FK |
| security_id | int | Which security |
| target_percentage | decimal(2) | % of portfolio |

---

## 🏢 SecuritySector
**Sector classification. Risk analysis.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| security_id | int | FK |
| sector | enum | Category |
| weight | decimal | % composition |

**Refresh:** Daily scheduled (if outdated >7d) OR Manual

---

## 💌 Invitation
**User signup via invite.**

| Field | Type | Purpose |
|-------|------|---------|
| id | int | PK |
| created_by | int | Admin |
| token | string | Validation |
| email | string | Target |
| used_at | timestamp | Activation |

---

## 🔒 Security Isolation

```mermaid
graph LR
    subgraph U1 ["User 123"]
        W1["Wallet A<br/>scope: user_id=123"]
        T1["Transaction<br/>scope: user_id=123"]
    end
    
    subgraph U2 ["User 456"]
        W2["Wallet B<br/>scope: user_id=456"]
        T2["Transaction<br/>scope: user_id=456"]
    end
    
    Admin["Admin<br/>role=admin<br/>bypass"]
    
    W1 -.-> T1
    W2 -.-> T2
    Admin -.->|Filament| W1 & W2
    
    classDef u1 fill:#fca5a5,color:#000
    classDef u2 fill:#bfdbfe,color:#000
    classDef admin fill:#fed7aa,color:#000
    
    class U1,W1,T1 u1
    class U2,W2,T2 u2
    class Admin admin
```

**Implementation:** Global scope filter on Wallet, Transaction, AllocationProfile

---

## 🔄 Entity Lifecycle

| Entity | Created | Updated | Deleted |
|--------|---------|---------|---------|
| User | Register | Edit profile | Leave app |
| Wallet | User action | Rename | User action |
| Transaction | User form | Edit | User action |
| Security | Admin import | UpdateSecurityJob | Never |
| SecurityPrice | Refresh command | Upsert | Prune old |
| WalletFee | User action | Edit | User action |
| AllocationProfile | User action | Edit | User action |
| SecuritySector | Refresh command | Upsert | Prune |
| Invitation | Admin action | — | Expire |
