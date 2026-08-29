---
paths:
  - 'app/Contexts/MarketView/**'
---

# Market View

## MarketView ne lit ses voisins que par ses ports, et jumelle leurs Datas
Le volet marché — les deux contrôleurs et `BuildMarketViewSnapshot` — ne touche aucune action ni Data de Portfolio, Valuation ou Income. Tout passe par `Ports/` : `PortfolioOverviewPort`, `ValuationPort`, `SectorBreakdownPort`, `IncomePort`, plus les trois plus anciens. Un port par contexte voisin, pas par action : `ValuationPort` porte les quatre lectures de valorisation, aux deux échelles.

Les Datas de `MarketView\Datas` jumellent celles des voisins et doivent reproduire leur JSON à l'octet près, ordre des clés compris — `SnapshotController` publie `sha1(json_encode($body))` comme version du blob hors-ligne, qu'un ordre différent ferait retélécharger à tous les clients. `HoldingRowData` est le piège : treize clés pour onze propriétés, `typeLabel` et `assetClassLabel` se dérivant de leur enum au moment de sérialiser. Toute clé ajoutée à `Portfolio\HoldingLineData` doit l'être ici aussi.

`PortfolioTotals` injecte `GetPortfolioOverview` et ne la construit jamais : l'action est liée en `scoped` et mémoïse ses lignes par utilisateur, une seule lecture du portefeuille servant les quatre expositions d'une requête.

Ni la profondeur d'historique ni le pas de valorisation ne traversent les ports — ce sont des décisions de rendu, absorbées par `ValuationHistory`. Seule exception restante à la frontière : `Valuation\Enums\ValuationRange`, réclamé par la signature de `GetHoldingTrends`.
