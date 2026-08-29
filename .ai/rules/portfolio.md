---
paths:
  - 'app/Contexts/Portfolio/**'
---

# Portfolio

## HoldingValuator est le site unique du gain ; gainPct est null sur coût nul
`Portfolio\Services\HoldingValuator` est le seul endroit qui calcule la valorisation d'une position et son gain — à la ligne (`value()`) comme au total (`totals()`). `MarketView` et `Wealth` le demandent plutôt que de refaire la formule.

`gainPct` (donc `pct()`) rend `null`, jamais `0.0`, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas de pourcentage, et « 0 % » mentirait. Vrai à la ligne comme au total.

`GetPortfolioPositions` et `GetPortfolioOverview` sont toutes deux liées en `scoped` dans `AppServiceProvider` et mémoïsent leurs lignes par utilisateur : une seule lecture du portefeuille sert tous les appelants d'une même requête HTTP. Un appel par position (une fiche par position détenue, par exemple) rouvrirait sinon un N+1 sur l'instantané.
