# État des Lieux : Architecture Asset et Polymorphie

## 1. Structure Physique DB

```
Table: securities
├─ id (primary key)
├─ type (AssetType enum: stock, etf, crypto, real_estate)
├─ isin (nullable, unique)
├─ ticker (nullable)
├─ name
├─ created_at
└─ updated_at

Table: asset_prices
├─ id
├─ asset_id (FK → securities.id)
├─ date
├─ open, high, low, close (decimal:4)
├─ volume
├─ created_at
└─ updated_at

Table: security_sectors
├─ id
├─ security_id (FK → securities.id)  ← NOTE: NOT asset_id
├─ sector (Sector enum)
├─ weight
├─ created_at
└─ updated_at

Table: transactions
├─ id
├─ user_id
├─ wallet_id
├─ asset_id (FK → securities.id)  ← Renamed from security_id
├─ type (buy|sell)
├─ quantity
├─ unit_price
├─ fees
├─ realized_gain
├─ created_at
└─ updated_at
```

## 2. Classes PHP Actuelles

### Asset Domain (nouveau)
```
Asset (abstract)
├─ table = 'securities'
├─ type casting = AssetType enum
├─ relations:
│  ├─ transactions() → HasMany(Transaction, asset_id)
│  ├─ prices() → HasMany(AssetPrice, asset_id)
│  ├─ latestPrice() → HasOne(AssetPrice, asset_id)
│  ├─ currentPrice() → HasOne(AssetPrice, asset_id)
│  └─ todayPrice() → HasOne(AssetPrice, asset_id)
│
└─ Stock (concrete)
   ├─ extends Asset
   ├─ fillable: [name, type, isin, ticker]
   └─ sectors() → HasMany(SecuritySector, 'security_id')
```

### Security Domain (legacy, sur MÊME TABLE)
```
Security (concrete Model)
├─ table = 'securities'
├─ fillable: [isin, name, ticker]  ← PAS de type colonne
├─ relations:
│  ├─ transactions() → HasMany(Transaction, asset_id)
│  ├─ prices() → HasMany(SecurityPrice, asset_id)
│  └─ sectors() → HasMany(SecuritySector, 'security_id')
│
├─ SecuritySector (n-m)
├─ SecurityPrice (deprecated, @deprecated)
└─ [scopes] forAuth(), forWallet()
```

### AssetPrice (nouveau)
```
AssetPrice
├─ table = 'asset_prices'
├─ asset() → BelongsTo(Security, 'asset_id')  ← points à Security, not Asset!
└─ casts: [date, open/high/low/close as decimal:4, volume as int]
```

---

## 3. Problèmes Identifiés

### ❌ Problème 1: Deux classes pour une même table
- `Security` = legacy model, plain extends Model
- `Stock` = new model, extends abstract Asset
- **Même table `securities`, deux représentations**
- Code mélange les deux : `Stock::find(1)` vs `Security::find(1)`

### ❌ Problème 2: Asset est abstract mais utilisé comme concrete
```php
// Asset.php ligne 29
protected $table = 'securities';  // ← PROBLÈME: abstract class désigne une table

// Eloquent NE PEUT PAS hydrater Asset directement
// Stock::find(1) fonctionne
// Asset::find(1)  ← ❌ Erreur: cannot instantiate abstract
```

### ❌ Problème 3: Relations pointent sur la mauvaise classe
```php
// AssetPrice.asset() relation
public function asset(): BelongsTo {
    return $this->belongsTo(Security::class, 'asset_id');  // ← NOT Asset::class!
}
// Raison: Asset est abstract, donc on utilise Security à la place
```

### ❌ Problème 4: SecuritySector reste sur security_id
```php
// Transaction table: asset_id → securities
// asset_prices table: asset_id → securities
// security_sectors table: security_id → securities  ← INCOHÉRENT!

// SecuritySector.php
public function security(): BelongsTo {
    return $this->belongsTo(Security::class, 'security_id');  // ← not asset_id
}
```

### ❌ Problème 5: Pas de vraie polymorphie pour nouveaux types
Pour supporter **Crypto** et **RealEstate**, il faudrait:
```php
// IMPOSSIBLE ACTUELLEMENT:
class Crypto extends Asset { }
class RealEstate extends Asset { }
class Stock extends Asset { }

// Pourquoi impossible? Car Security domain assume que 
// tout sur la table securities est un "Security"
```

### ❌ Problème 6: AssetType enum existe mais n'est pas utilisé partout
```php
// Asset.php: casts type → AssetType enum ✅
protected function casts(): array {
    return ['type' => AssetType::class];
}

// Security.php: AUCUN casting pour type ❌
// Donc Security ne sait pas qu'il y a un AssetType
```

---

## 4. Flux Actuel (Confus)

