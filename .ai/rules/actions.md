---
paths:
  - 'app/Contexts/Valuation/Actions/**'
---

# Actions

## Le filtre par enveloppe de BuildExposureSeries ne fait pas d'exception au cash
Le filtre par classe laisse passer tout mouvement sans `asset_id`, sous peine d'un cash bâti sur les seuls achats de la classe, négatif en permanence ; le filtre par enveloppe, lui, écarte franchement le cash des autres comptes, puisque le cash est tenu par wallet. Les deux ne se comportent pas pareil, à dessein — ne pas aligner l'un sur l'autre. Chaque filtre entre dans le nom de cache. Le test qui épingle la distinction est dans `BuildExposureSeriesTest` et repose sur un **achat** dans l'enveloppe voisine, pas sur un versement : un test bâti sur un versement passe même filtre inerte.
