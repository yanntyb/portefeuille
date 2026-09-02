---
paths:
  - 'app/Contexts/PortfolioView/**'
---

# Portfolio View

## PortfolioView sert toute page de positions valorisées, et ses ports prennent un périmètre
Exposition (`/actions`…), fiche instrument (`/asset/{id}`) et enveloppe (`/enveloppes/{id}`) sont trois découpes du même portefeuille : elles vivent donc dans ce contexte, avec les mêmes ports, les mêmes Datas et les mêmes sections. La page d'une enveloppe a vécu un temps dans `Wealth`, qui y avait dupliqué cinq actions, deux Datas, un port et un adaptateur ; `Wealth` ne garde que le patrimoine (tableau de bord, registre de classes, `InvestedCapital`, revenus).

Le périmètre passe par `Market\Datas\HoldingScope`, jamais par un `AssetClass` : `overviewFor`, `classBreakdownFor`, `analysisFor`, `performancesFor`, `seriesFor`, `breakdownFor`, `transactionsForScope`. Seule `ValuationPort::evolutionFor()` prend encore une exposition, parce que `BuildEvolutionSeries` ne sait pas découper une enveloppe (voir `.ai/rules/actions.md`). `BasketAnalysis` est la seule composition d'analyse : ne pas en écrire une seconde pour une nouvelle découpe — lui passer un périmètre suffit.

Deux périmètres restent volontairement larges, et ce ne sont pas des oublis : le bloc sectoriel d'une **page d'exposition** montre les secteurs du portefeuille entier (comportement d'origine), et `positionFor()` confond les enveloppes.

## PortfolioView ne lit ses voisins que par ses ports, et jumelle leurs Datas
Le volet portefeuille — ses trois contrôleurs et `BuildPortfolioViewSnapshot` — ne touche aucune action ni Data de Portfolio, Valuation ou Income. Tout passe par `Ports/` : `PortfolioOverviewPort`, `ValuationPort`, `SectorBreakdownPort`, `IncomePort`, plus les trois plus anciens. Un port par contexte voisin, pas par action : `ValuationPort` porte les quatre lectures de valorisation, aux deux échelles.

Les Datas de `PortfolioView\Datas` jumellent celles des voisins et doivent reproduire leur JSON à l'octet près, ordre des clés compris — `SnapshotController` publie `sha1(json_encode($body))` comme version du blob hors-ligne, qu'un ordre différent ferait retélécharger à tous les clients. `HoldingRowData` est le piège : treize clés pour onze propriétés, `typeLabel` et `assetClassLabel` se dérivant de leur enum au moment de sérialiser. Toute clé ajoutée à `Portfolio\HoldingLineData` doit l'être ici aussi.

`PortfolioTotals` injecte `GetPortfolioOverview` et ne la construit jamais : l'action est liée en `scoped` et mémoïse ses lignes par utilisateur, une seule lecture du portefeuille servant les quatre expositions d'une requête. L'adaptateur injecte de la même façon `GetPortfolioPositions`, liée en `scoped` et mémoïsée par utilisateur selon le même principe.

Ni la profondeur d'historique ni le pas de valorisation ne traversent les ports — ce sont des décisions de rendu, absorbées par `ValuationHistory`. Aucun type de Portfolio, Valuation ou Income n'apparaît hors de `Infrastructure/`, où traduire l'un dans l'autre est précisément le travail des adaptateurs.

`AccountRowData` est un cas de jumelage à trois : `Portfolio\AccountLineData`, `Wealth\WealthAccountData` (cartes du tableau de bord) et lui rendent le même JSON, le front ne lisant qu'un type `WealthAccount`. Les trois bougent ensemble.

`GetHoldingTrends` prenait naguère un `ValuationRange` pour resserrer sa fenêtre, alimenté par un `?range=` que rien n'envoyait : ni le sélecteur `ChartRangeToggle.vue`, orphelin, ni aucune page. Le paramètre a été retiré plutôt que jumelé — la sparkline lit tout l'historique, et le sous-échantillonnage lui donne de toute façon le même nombre de points. Rebrancher un sélecteur de plage suppose donc de réintroduire la fenêtre, et alors par un enum de PortfolioView, pas par celui de Valuation.

## PortfolioView ne calcule rien — mais un adaptateur peut composer une action et un Services/ du voisin
« PortfolioView ne calcule rien » ne veut pas dire qu'`Infrastructure/` ne fait qu'appeler des actions telles quelles. Un adaptateur peut enchaîner une action **et** un calculateur `Services/` du contexte propriétaire, tant qu'il n'écrit jamais la formule lui-même : c'est ce que fait `ValuationHistory::drawdownFor()`, qui appelle `BuildExposureSeries` puis passe le résultat à `Valuation\Services\Drawdown`. La composition (quoi appeler, dans quel ordre, avec quelles données) vit dans `Infrastructure/` ; le calcul reste chez le contexte propriétaire.
