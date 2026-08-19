---
paths:
  - 'app/Contexts/*/Infrastructure/**'
---

# Infrastructure

## La moyenne pondérée du prix de revient est dupliquée par contexte, à dessein
`InstrumentView\Infrastructure\PortfolioHoldings::aggregate()` et `Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory::positionFor()` calculent la même moyenne pondérée du `avg_cost` sur les enveloppes d'un actif, garde `$qtyWithCost > 0` incluse. La duplication est voulue : chaque contexte possède ses adaptateurs et ne dépend pas de ceux d'un voisin.

Elle est porteuse, pas seulement tolérée : c'est ce qui fait que le rendement sur coût de la fiche instrument se rapporte au même prix de revient que celui qu'elle affiche. Toute correction de cette formule doit être portée dans les deux fichiers, sous peine de faire diverger deux chiffres montrés côte à côte.
