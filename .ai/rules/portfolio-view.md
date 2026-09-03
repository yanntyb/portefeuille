---
paths:
  - 'app/Contexts/PortfolioView/**'
---

# Portfolio View

## PortfolioView compose sans port ; un composeur par page sert contrôleur et snapshot
Le contexte n'a ni `Ports/`, ni `Infrastructure/`, ni provider. Ses `Pages/` (`AssetClassPage`, `AssetPage`, `WalletPage`) injectent directement les actions de Portfolio, Valuation, Income et les contrats de Market, et rendent leurs Datas au front : `PortfolioOverviewData`, `HoldingLineData`, `AccountLineData`, `TransactionLineData`, `EvolutionSeriesData`, `PerformanceData`, `ValuationSeriesData`, `AllocationSliceData`, `AssetDividendHistoryData`. `Datas/` ne garde que les formes qu'aucun voisin n'a : la fiche (`InstrumentDetailData`), le catalogue, les tendances, les analyses, les tranches par classe, l'historique de cours.

Un composeur rend un `App\Shared\Inertia\PageProps` : les props sync, et les différées avec leur groupe. Le contrôleur fait `->render()`, `BuildPortfolioViewSnapshot` fait `->resolve()` — le blob hors-ligne et la page sont identiques par construction. Ajouter une prop à une page, c'est l'ajouter au composeur, une fois ; ne pas la recopier dans le snapshot. Le catalogue (`AssetClassCatalogController`) n'a que du sync et n'entre pas dans le snapshot : pas de composeur.

Les pages se rendent vides, sans erreur, à un visiteur non connecté (`userId` 0) : les actions de Portfolio qui prennent un `User` sont gardées par un `$user === null ? Xxx::empty() : ...` dans le composeur. `WalletPage` et `AssetPage` rendent `null` sur une ressource inconnue ou étrangère, et le contrôleur répond 404, jamais 403.

Ce que PortfolioView calcule lui-même vit dans `Actions/` (`GetBasketAnalysis`, `GetInstrumentAnalysis`, `GetHoldingTrends`, `GetClassCatalog`, `GetInstrumentDetail`) et `Services/` (purs : `ClassBreakdown`, `ChartStep`, `SparklineReducer`, les deux fenêtres). Une formule qui appartient à un voisin reste chez lui : `HoldingValuator`, `PositionAggregator`, `Drawdown`, `TransactionFlow` se demandent, ne se refont pas.

## PortfolioView sert toute page de positions valorisées, et ses lectures prennent un périmètre
Exposition (`/actions`…), fiche instrument (`/asset/{id}`) et enveloppe (`/enveloppes/{id}`) sont trois découpes du même portefeuille : elles vivent ici, avec les mêmes actions et les mêmes sections. Le périmètre passe par `Market\Datas\HoldingScope`, jamais par un `AssetClass` nu : `GetPortfolioOverview`, `GetSectorBreakdown`, `GetTransactionJournal`, `BuildEvolutionSeries`, `BuildPortfolioPerformances`, `GetBasketAnalysis`, `GetAccountBreakdown` le prennent en dernier paramètre. `GetBasketAnalysis` est la seule composition d'analyse : ne pas en écrire une seconde pour une nouvelle découpe.

Deux périmètres restent volontairement larges : le bloc sectoriel d'une **page d'exposition** montre les secteurs du portefeuille entier (`HoldingScope::all()`, comportement d'origine), et la position d'une fiche confond les enveloppes (`GetPortfolioPositions`).

Le journal d'une exposition ne montre aucune ligne sans actif, celui d'une enveloppe montre ses versements et retraits : c'est `GetTransactionJournal` qui porte l'asymétrie, pas la page.

Ni la profondeur d'historique ni le pas de valorisation ne se décident chez Valuation : `AssetPage` lit la série au jour et la ré-échantillonne selon `ChartStep`. Rebrancher un sélecteur de plage passerait par un enum de PortfolioView, pas par `ValuationRange`.
