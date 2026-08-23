# Store hors-ligne PWA — plan d'implémentation

> **Pour les agents :** SOUS-SKILL REQUISE — utiliser `superpowers:subagent-driven-development`
> (recommandé) ou `superpowers:executing-plans` pour exécuter ce plan tâche par tâche. Les étapes
> utilisent la syntaxe case à cocher (`- [ ]`).

**But :** rendre tout le patrimoine lisible hors-ligne, y compris sur des pages jamais visitées, en
alimentant les pages depuis un instantané serveur stocké côté client.

**Architecture :** un endpoint `GET /instantane` compose, via une action par contexte, la totalité
des données que les six pages affichent. Le client stocke cette charge utile en un blob unique dans
IndexedDB, l'expose par un store Pinia, et chaque section rend la valeur du store tant que la prop
Inertia n'est pas arrivée.

**Pile :** Laravel 12 / PHP 8.5, Inertia v3, Vue 3.5, Pinia 3, `idb-keyval`, Vitest, Pest,
Playwright.

**Spec :** `docs/superpowers/specs/2026-08-23-store-hors-ligne-pwa-design.md`

## Contraintes globales

- **Français.** Tout texte visible par le lecteur est en français, accents compris. Les commentaires
  du dépôt sont en français ; les identifiants restent en anglais.
- **Pint.** Après toute modification PHP : `vendor/bin/pint --dirty --format agent`.
- **Tests PHP colocalisés.** Un test Pest vit à côté de sa classe (`app/Contexts/X/Actions/YTest.php`),
  jamais dans `tests/`. `phpunit.xml` scanne `tests`, `app/Contexts` et `app/Shared`.
- **Tests JS colocalisés** aussi : `resources/js/**/*.test.ts`.
- **Un commit par tâche**, message conventionnel en français à la troisième personne
  (`feat: expose un instantané hors-ligne du patrimoine`).
- **Ne jamais appeler `useXxxStore()` au niveau module.** Toujours dans un composant, une fonction,
  ou le getter d'un `computed`. Un appel au chargement du module s'exécuterait avant que Pinia soit
  actif, selon l'ordre d'import.
- **`resources/js/lib/theme.ts` garde `THEME_STORAGE_KEY`** : le script inline du layout Blade relit
  cette clé avant le premier rendu. Déplacer la valeur casserait le premier affichage.
- Commandes : `php artisan test --compact --filter=X`, `bun run test:js`, `bun run typecheck`.

## Structure des fichiers

**Serveur**

| Fichier | Responsabilité |
| --- | --- |
| `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php` | Part patrimoine de l'instantané |
| `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php` | Parts actions et crypto |
| `app/Contexts/RealEstate/Actions/BuildRealEstateSnapshot.php` | Part immobilier |
| `app/Shared/Pwa/Http/SnapshotController.php` | Agrège les trois, appose `version` et `generatedAt` |
| `routes/pwa.php` | Déclare `pwa.snapshot` |

**Client**

| Fichier | Responsabilité |
| --- | --- |
| `resources/js/stores/pinia.ts` | Instance unique partagée par les deux racines Vue |
| `resources/js/stores/viewport.ts` | Largeur d'écran (migré de `lib/viewport.ts`) |
| `resources/js/stores/theme.ts` | Thème (migré de `lib/theme.ts`) |
| `resources/js/stores/serviceWorker.ts` | État du worker (migré de `lib/serviceWorker.ts`) |
| `resources/js/stores/snapshot.ts` | Instantané en mémoire, hydratation, resynchronisation |
| `resources/js/lib/snapshotContract.ts` | Types de la charge utile |
| `resources/js/lib/snapshotStorage.ts` | Lecture/écriture IndexedDB (`idb-keyval`), isolé donc mockable |

---

### Task 1 : Pinia, instance partagée, migration de `viewport`

`viewport` est le plus petit des trois modules d'état : il sert de banc d'essai au montage sur les
deux racines Vue.

**Fichiers :**
- Créer : `resources/js/stores/pinia.ts`, `resources/js/stores/viewport.ts`,
  `resources/js/stores/viewport.test.ts`
- Modifier : `package.json` (dépendance), `resources/js/app.ts`, `resources/js/pwa/banner.ts`,
  `resources/js/lib/layout.ts`
- Supprimer : `resources/js/lib/viewport.ts`

**Interfaces :**
- Produit : `pinia: Pinia` (`@/stores/pinia`) ; `useViewportStore(): { isWide: Ref<boolean> }`
  (`@/stores/viewport`).

- [ ] **Étape 1 : installer Pinia**

```bash
bun add pinia
```

- [ ] **Étape 2 : écrire le test qui échoue**

`resources/js/stores/viewport.test.ts` :

```ts
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/** happy-dom fige `matchMedia` : on le remplace par un `ref` pilotable, comme dans theme.test.ts. */
const { wide } = await vi.hoisted(async () => ({ wide: (await import('vue')).ref(false) }));

vi.mock('@vueuse/core', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@vueuse/core')>()),
    useMediaQuery: () => wide,
}));

const { useViewportStore } = await import('@/stores/viewport');

beforeEach((): void => {
    setActivePinia(createPinia());
    wide.value = false;
});

describe('store de viewport', () => {
    it('suit la requête média', () => {
        const viewport = useViewportStore();

        expect(viewport.isWide).toBe(false);

        wide.value = true;

        expect(viewport.isWide).toBe(true);
    });
});
```

- [ ] **Étape 3 : vérifier l'échec**

```bash
bun run test:js -- viewport
```
Attendu : ÉCHEC, `Cannot find module '@/stores/viewport'`.

- [ ] **Étape 4 : créer l'instance partagée**

`resources/js/stores/pinia.ts` :

```ts
import { createPinia, setActivePinia, type Pinia } from 'pinia';

/**
 * Instance unique. L'application monte deux racines Vue — l'application Inertia et le bandeau du
 * service worker — et deux instances leur donneraient deux états séparés, donc un bandeau aveugle
 * à ce que la page sait.
 *
 * `setActivePinia` est appelé ici, à l'import : `app.ts` utilise des stores avant
 * `createInertiaApp`, donc hors de tout composant, ce qui exige une instance déjà active.
 */
export const pinia: Pinia = createPinia();

setActivePinia(pinia);
```

- [ ] **Étape 5 : écrire le store**

`resources/js/stores/viewport.ts` :

```ts
import { useMediaQuery } from '@vueuse/core';
import { defineStore } from 'pinia';
import type { Ref } from 'vue';

/**
 * Seuil `md` de Tailwind, en rem pour suivre la taille de police du lecteur comme le font les
 * variantes CSS. Partagé plutôt que recopié : un écart entre ce seuil et celui des classes `md:`
 * ferait cohabiter deux comportements sur la même largeur d'écran.
 */
const WIDE_VIEWPORT = '(min-width: 48rem)';

/**
 * Vrai au-delà du mobile. Réservé aux composants qui changent de *comportement* et pas seulement
 * d'apparence — le reste passe par les variantes `md:`, qui n'ont besoin d'aucun JavaScript.
 */
export const useViewportStore = defineStore('viewport', () => {
    const isWide: Ref<boolean> = useMediaQuery(WIDE_VIEWPORT);

    return { isWide };
});
```

- [ ] **Étape 6 : brancher les consommateurs**

`resources/js/lib/layout.ts` — remplacer l'import et le corps du `computed` :

```ts
import { computed, type ComputedRef } from 'vue';
import { useViewportStore } from '@/stores/viewport';
```

```ts
/**
 * Hauteur de tous les graphes de l'application, tableau de bord comme fiche instrument : ce sont
 * les mêmes tracés d'une page à l'autre, une hauteur propre à chaque page se lirait comme deux
 * graphes différents. Chiffrée parce qu'echarts peint dans une boîte de hauteur connue.
 *
 * Le store est résolu dans le getter, pas au niveau module : à l'import de ce fichier, Pinia peut
 * ne pas encore être actif.
 */
export const chartHeight: ComputedRef<number> = computed(
    (): number => (useViewportStore().isWide ? WIDE_CHART_HEIGHT : COMPACT_CHART_HEIGHT),
);
```

`resources/js/app.ts` — passer l'instance à l'application Inertia :

```ts
import { pinia } from '@/stores/pinia';
```

```ts
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .mount(el);
    },
```

`resources/js/pwa/banner.ts` — même instance sur la seconde racine :

```ts
import { createApp } from 'vue';
import AppServiceWorkerBanner from '@/components/AppServiceWorkerBanner.vue';
import { registerServiceWorker } from '@/lib/serviceWorker';
import { pinia } from '@/stores/pinia';

/**
 * Application Vue distincte : les pages Inertia sont des racines indépendantes, sans layout
 * partagé, donc le bandeau serait sinon à dupliquer dans chacune. Elle reçoit la même instance de
 * Pinia que l'application Inertia — sinon les deux liraient deux états séparés.
 */
export function mountServiceWorkerBanner(): void {
    const host = document.getElementById('pwa-banner');

    if (host === null) {
        return;
    }

    registerServiceWorker();
    createApp(AppServiceWorkerBanner).use(pinia).mount(host);
}
```

