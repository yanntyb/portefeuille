---
paths:
  - 'app/Contexts/Wealth/**'
---

# Wealth

## Wealth tient le patrimoine, pas la page d'une enveloppe
Le contexte sert le tableau de bord : résumé, classes, revenus, secteurs, journal, et les cartes repliées des enveloppes (`AccountsPort::accountsFor()`, une liste, rien de plus). La page `/enveloppes/{id}` appartient à `PortfolioView` — elle lit des positions valorisées, comme une page d'exposition. Ne pas y rapatrier d'action `GetWallet*` : c'est précisément la duplication qui a été retirée (cinq actions, `WealthHoldingData`, `WalletClassSliceData`, `ValuationPort`, `WalletValuation`).

## Péremption par empreinte, jamais par observer
La péremption des séries dérivées se lit dans les données, jamais par un observateur Eloquent. `RealEstateDemoSeeder::purgeRelated()` supprime en masse par le query builder, donc aucun événement de modèle n'en part, et le dépôt contient déjà des `upsert()` et `DB::table()->insert()` ailleurs. Voir `LaravelRealEstateCache` et son aîné `Valuation\Infrastructure\LaravelSeriesCache`. L'empreinte immobilière porte le jour courant, contrairement à celle des titres : `Price::max('date')` avance de lui-même, le parc immobilier non.

## Une exposition, une origine de revenu, au plus une fois
`monthlyIncomeFor()` filtre par `IncomeSource` et non par exposition : deux expositions renvoyant
la même origine compteraient deux fois les mêmes encaissements. `IncomeSource::forAssetClass()` est
la seule définition de cette correspondance, et `WealthInvariantTest` la garde.

Les classes de patrimoine ne s'écrivent plus une par une : `PortfolioAssetClass` est paramétrée par
`AssetClass` et le registre boucle sur ses cas. Seule une classe qui n'est pas un portefeuille —
`RealEstateClass` — s'écrit à la main.
