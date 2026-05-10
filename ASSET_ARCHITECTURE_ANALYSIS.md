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

Conserver `securities` comme table parent avec les champs communs uniquement. Chaque type possède sa propre table de détail.

### Schéma cible

```
securities
  id, name, type, created_at, updated_at

stocks          → isin (required), ticker (required)
etfs            → isin (required), ticker (required)
bonds           → isin (required), ticker (nullable)
cryptos         → ticker (required)
real_estates    → isin (nullable)
savings         → (aucun champ spécifique)
```

### Modèles Eloquent

Trait générique `HasDetailsRelation` pour réduire la duplication :

```php
// app/Infrastructure/Eloquent/Traits/HasDetailsRelation.php
trait HasDetailsRelation
{
    abstract protected function getDetailsModel(): string;
    abstract protected function getDetailsForeignKey(): string;

    public function details(): HasOne
    {
        return $this->hasOne(
            $this->getDetailsModel(),
            $this->getDetailsForeignKey(),
            $this->getKeyName()
        );
    }
}
```

Chaque sous-classe implémente :

```php
// Stock.php
use HasDetailsRelation;

protected function getDetailsModel(): string { return StockDetails::class; }
protected function getDetailsForeignKey(): string { return 'asset_id'; }

// Accesseur de rétro-compatibilité
public function getIsinAttribute(): ?string
{
    return $this->details?->isin;
}
```

Accesseurs restent manuels par type (logique spécifique `isin`, `ticker`, etc.)

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
- **Migrations** : 2 existantes + 6 nouvelles
- **Seeders** : 2 fichiers → 1 fichier → ~8 pts (NuclearSecuritiesSeeder supprimé)
- **Tests** : 1 fichier → 4 pts

### Points critiques

- `orderBy('isin')` dans `TransactionForm.php:91` — join nécessaire après CTI
- `firstOrCreate(['isin' => ...])` dans les seeders — lookup doit passer par la table de détail
- `TextColumn::make('isin')` dans les widgets Filament — relation dot-notation à mettre à jour
- `$asset->ticker` dans `YahooFinanceAdapter` — déléguer vers `$asset->details->ticker`

### Stratégie de migration

1. **Nouvelles migrations** — créer les tables de détail, migrer les données existantes, supprimer `isin`/`ticker` de `securities`
2. **Modèles** — ajouter `hasOne` + accesseurs de délégation sur chaque sous-classe
3. **Factories** — `afterCreating` pour créer le détail au moment de la création de l'actif
4. **Adapters + Seeders** — accéder via `$asset->details->ticker`
5. **Filament** — mettre à jour dot-notation (`security.details.isin`) ou utiliser des accesseurs Eloquent

### Priorité

**Haute** — Le modèle actuel produit des données incorrectes (ISIN fictifs) et viole l'intégrité des types. La correction garantit que chaque actif porte uniquement les identifiants qui lui correspondent réellement.
