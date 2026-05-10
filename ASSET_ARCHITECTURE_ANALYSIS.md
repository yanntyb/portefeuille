# Analyse Architecturale — Domaine Asset

## Problème de conception : colonne `isin`

### Constat

La table `securities` définit `isin` comme `VARCHAR NOT NULL` avec un index `UNIQUE` global. Or tous les types d'actifs ne disposent pas d'un ISIN.

### Réalité financière par type d'actif

| Type | ISIN | Ticker | Identifiant standard |
|---|---|---|---|
| Stock | ✅ Obligatoire | ✅ Obligatoire | ISIN 12 car. (ex: FR0000131104) |
| ETF | ✅ Obligatoire | ✅ Obligatoire | ISIN 12 car. |
| Bond | ✅ Obligatoire | ❓ Optionnel | ISIN 12 car. |
| Crypto | ❌ Jamais | ✅ Obligatoire | Ticker (BTC, ETH…) |
| RealEstate | ❓ Seulement si REIT coté | ❓ Optionnel | ISIN si REIT, rien sinon |
| Savings | ❌ Jamais | ❌ Jamais | Produit bancaire, nom uniquement |

### Problèmes actuels

1. **Contrainte incorrecte** — `isin NOT NULL` force Crypto et Savings à avoir une valeur inexistante.
2. **Factories mensongères** — génèrent des ISIN fictifs pour Crypto, Savings, RealEstate.
3. **Index UNIQUE cassant** — empêche plusieurs actifs sans ISIN.
4. **Modèle STI trop plat** — `securities` porte des colonnes qui ne concernent que certains types.

---

## Solution : Class Table Inheritance (CTI)

Renommer `securities` → `assets` (parent commun). Une table de détails par type d'actif.

### Schéma cible

```
assets
  id, name, type, created_at, updated_at

stock_asset_infos      → asset_id (FK), isin (required), ticker (required)
etf_asset_infos        → asset_id (FK), isin (required), ticker (required)
bond_asset_infos       → asset_id (FK), isin (required), ticker (nullable)
crypto_asset_infos     → asset_id (FK), ticker (required)
realestate_asset_infos → asset_id (FK), isin (nullable)
savings_asset_infos    → asset_id (FK)  [no fields except FK]
```

### Modèles Eloquent

Trait générique `HasDetailsRelation` avec abstract methods pour flexibilité :

```php
// app/Infrastructure/Eloquent/Traits/HasDetailsRelation.php
trait HasDetailsRelation
{
    abstract protected function getDetailsModel(): string;

    public function details(): HasOne
    {
        return $this->hasOne($this->getDetailsModel(), 'asset_id');
    }
}
```

Chaque sous-classe implémente :

```php
// Stock.php
use HasDetailsRelation;

protected function getDetailsModel(): string { return StockAssetInfo::class; }

public function getIsinAttribute(): ?string { return $this->details?->isin; }
public function getTickerAttribute(): ?string { return $this->details?->ticker; }

// ETF.php
protected function getDetailsModel(): string { return ETFAssetInfo::class; }
// ... idem accesseurs

// Crypto.php
protected function getDetailsModel(): string { return CryptoAssetInfo::class; }
public function getTickerAttribute(): ?string { return $this->details?->ticker; }
```

DB constraints appliquent les règles ; accesseurs les exposent.

---

## Cleanup pre-CTI

### Étape 1 : réductions de contact points (Commit 98a8c6f)

- ✅ Suppression `NuclearSecuritiesSeeder` (imports cassés, jamais enregistré)
- ✅ Migration: `isin` `NOT NULL` → `nullable` + drop `UNIQUE`
- ✅ Factories Crypto, RealEstate, Savings : suppression `isin`/`ticker`

**Résultat :** -4 fichiers, -14 contact points

### Étape 2 : préparation architecturale pour CTI

- ✅ Trait `HasDetailsRelation` (`app/Infrastructure/Eloquent/Traits/HasDetailsRelation.php`)
  - Réduit duplication : chaque sous-classe (Stock, ETF, etc) implémente 2 abstract methods au lieu de `hasOne()` manuel
  - Prêt pour appel lors de la CTI

---

## Portée de la migration CTI (post-cleanup)

**Avant cleanup :** 19 fichiers, ~56 contact points  
**Après cleanup :** 15 fichiers, ~39 contact points  
**Gain :** -4 fichiers, -17 contact points

### Détail par catégorie

- **Modèles** (`$fillable`, PHPDoc) : 3 fichiers → 6 pts
- **Factories** : 6 fichiers → 3 fichiers → 7 pts (Crypto/RealEstate/Savings exclus)
- **Adapters** (YahooFinanceAdapter) : 1 fichier → 4 pts
- **Filament Portfolio** : 2 fichiers → 6 pts
- **Filament Analytics** : 2 fichiers → 4 pts
- **Migrations** : 2 existantes + 8-9 nouvelles (6 create detail tables, migrate data, cleanup, rename `securities` → `assets`)
- **Seeders** : 2 fichiers → 1 fichier → ~8 pts (NuclearSecuritiesSeeder supprimé)
- **Tests** : 1 fichier → 4 pts

### Points critiques

- `orderBy('isin')` dans `TransactionForm.php:91` — join nécessaire après CTI
- `firstOrCreate(['isin' => ...])` dans les seeders — lookup doit passer par la table de détail
- `TextColumn::make('isin')` dans les widgets Filament — relation dot-notation à mettre à jour
- `$asset->ticker` dans `YahooFinanceAdapter` — déléguer vers `$asset->details->ticker`

### Stratégie de migration

1. **Migrations** :
   - Créer 6 tables de détail (stock_asset_infos, etf_asset_infos, bond_asset_infos, crypto_asset_infos, realestate_asset_infos, savings_asset_infos)
   - Migrer isin/ticker de `securities` par type vers la table correspondante
   - Supprimer isin/ticker de `securities`
   - Renommer `securities` → `assets`

2. **Modèles** :
   - Stock/ETF/Bond/Crypto/RealEstate/Savings : `use HasDetailsRelation`
   - Chaque implémente `getDetailsModel()` → sa classe (StockAssetInfo, ETFAssetInfo, etc)
   - Accesseurs pour `isin`, `ticker` délégant vers `details()`

3. **Factories** — `afterCreating` pour créer le détail correspondant

4. **Filament + Seeders** — utilisent accesseurs Eloquent (pas de changement apparent si accesseurs bien implémentés)

### Priorité

**Haute** — Le modèle actuel produit des données incorrectes (ISIN fictifs) et viole l'intégrité des types. La correction garantit que chaque actif porte uniquement les identifiants qui lui correspondent réellement.