```
User crée transaction pour Stock AAPL
        ↓
Transaction.asset_id = 1 (points à securities.id = 1)
        ↓
Deux façons de récupérer:
  ├─ Stock::find(1)      ← Asset domain path
  ├─ Security::find(1)   ← Security domain path
        ↓
Les deux fonctionnent mais:
  ├─ Stock: connaît type (AssetType enum)
  ├─ Security: ne connaît PAS type
```

---

## 5. Portfolio/Analytics Impact

| Code | Utilise | Problème |
|------|---------|----------|
| `Transaction.asset()` | FK `asset_id` | Points à Security, pas à Asset |
| `Asset.transactions()` | Query `asset_id` | Fonctionne mais Asset est abstract |
| `AssetPrice.asset()` | FK `asset_id` | Points à Security, pas à Asset |
| `SecuritySector` | FK `security_id` | Seule chose qui reste sur security_id |
| `RebalancingCalculator` | Charge via Security | Perd information AssetType |
| `YahooFinanceService` | Upsert SecurityPrice | Deprecated mais toujours utilisé |

---

## 6. Vue Graphique: Incohérence Structurelle

```
┌─────────────────────────────────────────────────────────┐
│           Table: securities (une seule)                │
│   ID │ TYPE │ ISIN │ TICKER │ NAME │ CREATED_AT      │
└─────────────────────────────────────────────────────────┘
  ▲         ▲
  │         │
  │      Colonne TYPE
  │      (AssetType enum)
  │         │
  └─────┬───┴──────────────────────┐
        │                          │
   ┌────▼────┐           ┌────────▼──────┐
   │  Asset  │           │   Security    │
   │(abstract)           │  (concrete)   │
   └────┬────┘           └────────┬──────┘
        │                         │
        │                    Ignore TYPE!
        │                      │
        └──────┬───────────────┘
               │
        Deux classes, même table
        Polymorphie brisée
```

---

## 7. Quels Modèles Supportent Quel Type?

```
AssetType::Stock
  ├─ Créé via Stock::create() ✅
  └─ Récupéré via Security::find() ✅

AssetType::ETF
  ├─ Créé via Security::create() (pas de sous-classe) ⚠️
  └─ Récupéré via Security::find() ✅

AssetType::Crypto
  ├─ N'existe pas (pas de classe Crypto extends Asset)
  ├─ Ne peut pas être créé type-safe
  └─ Si créé: Security::find() le récupère mais type = 'crypto'

AssetType::RealEstate
  ├─ N'existe pas (pas de classe RealEstate extends Asset)
  └─ Impossible de supporter sans refactor
```

---

## 8. Scénario: Ajouter Support Crypto

```
// Phase 10 veut: Crypto prices depuis CoinGecko
// Faudrait:

1. Créer classe Crypto
class Crypto extends Asset {
    public function coingeckoData() { ... }
}

2. Mais Security domain attend tout sur security_id
   → Security.sectors() cherche security_id
   → Crypto n'a pas de sectors
   
3. Créer CryptoPrice extends AssetPrice?
   → Mais AssetPrice.asset() pointe à Security
   → Pas d'interface commune pour Price
```

---

## 9. Root Causes

| Cause | Impact |
|-------|--------|
| Asset domain crée pendant refactor, Security domain reste legacy | Deux implémentations coexistent |
| Asset.php abstract mais pointe sur table | Relation assets() impossible |
| AssetPrice.asset() pointe Security::class (workaround pour abstract) | Pas de polymorphie |
| Seul Stock hérite de Asset, autres types = Security + type colonne | Inconsistency |
| security_sectors jamais migré vers asset_id | FK split: transactions/prices sur asset_id, sectors sur security_id |
| No casting AssetType dans Security | Legacy code ne voit pas le type |

---

## 10. Options de Fix

### Option A: Complète polymorphie (idéal)
```php
// Supprimer Security, utiliser Asset + sous-classes
Asset (abstract)
├─ Stock
├─ Crypto  
├─ RealEstate
└─ ETF

// Tous les FK points à Asset
// AssetPrice.asset() → BelongsTo(Asset)
// Transaction.asset() → BelongsTo(Asset)
```

### Option B: Single Table Inheritance (pragmatique)
```php
// Garder une seule classe Security
// Ajouter type casting + interfaces
Security {
    type: AssetType (cast)
}

// Selon type, comportements différents
if ($security->type === AssetType::Crypto) { ... }
```

### Option C: Hybrid (étapes)
```php
// Phase 1: Converger sur Asset comme source de truth
// Phase 2: Supprimer Security graduellement
// Phase 3: Créer sous-classes pour chaque AssetType
```

---

## 11. Ligne d'Action Immédiate

1. **Clarifier:** Asset vs Security - une seule abstraction
2. **Migrer:** security_sectors.security_id → asset_id (cohésion)
3. **Typer:** AssetType casting dans Security si on la garde
4. **Tester:** Peut-on créer Crypto/RealEstate sans casser existant?

