---
paths:
  - 'app/Contexts/*/Services/**'
---

# Services

## Services/ est pur, sans Eloquent ni port ni conteneur
Un `Services/` ne connaît ni Eloquent, ni port, ni conteneur : entrées nues ou Datas de son propre contexte en paramètres, sortie nue ou Data en retour. Onze fichiers appliquent déjà cette doctrine (ex. `Portfolio\Services\HoldingValuator`, `Wealth\Services\SeriesAligner`). Son test est co-localisé (`XxxTest.php` à côté de la classe) et construit l'objet avec `new`, sans base de données ni `RefreshDatabase`.

À distinguer de `Support/`, qui traduit Eloquent vers des Datas et rien d'autre, et de `Actions/`, qui lit en base, appelle les `Services/` et emballe le résultat.
