---
paths:
  - 'app/Contexts/**'
---

# Contexts

## AssetClass est la seule définition du partage par exposition
La crypto a sa propre page, sa propre classe de patrimoine et ses propres fiches ; les actions, obligations et matières premières restent ensemble. Ce partage se lit dans `AssetClass` (colonne `assets.asset_class`), nulle part ailleurs — jamais par `InstrumentType`. Deux chemins distincts y filtrent : `Portfolio\GetPortfolioOverview($user, $classes)` filtre directement en SQL (`whereHas('asset', … whereIn('asset_class', …))`), tandis que `Valuation\BuildEvolutionSeries(..., $classes)` et `BuildPortfolioPerformances($userId, $classes)` passent par `InstrumentDirectoryPort::idsOfClasses()`. Les adaptateurs de `Wealth\Infrastructure` (`PortfolioAssetClass::classes()`) ne font qu'appeler ces actions ; ils ne filtrent rien eux-mêmes.

`InstrumentType` reste l'enveloppe (titre vif, ETF, contrat à terme) : il garde `isCrypto()`, utilisé par `InstrumentDetailController`, `CryptoDetailController` et `BuildMarketViewSnapshot::detailsByClass()` pour router une fiche vers la bonne page — un usage distinct du filtrage par exposition, à ne pas confondre.

Deux pièges liés :
- La série d'évolution se filtre APRÈS son cache (elle porte `assetId` par actif), les performances AVANT et sous un nom de cache distinct : une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'une classe à l'autre.
- `InstrumentDetailController` et `CryptoDetailController` renvoient 404 sur l'actif de l'autre classe. Sans cela un même actif répondrait à deux adresses, avec deux fils d'Ariane contradictoires.