- [ ] **Étape 7 : supprimer l'ancien module**

```bash
rm resources/js/lib/viewport.ts
```

- [ ] **Étape 8 : vérifier**

```bash
bun run test:js && bun run typecheck
```
Attendu : tout passe, y compris `layout.test.ts`. Si `layout.test.ts` lit `chartHeight` sans Pinia
actif, y ajouter `setActivePinia(createPinia())` en `beforeEach`.

- [ ] **Étape 9 : commit**

```bash
git add package.json bun.lock resources/js
git commit -m "refactor: confie l'état de largeur d'écran à Pinia"
```

---

### Task 2 : migration de `theme`

**Fichiers :**
- Créer : `resources/js/stores/theme.ts`
- Modifier : `resources/js/lib/theme.test.ts` → déplacer en `resources/js/stores/theme.test.ts`,
  `resources/js/lib/chart.ts`, `resources/js/components/ThemeToggle.vue`, `resources/js/app.ts`
- Supprimer : `resources/js/lib/theme.ts`

**Interfaces :**
- Consomme : `pinia` (Task 1).
- Produit : `useThemeStore(): { mode: Ref<ThemeMode>, isDark: ComputedRef<boolean>, cycle(): void, apply(): void }`,
  et les exports `THEME_STORAGE_KEY`, `ThemeMode`, `nextThemeMode` conservés depuis `@/stores/theme`.

- [ ] **Étape 1 : déplacer le test et l'adapter**

```bash
git mv resources/js/lib/theme.test.ts resources/js/stores/theme.test.ts
```

Remplacer l'import et le `beforeEach` :

```ts
import { createPinia, setActivePinia } from 'pinia';
```

```ts
const { THEME_STORAGE_KEY, nextThemeMode, useThemeStore } = await import('@/stores/theme');

let theme: ReturnType<typeof useThemeStore>;

beforeEach((): void => {
    setActivePinia(createPinia());
    theme = useThemeStore();
    preferredDark.value = false;
    theme.mode = 'auto';
    document.documentElement.classList.remove('dark');
});
```

