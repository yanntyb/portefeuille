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
