---
paths:
  - 'app/Contexts/Market/**'
---

# Market

## Deux axes : enveloppe et exposition
`InstrumentType` dit COMMENT l'actif est détenu — titre vif, ETF, contrat à terme. Il décide du
badge `typeLabel` sur chaque ligne et de `YahooFinanceAdapter::supportsSectors()`. `AssetClass` dit
À QUOI le porteur est exposé — actions, obligations, matières premières, crypto. Il décide de la
classe de patrimoine, des pages liste et de tout filtre par classe.

Ne jamais partager le portefeuille par `InstrumentType` : ce filtre passe par `AssetClass`, et par
elle seule. `AssetClass::defaultForType()` est un défaut posé au `creating` puis stocké, pas une
dérivation : la valeur en base fait foi, et un ETF obligataire se corrige à la main.

L'ordre des cas de `AssetClass` est un contrat : il fixe l'ordre des lignes du résumé patrimonial
et l'empilement des bandes de son graphe.

## latestForAsset / latestClosesForAssets n'ont pas de clé de tri secondaire
Ni `EloquentPriceRepository::latestForAsset()` ni `latestClosesForAssets()` ne trient sur une clé secondaire (seulement `orderByDesc('date')` / équivalent par jointure). Elles restent d'accord entre elles grâce à `unique(['asset_id', 'date'])` sur le chemin d'écriture `upsertForAsset()`, qui garantit au plus une ligne par actif et par jour.

Une insertion de `Price` hors de ce chemin (seed manuel, script ponctuel) pourrait créer deux lignes pour le même jour et ferait diverger le dernier cours affiché en tête de fiche instrument de celui qui valorise la position en dessous. Correctif si le besoin se présente : ajouter `orderByDesc('id')` en second critère aux deux méthodes — pas fait aujourd'hui, ce n'est pas un oubli.
