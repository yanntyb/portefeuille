---
paths:
  - 'app/Contexts/MarketView/**'
---

# Market View

## MarketView ne lit ses voisins que par ses ports, et jumelle leurs Datas
Le volet marché — les deux contrôleurs et `BuildMarketViewSnapshot` — ne touche aucune action ni Data de Portfolio, Valuation ou Income. Tout passe par `Ports/` : `PortfolioOverviewPort`, `ValuationPort`, `SectorBreakdownPort`, `IncomePort`, plus les trois plus anciens. Un port par contexte voisin, pas par action : `ValuationPort` porte les quatre lectures de valorisation, aux deux échelles.

Les Datas de `MarketView\Datas` jumellent celles des voisins et doivent reproduire leur JSON à l'octet près, ordre des clés compris — `SnapshotController` publie `sha1(json_encode($body))` comme version du blob hors-ligne, qu'un ordre différent ferait retélécharger à tous les clients. `HoldingRowData` est le piège : treize clés pour onze propriétés, `typeLabel` et `assetClassLabel` se dérivant de leur enum au moment de sérialiser. Toute clé ajoutée à `Portfolio\HoldingLineData` doit l'être ici aussi.

`PortfolioTotals` injecte `GetPortfolioOverview` et ne la construit jamais : l'action est liée en `scoped` et mémoïse ses lignes par utilisateur, une seule lecture du portefeuille servant les quatre expositions d'une requête.

Ni la profondeur d'historique ni le pas de valorisation ne traversent les ports — ce sont des décisions de rendu, absorbées par `ValuationHistory`. Aucun type de Portfolio, Valuation ou Income n'apparaît hors de `Infrastructure/`, où traduire l'un dans l'autre est précisément le travail des adaptateurs.

`GetHoldingTrends` prenait naguère un `ValuationRange` pour resserrer sa fenêtre, alimenté par un `?range=` que rien n'envoyait : ni le sélecteur `ChartRangeToggle.vue`, orphelin, ni aucune page. Le paramètre a été retiré plutôt que jumelé — la sparkline lit tout l'historique, et le sous-échantillonnage lui donne de toute façon le même nombre de points. Rebrancher un sélecteur de plage suppose donc de réintroduire la fenêtre, et alors par un enum de MarketView, pas par celui de Valuation.