Puis, dans les tests : `themeMode.value` devient `theme.mode`, `isDark.value` devient
`theme.isDark`, `cycleTheme()` devient `theme.cycle()`, `useStoredTheme()` devient `theme.apply()`.

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- theme
```
Attendu : ÉCHEC, `Cannot find module '@/stores/theme'`.

- [ ] **Étape 3 : écrire le store**

`resources/js/stores/theme.ts` :

```ts
import { usePreferredDark, useStorage } from '@vueuse/core';
import { defineStore } from 'pinia';
import { computed, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/** Automatique suit le système ; clair et sombre sont des choix explicites du lecteur. */
export type ThemeMode = 'auto' | 'light' | 'dark';

/**
 * Clé partagée avec le script inline du layout, qui relit ce choix avant le premier rendu. Les deux
 * lectures doivent porter sur la même clé, sinon la page s'ouvre sur un thème puis bascule.
 */
export const THEME_STORAGE_KEY = 'argent-theme';

/** Ordre des clics du bouton : l'automatique reste atteignable, donc réversible. */
const CYCLE: readonly ThemeMode[] = ['auto', 'light', 'dark'];

/** Mode suivant dans le cycle du bouton. */
export function nextThemeMode(mode: ThemeMode): ThemeMode {
    const position = CYCLE.indexOf(mode);

    return CYCLE[(position + 1) % CYCLE.length];
}

export const useThemeStore = defineStore('theme', () => {
    /** Mode choisi, retenu d'une visite à l'autre. */
    const mode: Ref<ThemeMode> = useStorage<ThemeMode>(THEME_STORAGE_KEY, 'auto');

    const preferredDark: Ref<boolean> = usePreferredDark();

    /**
     * Thème effectivement affiché, réactif : les graphes s'y accrochent pour choisir leur palette.
     * Tout ce qui n'est pas un choix explicite retombe sur le système, y compris un stockage abîmé.
     */
    const isDark: ComputedRef<boolean> = computed((): boolean => {
        if (mode.value === 'light') {
            return false;
        }

        if (mode.value === 'dark') {
            return true;
        }

        return preferredDark.value;
    });

    /** Avance d'un cran dans le cycle. Appelé par le bouton de thème. */
    function cycle(): void {
        mode.value = nextThemeMode(mode.value);
    }

    /**
     * Suit le thème retenu en posant la classe `dark` sur la racine. Le premier rendu est déjà
     * traité par le script inline du layout ; ce watcher gère les changements à chaud.
     */
    function apply(): void {
        watch(
            isDark,
            (dark: boolean): void => {
                document.documentElement.classList.toggle('dark', dark);
            },
            { immediate: true },
        );
    }

    return { mode, isDark, cycle, apply };
});
```

- [ ] **Étape 4 : brancher les consommateurs**

`resources/js/lib/chart.ts` — supprimer `import { isDark } from './theme';`, ajouter
`import { useThemeStore } from '@/stores/theme';`, et remplacer chaque `isDark.value` par
`useThemeStore().isDark`. Les deux occurrences sont dans des fonctions (lignes 29 et 76), jamais au
niveau module : aucune n'est évaluée à l'import.

`resources/js/components/ThemeToggle.vue` :

```ts
import { useThemeStore } from '@/stores/theme';
import type { ThemeMode } from '@/stores/theme';

const theme = useThemeStore();
```
puis, dans le template, `themeMode` devient `theme.mode` et `cycleTheme` devient `theme.cycle`.

`resources/js/app.ts` :

```ts
import { useThemeStore } from '@/stores/theme';
```
```ts
useThemeStore().apply();
```

- [ ] **Étape 5 : supprimer l'ancien module et vérifier**

```bash
rm resources/js/lib/theme.ts
bun run test:js && bun run typecheck
```
Attendu : tout passe, `chart.test.ts` et `chart.dark.test.ts` compris. Si ces deux fichiers pilotent
le thème, y ajouter `setActivePinia(createPinia())` en `beforeEach` et régler `useThemeStore().mode`.

- [ ] **Étape 6 : commit**

```bash
git add resources/js
git commit -m "refactor: confie le thème à Pinia"
```

---

### Task 3 : migration de `serviceWorker`

C'est la migration qui rapporte le plus : `resetServiceWorkerState()` disparaît.

**Fichiers :**
- Créer : `resources/js/stores/serviceWorker.ts`
- Déplacer : `resources/js/lib/serviceWorker.test.ts` → `resources/js/stores/serviceWorker.test.ts`
- Modifier : `resources/js/components/AppServiceWorkerBanner.vue`, `resources/js/pwa/banner.ts`
- Supprimer : `resources/js/lib/serviceWorker.ts`

**Interfaces :**
- Produit : `useServiceWorkerStore()` exposant `updateAvailable`, `stale`, `lastSyncedAt`,
  `canInstall` (état) ; `trackRegistration`, `requestStatus`, `handleMessage`,
  `handleControllerChange`, `applyUpdate`, `handleBeforeInstallPrompt`, `promptInstall`,
  `dismissInstall`, `register`, `setReloader` (actions). Type `SwClientMessage` et
  `BeforeInstallPromptEvent` réexportés depuis `@/stores/serviceWorker`.

- [ ] **Étape 1 : déplacer le test et l'adapter**

```bash
git mv resources/js/lib/serviceWorker.test.ts resources/js/stores/serviceWorker.test.ts
```

Remplacer la mise en place :

```ts
import { createPinia, setActivePinia } from 'pinia';

const { useServiceWorkerStore } = await import('@/stores/serviceWorker');

let sw: ReturnType<typeof useServiceWorkerStore>;

/**
 * Une instance neuve par test remplace `resetServiceWorkerState()` : plus aucune garde ne survit
 * d'un test au suivant, donc plus rien à remettre à zéro à la main.
 */
beforeEach((): void => {
    setActivePinia(createPinia());
    sw = useServiceWorkerStore();
});
```

Puis, dans chaque test, `updateAvailable.value` devient `sw.updateAvailable`, `stale.value` devient
`sw.stale`, etc. ; les appels de fonction deviennent `sw.handleMessage(...)`, `sw.applyUpdate()`, …
**Supprimer tout appel à `resetServiceWorkerState`.**

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- serviceWorker
```
Attendu : ÉCHEC, `Cannot find module '@/stores/serviceWorker'`.

- [ ] **Étape 3 : écrire le store**

`resources/js/stores/serviceWorker.ts` : reprendre `lib/serviceWorker.ts` **à l'identique** —
commentaires compris, ils portent des pièges vérifiés — en enveloppant le corps dans
`defineStore('serviceWorker', () => { … })`. Les `ref` de module deviennent des `ref` locaux, les
variables `waitingWorker`, `installEvent`, `awaitingReload`, `reload` deviennent des variables
locales au setup, et `registerServiceWorker` devient l'action `register`. Retirer
`resetServiceWorkerState` : Pinia le remplace.

Squelette, à remplir avec le corps existant inchangé :

```ts
import { defineStore } from 'pinia';
import { ref, type Ref } from 'vue';

/** Type non standardisé, absent de la bibliothèque DOM, mais implémenté par Chrome. */
export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

export type SwClientMessage =
    | { type: 'FRESH' }
    | { type: 'SERVED_STALE'; cachedAt: number | null };

/** Un refus est définitif : l'invite d'installation ne se représente pas à chaque visite. */
const DISMISSED_KEY = 'pwa-install-dismissed';

export const useServiceWorkerStore = defineStore('serviceWorker', () => {
    const updateAvailable: Ref<boolean> = ref(false);
    const stale: Ref<boolean> = ref(false);
    const lastSyncedAt: Ref<number | null> = ref(null);
    const canInstall: Ref<boolean> = ref(false);

    let waitingWorker: ServiceWorker | null = null;
    let installEvent: BeforeInstallPromptEvent | null = null;

    /** Vrai seulement entre notre `SKIP_WAITING` et le `controllerchange` qui en découle. */
    let awaitingReload = false;

    let reload: () => void = (): void => {
        window.location.reload();
    };

    /** Point d'injection : happy-dom n'a pas de vraie navigation à recharger. */
    function setReloader(fn: () => void): void {
        reload = fn;
    }

    // … trackRegistration, requestStatus, handleMessage, handleControllerChange, applyUpdate,
    // … handleBeforeInstallPrompt, promptInstall, dismissInstall, scheduleUpdateChecks, register
    // … repris tels quels de lib/serviceWorker.ts, sans `.value` sur les refs déjà locales.

    return {
        updateAvailable, stale, lastSyncedAt, canInstall,
        setReloader, trackRegistration, requestStatus, handleMessage, handleControllerChange,
        applyUpdate, handleBeforeInstallPrompt, promptInstall, dismissInstall, register,
    };
});
```

- [ ] **Étape 4 : brancher les consommateurs**

`resources/js/components/AppServiceWorkerBanner.vue` :

```ts
import { useServiceWorkerStore } from '@/stores/serviceWorker';

const sw = useServiceWorkerStore();
```
puis `updateAvailable.value` → `sw.updateAvailable`, `stale.value` → `sw.stale`,
`lastSyncedAt.value` → `sw.lastSyncedAt`, `canInstall.value` → `sw.canInstall`, et dans le template
`@click="applyUpdate"` → `@click="sw.applyUpdate"`, `dismissInstall` → `sw.dismissInstall`,
`promptInstall` → `sw.promptInstall`.

`resources/js/pwa/banner.ts` : `registerServiceWorker()` devient
`useServiceWorkerStore().register()`, appelé **après** `createApp(...).use(pinia)` — l'instance doit
exister avant l'appel. L'import de `@/stores/pinia` suffit à la rendre active.

- [ ] **Étape 5 : supprimer l'ancien module et vérifier**

```bash
rm resources/js/lib/serviceWorker.ts
bun run test:js && bun run typecheck
grep -rn "resetServiceWorkerState" resources/js
```
Attendu : tests verts, et le `grep` ne renvoie rien.

- [ ] **Étape 6 : commit**

```bash
git add resources/js
git commit -m "refactor: confie l'état du service worker à Pinia"
```

---

### Task 4 : le worker laisse passer `/instantane`

À faire **avant** l'endpoint : sans ce garde-fou, la première resynchronisation hors-ligne fait
basculer le bandeau du lecteur.

**Fichiers :**
- Modifier : `resources/js/lib/swCache.ts`, `resources/js/lib/swCache.test.ts`

**Interfaces :**
- Produit : `classifyRequest` renvoie `'passthrough'` pour `/instantane`.

- [ ] **Étape 1 : écrire le test qui échoue**

Dans `resources/js/lib/swCache.test.ts`, à la suite des cas de `classifyRequest` :

```ts
it('laisse passer l\'instantané hors-ligne, que le store gère lui-même', () => {
    const shape = {
        method: 'GET',
        url: 'https://argent.test/instantane',
        mode: 'cors',
        inertia: false,
        partialData: null,
    };

    expect(classifyRequest(shape, 'https://argent.test')).toBe('passthrough');
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- swCache
```
Attendu : ÉCHEC, reçu `'other'`.

- [ ] **Étape 3 : implémenter**

Dans `resources/js/lib/swCache.ts`, sous `BUILD_PREFIX` :

```ts
/**
 * L'instantané hors-ligne n'est pas une ressource de page : le store le demande en tâche de fond
 * et absorbe lui-même ses échecs. Le laisser tomber dans `staleWhileRevalidate` ferait diffuser
 * `FRESH` / `SERVED_STALE` depuis une requête qui ne décrit pas la page affichée — le bandeau du
 * lecteur basculerait sur l'état d'une synchronisation de fond.
 */
const SNAPSHOT_PATH = '/instantane';
```

et, dans `classifyRequest`, juste après le contrôle d'origine :

```ts
    if (url.pathname === SNAPSHOT_PATH) {
        return 'passthrough';
    }
```

- [ ] **Étape 4 : vérifier**

```bash
bun run test:js -- swCache
```
Attendu : PASSE.

- [ ] **Étape 5 : commit**

```bash
git add resources/js/lib/swCache.ts resources/js/lib/swCache.test.ts
git commit -m "fix: soustrait l'instantané hors-ligne aux stratégies du worker"
```

---

### Task 5 : `BuildWealthSnapshot`

**Fichiers :**
- Créer : `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php`,
  `app/Contexts/Wealth/Actions/BuildWealthSnapshotTest.php`

**Interfaces :**
- Produit : `BuildWealthSnapshot::__invoke(int $userId): array` renvoyant
  `array{overview: WealthOverviewData, series: WealthSeriesData, income: WealthIncomeData}`.

- [ ] **Étape 1 : écrire le test qui échoue**

`app/Contexts/Wealth/Actions/BuildWealthSnapshotTest.php` :

```php
<?php

use App\Contexts\Wealth\Actions\BuildWealthSnapshot;

it('rassemble les trois blocs du tableau de bord', function () {
    ['user' => $user] = portfolioFixture();

    $snapshot = app(BuildWealthSnapshot::class)($user->id);

    expect($snapshot)->toHaveKeys(['overview', 'series', 'income'])
        ->and($snapshot['overview']->totalValue)->toBeGreaterThan(0.0);
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
php artisan test --compact --filter=BuildWealthSnapshot
```
Attendu : ÉCHEC, classe introuvable.

- [ ] **Étape 3 : implémenter**

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSeriesData;

/**
 * Part patrimoine de l'instantané hors-ligne : les trois props du tableau de bord, y compris les
 * deux qu'il diffère. Les mêmes actions que le contrôleur, donc jamais une composition parallèle
 * qui pourrait diverger de ce que la page affiche.
 */
class BuildWealthSnapshot
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
    ) {}

    /**
     * @return array{
     *     overview: WealthOverviewData,
     *     series: WealthSeriesData,
     *     income: WealthIncomeData,
     * }
     */
    public function __invoke(int $userId): array
    {
        return [
            'overview' => ($this->overview)($userId),
            'series' => ($this->series)($userId),
            'income' => ($this->income)($userId),
        ];
    }
}
```

- [ ] **Étape 4 : vérifier**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=BuildWealthSnapshot
```
Attendu : PASSE.

- [ ] **Étape 5 : commit**

```bash
git add app/Contexts/Wealth/Actions
git commit -m "feat: compose la part patrimoine de l'instantané hors-ligne"
```

---

### Task 6 : `BuildMarketViewSnapshot`

**Fichiers :**
- Créer : `app/Contexts/MarketView/Actions/BuildMarketViewSnapshot.php`,
  `app/Contexts/MarketView/Actions/BuildMarketViewSnapshotTest.php`

**Interfaces :**
- Produit : `BuildMarketViewSnapshot::__invoke(int $userId): array` renvoyant
  `array{instruments: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>}, crypto: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>}}`.

Les clés de `list` reprennent exactement les noms de props des pages : `overview`, `trends`,
`performances`, `evolutionSeries`, `sectorBreakdown`, `income`, `annualIncome` pour les actions ;
`overview`, `trends`, `performances`, `evolutionSeries` pour la crypto. Celles de `byId` :
`instrument`, `performances`, `priceHistory`, `valuation`, plus `dividends` pour les seules actions.

- [ ] **Étape 1 : écrire le test qui échoue**

`app/Contexts/MarketView/Actions/BuildMarketViewSnapshotTest.php` :

```php
<?php

use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;

it('porte la page liste et une fiche par position détenue', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['instruments']['list'])->toHaveKeys([
        'overview', 'trends', 'performances', 'evolutionSeries', 'sectorBreakdown', 'income', 'annualIncome',
    ])
        ->and($snapshot['instruments']['byId'])->toHaveKey($instrument->id)
        ->and($snapshot['instruments']['byId'][$instrument->id])->toHaveKeys([
            'instrument', 'performances', 'priceHistory', 'valuation', 'dividends',
        ]);
});

it('range la crypto à part, sans dividendes sur ses fiches', function () {
    ['user' => $user, 'crypto' => $bitcoin] = cryptoFixture();

    $snapshot = app(BuildMarketViewSnapshot::class)($user->id);

    expect($snapshot['crypto']['byId'])->toHaveKey($bitcoin->id)
        ->and($snapshot['crypto']['byId'][$bitcoin->id])->not->toHaveKey('dividends')
        ->and($snapshot['instruments']['byId'])->not->toHaveKey($bitcoin->id);
});
```

