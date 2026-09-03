---
paths:
  - 'app/Contexts/*/Infrastructure/**'
  - 'app/Contexts/*/Sources/*/Infrastructure/**'
---

# Infrastructure

## La position par actif est calculée une seule fois, par `Portfolio`
`Income\Sources\Dividend\Infrastructure\PortfolioPositionHistory` et les actions de `PortfolioView` (`GetInstrumentDetail`, `GetClassCatalog`, `GetHoldingTrends`, `GetInstrumentAnalysis`) ne recalculent pas la moyenne pondérée du `avg_cost` sur les enveloppes d'un actif : elles lisent `App\Contexts\Portfolio\Actions\GetPortfolioPositions`, qui la calcule via `Portfolio\Services\PositionAggregator` et la valorise via `Portfolio\Services\HoldingValuator`. Aucune classe de ces deux contextes ne doit requêter `Holding` directement — `grep -rn "Holding::query()" app/Contexts/PortfolioView app/Contexts/Income` doit rester vide (hors fixtures de test).

Une seule formule : toute correction du prix de revient moyen se porte dans `PositionAggregator`, pas dans les adaptateurs qui le consomment.
