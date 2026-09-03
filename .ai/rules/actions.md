---
paths:
  - 'app/Contexts/Valuation/Actions/**'
---

# Actions

## Un périmètre de lecture est un `HoldingScope`, jamais des paramètres égrenés
`Market\Datas\HoldingScope` porte les deux filtres d'une lecture du portefeuille — classes, enveloppe — et **la seule** définition du suffixe de cache d'un périmètre (`cacheKey()`). Les actions le prennent en dernier paramètre, `null` valant `HoldingScope::all()` : `BuildExposureSeries`, `BuildPortfolioPerformances`, `GetPortfolioOverview`, `GetSectorBreakdown`, `GetRealizedGains::totalFor()`, `GetPortfolioAnalysis`. Ne pas y ajouter un `?int $walletId` ou un `?array $classes` de plus : c'est exactement l'état d'avant, où six signatures divergeaient et où seule la série savait filtrer par enveloppe.

`BuildEvolutionSeries` prend le périmètre en dernier paramètre comme les autres, mais l'applique à deux endroits distincts, à dessein : par **classe**, APRÈS son cache (la série porte `assetId`, une seule construction sert les pages Actions et Crypto sur la même abscisse) ; par **enveloppe**, AVANT, avec `cacheKey()` dans le nom retenu — une ligne par actif ne dit pas quelle enveloppe le tient, et trier après coup rendrait les quantités de toutes. Ne pas aligner l'un sur l'autre.

## Le filtre par enveloppe ne fait pas d'exception au cash
Le filtrage du journal vit dans `Valuation\Services\ScopedTransactions`, seul site : le filtre par classe laisse passer tout mouvement sans `asset_id`, sous peine d'un cash bâti sur les seuls achats de la classe, négatif en permanence ; le filtre par enveloppe, lui, écarte franchement le cash des autres comptes, puisque le cash est tenu par wallet. Les deux ne se comportent pas pareil, à dessein — ne pas aligner l'un sur l'autre.

La même asymétrie se rejoue sur les listes d'opérations (`Portfolio\Actions\GetTransactionJournal`) : une exposition ne montre aucune ligne sans actif, une enveloppe montre ses versements et retraits.

Le test qui épingle la distinction est dans `BuildExposureSeriesTest` (et `ScopedTransactionsTest`) et repose sur un **achat** dans l'enveloppe voisine, pas sur un versement : un test bâti sur un versement passe même filtre inerte.