Si `portfolioFixture()` ne renvoie pas de clé `instrument`, lire `tests/Pest.php:89` et employer la
clé qu'il expose réellement.

- [ ] **Étape 2 : vérifier l'échec**

```bash
php artisan test --compact --filter=BuildMarketViewSnapshot
```
Attendu : ÉCHEC, classe introuvable.

- [ ] **Étape 3 : implémenter**

```php
<?php

namespace App\Contexts\MarketView\Actions;

use App\Contexts\Income\Actions\GetAnnualIncome;
use App\Contexts\Income\Actions\GetAssetDividendHistory;
use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\MarketView\Datas\HoldingSnapshotData;
use App\Contexts\MarketView\Datas\InstrumentDetailData;
use App\Contexts\MarketView\Ports\HoldingsPort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Datas\EvolutionSeriesData;
use App\Contexts\Valuation\Enums\ValuationRange;
use App\Contexts\Income\Datas\IncomeSummaryData;
use Illuminate\Support\Carbon;

/**
 * Parts actions et crypto de l'instantané hors-ligne : les deux pages liste, et une fiche par
 * position détenue. Les compositions reprennent celles des quatre contrôleurs, props différées
 * comprises — l'instantané les résout toutes, puisqu'il n'a pas d'affichage à ne pas faire
 * attendre.
 *
 * Seule la plage de valorisation par défaut est portée : les autres restent en ligne seulement.
 */
class BuildMarketViewSnapshot
{
    public function __construct(
        private HoldingsPort $holdings,
        private MarketDataPort $market,
        private GetInstrumentDetail $getDetail,
        private GetHoldingTrends $getTrends,
        private GetPortfolioOverview $getOverview,
    ) {}

    /**
     * @return array{
     *     instruments: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>},
     *     crypto: array{list: array<string, mixed>, byId: array<int, array<string, mixed>>},
     * }
     */
    public function __invoke(int $userId): array
    {
        $user = User::query()->find($userId);

        /**
         * `GetPortfolioOverview` et `GetSectorBreakdown` prennent un `User`, pas un identifiant :
         * les quatre contrôleurs les gardent tous derrière un `$user !== null`. Sans cette sortie,
         * un instantané demandé sur une base vide passerait `null` à des paramètres typés.
         */
        if ($user === null) {
            return [
                'instruments' => ['list' => $this->emptyList(forSecurities: true), 'byId' => []],
                'crypto' => ['list' => $this->emptyList(forSecurities: false), 'byId' => []],
            ];
        }

        $securities = InstrumentType::securities();
        $crypto = [InstrumentType::Crypto];

        $details = $this->detailsByClass($userId);

        return [
            'instruments' => [
                'list' => [
                    'overview' => ($this->getOverview)($user, $securities),
                    'trends' => ($this->getTrends)($userId, ValuationRange::Max),
                    'performances' => app(BuildPortfolioPerformances::class)($userId, $securities),
                    'evolutionSeries' => app(BuildEvolutionSeries::class)(
                        $userId,
                        null,
                        ValuationGranularity::Week,
                        $securities,
                    ),
                    'sectorBreakdown' => app(GetSectorBreakdown::class)($user),
                    'income' => app(GetIncomeSummary::class)($userId, IncomeSource::Dividend),
                    'annualIncome' => app(GetAnnualIncome::class)($userId, IncomeSource::Dividend),
                ],
                'byId' => $details['securities'],
            ],
            'crypto' => [
                'list' => [
                    'overview' => ($this->getOverview)($user, $crypto),
                    'trends' => ($this->getTrends)($userId, ValuationRange::Max),
                    'performances' => app(BuildPortfolioPerformances::class)($userId, $crypto),
                    'evolutionSeries' => app(BuildEvolutionSeries::class)(
                        $userId,
                        null,
                        ValuationGranularity::Week,
                        $crypto,
                    ),
                ],
                'byId' => $details['crypto'],
            ],
        ];
    }

    /**
     * Page liste sans utilisateur : les mêmes `Data::empty()` que servent les contrôleurs dans ce
     * cas. `$forSecurities` distingue la page Actions, qui porte trois blocs de plus.
     *
     * @return array<string, mixed>
     */
    private function emptyList(bool $forSecurities): array
    {
        $list = [
            'overview' => PortfolioOverviewData::empty(),
            'trends' => [],
            'performances' => [],
            'evolutionSeries' => EvolutionSeriesData::empty(),
        ];

        if (! $forSecurities) {
            return $list;
        }

        return [
            ...$list,
            'sectorBreakdown' => [],
            'income' => IncomeSummaryData::empty(),
            'annualIncome' => [],
        ];
    }

    /**
     * Les fiches, réparties selon la classe que porte l'actif lui-même. `InstrumentType::isCrypto()`
     * est la seule définition de ce partage : le recopier ici ferait diverger l'instantané des deux
     * contrôleurs de fiche, qui renvoient 404 sur l'actif de l'autre classe.
     *
     * @return array{securities: array<int, array<string, mixed>>, crypto: array<int, array<string, mixed>>}
     */
    private function detailsByClass(int $userId): array
    {
        $byClass = ['securities' => [], 'crypto' => []];

        foreach ($this->holdings->holdingsFor($userId) as $holding) {
            /** @var HoldingSnapshotData $holding */
            $detail = ($this->getDetail)($userId, $holding->assetId);

            if ($detail === null) {
                continue;
            }

            $byClass[$detail->type->isCrypto() ? 'crypto' : 'securities'][$holding->assetId]
                = $this->page($userId, $holding->assetId, $detail);
        }

        return $byClass;
    }

    /** @return array<string, mixed> */
    private function page(int $userId, int $assetId, InstrumentDetailData $detail): array
    {
        $page = [
            'instrument' => $detail,
            'performances' => app(BuildAssetPerformances::class)($userId, $assetId),
            'priceHistory' => $this->market->priceHistory($assetId, Carbon::now()->subMonths(12)),
            'valuation' => app(BuildAssetValuationSeries::class)(
                $userId,
                $assetId,
                ValuationRange::Max,
                ValuationGranularity::Week,
            ),
        ];

        /** La fiche crypto n'affiche pas de dividendes : les porter ici gonflerait le blob pour rien. */
        if (! $detail->type->isCrypto()) {
            $page['dividends'] = app(GetAssetDividendHistory::class)($userId, $assetId);
        }

        return $page;
    }
}
```

Vérifier les espaces de noms de `BuildAssetPerformances`, `BuildAssetValuationSeries`,
`GetAssetDividendHistory`, `GetSectorBreakdown`, `GetIncomeSummary`, `GetAnnualIncome` en relisant
les `use` de `InstrumentDetailController` et `InstrumentsController` — ce sont exactement les mêmes.

- [ ] **Étape 4 : vérifier**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=BuildMarketViewSnapshot
```
Attendu : PASSE.

- [ ] **Étape 5 : commit**

```bash
git add app/Contexts/MarketView/Actions
git commit -m "feat: compose les parts actions et crypto de l'instantané hors-ligne"
```

---

### Task 7 : `BuildRealEstateSnapshot`

**Fichiers :**
- Créer : `app/Contexts/RealEstate/Actions/BuildRealEstateSnapshot.php`,
  `app/Contexts/RealEstate/Actions/BuildRealEstateSnapshotTest.php`

**Interfaces :**
- Produit : `BuildRealEstateSnapshot::__invoke(int $userId): array` renvoyant
  `array{list: array<string, mixed>, byId: array<int, array<string, mixed>>}`. Clés de `list` :
  `realEstate`, `series`, `profitability`, `income`. Clés de `byId` : `property`, `amortization`.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

use App\Contexts\RealEstate\Actions\BuildRealEstateSnapshot;

it('porte la page liste et une fiche par bien', function () {
    ['user' => $user, 'property' => $property] = propertyFixture();

    $snapshot = app(BuildRealEstateSnapshot::class)($user->id);

    expect($snapshot['list'])->toHaveKeys(['realEstate', 'series', 'profitability', 'income'])
        ->and($snapshot['byId'])->toHaveKey($property->id)
        ->and($snapshot['byId'][$property->id])->toHaveKeys(['property', 'amortization']);
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
php artisan test --compact --filter=BuildRealEstateSnapshot
```
Attendu : ÉCHEC, classe introuvable.

- [ ] **Étape 3 : implémenter**

