---
paths:
  - 'app/Contexts/Portfolio/**'
---

# Portfolio

## HoldingValuator est le site unique du gain ; gainPct est null sur coût nul
`Portfolio\Services\HoldingValuator` est le seul endroit qui calcule la valorisation d'une position et son gain — à la ligne (`value()`) comme au total (`totals()`). `MarketView` et `Wealth` le demandent plutôt que de refaire la formule.

`gainPct` (donc `pct()`) rend `null`, jamais `0.0`, quand le coût est nul : un gain sans mise à laquelle le rapporter n'a pas de pourcentage, et « 0 % » mentirait. Vrai à la ligne comme au total.

`GetPortfolioPositions` et `GetPortfolioOverview` sont toutes deux liées en `scoped` dans `AppServiceProvider` et mémoïsent leurs lignes par utilisateur : une seule lecture du portefeuille sert tous les appelants d'une même requête HTTP. Un appel par position (une fiche par position détenue, par exemple) rouvrirait sinon un N+1 sur l'instantané.

## Les analyses lisent les positions, les listes lisent les lignes
`HoldingLineData` = une ligne par enveloppe ; `PositionLineData` = une position par actif, enveloppes confondues. La page liste affiche des lignes, la page analyse raisonne en positions. L'argument est la concentration : un titre à 30 % réparti sur deux comptes apparaîtrait comme deux lignes à 15 %, et le HHI le ferait passer pour une exposition modérée — l'enveloppe est un fait fiscal, pas un fait de marché. Conséquence assumée : un même titre apparaît une fois sur `/actions/analyse` et deux sur `/actions`, et tout rendu qui regroupe doit le dire. `GetPortfolioAnalysis::positionsOf()` refait ce regroupement au lieu de réutiliser `GetPortfolioPositions`, faute d'avoir étendu cette dernière avec un filtre par classe et le nom de l'actif — consolidation à trancher dans un chantier ultérieur, pas un correctif à faire au fil de l'eau.
