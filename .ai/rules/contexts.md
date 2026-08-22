---
paths:
  - 'app/Contexts/**'
---

# Contexts

## InstrumentType::securities() est la seule définition de ce qui n'est pas crypto
La crypto a sa propre page, sa propre classe de patrimoine et ses propres fiches ; les actions, ETF, obligations et matières premières restent ensemble. Ce partage se lit dans `InstrumentType::securities()` et `isCrypto()`, nulle part ailleurs. Tout filtre par classe d'actif — `GetPortfolioOverview($user, $types)`, `BuildEvolutionSeries(..., $types)`, `BuildPortfolioPerformances($userId, $types)`, les adaptateurs de `Wealth\Infrastructure` — passe par elles.

Deux pièges liés :
- La série d'évolution se filtre APRÈS son cache (elle porte `assetId` par actif), les performances AVANT et sous un nom de cache distinct : une fenêtre glissante agrège les transactions, elle ne se découpe pas après coup. Un nom réutilisé ferait servir le résultat d'une classe à l'autre.
- `InstrumentDetailController` et `CryptoDetailController` renvoient 404 sur l'actif de l'autre classe. Sans cela un même actif répondrait à deux adresses, avec deux fils d'Ariane contradictoires.
