---
paths:
  - 'resources/js/components/**'
  - 'resources/js/Pages/**'
---

# Components

## Une prop différée s'attend par DeferredBlock, et `null` veut dire pas encore
Toute section qui attend une prop différée rend `<DeferredBlock :data="clé">` dans sa branche `v-else` : squelette, message « Données indisponibles hors-ligne. » et sentinelle y sont écrits une fois. Un squelette particulier (graphe) passe par le slot `fallback`. Ne pas réécrire un `<Deferred>` à la main — dix-neuf copies ont existé. Une seule exception assumée : le `<Deferred>` du slot `#value` de `WealthIncomeSection`, qui affiche un chiffre différé dans un en-tête toujours rendu — un squelette en ligne et un tiret, pas un bloc.

Le dialecte du chargement est unique : `null` (ou `undefined`, ce que vaut une prop Inertia non servie) = pas encore arrivé, squelette ; tableau ou objet vide = rien à montrer, libellé vide. Pas de prop `loaded` booléenne — `ValueVsInvestedChart` est l'exception assumée, un objet présent pouvant porter une série vide.

Les sections partagées sont `TransactionsSection` (journal, variantes `named`/`bare`), `PortfolioSummarySection` (grand chiffre, préfixe `portfolio`/`wealth` des `data-*`) et `SectorsSection` (ventilation, variantes `section`/`block`) ; leurs lignes se convertissent dans `lib/sector.ts` (`rowsFromWeights`, `rowsFromSlices`, `rowsFromShares`), jamais dans un composant. Une nouvelle page assemble ces trois-là avec ses props ; elle n'en copie pas une.
