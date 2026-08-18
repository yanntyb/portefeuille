# PWA cœur — conception

Date : 2026-08-18
Statut : validé, prêt pour le plan d'implémentation

## Périmètre

Rendre l'application installable sur Android/Chrome, démarrer instantanément au second
lancement, et rester consultable hors-ligne sur les pages déjà visitées.

**Hors périmètre.** Les notifications push font l'objet d'un cycle séparé : elles supposent
des clés VAPID, une table d'abonnements, un endpoint de souscription, un job d'envoi et un
déclencheur métier. Elles dépendent du service worker défini ici, donc viennent après.
iOS/Safari est également hors périmètre : la cible retenue est Android/Chrome, ce qui permet
de s'appuyer sur `beforeinstallprompt` et d'omettre les balises `apple-mobile-web-app-*`.

## État de départ

Un échafaudage partiel existe et n'est pas fonctionnel :

- `config/pwa.php` — nom, couleurs, icônes, `display: standalone`. Complet.
- `routes/pwa.php` — sert `manifest.json` (JSON construit depuis la config) et `sw.js`
  (vue Blade, `Content-Type: application/javascript`, `Cache-Control: no-cache`). Complet.
- `public/icons/icon-192x192.png` et `icon-512x512.png`. Présents.
- `resources/views/pwa/meta-tags.blade.php` — **vide (0 octet)**.
- `resources/views/pwa/sw.blade.php` — **vide (0 octet)**.
- `resources/views/app.blade.php` — n'inclut ni le manifest ni l'enregistrement du worker.

Rien n'est donc actif aujourd'hui : pas de `<link rel="manifest">` dans le document, et
`/sw.js` renvoie un corps vide.

## Contexte applicatif

Deux routes seulement : `/` (`DashboardController`) et `/instruments/{id}`
(`InstrumentDetailController`). Pas d'authentification. Interface orientée mobile
(carrousel plein écran, hauteurs en `dvh`).

Les deux contrôleurs s'appuient massivement sur `Inertia::defer()` :

| Page | Props immédiates | Props différées (groupe) |
| --- | --- | --- |
| `Dashboard` | `overview`, `catalogRange` | `catalog`, `trends` (`catalogue`) ; `performances` (`performances`) ; `evolutionSeries` (`evolution`) ; `sectorBreakdown` (`secteurs`) |
| `Instruments/Show` | `instrument`, `performances` | `priceHistory`, `valuation` (groupe par défaut) |

Chaque page produit donc une requête initiale **plus** une requête partielle par groupe,
portant l'en-tête `X-Inertia-Partial-Data`. Toute la conception du cache découle de ce fait.

## Décisions

| Sujet | Décision |
| --- | --- |
| Objectifs | Installable + démarrage instantané + hors-ligne. Push reporté. |
| Cible | Android / Chrome. |
| Hors-ligne | Stale-while-revalidate sur les pages déjà visitées, bandeau de fraîcheur. Pas de miroir IndexedDB. |
| Mise à jour | Bandeau « nouvelle version » + bouton recharger. Pas de `skipWaiting` automatique. |
| Implémentation | Service worker maison, servi depuis la racine par la route Blade existante. Pas de `vite-plugin-pwa`. |
| Pipeline | Le worker est compilé par un second config Vite vers un nom de fichier fixe ; la route y préfixe les constantes. |
| Bandeau | Flottant en bas d'écran, au-dessus du contenu. |
| Test hors-ligne | Script Playwright dédié, hors suite Pest. |

### Pourquoi pas `vite-plugin-pwa`

Workbox générerait le worker et son manifeste de précache, mais la sortie atterrit dans
`public/build/`. Un worker à `/build/sw.js` ne peut pas contrôler `/` sans en-tête
`Service-Worker-Allowed`, ou sans forcer la sortie à la racine, ce qui casse le pipeline
Laravel-Vite. L'échafaudage PHP existant deviendrait mort. Le gain — du code Workbox
éprouvé — ne compense pas le combat contre le setup, ni la dépendance ajoutée.

### Pourquoi un précache, et pas du runtime-caching seul

Les URLs sous `/build/` sont hashées, donc immuables : un simple cache-first suffirait à les
servir hors-ligne. Mais les pages Inertia sont chargées par `import.meta.glob`, donc
code-splittées : après un déploiement, les chunks jamais demandés manquent, et la première
visite hors-ligne échoue. Le précache est donc nécessaire, pas confortable.

## Architecture

### Invariant central

`HandleInertiaRequests::version()` délègue à `parent::version()`, qui renvoie le hash du
manifest Vite — la valeur que `Vite::manifestHash()` injecte dans `CACHE_VERSION`. La version
d'assets Inertia et la version du cache du worker sont donc le même nombre.

