---
paths:
  - 'resources/js/pwa/**'
---

# Pwa

## Ne jamais laisser deferredProps dans une réponse partielle synthétisée
Une réponse Inertia partielle synthétisée (rescapée depuis le cache) ne doit jamais porter `deferredProps` : ce champ n'appartient qu'à la toute première page. S'il est présent sur une réponse partielle, le cœur d'Inertia (`page.set()`) le stocke en `pendingDeferredProps` et relance `loadDeferredProps` juste après le rendu — qui redéclenche la même synthèse, indéfiniment. `rescuedPartialPayload` (resources/js/lib/swCache.ts) le retire explicitement ; ne pas le réintroduire en spreadant `{ ...page }` sans y penser.

## L'await sur notify(...) dans networkFirst est critique pour l'ordre
Dans `networkFirst` (resources/js/pwa/strategies.ts), `await notify(cache, broadcast, { type: 'FRESH' })` doit rester attendu avant que la réponse ne soit renvoyée au navigateur. Le marqueur d'état doit être persisté en cache AVANT le retour de la réponse, sinon le client qui vient de démarrer sur ce document peut envoyer son `REQUEST_STATUS` avant l'écriture et lire l'état précédent au lieu du sien. Ne pas retirer cet `await` pour « alléger » le chemin chaud — ce nettoyage en apparence évident réintroduit ce bug.
