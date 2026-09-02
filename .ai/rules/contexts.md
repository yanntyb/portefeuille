---
paths:
  - 'app/Contexts/**'
---

# Contexts

## AssetClass est la seule définition du partage par exposition
Chaque exposition a sa propre page liste et sa propre classe de patrimoine — `/actions`, `/obligations`, `/matieres-premieres`, `/crypto` (`AssetClass::slug()`), une par cas de `AssetClass::cases()`. Toutes les expositions partagent en revanche une seule fiche par actif (`/asset/{id}`, `AssetController`) : le fil d'Ariane s'y déduit de `assetClass`, pas de la route empruntée. Ce partage se lit dans `AssetClass` (colonne `assets.asset_class`), nulle part ailleurs — jamais par `InstrumentType`. Deux chemins distincts y filtrent : `Portfolio\GetPortfolioOverview($user, $classes)` lit tout le portefeuille en SQL une seule fois par utilisateur — l'action est liée en `scoped`, la lecture est mémoïsée pour la durée de la requête — puis filtre en mémoire sur `HoldingLineData::$assetClass`, tandis que `Valuation\BuildEvolutionSeries(..., $classes)` et `BuildPortfolioPerformances($userId, $classes)` passent par `InstrumentDirectoryPort::idsOfClasses()`. Les adaptateurs de `Wealth\Infrastructure` (`PortfolioAssetClass`) ne font qu'appeler ces actions ; ils ne filtrent rien eux-mêmes — c'est une seule classe paramétrée par l'exposition qu'elle porte en constructeur (`$this->exposure : AssetClass`), instanciée une fois par cas de `AssetClass::cases()` par `WealthProvider::registers()`, `RealEstateClass` restant la seule classe de patrimoine écrite à la main.

`InstrumentType` reste l'enveloppe (titre vif, ETF, contrat à terme), indépendante de ce partage. `BuildPortfolioViewSnapshot` n'en dépend pas : sa fiche unique (`pagesFor()`) et son gate de dividendes (`page()`) se lisent sur `IncomeSource::forAssetClass($detail->assetClass)`, pas sur l'enveloppe — c'est la même règle qu'applique `AssetController` pour décider d'inclure ou non la prop `dividends`.

Un piège lié :
- La série d'évolution se filtre APRÈS son cache (elle porte `assetId` par actif), les performances AVANT et sous un nom de cache distinct : une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'une classe à l'autre.
