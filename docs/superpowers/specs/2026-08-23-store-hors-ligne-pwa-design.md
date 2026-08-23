# Store hors-ligne PWA — conception

Date : 2026-08-23

## Problème

Le service worker met en cache les charges utiles Inertia, indexées par URL et par jeu de props
partielles (`cacheKeyFor`), avec synthèse de secours quand un groupe différé manque
(`rescuedPartialPayload`). Cela couvre bien un cas : revenir hors-ligne sur une page déjà ouverte.

Deux manques subsistent :

1. **Les pages jamais visitées n'existent pas hors-ligne.** Le contenu disponible dépend
   entièrement du parcours passé du lecteur. Une fiche instrument jamais ouverte tombe sur
   `hors-ligne` ou sur un `#rescue` vide, alors que la donnée était connue du serveur depuis le
   premier chargement du tableau de bord.
2. **Les données ne sont adressables que par URL.** Impossible de recomposer une vue à partir des
   entités déjà connues, ni de répondre hors-ligne à une question qui traverse plusieurs pages.

## Objectifs

- Rendre tout le patrimoine lisible hors-ligne, indépendamment des pages déjà visitées.
- Adresser les données par entité (`instrument#id`, `property#id`) et non par URL.
- Rendre immédiatement la dernière valeur connue, même en ligne, puis la remplacer par la réponse
  Inertia dès son arrivée (« store en avance de phase »).

## Non-objectifs

- **Écritures hors-ligne.** L'application est en lecture seule : huit routes, toutes en `GET`.
  Aucune file d'attente de mutations, aucun `Background Sync`.
- **Recalcul du domaine côté client.** La composition métier (`WealthOverviewData`,
  `EvolutionSeriesData`, performances, secteurs) vit en PHP. La porter en JavaScript
  dupliquerait le domaine ; le client ne fait que stocker et choisir.
- **Gestion fine du quota.** Le volume est petit (36 actifs, 8 positions, 3 biens) et le blob se
  réécrit en entier. Le sujet ne se pose qu'au-delà de ~1 Mo — voir « Évolutions écartées ».

## Décisions

| Décision | Retenu | Écarté |
| --- | --- | --- |
| Source des données hors-ligne | Un instantané serveur unique, composé par les actions existantes | Accumulation opportuniste depuis les réponses Inertia reçues (ne couvre pas les pages jamais visitées, et devine la forme des props) |
| Persistance | Un blob JSON unique dans IndexedDB, via `idb-keyval` | Object stores indexés (`idb`, Dexie) : schéma, migrations de schéma client, requêtes async dans les composants — sans volume qui le justifie |
| État réactif | Pinia, adopté comme pattern d'état de l'application | `ref` de module (statu quo) : reconduit les `resetXxxState()` de test |
| Rôle du store dans le rendu | En avance de phase : le store rend, la prop Inertia remplace | Filet hors-ligne seul ; source de lecture unique (réécriture de toutes les pages) |

### Pourquoi Pinia, et partout

Pinia est le store d'état officiel de Vue. L'argument décisif ici est l'isolation de test :
`setActivePinia(createPinia())` en `beforeEach` remplace les remises à zéro manuelles.
`serviceWorker.ts` porte déjà ce hack (`resetServiceWorkerState`) avec son commentaire sur l'état
de module qui survit d'un test à l'autre ; le store d'instantané aurait le même besoin.

L'adoption est globale — `snapshot`, `serviceWorker`, `theme`, `viewport` — parce que Pinia sur le
seul store d'instantané ferait cohabiter deux patterns d'état pour toujours.

L'application monte **deux racines Vue** : `banner.ts` et l'application Inertia. Ce n'est pas un
obstacle mais une contrainte à traiter explicitement : une instance de Pinia unique, partagée par
les deux montages.

## Contrat serveur

### Route

