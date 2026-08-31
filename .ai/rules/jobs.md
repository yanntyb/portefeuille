---
paths:
  - 'app/Contexts/Market/Jobs/**'
---

# Jobs

## Le verrou de synchro est Cache::add, jamais ShouldBeUnique
`SyncMarketDataJob` est une coquille : il publie l'état (`MarketSyncStatePort`) et délègue à `Market\Actions\SyncMarketData`, qui enchaîne prix, secteurs puis dividendes EN SÉRIE — chaque source ouvre un process Python et le planificateur décale déjà ses horaires pour ne jamais en croiser deux.

L'unicité ne passe pas par `ShouldBeUnique` mais par `Cache::add('market.sync.busy', …)` pris dans `StartSyncController` AVANT le dispatch : un job avalé par le middleware d'unicité ne laisserait rien à afficher au cliqueur, alors qu'un `begin()` refusé se lit tout de suite dans l'état.

Le worker tourne avec `--tries=1` : `failed()` est le seul chemin de sortie d'un échec, et le TTL de 30 min de la clé `busy` est le seul filet si le process est tué (`current()` redescend alors l'état en `Failed`). Ne pas rendre ce TTL infini.