```php
<?php

namespace App\Contexts\RealEstate\Actions;

use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Support\UserProperties;

/**
 * Part immobilier de l'instantané hors-ligne : la page liste et une fiche par bien, props différées
 * comprises. `UserProperties` fournit l'énumération, comme aux trois autres actions du contexte —
 * une requête propre ici rouvrirait le N+1 qu'il existe pour fermer.
 */
class BuildRealEstateSnapshot
{
    public function __construct(
        private UserProperties $properties,
        private GetRealEstateOverview $overview,
        private BuildRealEstateSeries $series,
        private GetRealEstateProfitability $profitability,
        private GetRealEstateIncome $income,
        private GetPropertyDetail $getDetail,
        private GetLoanSchedule $getSchedule,
    ) {}

    /**
     * @return array{list: array<string, mixed>, byId: array<int, array<string, mixed>>}
     */
    public function __invoke(int $userId): array
    {
        $byId = [];

        foreach ($this->properties->forUser($userId) as $property) {
            /** @var Property $property */
            $detail = ($this->getDetail)($userId, $property->id);

            if ($detail === null) {
                continue;
            }

            $byId[$property->id] = [
                'property' => $detail,
                'amortization' => ($this->getSchedule)($userId, $property->id),
            ];
        }

        return [
            'list' => [
                'realEstate' => ($this->overview)($userId),
                'series' => ($this->series)($userId),
                'profitability' => ($this->profitability)($userId),
                'income' => ($this->income)($userId),
            ],
            'byId' => $byId,
        ];
    }
}
```

- [ ] **Étape 4 : vérifier**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=BuildRealEstateSnapshot
```
Attendu : PASSE.

- [ ] **Étape 5 : commit**

```bash
git add app/Contexts/RealEstate/Actions
git commit -m "feat: compose la part immobilier de l'instantané hors-ligne"
```

---

### Task 8 : `SnapshotController` et la route `/instantane`

**Fichiers :**
- Créer : `app/Shared/Pwa/Http/SnapshotController.php`,
  `app/Shared/Pwa/Http/SnapshotControllerTest.php`
- Modifier : `routes/pwa.php`

**Interfaces :**
- Produit : `GET /instantane` (nom `pwa.snapshot`), JSON
  `{version, generatedAt, dashboard, instruments, crypto, properties}`.

- [ ] **Étape 1 : écrire le test qui échoue**

```php
<?php

use App\Contexts\Identity\Models\User;

it('sert l\'instantané complet du patrimoine', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user)
        ->getJson(route('pwa.snapshot'))
        ->assertOk()
        ->assertJsonStructure([
            'version',
            'generatedAt',
            'dashboard' => ['overview', 'series', 'income'],
            'instruments' => ['list', 'byId'],
            'crypto' => ['list', 'byId'],
            'properties' => ['list', 'byId'],
        ]);
});

it('garde la même version tant que les données ne bougent pas', function () {
    ['user' => $user] = portfolioFixture();

    $first = $this->actingAs($user)->getJson(route('pwa.snapshot'))->json('version');
    $second = $this->actingAs($user)->getJson(route('pwa.snapshot'))->json('version');

    expect($second)->toBe($first);
});

