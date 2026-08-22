---
paths:
  - 'app/Contexts/Wealth/**'
---

# Wealth

## Péremption par empreinte, jamais par observer
La péremption des séries dérivées se lit dans les données, jamais par un observateur Eloquent. `RealEstateDemoSeeder::purgeRelated()` supprime en masse par le query builder, donc aucun événement de modèle n'en part, et le dépôt contient déjà des `upsert()` et `DB::table()->insert()` ailleurs. Voir `LaravelRealEstateCache` et son aîné `Valuation\Infrastructure\LaravelSeriesCache`. L'empreinte immobilière porte le jour courant, contrairement à celle des titres : `Price::max('date')` avance de lui-même, le parc immobilier non.
