---
paths:
  - 'app/Contexts/*/Infrastructure/**'
  - 'app/Contexts/*/Sources/*/Infrastructure/**'
---

# Infrastructure

## La position par actif est calculée une seule fois, par `Portfolio`
`PortfolioView\Infrastructure\PortfolioHoldings` et `Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory` ne recalculent plus la moyenne pondérée du `avg_cost` sur les enveloppes d'un actif : les deux ne font plus que remapper `App\Contexts\Portfolio\Actions\GetPortfolioPositions`, qui la calcule via `Portfolio\Services\PositionAggregator` et la valorise via `Portfolio\Services\HoldingValuator`. Aucun adaptateur de ces deux contextes ne doit requêter `Holding` directement — `grep -rn "Holding::query()" app/Contexts/PortfolioView app/Contexts/Income` doit rester vide (hors fixtures de test).

Une seule formule : toute correction du prix de revient moyen se porte dans `PositionAggregator`, pas dans les adaptateurs qui le consomment.