Conséquence : une réponse Inertia en cache ne peut jamais être servie à côté d'une génération
d'assets différente. Au déploiement, `CACHE_VERSION` change, le worker se réinstalle, et les
anciens caches sont supprimés en bloc.

### Fichiers

**Serveur**

| Fichier | Rôle |
| --- | --- |
| `resources/views/pwa/sw.blade.php` | Préfixe les constantes `CACHE_VERSION` et `PRECACHE_URLS`, puis inline le runtime compilé. Aucune logique. |
| `resources/views/pwa/meta-tags.blade.php` | `<link rel="manifest">`, `<meta name="theme-color">`. Inclus depuis le `<head>` de `app.blade.php`. |
| `resources/views/pwa/offline.blade.php` | Page statique de repli, servie quand une URL jamais visitée est demandée hors-ligne. |
| `routes/pwa.php` | Ajout de la route `/hors-ligne` (nom `pwa.offline`). Les routes `pwa.manifest` et `pwa.sw` ne changent pas. |
| `config/pwa.php` | Ajout d'une clé `cache` : préfixes des noms de cache. |

`/hors-ligne` a besoin d'une vraie route parce que le worker précache par URL : sans URL
propre, il n'a rien à mettre en cache ni à servir en repli.

**Client**

| Fichier | Rôle |
| --- | --- |
| `resources/js/pwa/sw.ts` | Point d'entrée du worker : cycle de vie, gestionnaire `fetch`, diffusion des messages. |
| `resources/js/lib/swCache.ts` | Fonctions pures : classification des requêtes, dérivation des clés de cache, synthèse de la réponse partielle `rescuedProps`. |
| `resources/js/lib/swCache.test.ts` | Tests Vitest des fonctions ci-dessus. |
| `resources/js/lib/serviceWorker.ts` | Enregistrement et état réactif : `updateAvailable`, `applyUpdate()`, `stale`, `lastSyncedAt`, `canInstall`, `promptInstall()`. Seul endroit qui touche l'API service worker. |
| `resources/js/lib/serviceWorker.test.ts` | Tests Vitest avec un `navigator.serviceWorker` simulé. |
| `resources/js/components/AppServiceWorkerBanner.vue` | Bandeau unique, trois états exclusifs. |
| `vite.sw.config.ts` | Second build, sortie à nom fixe. |

Le découpage suit le pattern maison observé dans `resources/js/lib/` : la logique testable
vit dans `lib/` avec un test co-localisé, les composants Vue ne consomment qu'un état réactif
et ignorent l'API sous-jacente.

### Pipeline de build

`vite.sw.config.ts` compile `resources/js/pwa/sw.ts` avec `inlineDynamicImports`, sans
hashage, vers `public/sw-runtime.js`. Le script `build` de `package.json` enchaîne les deux
builds :

```
"build": "vite build && vite build --config vite.sw.config.ts"
```

La route `pwa.sw` lit `public/sw-runtime.js`, préfixe les constantes calculées côté PHP, et
sert le tout à `/sw.js`. Le worker reste servi depuis la racine, donc son scope est `/` sans
en-tête particulier.

**En développement**, `bun run dev` ne produit pas `public/sw-runtime.js`. La route sert alors
un worker inerte qui se désenregistre et vide ses caches — on ne veut pas de cache pendant le
HMR. La détection se fait sur l'existence du fichier, pas sur `app()->environment()`, pour que
le comportement reste juste quand on teste un build local.

## Stratégies de cache

Le gestionnaire `fetch` classe chaque requête dans cet ordre :

1. **Non-GET** → réseau direct, jamais de cache. Court-circuit en première ligne.
2. **`/build/*`** → cache-first pur, aucune revalidation. URLs hashées donc immuables : une
   correspondance en cache est vraie par construction. C'est ce qui donne le démarrage
   instantané.
3. **Requêtes Inertia** (en-tête `X-Inertia`) → stale-while-revalidate.
4. **Navigations** (`request.mode === 'navigate'`) → network-first, repli sur le document en
   cache, puis sur `/hors-ligne`.
5. **Reste** (manifest, icônes) → stale-while-revalidate.

### Clés de cache Inertia

L'URL `/` produit trois réponses différentes selon les en-têtes :

