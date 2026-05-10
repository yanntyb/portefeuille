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

## Solution : CTI simplifié (une table de détails générique)

Renommer `securities` → `assets` (parent commun). Une seule table `asset_infos` contient tous les détails, colonnes optionnelles nullables selon le type.

### Schéma cible

```
assets
  id, name, type, created_at, updated_at

asset_infos
  id, asset_id (FK), isin (nullable), ticker (nullable), created_at, updated_at
  
Type-specific constraints appliquées au niveau applicatif :
  - Stock/ETF/Bond : isin required, ticker required (Bond : ticker nullable)
  - Crypto : ticker required, isin null
  - RealEstate : isin nullable, ticker null
  - Savings : isin null, ticker null
```

### Modèles Eloquent

Trait générique `HasDetailsRelation` — tous les modèles pointent vers `AssetInfo` :

```php
// app/Infrastructure/Eloquent/Traits/HasDetailsRelation.php
trait HasDetailsRelation
{
    public function details(): HasOne
    {
        return $this->hasOne(AssetInfo::class, 'asset_id');
    }
}
```

Chaque sous-classe l'utilise :

```php
// Stock.php, ETF.php, Crypto.php, etc.
use HasDetailsRelation;

// Accesseurs de rétro-compatibilité
public function getIsinAttribute(): ?string
{
    return $this->details?->isin;
}

public function getTickerAttribute(): ?string
{
    return $this->details?->ticker;
}
```

Validation des contraintes par type : au niveau du modèle ou FormRequest, pas à la DB.

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
- **Migrations** : 2 existantes + 3-4 nouvelles (create `asset_infos`, migrate data, cleanup `securities`)
- **Seeders** : 2 fichiers → 1 fichier → ~8 pts (NuclearSecuritiesSeeder supprimé)
- **Tests** : 1 fichier → 4 pts

### Points critiques

- `orderBy('isin')` dans `TransactionForm.php:91` — join nécessaire après CTI
- `firstOrCreate(['isin' => ...])` dans les seeders — lookup doit passer par la table de détail
- `TextColumn::make('isin')` dans les widgets Filament — relation dot-notation à mettre à jour
- `$asset->ticker` dans `YahooFinanceAdapter` — déléguer vers `$asset->details->ticker`

### Stratégie de migration

1. **Migrations** :
   - Créer table `asset_infos` (asset_id FK, isin, ticker)
   - Migrer isin/ticker de `securities` → `asset_infos`
   - Supprimer isin/ticker de `securities`
   - Renommer `securities` → `assets`

2. **Modèles** — ajouter `use HasDetailsRelation` + accesseurs de délégation sur chaque sous-classe

3. **Factories** — `afterCreating` pour créer le détail au moment de la création

4. **Validation** — ajouter contraintes par type (Stock: isin required, Crypto: isin null, etc) au niveau applicatif

5. **Filament** — utiliser accesseurs Eloquent `$asset->isin`, `$asset->ticker`

### Priorité

**Haute** — Le modèle actuel produit des données incorrectes (ISIN fictifs) et viole l'intégrité des types. La correction garantit que chaque actif porte uniquement les identifiants qui lui correspondent réellement.
