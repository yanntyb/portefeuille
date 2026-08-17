# Fusion du catalogue d'instruments dans le tableau de bord

Date : 2026-08-17
Branche : `vendredi-soir`

## Intention

Le catalogue d'instruments vit aujourd'hui sur sa propre page, `/instruments`, atteinte
depuis le tableau de bord par un bouton « Voir plus ». Deux listes coexistent donc :
les positions détenues, en lignes compactes sur le tableau de bord, et le catalogue
complet, en tableau triable de cinq colonnes sur sa page.

La page `/instruments` disparaît. Sa recherche et son catalogue remontent dans la liste
du tableau de bord, qui devient la liste unique : sans recherche elle montre les
positions, dès la première frappe elle montre tout le catalogue.

## Décisions

**Une liste, deux états.** Recherche vide : toutes les positions détenues, triées par
valeur décroissante, sans la limite de dix d'aujourd'hui. Recherche remplie : tous les
instruments correspondants, détenus d'abord par valeur décroissante, non détenus ensuite
par nom.

**Le tableau triable disparaît.** Les résultats prennent le format compact des positions,
le seul qui tienne dans le carrousel plein écran du mobile. Le tri par colonnes n'a plus
de support et part avec le tableau.

**Le sélecteur de période reste.** Les tendances du catalogue continuent d'alimenter les
sparklines, et le sélecteur `1M / 6M / 1A / Max` monte dans le tableau de bord.

**La fusion est côté client.** Le tableau de bord reçoit le catalogue et les tendances en
propriétés différées, et les joint aux positions par `assetId` dans un module `lib/`
testé au vitest. Le serveur ne gagne aucune action nouvelle.

## Données

`DashboardController` gagne trois propriétés :

| Propriété | Transport | Source |
| --- | --- | --- |
| `catalog` | `Inertia::defer` | `GetInstrumentCatalog` |
| `catalogRange` | direct | `ValuationRange::fromRequest(request()->query('range'))` |
| `trends` | `Inertia::defer` | `GetCatalogTrends($range)` |

Les deux actions sont réutilisées sans modification. `GetCatalogTrends` couvre déjà tous
les instruments du marché, avec `changePct` et vingt-quatre points de sparkline au plus.

Le catalogue est différé et non direct : une recherche vide n'a besoin que
d'`overview.holdings`, déjà présent au premier rendu. Le temps de premier affichage du
tableau de bord ne bouge donc pas, alors que `/instruments` payait 12,6 Ko en direct.
Catalogue et tendances partagent le groupe différé par défaut, donc une seule requête
supplémentaire.

Le sélecteur de période appelle `router.reload({ only: ['trends'], data: { range } })`,
le mécanisme déjà en place dans `Instruments/Index.vue`. `catalogRange` n'est pas renvoyé
par ce rechargement ; la période sélectionnée reste tenue par une référence locale, comme
aujourd'hui.

## Front

**`Components/dashboard/InstrumentsSection.vue`** — remplace `HoldingsSection.vue`.
Assemble la recherche, le sélecteur de période, le libellé de comptage (`catalogCount`)
et la liste. Le bouton « Voir plus » disparaît.

**`Components/InstrumentList.vue`** — remplace `HoldingsList.vue`. Une seule forme de
ligne, deux remplissages :

- détenu : valeur, barre de poids, part en pourcentage, sparkline, gain en euros et en
  pourcentage ;
- non détenu : dernier prix à la place de la valeur, mention « non détenu » à la place de
  la part, sparkline, variation de la période à la place du gain.

**`Components/InstrumentSearch.vue`** — l'actuel `instruments/CatalogSearch.vue`, déplacé
sans changement. Le dossier `Components/instruments/` disparaît.

**`lib/instrumentList.ts`** — nouveau. Joint positions, lignes de catalogue et tendances
par `assetId`, puis ordonne les deux états décrits plus haut. Les poids et largeurs de
barre passent par `holdingWeights` sans limite, donc restent calculés sur les seules
positions : un résultat de recherche non détenu n'entre pas dans le total du
portefeuille. `filterCatalog`, `joinTrends` et `catalogCount` de `lib/catalog.ts` sont
réutilisés tels quels.

**Source unique pour les sparklines.** Les tendances du catalogue alimentent désormais
toutes les lignes, détenues comprises, et obéissent donc au sélecteur de période. La
propriété `series` de la liste disparaît ; `evolutionSeries` ne sert plus qu'au graphe
d'évolution.

**Chargement.** Aucun état bloquant : avant l'arrivée du catalogue, la recherche filtre
les positions déjà en main, et les sparklines gardent le squelette pulsant actuel.

## Suppressions et retombées

- route `/instruments` et son nom `instruments.index` (`routes/web.php:12`)
- `app/Contexts/InstrumentView/Http/InstrumentCatalogController.php`
- `resources/js/Pages/Instruments/Index.vue`
- `resources/js/Components/instruments/CatalogHeader.vue`
- `resources/js/Components/instruments/CatalogList.vue`
- `Pages/Instruments/Show.vue:66` : le cran « Instruments » du fil d'Ariane n'a plus de
  cible et tombe ; il reste `Tableau de bord › <instrument>`
- `docs/page-data.md` : les lignes 40 et 92 décrivent une page qui n'existe plus, à
  reporter sur le tableau de bord

Le `prefetch` des liens de ligne reste inchangé : il se déclenche au survol, donc une
recherche large ne provoque pas de rafale de requêtes.

## Tests

`tests/Feature/InstrumentCatalogPageTest.php` est supprimé, sa page ayant disparu. Ses
assertions sont reportées sur `DashboardPageTest` : présence de `catalog`, `catalogRange`
et `trends`, différement effectif des deux premières, lecture de `range` depuis la chaîne
de requête.

Adaptations :

- `tests/Browser/CatalogSearchTest.php` part de `/` au lieu de `/instruments`
- `tests/Browser/SmokeTest.php:52` retire `/instruments` de sa tournée
- `tests/Browser/PrefetchTest.php` part du tableau de bord
- `tests/Browser/DashboardTest.php:88` perd l'assertion sur le lien `data-holdings-all`,
  remplacée par une assertion de recherche
- `tests/Browser/BreadcrumbTest.php` attend un cran de moins sur la fiche instrument

Ajout : `resources/js/lib/instrumentList.test.ts` couvre la jointure par `assetId`, l'ordre
des deux états, et le fait que les poids ne comptent que les positions détenues.

Vérification : `php artisan test --compact`, `bun run test:js`, `bun run typecheck`,
`vendor/bin/pint --dirty --format agent`.

## Hors périmètre

- La fiche instrument `/instruments/{id}` ne change pas, hors son fil d'Ariane.
- Aucune recherche serveur : le catalogue tient en vingt-cinq lignes et 12,6 Ko, le
  filtrage reste en mémoire.
- Aucun ajout d'instrument depuis la recherche : la liste reste en lecture seule.