- le document HTML (pas d'en-tête `X-Inertia`) ;
- le JSON Inertia complet (`X-Inertia: true`, sans `X-Inertia-Partial-Data`) ;
- un JSON partiel par groupe différé (`X-Inertia-Partial-Data: catalog,trends`).

L'API Cache indexe sur l'URL seule. Sans précaution, la réponse partielle du groupe
`catalogue` écrase la page complète, et l'application se réhydrate sur une page amputée.

Le worker fabrique donc une clé synthétique, utilisée uniquement pour `cache.put` et
`cache.match`, jamais pour le `fetch` réel :

| Type de réponse | Clé de cache |
| --- | --- |
| Document HTML | `/` (URL nue) |
| JSON Inertia complet | `/?__sw=inertia` |
| JSON partiel | `/?__sw=partial:catalog,trends` |

Les noms de props sont triés avant d'être concaténés, pour que deux requêtes équivalentes dont
l'en-tête liste les props dans un ordre différent ne créent pas deux entrées.

### Props différées absentes du cache

Hors-ligne, un partiel peut ne pas avoir d'entrée en cache. Deux réponses naïves échouent :
laisser la requête échouer déclenche la modale d'erreur d'Inertia ; renvoyer un corps vide
laisse les squelettes tourner indéfiniment.

Inertia v3 fournit déjà le mécanisme adapté. Le composant `Deferred` accepte un slot
`#rescue`, rendu dès qu'une de ses clés figure dans `page.rescuedProps` — un champ de premier
niveau du payload, alimenté côté serveur par `PropsResolver` quand un
`Inertia::defer(rescue: true)` voit son résolveur échouer. Le rendu suit cette priorité :

```
propsAreDefined && !hasRescuedProps  → slot default
hasRescuedProps && slots.rescue      → slot rescue
sinon                                → slot fallback
```

Le worker synthétise donc, à partir de la page en cache, une réponse Inertia valide portant
`rescuedProps: ['catalog', 'trends']` et omettant simplement les props qu'il ne peut pas
fournir. Aucun type de prop ne change, aucune convention maison n'est introduite.

Quatre `<Deferred>` existent dans le projet, à compléter d'un `#rescue` affichant « Données
indisponibles hors-ligne » :

| Fichier | Clés |
| --- | --- |
| `components/ValueVsInvestedChart.vue` | `deferKey` — partagé par le tableau de bord (`evolutionSeries`) et la fiche instrument (`valuation`) |
| `components/dashboard/PerformancesSection.vue` | `performances` |
| `components/dashboard/SectorsSection.vue` | `sectorBreakdown` |
| `components/instrument/PriceHistorySection.vue` | `priceHistory` |

Un cinquième cas n'utilise pas `<Deferred>` : `components/dashboard/InstrumentsSection.vue`
dérive son état de chargement de `props.trends === undefined`. Son squelette tournerait
indéfiniment. Il lit donc `usePage().props.rescuedProps` pour éteindre `loading` — les
positions, servies en prop immédiate, restent affichées ; seule l'extension catalogue manque.

## Cycle de vie et mise à jour

**Installation.** Le worker précache `PRECACHE_URLS` — tous les `file` du manifest Vite, plus
`/` et `/hors-ligne` — puis s'arrête. Pas de `skipWaiting()` automatique : c'est ce qui
garantit que l'utilisateur ne perd pas son état en cours (zoom de graphe, page du carrousel).

**Activation.** Suppression de tous les caches dont le nom ne porte pas le `CACHE_VERSION`
courant, puis `clients.claim()`.

**Détection d'une nouvelle version.** Le navigateur ne re-télécharge `/sw.js` que sur une vraie
navigation — or Inertia n'en fait aucune. Sans déclencheur explicite, une application installée
peut rester des jours sur l'ancienne version. `serviceWorker.ts` appelle donc
`registration.update()` sur deux événements : le retour au premier plan (`visibilitychange`) et
chaque navigation Inertia. Le `Cache-Control: no-cache` déjà posé sur la route `pwa.sw` rend ce
re-téléchargement réel.

**Séquence de mise à jour**, une fois `registration.waiting` détecté :

1. `updateAvailable` passe à `true`, le bandeau apparaît.
2. Clic sur « Recharger » → le client envoie `{ type: 'SKIP_WAITING' }` au worker en attente.
3. Le worker appelle `skipWaiting()` et prend le contrôle.
4. Le client écoute `controllerchange` et fait `location.reload()`.

Un booléen `reloading` local garde l'étape 4 : sans lui, un `controllerchange` déclenché
autrement — mise à jour appliquée depuis un autre onglet — provoque une boucle de
rechargement.

**Fraîcheur des données : push, pas pull.** Chaque fois que le worker sert une réponse Inertia
depuis le cache sans avoir réussi à revalider, il diffuse `{ type: 'SERVED_STALE', cachedAt }`
à tous ses clients. Toute revalidation réussie diffuse `{ type: 'FRESH' }`, ce qui efface
l'état. Le client ne sonde jamais, et ce mécanisme couvre les partiels différés, qui n'ont pas
d'accroche naturelle côté Vue.

`navigator.onLine` ne sert que d'indice secondaire : il renvoie `true` derrière un portail
captif. La diffusion du worker fait autorité.

## Interface

`AppServiceWorkerBanner.vue` — bandeau flottant en bas d'écran, position fixe, au-dessus du
contenu. Il ne décale jamais la mise en page, donc ne casse ni le carrousel ni la hauteur des
graphes, et reste à portée de pouce sur mobile.

**Montage.** L'application n'a pas de layout partagé : `Dashboard.vue` et `Show.vue` sont deux
racines indépendantes. Le bandeau est donc monté comme une petite application Vue distincte sur
un `<div id="pwa-banner">` ajouté au layout Blade, plutôt que dupliqué dans chaque page. Il
survit ainsi aux navigations Inertia sans qu'on ait à introduire un layout persistant.

Trois états exclusifs, par priorité décroissante :

1. **Mise à jour disponible** — « Nouvelle version disponible » + bouton « Recharger ».
2. **Données périmées** — « Données du 18/08 à 11h » + bouton « Actualiser ».
3. **Installation possible** — « Installer l'app » + bouton, masquable définitivement.

Les états 1 et 2 ne coexistent pas utilement : si une nouvelle version existe, recharger règle
aussi la fraîcheur.

**Invite d'installation.** `beforeinstallprompt` est capturé et neutralisé au démarrage,
l'événement est mis de côté. Le bouton appelle `prompt()` à la demande. Un refus ou une
fermeture manuelle est mémorisé en `localStorage` : pas de relance à chaque visite.

Tous les textes sont en français, conformément au reste de l'interface.

## Tests

| Niveau | Couverture |
| --- | --- |
| Vitest — `lib/swCache.test.ts` | Les trois formes d'une même URL ne collisionnent pas ; tri des noms de props ; classification `/build/*` / navigation / Inertia / non-GET ; synthèse de la réponse partielle avec `rescuedProps` |
| Vitest — `lib/serviceWorker.test.ts` | `waiting` détecté → `updateAvailable` ; envoi de `SKIP_WAITING` ; garde anti-boucle sur `controllerchange` ; traitement de `SERVED_STALE` et `FRESH` |
| Pest Feature — `PwaRoutesTest` | `manifest.json` conforme à la config ; `/sw.js` contient le hash Vite courant et les URLs du manifest ; `Content-Type` et `Cache-Control` corrects ; worker inerte quand `public/sw-runtime.js` est absent ; `/hors-ligne` répond ; `app.blade.php` porte `<link rel="manifest">` |
| Pest Browser — `PwaTest` | Le worker s'enregistre et atteint l'état actif sans erreur console ; le cache versionné existe et contient les assets précachés ; le bandeau d'installation apparaît sur `beforeinstallprompt` simulé. Assertions via `assertScript()` |
| Playwright dédié | Parcours hors-ligne réel : charger, `context.setOffline(true)`, recharger, vérifier le contenu servi depuis le cache, le slot `#rescue` et le bandeau de fraîcheur |

`pest-plugin-browser` évalue bien du JavaScript arbitraire (`script()`, `assertScript()`), et la
suite Browser existante s'en sert déjà. Ce qui manque est le basculement hors-ligne :
`Pest\Browser\Playwright\Context` n'expose pas `setOffline`, et l'atteindre passerait par des
classes marquées `@internal`. Le dernier scénario s'appuie donc sur `playwright`, déjà présent
dans les dépendances npm, via un script lancé à la main.

## Risques

**Le worker sert une page périmée après un correctif urgent.** Atténué par la liaison
`CACHE_VERSION` / version Inertia : tout déploiement invalide le cache en bloc, et
`registration.update()` au retour au premier plan borne le délai de détection.

**Boucle de rechargement sur `controllerchange`.** Le garde `reloading` est explicitement
couvert par un test.

**Un partiel non caché casse une section.** Le slot `#rescue` d'Inertia rend le cas visible
plutôt que silencieux, et chaque `<Deferred>` a sa branche dédiée. Le seul point de vigilance
est `InstrumentsSection.vue`, qui n'utilise pas `<Deferred>` et doit lire `rescuedProps`
lui-même.

**Le second build est oublié en production.** La route sert alors un worker inerte : dégradation
propre, pas de page cassée. Le test Feature couvre ce cas.
