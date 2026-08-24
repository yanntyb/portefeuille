---
paths:
  - 'app/Contexts/**'
---

# Contexts

## AssetClass est la seule définition du partage par exposition
La crypto a sa propre page liste et sa propre classe de patrimoine ; les actions, obligations et matières premières restent ensemble. Toutes les expositions partagent en revanche une seule fiche par actif (`/asset/{id}`, `AssetController`) : le fil d'Ariane s'y déduit de `assetClass`, pas de la route empruntée. Ce partage se lit dans `AssetClass` (colonne `assets.asset_class`), nulle part ailleurs — jamais par `InstrumentType`. Deux chemins distincts y filtrent : `Portfolio\GetPortfolioOverview($user, $classes)` filtre directement en SQL (`whereHas('asset', … whereIn('asset_class', …))`), tandis que `Valuation\BuildEvolutionSeries(..., $classes)` et `BuildPortfolioPerformances($userId, $classes)` passent par `InstrumentDirectoryPort::idsOfClasses()`. Les adaptateurs de `Wealth\Infrastructure` (`PortfolioAssetClass::classes()`) ne font qu'appeler ces actions ; ils ne filtrent rien eux-mêmes.

`InstrumentType` reste l'enveloppe (titre vif, ETF, contrat à terme), indépendante de ce partage. `BuildMarketViewSnapshot` n'en dépend pas : sa fiche unique (`pagesFor()`) et son gate de dividendes (`page()`) se lisent sur `IncomeSource::forAssetClass($detail->assetClass)`, pas sur l'enveloppe — c'est la même règle qu'applique `AssetController` pour décider d'inclure ou non la prop `dividends`.

Un piège lié :
- La série d'évolution se filtre APRÈS son cache (elle porte `assetId` par actif), les performances AVANT et sous un nom de cache distinct : une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'une classe à l'autre.