`GET /instantane` → `pwa.snapshot`, déclarée dans `routes/pwa.php` aux côtés de `hors-ligne`
(même famille, même choix d'URL en français). Réponse JSON, hors Inertia.

### Composition

Une action par contexte, chacune ne parlant que de son domaine :

- `App\Contexts\Wealth\Actions\BuildWealthSnapshot` → `overview`, `series`, `income`
- `App\Contexts\MarketView\Actions\BuildMarketViewSnapshot` → `instruments: { list, byId }`,
  `crypto: { list, byId }`
- `App\Contexts\RealEstate\Actions\BuildRealEstateSnapshot` → `properties: { list, byId }`

`App\Shared\Pwa\Http\SnapshotController` les agrège et rien de plus : il ne connaît aucun modèle,
seulement ces trois points d'entrée publics. C'est le sens de circulation déjà en place pour
`Wealth\Infrastructure`.

Chaque action renvoie les mêmes objets `Data` que les contrôleurs correspondants. Aucune forme
parallèle n'est introduite : si un `Data` change, les pages et l'instantané changent ensemble.

### Charge utile

```json
{
  "version": "<empreinte du corps>",
  "generatedAt": 1755950000,
  "dashboard": { "overview": {}, "series": {}, "income": {} },
  "instruments": { "list": {}, "byId": {} },
  "crypto": { "list": {}, "byId": {} },
  "properties": { "list": {}, "byId": {} }
}
```

`version` est une empreinte du contenu, calculée après sérialisation. Le client la compare avant
de réécrire le blob : instantané identique, aucune écriture IndexedDB.

`generatedAt` est un horodatage Unix en secondes, produit par le serveur.

### Plages de valorisation

Les pages `Instruments/Index` et `Crypto/Index` acceptent un paramètre `range`
(`ValuationRange::fromRequest`). L'instantané ne porte que la **plage par défaut**. Les autres
plages restent en ligne seulement : hors-ligne, elles retombent sur le comportement actuel du
service worker. Porter toutes les plages multiplierait le volume pour un usage marginal.

### Utilisateur

Même résolution que les contrôleurs existants : `auth()->user() ?? User::query()->first()`, avec
repli sur les `Data::empty()` quand il n'y a personne.

### Deux pièges à traiter dès la conception

1. **Le service worker doit ignorer `/instantane`.** Sans changement, `classifyRequest` le range
   dans `other`, donc `staleWhileRevalidate`, qui diffuse `FRESH` / `SERVED_STALE`. Une
   resynchronisation de fond qui échoue hors-ligne ferait alors basculer le bandeau du lecteur
   sur un état qui ne décrit pas la page qu'il regarde. `/instantane` doit être classé
   `passthrough` ; le store gère son propre échec.
2. **Coût de calcul.** L'instantané résout d'un coup séries, performances, secteurs et revenus,
   pour toutes les entités. Les actions concernées ont déjà leurs caches ; vérifier qu'aucun
   chemin n'est recalculé plusieurs fois dans un même appel, en particulier `BuildEvolutionSeries`
   et `BuildPortfolioPerformances`, dont les noms de cache diffèrent selon la classe d'actif.

## Store client

### Fichiers

```
resources/js/stores/pinia.ts           instance partagée, setActivePinia
resources/js/stores/snapshot.ts        defineStore('snapshot')
resources/js/stores/serviceWorker.ts   migré depuis lib/serviceWorker.ts
resources/js/stores/theme.ts           migré depuis lib/theme.ts
resources/js/stores/viewport.ts        migré depuis lib/viewport.ts
resources/js/lib/snapshotStorage.ts    lecture/écriture du blob (idb-keyval), isolé donc mockable
resources/js/lib/snapshotContract.ts   types, composés depuis lib/wealth|instrument|realEstate
```

Les stores sont des *setup stores* : le corps actuel de chaque module devient le corps du store,
la conversion est mécanique.

`stores/pinia.ts` crée l'instance et appelle `setActivePinia()` immédiatement, pour que
`useThemeStore()` reste appelable hors composant — `app.ts` l'utilise avant `createInertiaApp`.
`app.ts` et `banner.ts` passent tous deux cette même instance à `.use()`.

### Interface du store d'instantané

```ts
state:   snapshot: Snapshot | null
         syncing: boolean
getters: dashboard, instrumentById(id), propertyById(id), cryptoById(id), generatedAt
actions: hydrate()   // IndexedDB → mémoire
         sync()      // réseau → mémoire → IndexedDB
```

L'indexation par entité se fait en mémoire depuis `byId`. Aucune requête IndexedDB par page : le
blob entier est déjà chargé.

### Cycle de vie

Dans `app.ts`, à côté de l'initialisation du thème :

1. `hydrate()` — asynchrone, ne bloque pas le premier rendu.
2. `sync()` en tâche de fond, puis à `visibilitychange` lorsque le document redevient visible.
   **Pas** sur `inertia:navigate` : trop coûteux pour ce que ça rapporte.

L'écriture IndexedDB n'a lieu que si `version` diffère de celle déjà en mémoire.

Un échec réseau de `sync()` est silencieux : `syncing` retombe, aucun bandeau, aucune trace
visible. Le lecteur garde ce qu'il avait.

### Types

`lib/snapshotContract.ts` compose les interfaces déjà écrites (`WealthOverview`, `WealthSeries`,
`WealthIncome`, `InstrumentDetail`, les types immobiliers). Aucun type parallèle.

## Intégration des pages

Sur les sections couvertes par l'instantané, `Deferred` cède la place à une fusion explicite :

```ts
const series = computed<WealthSeries | null>(
    () => props.series ?? snapshot.dashboard?.series ?? null,
);
```

La prop Inertia gagne dès qu'elle arrive ; le store comble l'intervalle ; les deux nuls donnent le
squelette, puis le message d'indisponibilité. Le slot `#rescue` reste en place sur les seules
sections que l'instantané ne couvre pas — les plages de valorisation non par défaut, notamment.

### Fraîcheur affichée

Pas de marqueur par section : en ligne, l'échange se fait en quelques millisecondes et un badge ne
ferait que clignoter. Le bandeau existant reste le seul porteur du message.

Sa date doit en revanche devenir la plus pertinente des deux : `generatedAt` de l'instantané quand
ce qui est à l'écran vient du store, `cachedAt` du service worker sinon. Sans cela, le lecteur lit
« synchronisé il y a 2 minutes » devant des chiffres vieux d'une journée de marché.

## Tests

**Pest**

- `SnapshotControllerTest` : forme de la charge utile, `version` stable à contenu égal, `version`
  différente après changement de données, utilisateur sans données.
- Un test par action de contexte (`BuildWealthSnapshot`, `BuildMarketViewSnapshot`,
  `BuildRealEstateSnapshot`), sur des fixtures existantes.

**Vitest**

- `classifyRequest` renvoie `passthrough` sur `/instantane`.
- Store : hydratation depuis IndexedDB, resynchronisation réseau, saut d'écriture à `version`
  identique, échec réseau silencieux, sélecteurs par entité.
- Composable de fusion : prop présente gagne, prop absente retombe sur le store, les deux nuls
  donnent le squelette.
- Migration Pinia : les tests existants de `serviceWorker`, `theme` et `viewport` passent après
  conversion, avec `setActivePinia(createPinia())` à la place des remises à zéro manuelles.

**Playwright (`tests/pwa-offline.mjs`)**

Le scénario qui prouve le manque nº1 : charger le tableau de bord en ligne, attendre la
synchronisation, couper le réseau, naviguer vers une fiche instrument **jamais ouverte**, vérifier
que la fiche rend ses données.

## Découpage en commits

1. Pinia + migration de `serviceWorker`, `theme`, `viewport` — aucun changement fonctionnel,
   tests verts.
2. Actions d'instantané par contexte + `GET /instantane`.
3. `classifyRequest` → `passthrough` sur `/instantane`.
4. Store d'instantané + persistance `idb-keyval`.
5. Câblage des pages en avance de phase.
6. Date du bandeau + test Playwright hors-ligne.

## Risques

- **Coût de l'instantané.** Il touche tous les chemins de calcul du domaine d'un coup. Si la durée
  de réponse devient sensible, la sortie est un cache serveur sur la charge utile complète,
  invalidé par les mêmes clés que les actions sous-jacentes.
- **Volume du blob.** Réécriture tout-ou-rien. Bon marché sous ~1 Mo ; au-delà, passer aux object
  stores indexés. `lib/snapshotStorage.ts` est isolé précisément pour que ce remplacement
  n'atteigne aucune page.
- **Divergence store / props.** Les deux sources sortent des mêmes objets `Data`, donc elles
  dérivent ensemble. Le risque réel est un contrôleur qui compose un `Data` autrement que l'action
  d'instantané — d'où le choix de réutiliser les actions plutôt que d'écrire une projection
  parallèle.

## Évolutions écartées pour l'instant

- Object stores indexés (`idb`, Dexie) : à reprendre si le blob dépasse ~1 Mo ou si des écritures
  partielles apparaissent.
- Instantané par plage de valorisation.
- Écritures hors-ligne et rejeu : sans objet tant que l'application est en lecture seule.