it('répond sans données quand aucun utilisateur n\'existe', function () {
    User::query()->delete();

    $this->getJson(route('pwa.snapshot'))
        ->assertOk()
        ->assertJsonPath('dashboard.overview.totalValue', 0);
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
php artisan test --compact --filter=SnapshotController
```
Attendu : ÉCHEC, route `pwa.snapshot` introuvable.

- [ ] **Étape 3 : implémenter le contrôleur**

`app/Shared/Pwa/Http/SnapshotController.php` :

```php
<?php

namespace App\Shared\Pwa\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\MarketView\Actions\BuildMarketViewSnapshot;
use App\Contexts\RealEstate\Actions\BuildRealEstateSnapshot;
use App\Contexts\Wealth\Actions\BuildWealthSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * Instantané hors-ligne : tout ce que les six pages affichent, en une réponse. Le service worker
 * n'indexe ses réponses que par URL, donc hors-ligne seul ce que le lecteur a déjà ouvert existe ;
 * cet instantané rend le reste lisible sans l'avoir visité.
 *
 * Ce contrôleur ne connaît aucun modèle : il n'assemble que trois actions publiques, une par
 * contexte, comme le fait `Wealth\Infrastructure` pour le tableau de bord.
 */
class SnapshotController
{
    public function __construct(
        private BuildWealthSnapshot $wealth,
        private BuildMarketViewSnapshot $marketView,
        private BuildRealEstateSnapshot $realEstate,
    ) {}

    public function __invoke(): JsonResponse
    {
        $userId = (auth()->user() ?? User::query()->first())?->id ?? 0;

        $market = ($this->marketView)($userId);

        $body = [
            'dashboard' => ($this->wealth)($userId),
            'instruments' => $market['instruments'],
            'crypto' => $market['crypto'],
            'properties' => ($this->realEstate)($userId),
        ];

        /**
         * Empreinte du contenu seul : `generatedAt` en est exclu, sinon la version changerait à
         * chaque appel et le client réécrirait son blob pour rien.
         */
        return response()->json([
            'version' => sha1((string) json_encode($body)),
            'generatedAt' => now()->timestamp,
            ...$body,
        ]);
    }
}
```

- [ ] **Étape 4 : déclarer la route**

Dans `routes/pwa.php`, après la route `hors-ligne` :

```php
/** Instantané hors-ligne : le worker le laisse passer, le store côté client s'en occupe seul. */
Route::get('instantane', SnapshotController::class)->name('pwa.snapshot');
```

avec `use App\Shared\Pwa\Http\SnapshotController;` en tête de fichier.

- [ ] **Étape 5 : vérifier**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=SnapshotController
```
Attendu : PASSE. Si le test « même version » échoue, chercher une valeur dépendante du temps
(horodatage, âge relatif) dans un `Data` composé et l'exclure du corps servant à l'empreinte.

- [ ] **Étape 6 : mesurer le coût, comme la spec l'exige**

```bash
php artisan tinker --execute 'DB::enableQueryLog(); $t = microtime(true); app(App\Shared\Pwa\Http\SnapshotController::class)(); echo round((microtime(true) - $t) * 1000)." ms, ".count(DB::getQueryLog())." requêtes\n";'
```
Consigner le résultat dans le message de commit. Au-delà de ~1 s ou de quelques centaines de
requêtes, ouvrir une tâche de suivi — ne pas optimiser ici.

- [ ] **Étape 7 : commit**

```bash
git add app/Shared/Pwa routes/pwa.php
git commit -m "feat: sert un instantané hors-ligne de tout le patrimoine"
```

---

### Task 9 : persistance du blob

**Fichiers :**
- Créer : `resources/js/lib/snapshotContract.ts`, `resources/js/lib/snapshotStorage.ts`,
  `resources/js/lib/snapshotStorage.test.ts`
- Modifier : `package.json`

**Interfaces :**
- Produit : type `Snapshot` et ses sous-types (`@/lib/snapshotContract`) ;
  `readSnapshot(): Promise<Snapshot | null>` et `writeSnapshot(snapshot: Snapshot): Promise<void>`
  (`@/lib/snapshotStorage`).

- [ ] **Étape 1 : installer la dépendance**

```bash
bun add idb-keyval
```

- [ ] **Étape 2 : écrire le contrat**

`resources/js/lib/snapshotContract.ts` :

```ts
import type { CatalogTrend } from './catalog';
import type { AnnualIncome, AssetDividendHistory, IncomeSummary } from './income';
import type { Instrument, PriceHistory, ValuationSeries } from './instrument';
import type { Performance } from './performance';
import type { EvolutionSeries, PortfolioOverview } from './portfolio';
import type {
    AmortizationLine,
    PropertyDetail,
    PropertyProfitability,
    RealEstateIncome,
    RealEstateOverview,
    RealEstateSeries,
} from './realEstate';
import type { SectorSlice } from './sector';
import type { WealthIncome, WealthOverview, WealthSeries } from './wealth';

/**
 * Miroir exact de ce que sert `GET /instantane`. Chaque bloc reprend les noms de props de la page
 * correspondante : la fusion côté page est alors une simple alternative entre deux valeurs de même
 * type, sans traduction possible à faire diverger.
 */
export interface DashboardSnapshot {
    overview: WealthOverview;
    series: WealthSeries;
    income: WealthIncome;
}

export interface InstrumentsListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    performances: Performance[];
    evolutionSeries: EvolutionSeries;
    sectorBreakdown: SectorSlice[];
    income: IncomeSummary;
    annualIncome: AnnualIncome[];
}

export interface CryptoListSnapshot {
    overview: PortfolioOverview;
    trends: CatalogTrend[];
    performances: Performance[];
    evolutionSeries: EvolutionSeries;
}

export interface InstrumentPageSnapshot {
    instrument: Instrument;
    performances: Performance[];
    priceHistory: PriceHistory;
    valuation: ValuationSeries;
    /** Absent des fiches crypto : leur page n'affiche pas de dividendes. */
    dividends?: AssetDividendHistory;
}

export interface PropertiesListSnapshot {
    realEstate: RealEstateOverview;
    series: RealEstateSeries;
    profitability: PropertyProfitability[];
    income: RealEstateIncome;
}

export interface PropertyPageSnapshot {
    property: PropertyDetail;
    amortization: AmortizationLine[];
}

export interface Snapshot {
    /** Empreinte du contenu : le client saute l'écriture IndexedDB quand elle n'a pas changé. */
    version: string;
    /** Horodatage Unix **en secondes**, produit par le serveur. */
    generatedAt: number;
    dashboard: DashboardSnapshot;
    instruments: { list: InstrumentsListSnapshot; byId: Record<string, InstrumentPageSnapshot> };
    crypto: { list: CryptoListSnapshot; byId: Record<string, InstrumentPageSnapshot> };
    properties: { list: PropertiesListSnapshot; byId: Record<string, PropertyPageSnapshot> };
}
```

Si un nom de type diffère (`PropertyProfitability`, `SectorSlice`, `AnnualIncome`…), reprendre celui
qu'importe déjà la page correspondante — les lignes d'import sont dans `Pages/Instruments/Index.vue`,
`Pages/Instruments/Show.vue`, `Pages/Properties/Index.vue` et `Pages/Properties/Detail.vue`.

- [ ] **Étape 3 : écrire le test qui échoue**

`resources/js/lib/snapshotStorage.test.ts` :

```ts
import { beforeEach, describe, expect, it, vi } from 'vitest';

const { entries } = vi.hoisted(() => ({ entries: new Map<string, unknown>() }));

vi.mock('idb-keyval', () => ({
    createStore: () => 'store',
    get: async (key: string) => entries.get(key),
    set: async (key: string, value: unknown) => { entries.set(key, value); },
}));

const { readSnapshot, writeSnapshot } = await import('@/lib/snapshotStorage');

beforeEach((): void => {
    entries.clear();
});

describe('persistance de l\'instantané', () => {
    it('rend null quand rien n\'a jamais été écrit', async () => {
        expect(await readSnapshot()).toBeNull();
    });

    it('relit ce qu\'il a écrit', async () => {
        const snapshot = { version: 'abc', generatedAt: 1 } as never;

        await writeSnapshot(snapshot);

        expect(await readSnapshot()).toStrictEqual(snapshot);
    });
});
```

- [ ] **Étape 4 : vérifier l'échec**

```bash
bun run test:js -- snapshotStorage
```
Attendu : ÉCHEC, `Cannot find module '@/lib/snapshotStorage'`.

- [ ] **Étape 5 : implémenter**

`resources/js/lib/snapshotStorage.ts` :

```ts
import { createStore, get, set, type UseStore } from 'idb-keyval';
import type { Snapshot } from './snapshotContract';

/**
 * Un blob unique, pas des object stores indexés : à ce volume — quelques dizaines d'actifs, trois
 * biens — un schéma client et ses migrations coûteraient plus qu'ils ne rapportent. Ce module est
 * la seule frontière à franchir le jour où ce ne sera plus vrai.
 */
const SNAPSHOT_KEY = 'snapshot';

const store: UseStore = createStore('argent', 'pwa');

/**
 * Lecture best-effort : IndexedDB est indisponible en navigation privée sur certains navigateurs,
 * et un quota dépassé fait rejeter la transaction. Aucun de ces cas ne doit empêcher la page de
 * s'afficher — elle retombe alors sur le comportement d'avant l'instantané.
 */
export async function readSnapshot(): Promise<Snapshot | null> {
    try {
        return (await get<Snapshot>(SNAPSHOT_KEY, store)) ?? null;
    } catch {
        return null;
    }
}

/** Écriture best-effort, pour les mêmes raisons que la lecture. */
export async function writeSnapshot(snapshot: Snapshot): Promise<void> {
    try {
        await set(SNAPSHOT_KEY, snapshot, store);
    } catch {
        /* Best-effort : voir le commentaire de `readSnapshot`. */
    }
}
```

- [ ] **Étape 6 : vérifier**

```bash
bun run test:js -- snapshotStorage && bun run typecheck
```
Attendu : PASSE.

- [ ] **Étape 7 : commit**

```bash
git add package.json bun.lock resources/js/lib
git commit -m "feat: persiste l'instantané hors-ligne dans IndexedDB"
```

---

### Task 10 : le store d'instantané

**Fichiers :**
- Créer : `resources/js/stores/snapshot.ts`, `resources/js/stores/snapshot.test.ts`

**Interfaces :**
- Consomme : `readSnapshot`, `writeSnapshot` (Task 9) ; type `Snapshot` (Task 9).
- Produit : `useSnapshotStore()` exposant l'état `snapshot`, `syncing` ; les getters `generatedAt`,
  `dashboard`, `instrumentsList`, `cryptoList`, `propertiesList`, `instrumentPage(id)`,
  `cryptoPage(id)`, `propertyPage(id)` ; les actions `hydrate()`, `sync()`.

- [ ] **Étape 1 : écrire le test qui échoue**

`resources/js/stores/snapshot.test.ts` :

```ts
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Snapshot } from '@/lib/snapshotContract';

const { stored, writes } = vi.hoisted(() => ({
    stored: { value: null as Snapshot | null },
    writes: [] as Snapshot[],
}));

vi.mock('@/lib/snapshotStorage', () => ({
    readSnapshot: async () => stored.value,
    writeSnapshot: async (snapshot: Snapshot) => { writes.push(snapshot); },
}));

const { useSnapshotStore } = await import('@/stores/snapshot');

const build = (version: string): Snapshot => ({
    version,
    generatedAt: 1_700_000_000,
    dashboard: { overview: {}, series: {}, income: {} },
    instruments: { list: {}, byId: { '7': { instrument: { name: 'Air Liquide' } } } },
    crypto: { list: {}, byId: {} },
    properties: { list: {}, byId: { '3': { property: { name: 'T2 Lyon 7e' } } } },
} as unknown as Snapshot);

beforeEach((): void => {
    setActivePinia(createPinia());
    stored.value = null;
    writes.length = 0;
    vi.unstubAllGlobals();
});

describe('hydratation', () => {
    it('charge ce qu\'IndexedDB avait retenu', async () => {
        stored.value = build('abc');

        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.snapshot?.version).toBe('abc');
    });

    it('reste vide quand rien n\'a été retenu', async () => {
        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.snapshot).toBeNull();
    });
});

describe('resynchronisation', () => {
    it('remplace l\'instantané et l\'écrit quand la version change', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify(build('def')), {
            headers: { 'Content-Type': 'application/json' },
        })));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(store.snapshot?.version).toBe('def');
        expect(writes).toHaveLength(1);
    });

    it('n\'écrit rien quand la version est inchangée', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify(build('abc')))));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(writes).toHaveLength(0);
    });

    it('garde silencieusement ce qu\'il avait quand le réseau échoue', async () => {
        stored.value = build('abc');
        vi.stubGlobal('fetch', vi.fn(async () => { throw new TypeError('offline'); }));

        const store = useSnapshotStore();
        await store.hydrate();
        await store.sync();

        expect(store.snapshot?.version).toBe('abc');
        expect(store.syncing).toBe(false);
    });
});

describe('sélecteurs par entité', () => {
    it('retrouve une fiche jamais visitée par son identifiant', async () => {
        stored.value = build('abc');

        const store = useSnapshotStore();
        await store.hydrate();

        expect(store.instrumentPage('7')?.instrument.name).toBe('Air Liquide');
        expect(store.propertyPage('3')?.property.name).toBe('T2 Lyon 7e');
        expect(store.instrumentPage('999')).toBeNull();
    });
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- stores/snapshot
```
Attendu : ÉCHEC, `Cannot find module '@/stores/snapshot'`.

- [ ] **Étape 3 : implémenter**

`resources/js/stores/snapshot.ts` :

```ts
import { defineStore } from 'pinia';
import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { readSnapshot, writeSnapshot } from '@/lib/snapshotStorage';
import type {
    CryptoListSnapshot,
    DashboardSnapshot,
    InstrumentPageSnapshot,
    InstrumentsListSnapshot,
    PropertiesListSnapshot,
    PropertyPageSnapshot,
    Snapshot,
} from '@/lib/snapshotContract';

/** Le worker laisse passer cette URL : le store absorbe lui-même ses échecs (cf. `classifyRequest`). */
const SNAPSHOT_URL = '/instantane';

export const useSnapshotStore = defineStore('snapshot', () => {
    const snapshot: Ref<Snapshot | null> = ref(null);
    const syncing: Ref<boolean> = ref(false);

    const generatedAt: ComputedRef<number | null> = computed(
        (): number | null => snapshot.value?.generatedAt ?? null,
    );

    const dashboard: ComputedRef<DashboardSnapshot | null> = computed(
        (): DashboardSnapshot | null => snapshot.value?.dashboard ?? null,
    );

    const instrumentsList: ComputedRef<InstrumentsListSnapshot | null> = computed(
        (): InstrumentsListSnapshot | null => snapshot.value?.instruments.list ?? null,
    );

    const cryptoList: ComputedRef<CryptoListSnapshot | null> = computed(
        (): CryptoListSnapshot | null => snapshot.value?.crypto.list ?? null,
    );

    const propertiesList: ComputedRef<PropertiesListSnapshot | null> = computed(
        (): PropertiesListSnapshot | null => snapshot.value?.properties.list ?? null,
    );

    /**
     * L'indexation par entité se fait en mémoire : le blob entier est déjà chargé, une requête
     * IndexedDB par page n'apporterait qu'une latence.
     */
    function instrumentPage(id: string): InstrumentPageSnapshot | null {
        return snapshot.value?.instruments.byId[id] ?? null;
    }

    function cryptoPage(id: string): InstrumentPageSnapshot | null {
        return snapshot.value?.crypto.byId[id] ?? null;
    }

    function propertyPage(id: string): PropertyPageSnapshot | null {
        return snapshot.value?.properties.byId[id] ?? null;
    }

    /** Lecture du blob retenu. Asynchrone, donc jamais dans le chemin du premier rendu. */
    async function hydrate(): Promise<void> {
        if (snapshot.value !== null) {
            return;
        }

        snapshot.value = await readSnapshot();
    }

    /**
     * Resynchronisation de fond. Tout échec est silencieux : le lecteur garde ce qu'il avait, et
     * aucun bandeau ne bascule — cette requête ne décrit pas la page qu'il regarde.
     */
    async function sync(): Promise<void> {
        if (syncing.value) {
            return;
        }

        syncing.value = true;

        try {
            const response = await fetch(SNAPSHOT_URL, { headers: { Accept: 'application/json' } });

            if (!response.ok) {
                return;
            }

            const fresh = (await response.json()) as Snapshot;

            /** Contenu identique : ni remplacement en mémoire, ni écriture IndexedDB. */
            if (fresh.version === snapshot.value?.version) {
                return;
            }

            snapshot.value = fresh;
            await writeSnapshot(fresh);
        } catch {
            /* Silencieux : voir le commentaire de la fonction. */
        } finally {
            syncing.value = false;
        }
    }

    return {
        snapshot,
        syncing,
        generatedAt,
        dashboard,
        instrumentsList,
        cryptoList,
        propertiesList,
        instrumentPage,
        cryptoPage,
        propertyPage,
        hydrate,
        sync,
    };
});
```

- [ ] **Étape 4 : vérifier**

```bash
bun run test:js -- stores/snapshot && bun run typecheck
```
Attendu : PASSE.

- [ ] **Étape 5 : commit**

```bash
git add resources/js/stores
git commit -m "feat: retient l'instantané hors-ligne dans un store"
```

---

### Task 11 : amorçage du store

**Fichiers :**
- Modifier : `resources/js/app.ts`

**Interfaces :**
- Consomme : `useSnapshotStore` (Task 10), `pinia` (Task 1).

- [ ] **Étape 1 : câbler**

Dans `resources/js/app.ts`, après `useThemeStore().apply();` :

```ts
import { useSnapshotStore } from '@/stores/snapshot';
```

```ts
/**
 * Hydratation puis resynchronisation, dans cet ordre : le blob retenu doit être en mémoire avant
 * que la réponse réseau ne le remplace, sinon une resynchronisation rapide écrirait par-dessus
 * rien et le premier rendu n'aurait aucune valeur d'avance.
 */
const snapshot = useSnapshotStore();

void snapshot.hydrate().then((): Promise<void> => snapshot.sync());

/**
 * Même déclencheur que les contrôles de mise à jour du worker, et pour la même raison : Inertia ne
 * fait aucune vraie navigation, donc rien ne rafraîchit l'instantané de lui-même. `inertia:navigate`
 * est volontairement écarté — une resynchronisation complète à chaque clic coûterait bien plus
 * qu'elle ne rapporte.
 */
document.addEventListener('visibilitychange', (): void => {
    if (document.visibilityState === 'visible') {
        void snapshot.sync();
    }
});
```

- [ ] **Étape 2 : vérifier à la main**

```bash
bun run build
```
Ouvrir `https://argent.test`, puis dans la console du navigateur :

```js
await (await fetch('/instantane')).json()
```
Attendu : la charge utile complète. Puis, dans l'onglet Application → IndexedDB → `argent` → `pwa` :
une clé `snapshot`.

- [ ] **Étape 3 : commit**

```bash
git add resources/js/app.ts
git commit -m "feat: amorce et resynchronise l'instantané au chargement"
```

---

### Task 12 : les pages rendent en avance de phase

**Fichiers :**
- Modifier : `resources/js/Pages/Dashboard.vue`, `resources/js/Pages/Instruments/Index.vue`,
  `resources/js/Pages/Instruments/Show.vue`, `resources/js/Pages/Crypto/Index.vue`,
  `resources/js/Pages/Crypto/Show.vue`, `resources/js/Pages/Properties/Index.vue`,
  `resources/js/Pages/Properties/Detail.vue`, et les composants de section qui portent un
  `<Deferred>` sur une prop couverte par l'instantané
- Créer : `resources/js/lib/aheadOfNetwork.ts`, `resources/js/lib/aheadOfNetwork.test.ts`

**Interfaces :**
- Consomme : `useSnapshotStore` (Task 10).
- Produit : `aheadOfNetwork<T>(prop: () => T | undefined, stored: () => T | null | undefined): ComputedRef<T | null>`.

- [ ] **Étape 1 : écrire le test de la fusion**

`resources/js/lib/aheadOfNetwork.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';

describe('fusion prop / instantané', () => {
    it('préfère la prop Inertia dès qu\'elle est là', () => {
        const merged = aheadOfNetwork(() => 'réseau', () => 'store');

        expect(merged.value).toBe('réseau');
    });

    it('retombe sur l\'instantané tant que la prop manque', () => {
        const merged = aheadOfNetwork(() => undefined, () => 'store');

        expect(merged.value).toBe('store');
    });

    it('rend null quand ni l\'un ni l\'autre n\'existe', () => {
        const merged = aheadOfNetwork(() => undefined, () => null);

        expect(merged.value).toBeNull();
    });

    it('bascule sur la prop dès son arrivée', () => {
        const prop = ref<string | undefined>(undefined);
        const merged = aheadOfNetwork(() => prop.value, () => 'store');

        expect(merged.value).toBe('store');

        prop.value = 'réseau';

        expect(merged.value).toBe('réseau');
    });
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- aheadOfNetwork
```
Attendu : ÉCHEC, module introuvable.

- [ ] **Étape 3 : implémenter la fusion**

`resources/js/lib/aheadOfNetwork.ts` :

```ts
import { computed, type ComputedRef } from 'vue';

/**
 * Rend la dernière valeur connue en attendant celle du réseau. La prop Inertia gagne dès qu'elle
 * arrive — l'instantané ne comble que l'intervalle, y compris l'intervalle infini d'une page
 * ouverte hors-ligne sans jamais avoir été visitée.
 *
 * `null` signifie « ni l'un ni l'autre » : à la section d'afficher son squelette, puis son message
 * d'indisponibilité.
 */
export function aheadOfNetwork<T>(
    prop: () => T | undefined,
    stored: () => T | null | undefined,
): ComputedRef<T | null> {
    return computed((): T | null => prop() ?? stored() ?? null);
}
```

- [ ] **Étape 4 : câbler le tableau de bord**

`resources/js/Pages/Dashboard.vue` :

```ts
import { aheadOfNetwork } from '@/lib/aheadOfNetwork';
import { useSnapshotStore } from '@/stores/snapshot';

const props = defineProps<{
    overview: WealthOverview;
    series?: WealthSeries;
    income?: WealthIncome;
}>();

const snapshot = useSnapshotStore();

/** `overview` est synchrone côté serveur : elle est toujours là, rien à combler. */
const series = aheadOfNetwork(() => props.series, () => snapshot.dashboard?.series);
const income = aheadOfNetwork(() => props.income, () => snapshot.dashboard?.income);
```

```html
        <WealthEvolutionSection :series="series" />

        <WealthIncomeSection :income="income" />
```

- [ ] **Étape 5 : retirer `<Deferred>` des sections couvertes**

`resources/js/components/dashboard/WealthEvolutionSection.vue` : la prop devient
`defineProps<{ series?: WealthSeries | null }>()`, l'import de `Deferred` disparaît, et le template
remplace le bloc `<Deferred>` par :

```html
        <div v-if="props.series === null || props.series === undefined" class="px-6">
            <ChartSkeleton />
        </div>
        <div v-else-if="hasHistory" class="px-6">
            <BaseChart :option="option" @zoom="rememberZoom" />
        </div>
        <p v-else class="py-8 text-center text-sm text-muted-foreground">
            Pas encore d'historique de valorisation.
        </p>
```

Le slot `#rescue` disparaît **seulement** sur les sections que l'instantané couvre : hors-ligne, la
valeur vient désormais du store et le squelette ne tourne plus indéfiniment. Appliquer le même
traitement à `WealthIncomeSection` et aux sections équivalentes des autres pages.

- [ ] **Étape 6 : câbler les cinq autres pages**

Même schéma, une ligne `aheadOfNetwork` par prop optionnelle :

| Page | Props fusionnées | Source dans le store |
| --- | --- | --- |
| `Instruments/Index.vue` | `trends`, `performances`, `evolutionSeries`, `sectorBreakdown`, `income`, `annualIncome` | `snapshot.instrumentsList?.<prop>` |
| `Crypto/Index.vue` | `trends`, `performances`, `evolutionSeries` | `snapshot.cryptoList?.<prop>` |
| `Instruments/Show.vue` | `priceHistory`, `valuation` | `snapshot.instrumentPage(String(props.instrument.id))?.<prop>` |
| `Crypto/Show.vue` | `priceHistory`, `valuation` | `snapshot.cryptoPage(String(props.instrument.id))?.<prop>` |
| `Properties/Index.vue` | `series`, `profitability`, `income` | `snapshot.propertiesList?.<prop>` |
| `Properties/Detail.vue` | `amortization` | `snapshot.propertyPage(String(props.property.id))?.amortization` |

Les props **non** optionnelles (`overview`, `realEstate`, `instrument`, `property`, `dividends`,
`performances` des fiches) sont servies de façon synchrone par le serveur et présentes dans le
document initial : elles n'ont rien à combler.

- [ ] **Étape 7 : vérifier**

```bash
bun run test:js && bun run typecheck && bun run build
php artisan test --compact --filter=Browser
```
Attendu : tout passe. Les tests navigateur existants couvrent le rendu de chaque section : une
régression du câblage s'y voit.

- [ ] **Étape 8 : commit**

```bash
git add resources/js
git commit -m "feat: affiche la dernière valeur connue en attendant le réseau"
```

---

### Task 13 : date du bandeau, et preuve hors-ligne

**Fichiers :**
- Modifier : `resources/js/components/AppServiceWorkerBanner.vue`, `resources/js/lib/format.ts`
  (si `syncedAtLabel` doit accepter la nouvelle entrée), `tests/pwa-offline.mjs`
- Créer : `resources/js/lib/syncedAt.ts`, `resources/js/lib/syncedAt.test.ts`

**Interfaces :**
- Produit : `displayedSyncedAt(lastSyncedAt: number | null, generatedAt: number | null, stale: boolean): number | null`,
  en millisecondes.

- [ ] **Étape 1 : écrire le test qui échoue**

`resources/js/lib/syncedAt.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { displayedSyncedAt } from '@/lib/syncedAt';

/** `generatedAt` est en secondes côté serveur, `lastSyncedAt` en millisecondes côté client. */
const SECONDS = 1_700_000_000;
const MS = SECONDS * 1000;

describe('date affichée par le bandeau', () => {
    it('suit le worker tant que rien n\'est périmé', () => {
        expect(displayedSyncedAt(MS, SECONDS - 86_400, false)).toBe(MS);
    });

    it('retient la plus ancienne des deux quand l\'écran est périmé', () => {
        expect(displayedSyncedAt(MS, SECONDS - 86_400, true)).toBe(MS - 86_400_000);
    });

    it('retient le worker quand l\'instantané est plus récent', () => {
        expect(displayedSyncedAt(MS - 86_400_000, SECONDS, true)).toBe(MS - 86_400_000);
    });

    it('se contente de ce qu\'il a quand l\'autre manque', () => {
        expect(displayedSyncedAt(null, SECONDS, true)).toBe(MS);
        expect(displayedSyncedAt(MS, null, true)).toBe(MS);
        expect(displayedSyncedAt(null, null, true)).toBeNull();
    });
});
```

- [ ] **Étape 2 : vérifier l'échec**

```bash
bun run test:js -- syncedAt
```
Attendu : ÉCHEC, module introuvable.

- [ ] **Étape 3 : implémenter**

`resources/js/lib/syncedAt.ts` :

```ts
/**
 * Date que le bandeau annonce. Quand l'écran est périmé, il mélange deux âges : les props servies
 * par le cache du worker, et les valeurs venues de l'instantané. Annoncer la plus récente des deux
 * ferait lire « synchronisé il y a 2 minutes » devant des chiffres vieux d'une journée de marché —
 * on annonce donc la plus ancienne, la seule qui ne mente sur rien.
 *
 * `generatedAt` arrive du serveur en secondes, `lastSyncedAt` est un `Date.now()` en millisecondes.
 */
export function displayedSyncedAt(
    lastSyncedAt: number | null,
    generatedAt: number | null,
    stale: boolean,
): number | null {
    const snapshotAt = generatedAt === null ? null : generatedAt * 1000;

    if (!stale) {
        return lastSyncedAt;
    }

    if (lastSyncedAt === null) {
        return snapshotAt;
    }

    if (snapshotAt === null) {
        return lastSyncedAt;
    }

    return Math.min(lastSyncedAt, snapshotAt);
}
```

- [ ] **Étape 4 : câbler le bandeau**

`resources/js/components/AppServiceWorkerBanner.vue` :

```ts
import { displayedSyncedAt } from '@/lib/syncedAt';
import { useSnapshotStore } from '@/stores/snapshot';

const snapshot = useSnapshotStore();

const syncedLabel = computed<string>(() => syncedAtLabel(
    displayedSyncedAt(sw.lastSyncedAt, snapshot.generatedAt, sw.stale),
));
```

- [ ] **Étape 5 : étendre la preuve hors-ligne**

Dans `tests/pwa-offline.mjs`, **avant** `context.setOffline(true)`, relever un identifiant depuis
l'instantané, puis, après la coupure, ouvrir cette fiche jamais visitée :

```js
    /** L'instantané doit être en place avant la coupure : c'est lui qui rend lisible l'inconnu. */
    const snapshot = await page.evaluate(() => fetch('/instantane').then((response) => response.json()));
    const instrumentId = Object.keys(snapshot.instruments.byId)[0];
    assert.ok(instrumentId, "L'instantané doit porter au moins une fiche instrument.");

    await page.waitForFunction(
        () => indexedDB.databases().then((bases) => bases.some((base) => base.name === 'argent')),
        undefined,
        { timeout: TIMEOUT },
    );
```

puis, après le bloc hors-ligne existant :

```js
    /**
     * Le cœur du sujet : cette fiche n'a jamais été ouverte, donc le cache du worker n'en a aucune
     * réponse Inertia. Seul l'instantané peut la rendre.
     */
    await page.goto(`${BASE_URL}/instruments/${instrumentId}`, {
        waitUntil: 'domcontentloaded',
        timeout: TIMEOUT,
    });

    const detail = await page.textContent('body');
    assert.ok(
        !detail.includes('Pas de connexion'),
        'Une fiche jamais visitée doit rester lisible hors-ligne grâce à l\'instantané.',
    );
    assert.ok(
        detail.includes('Valorisation'),
        'La section valorisation doit rendre depuis l\'instantané, pas un squelette.',
    );
```

Adapter les deux libellés attendus à ce que la fiche affiche réellement — les relever dans
`resources/js/components/instrument/ValuationSection.vue`.

- [ ] **Étape 6 : vérifier**

```bash
bun run test:js && bun run typecheck && bun run build
node tests/pwa-offline.mjs
```
Attendu : les deux passent. Le script exige un build réel et l'absence de `public/hot` — il le
signale lui-même sinon.

- [ ] **Étape 7 : commit**

```bash
git add resources/js tests/pwa-offline.mjs
git commit -m "feat: date le bandeau sur la plus ancienne des deux sources"
```

---

## Auto-revue

**Couverture de la spec**

| Exigence de la spec | Tâche |
| --- | --- |
| Route `GET /instantane`, nom `pwa.snapshot` | 8 |
| Une action d'instantané par contexte | 5, 6, 7 |
| `SnapshotController` n'agrège que les trois actions | 8 |
| `version` = empreinte du contenu, `generatedAt` exclu | 8 |
| Plage de valorisation par défaut seulement | 6 |
| `auth()->user() ?? User::query()->first()` | 8 |
| `/instantane` classé `passthrough` | 4 |
| Mesure du coût de calcul | 8, étape 6 |
| Pinia partout, instance unique sur deux racines | 1, 2, 3 |
| `setActivePinia(createPinia())` remplace `resetXxxState()` | 3 |
| `stores/` + `lib/snapshotStorage.ts` + `lib/snapshotContract.ts` | 9, 10 |
| Getters par entité, indexation en mémoire | 10 |
| `hydrate()` puis `sync()`, plus `visibilitychange` | 11 |
| Pas de resynchronisation sur `inertia:navigate` | 11 |
| Écriture sautée à `version` identique | 10 |
| Échec réseau silencieux | 10 |
| Fusion prop / store, `Deferred` retiré des sections couvertes | 12 |
| Date du bandeau la plus pertinente | 13 |
| Playwright : fiche jamais visitée hors-ligne | 13 |

**Écarts assumés**

- La spec parle de « `generatedAt` quand ce qui est à l'écran vient du store ». Suivre la
  provenance section par section demanderait un mécanisme de traçage pour un bandeau unique ; la
  tâche 13 retient la règle équivalente et testable de la plus ancienne des deux dates, qui ne
  surestime jamais la fraîcheur.
- La spec mentionne un cache serveur possible sur l'instantané. Il n'est pas planifié : la tâche 8
  mesure d'abord, et n'optimise que si la mesure le justifie.
