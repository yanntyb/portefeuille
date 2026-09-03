---
paths:
  - 'app/Contexts/**'
---

# Contexts

## AssetClass est la seule définition du partage par exposition
Chaque exposition a sa propre page liste et sa propre classe de patrimoine — `/actions`, `/obligations`, `/matieres-premieres`, `/crypto` (`AssetClass::slug()`), une par cas de `AssetClass::cases()`. Toutes les expositions partagent en revanche une seule fiche par actif (`/asset/{id}`, `AssetController`) : le fil d'Ariane s'y déduit de `assetClass`, pas de la route empruntée. Ce partage se lit dans `AssetClass` (colonne `assets.asset_class`), nulle part ailleurs — jamais par `InstrumentType`. Deux chemins distincts y filtrent : `Portfolio\GetPortfolioOverview($user, $classes)` lit tout le portefeuille en SQL une seule fois par utilisateur — l'action est liée en `scoped`, la lecture est mémoïsée pour la durée de la requête — puis filtre en mémoire sur `HoldingLineData::$assetClass`, tandis que `Valuation\BuildEvolutionSeries(..., $scope)` et `BuildPortfolioPerformances($userId, $scope)` passent par `InstrumentDirectoryPort::idsOfClasses()`. Les adaptateurs de `Wealth\Infrastructure` (`PortfolioAssetClass`) ne font qu'appeler ces actions ; ils ne filtrent rien eux-mêmes — c'est une seule classe paramétrée par l'exposition qu'elle porte en constructeur (`$this->exposure : AssetClass`), instanciée une fois par cas de `AssetClass::cases()` par `WealthProvider::registers()`, `RealEstateClass` restant la seule classe de patrimoine écrite à la main.

`InstrumentType` reste l'enveloppe (titre vif, ETF, contrat à terme), indépendante de ce partage. `AssetPage::for()` — servi au contrôleur comme au snapshot — n'ajoute `dividends` que si `IncomeSource::forAssetClass($detail->assetClass)` rend une origine, jamais sur l'enveloppe (`InstrumentType`).

Un piège lié :
- La série d'évolution se filtre par classe APRÈS son cache (elle porte `assetId` par actif), mais par enveloppe AVANT et sous un nom de cache portant `HoldingScope::cacheKey()` — un actif détenu dans deux enveloppes n'a qu'une ligne. Les performances filtrent AVANT dans tous les cas, sous un nom distinct : une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'un périmètre à l'autre.

## Un port est une frontière technique, jamais une politesse entre contextes
`Ports/` ne contient que ce qui a une vraie frontière derrière : HTTP et Python (Yahoo), cache (`SeriesCachePort`, `RealEstateCachePort`), état de synchronisation, dépôts Eloquent de Market, et les registres d'extension (`AssetClassPort`, `IncomeSourcePort`). Entre deux contextes du monolithe, on injecte l'action du voisin et on rend sa Data au front telle quelle — jamais d'interface, d'adaptateur ni de Data jumelle. PortfolioView et Wealth ont porté vingt-deux Datas recopiées à l'octet, trente méthodes d'adaptateur passe-plat et cent cinquante tests de recopie avant le chantier du 3 septembre 2026 : dix-neuf fichiers à ouvrir pour lire un chiffre, un hash d'instantané déplacé dix-neuf fois. Les ports de Valuation et Income vers Portfolio (`TransactionHistoryPort`, `PositionHistoryPort`, `DividendHistoryPort`) survivent pour l'instant, leurs adaptateurs ayant une logique propre : même doctrine, chantier suivant.
