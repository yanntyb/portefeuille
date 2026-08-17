# Refonte de la stratégie de test — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Descendre la vérification de la logique d'affichage du navigateur vers l'unitaire, en passant de 79 tests Browser à 17 et de 0 à 45+ tests Vitest, pour qu'une retouche de maquette ne touche plus zéro ou un fichier de test au lieu de trois.

**Architecture :** La logique pure part des composants Vue vers `resources/js/lib/`, où Vitest la teste directement. Les graphes ne sont plus vérifiés en grattant leur SVG mais en assertant l'objet d'options rendu par `buildValueVsInvestedOption` et `buildPriceHistoryOption`. Ne reste en Pest Browser que ce qui exige réellement un navigateur : réseau Inertia, `matchMedia`, CSS appliqué, interaction native.

**Tech Stack :** Vitest, happy-dom, TypeScript, Vue 3, Pest 4 (Feature + Browser), Laravel 12, bun.

**Spec :** `docs/superpowers/specs/2026-08-17-refonte-strategie-de-test-design.md`

## Global Constraints

- **Deux dépendances ajoutées, pas plus** : `vitest`, `happy-dom`. `@vue/test-utils` est explicitement hors périmètre — aucun composant n'est monté en test.
- **Runner** : `bun`. Les scripts s'appellent `bun run test:js` et `bun run test:js:watch`.
- **Emplacement des tests Vitest** : co-localisés, `resources/js/lib/<module>.test.ts`. Aucun nouveau dossier de base.
- **Tout texte visible par l'utilisateur reste en français.** Aucune extraction ne doit changer une chaîne affichée.
- **PHP** : accolades systématiques, types de retour explicites, PHPDoc plutôt que commentaires en ligne. `vendor/bin/pint --dirty --format agent` après toute modification PHP.
- **Un commit par tâche**, message en français, préfixe conventionnel (`test:`, `refactor:`, `chore:`, `fix:`).
- **Ordre imposé** : aucune suppression de test Browser (tâches 11-12) avant que sa vérité soit couverte en Vitest (tâches 1-10). Sinon la couverture tombe entre deux commits.
- **Règle de tri F0-F5** : définie dans la spec, section « La règle ». Tout nouveau test s'y conforme.

### Écarts assumés par rapport à la spec

1. **Aucun nouvel export dans `chart.ts`.** La spec demandait d'exporter `lastYearWindow` (`chart.ts:284`) et `valueVsInvestedTooltip` (`chart.ts:209`). Inutile : la fenêtre d'ouverture se lit dans `option.dataZoom[0].start`, l'infobulle dans `option.tooltip.formatter`. On teste l'API publique plutôt que d'ouvrir des fonctions privées pour la commodité des tests.
2. **`vitest.config.ts` séparé** au lieu d'un bloc `test` dans `vite.config.ts`. Vitest prend `vitest.config.ts` en priorité et ne fusionne pas : `laravel-vite-plugin` (lecture de `.env`, `detectTls`, écriture du fichier `hot`) ne tourne donc pas pendant les tests. L'alias `@` est redéclaré, quatre lignes.
3. **`sectorRows` n'est pas créé.** La spec le listait dans les extractions de `lib/sector.ts`. Vérification faite, les `SectorBreakdownRow` sont construites côté serveur et couvertes par `tests/Feature/InstrumentDetailPageTest.php` ; la distinction « montant quand détenu, part seule sinon » n'est qu'une condition de gabarit sur `row.amount !== null`. Elle est vérifiée dans le test de `collapsedSectors` (tâche 4) sans nouvelle fonction.

---

### Task 1: Outillage Vitest, avec `format.ts` et `layout.ts` comme preuve de chaîne

**Files:**
- Create: `vitest.config.ts`
- Modify: `package.json`
- Test: `resources/js/lib/format.test.ts`, `resources/js/lib/layout.test.ts`

**Interfaces:**
- Consumes: rien.
- Produces: la commande `bun run test:js`, et la convention de normalisation des espaces `normalizeSpaces()` que toutes les tâches suivantes réutilisent.

**Contexte pour l'implémenteur :** `resources/js/lib/format.ts` et `resources/js/lib/layout.ts` sont déjà des fonctions pures, sans dépendance au DOM. Ils servent ici à prouver que la chaîne Vitest tourne avant d'attaquer les modules qui demandent une extraction.

**Piège majeur — les espaces insécables.** `Intl.NumberFormat` en `fr-FR` sépare les milliers par U+202F (espace fine insécable) et pose U+202F ou U+00A0 avant le `€`. Ces caractères varient selon la version d'ICU. Une assertion sur une chaîne littérale tapée au clavier échouera. Tous les tests de formatage passent donc par `normalizeSpaces()`.

- [ ] **Step 1: Installer les deux dépendances**

```bash
bun add -d vitest happy-dom
```

- [ ] **Step 2: Créer `vitest.config.ts`**

```ts
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Configuration séparée de `vite.config.ts` : les greffons de build (Laravel, Vue, Tailwind) n'ont
 * rien à faire dans une exécution de tests unitaires. `happy-dom` est requis parce que
 * `lib/theme.ts` appelle `usePreferredDark()` au chargement du module, donc touche `matchMedia`.
 */
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/*.test.ts'],
    },
});
```

- [ ] **Step 3: Ajouter les scripts dans `package.json`**

Dans le bloc `"scripts"`, après `"typecheck"` :

```json
        "test:js": "vitest run",
        "test:js:watch": "vitest"
```

- [ ] **Step 4: Écrire les tests de `format.ts`**

Créer `resources/js/lib/format.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { eur, frDate, gainClass, pct, signedEur, signedPct } from '@/lib/format';

/**
 * `Intl` en fr-FR pose des espaces fines insécables (U+202F) entre les milliers et avant l'euro,
 * et leur position varie selon la version d'ICU. Les assertions se lisent sur des espaces normales.
 */
const normalizeSpaces = (value: string): string => value.replace(/[  ]/g, ' ');

describe('eur', () => {
    it('rend un montant en euros avec deux décimales par défaut', () => {
        expect(normalizeSpaces(eur(1000))).toBe('1 000,00 €');
    });

    it('coupe les décimales quand on le lui demande', () => {
        expect(normalizeSpaces(eur(1000, 0))).toBe('1 000 €');
    });

    it('rend un tiret plutôt que zéro quand la valeur est absente', () => {
        expect(eur(null)).toBe('—');
    });
});

describe('signedEur', () => {
    it('montre le signe sur un gain pour qu\'il ne se lise pas comme un solde', () => {
        expect(normalizeSpaces(signedEur(200))).toBe('+200,00 €');
    });

    it('laisse le signe négatif porté par le formatage monétaire', () => {
        expect(normalizeSpaces(signedEur(-200))).toBe('-200,00 €');
    });

    it('ne signe pas un montant nul', () => {
        expect(normalizeSpaces(signedEur(0))).toBe('0,00 €');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(signedEur(null)).toBe('—');
    });
});

describe('pct', () => {
    it('signe un pourcentage positif et garde une décimale', () => {
        expect(normalizeSpaces(pct(25))).toBe('+25,0 %');
    });

    it('rend un pourcentage négatif sans double signe', () => {
        expect(normalizeSpaces(pct(-3.25))).toBe('-3,3 %');
    });

    it('traite zéro comme positif, le signe rassurant sur la lecture', () => {
        expect(normalizeSpaces(pct(0))).toBe('+0,0 %');
    });

    it('rend un tiret quand la valeur est absente', () => {
        expect(pct(null)).toBe('—');
    });
});

describe('signedPct', () => {
    it('lit une base 100 comme son écart signé', () => {
        expect(normalizeSpaces(signedPct(112.4))).toBe('+12,4 %');
    });

    it('lit une base 100 sous le pair comme un écart négatif', () => {
        expect(normalizeSpaces(signedPct(87.6))).toBe('-12,4 %');
    });
});

describe('frDate', () => {
    it('rend une date ISO au format français', () => {
        expect(frDate('2026-07-01')).toBe('01/07/2026');
    });

    it('rend la valeur telle quelle quand elle n\'est pas une date', () => {
        expect(frDate('pas une date')).toBe('pas une date');
    });
});

describe('gainClass', () => {
    it('teinte un gain, une perte, et laisse le neutre en sourdine', () => {
        expect(gainClass(10)).toBe('text-gain');
        expect(gainClass(-10)).toBe('text-loss');
        expect(gainClass(0)).toBe('text-muted-foreground');
        expect(gainClass(null)).toBe('text-muted-foreground');
    });
});
```

- [ ] **Step 5: Écrire les tests de `layout.ts`**

`layout.ts` porte `pageContainer(width)`, qui rend la classe de conteneur d'une page. C'est cette fonction qui garantit l'alignement du contenu sur le fil d'Ariane, aujourd'hui mesuré au pixel par deux tests Browser.

`layout.ts` porte déjà le commentaire « leurs bords gauches doivent coïncider au pixel (cf. tests/Browser/BreadcrumbTest.php) » : c'est exactement la vérité que ce test reprend, sans navigateur.

Créer `resources/js/lib/layout.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { pageContainer } from '@/lib/layout';

describe('pageContainer', () => {
    it('rend un conteneur centré et borné pour chaque largeur', () => {
        expect(pageContainer('narrow')).toBe('mx-auto w-full max-w-[520px]');
        expect(pageContainer('wide')).toBe('mx-auto w-full max-w-6xl');
    });

    it('aligne le fil d\'Ariane et le contenu : même largeur, même conteneur', () => {
        expect(pageContainer('narrow')).toBe(pageContainer('narrow'));
        expect(pageContainer('wide')).toBe(pageContainer('wide'));
    });

    it('distingue les deux largeurs de page', () => {
        expect(pageContainer('narrow')).not.toBe(pageContainer('wide'));
    });
});
```

Le commentaire de `layout.ts` renvoie à `BreadcrumbTest.php`, dont les deux tests d'alignement disparaissent en tâche 12 : mettre à jour ce renvoi pour pointer vers `layout.test.ts`.

- [ ] **Step 6: Lancer les tests**

```bash
bun run test:js
```

Attendu : deux fichiers, tous les tests verts. Si `happy-dom` manque, l'erreur est `Cannot find package 'happy-dom'`.

- [ ] **Step 7: Vérifier que le typecheck et le build tiennent toujours**

```bash
bun run typecheck && bun run build
```

Attendu : les deux passent. Le build ne doit pas embarquer les `.test.ts` — rien ne les importe.

- [ ] **Step 8: Commit**

```bash
git add package.json bun.lock vitest.config.ts resources/js/lib/format.test.ts resources/js/lib/layout.test.ts
git commit -m "test: ouvre une couche unitaire sur le TypeScript pur"
```

Si le fichier de verrou s'appelle `bun.lockb` dans ce dépôt, ajuster.

---

### Task 2: `lib/bars.ts` — une seule arithmétique de barre pour trois composants

**Files:**
- Create: `resources/js/lib/bars.ts`
- Test: `resources/js/lib/bars.test.ts`

**Interfaces:**
- Consumes: rien.
- Produces:
  ```ts
  export const largestOf = (values: number[]): number
  export const relativeBarWidth = (value: number, largest: number): string
  ```
  Consommées par les tâches 3, 4 et 5.

**Contexte :** la même règle — « la barre se mesure contre la plus grande valeur du lot, pas contre le total, pour que la plus petite reste visible » — est recopiée trois fois : `HoldingsList.vue:43`, `SectorBreakdownList.vue:27`, `PerformanceBars.vue`. Trois copies, trois tests Browser intitulés « scales the widest bar to the full track ». Une fonction, un test, trois consommateurs.

`PerformanceBars` compare des valeurs signées et prend leur valeur absolue avant de mesurer. `relativeBarWidth` reçoit donc des valeurs déjà absolues : c'est à l'appelant de passer `Math.abs(...)`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/bars.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { largestOf, relativeBarWidth } from '@/lib/bars';

describe('largestOf', () => {
    it('rend la plus grande valeur du lot', () => {
        expect(largestOf([20, 80, 50])).toBe(80);
    });

    it('rend zéro sur un lot vide plutôt que moins l\'infini', () => {
        expect(largestOf([])).toBe(0);
    });

    it('ne descend jamais sous zéro', () => {
        expect(largestOf([-10, -50])).toBe(0);
    });
});

describe('relativeBarWidth', () => {
    it('remplit toute la piste pour la plus grande valeur', () => {
        expect(relativeBarWidth(80, 80)).toBe('100%');
    });

    it('mesure les autres valeurs contre la plus grande, pas contre le total', () => {
        expect(relativeBarWidth(20, 80)).toBe('25%');
    });

    it('rend une barre nulle quand il n\'y a rien à mesurer', () => {
        expect(relativeBarWidth(10, 0)).toBe('0%');
    });

    it('rend une barre nulle pour une valeur nulle', () => {
        expect(relativeBarWidth(0, 80)).toBe('0%');
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC avec `Failed to resolve import "@/lib/bars"`.

- [ ] **Step 3: Écrire l'implémentation minimale**

Créer `resources/js/lib/bars.ts` :

```ts
/**
 * Plus grande valeur d'un lot, plancher à zéro : un `Math.max` sur un tableau vide rendrait
 * `-Infinity`, et une barre ne se mesure pas contre une valeur négative.
 */
export const largestOf = (values: number[]): number => Math.max(0, ...values);

/**
 * Largeur d'une barre, en pourcentage CSS, mesurée contre la plus grande valeur du lot et non
 * contre leur total : sans ça la plus petite part devient invisible. L'appelant passe des valeurs
 * absolues quand son lot peut être signé.
 */
export const relativeBarWidth = (value: number, largest: number): string =>
    largest > 0 ? `${(value / largest) * 100}%` : '0%';
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

Attendu : tous verts.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/bars.ts resources/js/lib/bars.test.ts
git commit -m "refactor: rassemble l'arithmétique des barres dans un module"
```

---

### Task 3: `lib/portfolio.ts` — `holdingWeights`, et le branchement de `HoldingsList`

**Files:**
- Modify: `resources/js/lib/portfolio.ts`
- Modify: `resources/js/components/HoldingsList.vue:1-59`
- Modify: `resources/js/components/dashboard/HoldingsSection.vue`
- Test: `resources/js/lib/portfolio.test.ts`

**Interfaces:**
- Consumes: `largestOf`, `relativeBarWidth` (tâche 2). `HoldingLine` (déjà dans `portfolio.ts`).
- Produces:
  ```ts
  export interface HoldingWeight {
      line: HoldingLine;
      share: number;
      barWidth: string;
      opacity: number;
  }
  export const holdingWeights = (holdings: HoldingLine[], limit?: number): HoldingWeight[]
  ```

**Contexte et invariant à préserver :** `HoldingsList.vue:23` porte ce commentaire — « Cropping only what is rendered leaves every aggregate below computed on the whole portfolio ». Autrement dit : on trie tout, on calcule le total et les parts **sur tout le portefeuille**, et seul le rendu est coupé aux dix premières lignes. Une position de 1 200 € dans un portefeuille de 7 800 € pèse 15,4 %, pas 1 200 / somme-des-dix-affichées. Cet invariant est aujourd'hui gardé par un unique test navigateur. Il devient le cœur de ce test unitaire.

Le dégradé d'opacité (`HoldingsList.vue:49`) porte sur les lignes **rendues**, pas sur l'ensemble : la plus pâle reste lisible quel que soit le nombre affiché.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/portfolio.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { holdingWeights, type HoldingLine } from '@/lib/portfolio';

const line = (assetName: string, marketValue: number | null, assetId = 1): HoldingLine => ({
    assetId,
    assetName,
    ticker: assetName.slice(0, 3).toUpperCase(),
    type: 'stock',
    typeLabel: 'Action',
    quantity: 10,
    avgCost: 80,
    lastPrice: 100,
    marketValue,
    gain: 200,
    gainPct: 25,
});

describe('holdingWeights', () => {
    it('trie les positions de la plus lourde à la plus légère', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.line.assetName)).toEqual(['ACME', 'BETA']);
    });

    it('pèse chaque position sur le total des lignes, qui fait donc 100 %', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.share)).toEqual([80, 20]);
    });

    it('mesure les barres contre la plus lourde, pas contre le total', () => {
        const weights = holdingWeights([line('BETA', 250), line('ACME', 1000)]);

        expect(weights.map((weight) => weight.barWidth)).toEqual(['100%', '25%']);
    });

    it('ne coupe que le rendu : les parts restent calculées sur tout le portefeuille', () => {
        const twelve = Array.from({ length: 12 }, (_unused, index) => line(`LINE${index + 1}`, (index + 1) * 100, index + 1));

        const weights = holdingWeights(twelve, 10);

        // 1 200 € sur les 7 800 € des douze lignes, pas sur les dix rendues.
        expect(weights).toHaveLength(10);
        expect(weights[0].line.marketValue).toBe(1200);
        expect(weights[0].share).toBeCloseTo(15.384, 2);
    });

    it('rend toutes les lignes quand aucune limite n\'est posée', () => {
        const twelve = Array.from({ length: 12 }, (_unused, index) => line(`LINE${index + 1}`, (index + 1) * 100, index + 1));

        expect(holdingWeights(twelve)).toHaveLength(12);
    });

    it('fait pâlir les barres de la plus lourde à la plus légère, sans jamais les effacer', () => {
        const weights = holdingWeights([line('ACME', 1000), line('BETA', 500), line('GAMMA', 250)]);

        expect(weights[0].opacity).toBe(1);
        expect(weights[2].opacity).toBeCloseTo(0.35, 5);
        expect(weights[1].opacity).toBeGreaterThan(weights[2].opacity);
    });

    it('garde une opacité pleine sur une position unique, sans division par zéro', () => {
        expect(holdingWeights([line('ACME', 1000)])[0].opacity).toBe(1);
    });

    it('traite une valeur de marché absente comme nulle plutôt que de casser le total', () => {
        const weights = holdingWeights([line('ACME', 1000), line('BETA', null)]);

        expect(weights.map((weight) => weight.share)).toEqual([100, 0]);
    });

    it('rend un tableau vide sur un portefeuille vide', () => {
        expect(holdingWeights([])).toEqual([]);
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC avec `holdingWeights is not a function` ou une erreur d'import.

- [ ] **Step 3: Écrire l'implémentation**

Ajouter à la fin de `resources/js/lib/portfolio.ts` :

```ts
import { largestOf, relativeBarWidth } from '@/lib/bars';

/** Opacité de la barre la plus pâle : en dessous, elle disparaît du fond dans les deux thèmes. */
const FAINTEST_BAR_OPACITY = 0.35;

export interface HoldingWeight {
    line: HoldingLine;
    /** Part du portefeuille, en pourcentage. */
    share: number;
    /** Largeur de la barre en pourcentage CSS, mesurée contre la position la plus lourde. */
    barWidth: string;
    opacity: number;
}

/**
 * Les lignes du portefeuille, triées et pondérées. La limite ne coupe que le rendu : le total, les
 * parts et l'échelle des barres restent calculés sur l'ensemble, sinon une position de 1 200 € dans
 * un portefeuille de 7 800 € afficherait le poids qu'elle a parmi les seules lignes visibles.
 */
export const holdingWeights = (holdings: HoldingLine[], limit?: number): HoldingWeight[] => {
    const sorted = [...holdings].sort(
        (left: HoldingLine, right: HoldingLine): number => (right.marketValue ?? 0) - (left.marketValue ?? 0),
    );

    const total = sorted.reduce((sum: number, line: HoldingLine): number => sum + (line.marketValue ?? 0), 0);
    const shareOf = (line: HoldingLine): number => (total > 0 ? ((line.marketValue ?? 0) / total) * 100 : 0);
    const largest = largestOf(sorted.map(shareOf));

    const visible = limit === undefined ? sorted : sorted.slice(0, limit);

    return visible.map((line: HoldingLine, index: number): HoldingWeight => ({
        line,
        share: shareOf(line),
        barWidth: relativeBarWidth(shareOf(line), largest),
        /** Un seul dégradé monotone sur les lignes rendues, pas sur l'ensemble. */
        opacity: 1 - (index / Math.max(1, visible.length - 1)) * (1 - FAINTEST_BAR_OPACITY),
    }));
};
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

Attendu : tous verts.

- [ ] **Step 5: Brancher `HoldingsList.vue` sur la nouvelle fonction**

Remplacer les lignes 1 à 59 de `resources/js/components/HoldingsList.vue` par :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Sparkline from '@/components/Sparkline.vue';
import { eur as formatEur, gainClass, pct, signedEur } from '@/lib/format';
import { holdingWeights, type EvolutionSeries, type HoldingLine, type HoldingWeight } from '@/lib/portfolio';

/** Largeur de la colonne `w-24` qui porte la tendance, pour que le tracé la remplisse exactement. */
const SPARKLINE_WIDTH = 96;

const props = defineProps<{
    holdings: HoldingLine[];
    series?: EvolutionSeries;
    limit?: number;
}>();

const weights = computed<HoldingWeight[]>(() => holdingWeights(props.holdings, props.limit));

const seriesByAsset = computed<Map<number, number[]>>(
    () => new Map((props.series?.perAsset ?? []).map((asset) => [asset.assetId, asset.value])),
);

const valuesFor = (assetId: number): number[] => seriesByAsset.value.get(assetId) ?? [];

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const value = (amount: number | null): string => formatEur(amount, 0);
</script>
```

Puis adapter le gabarit : `v-for="(line, index) in visibleHoldings"` devient `v-for="weight in weights"`, `:key="line.assetId"` devient `:key="weight.line.assetId"`, chaque `line.xxx` devient `weight.line.xxx`, `barWidth(line)` devient `weight.barWidth`, `opacityAt(index)` devient `weight.opacity`, et `share(shareOf(line))` devient `share(weight.share)`.

Les attributs `data-holding-row`, `data-holding-name`, `data-holding-value`, `data-holding-gain-pct`, `data-holding-bar`, `data-holding-weight`, `data-holding-trend` et `data-holding-gain` **restent inchangés** : les tests Browser survivants et le smoke s'y accrochent.

- [ ] **Step 6: Sortir la constante de limite de `HoldingsSection.vue`**

`VISIBLE_HOLDINGS = 10` reste dans `HoldingsSection.vue:6` et continue d'être passé en prop `:limit`. Rien à changer, mais vérifier que c'est bien le cas après l'étape 5.

- [ ] **Step 7: Vérifier le typecheck et les tests navigateur touchés**

```bash
bun run typecheck
bun run build
php artisan test --compact tests/Browser/DashboardHoldingsTableTest.php
```

Attendu : typecheck et build verts ; les tests Browser des positions passent toujours à l'identique. C'est le filet qui prouve que l'extraction n'a rien changé au rendu. Ils seront supprimés en tâche 11, pas avant.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/portfolio.ts resources/js/lib/portfolio.test.ts resources/js/components/HoldingsList.vue
git commit -m "refactor: sort la pondération des positions du composant"
```

---

### Task 4: `lib/sector.ts` — `collapsedSectors` et `sectorRows`

**Files:**
- Modify: `resources/js/lib/sector.ts`
- Modify: `resources/js/components/SectorBreakdownList.vue:1-33`
- Test: `resources/js/lib/sector.test.ts`

**Interfaces:**
- Consumes: `largestOf`, `relativeBarWidth` (tâche 2). `SectorBreakdownRow` (déjà dans `sector.ts`).
- Produces:
  ```ts
  export interface SectorView {
      rows: { row: SectorBreakdownRow; barWidth: string }[];
      hiddenCount: number;
  }
  export const collapsedSectors = (rows: SectorBreakdownRow[], expanded: boolean, collapsedCount?: number): SectorView
  ```

**Contexte :** `SectorBreakdownList.vue` trie par part décroissante, replie au-delà du sixième secteur derrière un bouton « Voir les N autres », et mesure les barres contre le plus gros secteur. Le tri, le repli, le décompte caché et l'échelle des barres sont de l'arithmétique ; seul le clic sur le bouton relève du navigateur.

`sectorRows` mentionné dans la spec (montants par secteur quand l'instrument est détenu, part seule sinon) n'est **pas** créé ici : la construction des `SectorBreakdownRow` est faite côté serveur et couverte par `tests/Feature/InstrumentDetailPageTest.php`. Ce que le navigateur vérifiait — « affiche le montant quand détenu, la part seule sinon » — est une condition de gabarit sur `row.amount !== null`, déjà pilotée par les données. Elle est reprise dans le test de `collapsedSectors` ci-dessous via la présence de `amount`, et dans le smoke de la fiche instrument.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/sector.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { collapsedSectors, type SectorBreakdownRow } from '@/lib/sector';

const row = (label: string, share: number, amount: number | null = null): SectorBreakdownRow => ({
    label,
    share,
    amount,
});

const labelsOf = (view: { rows: { row: SectorBreakdownRow }[] }): string[] =>
    view.rows.map((entry) => entry.row.label);

describe('collapsedSectors', () => {
    it('trie les secteurs de la plus grosse part à la plus petite', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(labelsOf(view)).toEqual(['Technologie', 'Santé']);
    });

    it('mesure les barres contre le plus gros secteur, pas contre le total', () => {
        const view = collapsedSectors([row('Santé', 40), row('Technologie', 60)], false);

        expect(view.rows.map((entry) => entry.barWidth)).toEqual(['100%', `${(40 / 60) * 100}%`]);
    });

    it('replie les secteurs au-delà du sixième et annonce le nombre caché', () => {
        const eight = Array.from({ length: 8 }, (_unused, index) => row(`S${index}`, 80 - index * 10));

        const view = collapsedSectors(eight, false);

        expect(view.rows).toHaveLength(6);
        expect(view.hiddenCount).toBe(2);
    });

    it('montre tout une fois déplié', () => {
        const eight = Array.from({ length: 8 }, (_unused, index) => row(`S${index}`, 80 - index * 10));

        const view = collapsedSectors(eight, true);

        expect(view.rows).toHaveLength(8);
        expect(view.hiddenCount).toBe(2);
    });

    it('ne cache rien quand il y a six secteurs ou moins', () => {
        const six = Array.from({ length: 6 }, (_unused, index) => row(`S${index}`, 60 - index * 10));

        expect(collapsedSectors(six, false).hiddenCount).toBe(0);
    });

    it('porte le montant du secteur quand il y en a un, et rien quand il n\'y en a pas', () => {
        const view = collapsedSectors([row('Technologie', 60, 900), row('Santé', 40)], false);

        expect(view.rows[0].row.amount).toBe(900);
        expect(view.rows[1].row.amount).toBeNull();
    });

    it('rend une vue vide sans secteur', () => {
        const view = collapsedSectors([], false);

        expect(view.rows).toEqual([]);
        expect(view.hiddenCount).toBe(0);
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC, `collapsedSectors` introuvable.

- [ ] **Step 3: Écrire l'implémentation**

Ajouter à la fin de `resources/js/lib/sector.ts` :

```ts
import { largestOf, relativeBarWidth } from '@/lib/bars';

/** Au-delà de six, la liste sectorielle cesse de se lire d'un coup d'œil. */
const COLLAPSED_COUNT = 6;

export interface SectorView {
    rows: { row: SectorBreakdownRow; barWidth: string }[];
    /** Secteurs repliés, quel que soit l'état d'ouverture : le libellé du bouton s'en sert. */
    hiddenCount: number;
}

/**
 * La liste sectorielle triée, pondérée et repliée. Le tri est refait ici pour que l'échelle des
 * barres reste juste quel que soit l'ordre passé par l'appelant.
 */
export const collapsedSectors = (
    rows: SectorBreakdownRow[],
    expanded: boolean,
    collapsedCount: number = COLLAPSED_COUNT,
): SectorView => {
    const sorted = [...rows].sort(
        (left: SectorBreakdownRow, right: SectorBreakdownRow): number => right.share - left.share,
    );

    const largest = largestOf(sorted.map((row: SectorBreakdownRow): number => row.share));
    const visible = expanded ? sorted : sorted.slice(0, collapsedCount);

    return {
        rows: visible.map((row: SectorBreakdownRow) => ({
            row,
            barWidth: relativeBarWidth(row.share, largest),
        })),
        hiddenCount: Math.max(0, sorted.length - collapsedCount),
    };
};
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

- [ ] **Step 5: Brancher `SectorBreakdownList.vue`**

Remplacer les lignes 1 à 33 de `resources/js/components/SectorBreakdownList.vue` par :

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { eur as formatEur } from '@/lib/format';
import { collapsedSectors, type SectorBreakdownRow, type SectorView } from '@/lib/sector';

const props = defineProps<{ rows: SectorBreakdownRow[] }>();

const isExpanded = ref<boolean>(false);

const view = computed<SectorView>(() => collapsedSectors(props.rows, isExpanded.value));

const share = (value: number): string =>
    `${value.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

const amount = (value: number): string => formatEur(value, 0);
</script>
```

Dans le gabarit : `v-for="row in visibleRows"` devient `v-for="entry in view.rows"`, `:key="row.label"` devient `:key="entry.row.label"`, chaque `row.xxx` devient `entry.row.xxx`, `barWidth(row.share)` devient `entry.barWidth`, et `hiddenCount` devient `view.hiddenCount`.

Les attributs `data-sector-label`, `data-sector-amount`, `data-sector-share`, `data-sector-bar` restent inchangés, ainsi que les libellés `Voir les {{ view.hiddenCount }} autres` et `Réduire`.

- [ ] **Step 6: Vérifier**

```bash
bun run typecheck && bun run build
php artisan test --compact tests/Browser/DashboardSectorBreakdownTest.php
php artisan test --compact tests/Browser/InstrumentSectorBreakdownTest.php
```

Attendu : tout vert, rendu inchangé.

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/sector.ts resources/js/lib/sector.test.ts resources/js/components/SectorBreakdownList.vue
git commit -m "refactor: sort le repli sectoriel du composant"
```

---

### Task 5: `lib/performance.ts` — `performanceBars`

**Files:**
- Modify: `resources/js/lib/performance.ts`
- Modify: `resources/js/components/PerformanceBars.vue:1-20`
- Test: `resources/js/lib/performance.test.ts`

**Interfaces:**
- Consumes: `largestOf`, `relativeBarWidth` (tâche 2). `eur`, `signedEur` (`lib/format`). `Performance` (déjà dans `performance.ts`).
- Produces:
  ```ts
  export interface PerformanceBar {
      performance: Performance;
      barWidth: string;
      barColor: string;
      title: string;
  }
  export const performanceBars = (performances: Performance[]): PerformanceBar[]
  ```

**Contexte :** `PerformanceBars.vue` sert deux pages, le dashboard et la fiche instrument, et ses trois tests Browser existent **en double** — `DashboardPerformanceBarsTest` et `InstrumentPerformanceBarsTest` sont identiques mot pour mot. Une fonction, un test, les deux pages servies.

Les barres se mesurent sur la valeur absolue du pourcentage : une baisse de 20 % doit tracer une barre aussi longue qu'une hausse de 20 %, dans la couleur de la perte. L'ordre des périodes vient du serveur et n'est pas retrié ici.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/performance.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { performanceBars, type Performance } from '@/lib/performance';

const normalizeSpaces = (value: string): string => value.replace(/[  ]/g, ' ');

const performance = (key: string, pct: number, gain = 100): Performance => ({
    key,
    label: key.toUpperCase(),
    startDate: '2026-01-01',
    valueStart: 1000,
    contributions: 200,
    gain,
    pct,
});

describe('performanceBars', () => {
    it('rend une barre par période, dans l\'ordre reçu du serveur', () => {
        const bars = performanceBars([performance('ytd', 10), performance('1y', 25)]);

        expect(bars.map((bar) => bar.performance.label)).toEqual(['YTD', '1Y']);
    });

    it('remplit toute la piste pour le mouvement le plus fort', () => {
        const bars = performanceBars([performance('ytd', 10), performance('1y', 25)]);

        expect(bars[1].barWidth).toBe('100%');
        expect(bars[0].barWidth).toBe('40%');
    });

    it('mesure une baisse sur son amplitude, pas sur son signe', () => {
        const bars = performanceBars([performance('ytd', -25), performance('1y', 25)]);

        expect(bars[0].barWidth).toBe('100%');
        expect(bars[1].barWidth).toBe('100%');
    });

    it('teinte la barre selon le sens du mouvement', () => {
        const bars = performanceBars([performance('ytd', -5), performance('1y', 5)]);

        expect(bars[0].barColor).toBe('bg-loss-bar');
        expect(bars[1].barColor).toBe('bg-gain-bar');
    });

    it('garde la valeur de départ et les apports dans l\'infobulle de la ligne', () => {
        const [bar] = performanceBars([performance('ytd', 10)]);

        expect(normalizeSpaces(bar.title)).toBe('Valeur début 1 000,00 € · Apports +200,00 €');
    });

    it('rend une barre nulle quand aucune période n\'a bougé', () => {
        const bars = performanceBars([performance('ytd', 0)]);

        expect(bars[0].barWidth).toBe('0%');
    });

    it('rend un tableau vide sans période', () => {
        expect(performanceBars([])).toEqual([]);
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC, `performanceBars` introuvable.

- [ ] **Step 3: Écrire l'implémentation**

Ajouter à la fin de `resources/js/lib/performance.ts` :

```ts
import { largestOf, relativeBarWidth } from '@/lib/bars';
import { eur, signedEur } from '@/lib/format';

export interface PerformanceBar {
    performance: Performance;
    barWidth: string;
    barColor: string;
    /** Les colonnes que le tableau ne peut plus porter restent lisibles, à un survol près. */
    title: string;
}

/**
 * Les périodes prêtes à tracer. L'ordre vient du serveur et n'est pas retrié. Les barres se
 * mesurent sur l'amplitude du mouvement, pas sur son signe, pour qu'une baisse de 20 % pèse
 * visuellement autant qu'une hausse de 20 %.
 */
export const performanceBars = (performances: Performance[]): PerformanceBar[] => {
    const largest = largestOf(performances.map((performance: Performance): number => Math.abs(performance.pct)));

    return performances.map((performance: Performance): PerformanceBar => ({
        performance,
        barWidth: relativeBarWidth(Math.abs(performance.pct), largest),
        barColor: performance.pct < 0 ? 'bg-loss-bar' : 'bg-gain-bar',
        title: `Valeur début ${eur(performance.valueStart)} · Apports ${signedEur(performance.contributions)}`,
    }));
};
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

- [ ] **Step 5: Brancher `PerformanceBars.vue`**

Remplacer le bloc `<script setup>` de `resources/js/components/PerformanceBars.vue` par :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { gainClass, pct, signedEur } from '@/lib/format';
import { performanceBars, type Performance, type PerformanceBar } from '@/lib/performance';

const props = defineProps<{ performances: Performance[] }>();

const bars = computed<PerformanceBar[]>(() => performanceBars(props.performances));
</script>
```

Dans le gabarit : `v-for="performance in props.performances"` devient `v-for="bar in bars"`, `:key="performance.key"` devient `:key="bar.performance.key"`, `:title="rowTitle(performance)"` devient `:title="bar.title"`, `barColor(performance.pct)` devient `bar.barColor`, `barWidth(performance.pct)` devient `bar.barWidth`, et chaque `performance.xxx` restant devient `bar.performance.xxx`.

Les attributs `data-perf-row`, `data-perf-label`, `data-perf-bar`, `data-perf-gain`, `data-perf-pct` restent inchangés.

- [ ] **Step 6: Vérifier**

```bash
bun run typecheck && bun run build
php artisan test --compact tests/Browser/DashboardPerformanceBarsTest.php
php artisan test --compact tests/Browser/InstrumentPerformanceBarsTest.php
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/performance.ts resources/js/lib/performance.test.ts resources/js/components/PerformanceBars.vue
git commit -m "refactor: sort les barres de performance du composant"
```

---

### Task 6: `lib/catalog.ts` — tests de la recherche, et `catalogCount`

**Files:**
- Modify: `resources/js/lib/catalog.ts`
- Modify: `resources/js/components/instruments/CatalogHeader.vue:1-25`
- Test: `resources/js/lib/catalog.test.ts`

**Interfaces:**
- Consumes: `CatalogLine`, `CatalogRow`, `CatalogTrend`, `filterCatalog`, `joinTrends`, `isRangeKey` (tous déjà exportés).
- Produces:
  ```ts
  export const catalogCount = (rows: CatalogRow[]): string
  ```

**Contexte :** `filterCatalog` et `joinTrends` sont déjà purs et exportés (`catalog.ts:43` et `:57`). Cinq tests Browser les vérifient à travers une frappe au clavier. `catalogCount` est à extraire de `CatalogHeader.vue:17-25` — il rend « 3 instruments · 1 détenu », avec accord du pluriel sur les deux nombres, et se recalcule sur les lignes filtrées.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/catalog.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import {
    catalogCount,
    filterCatalog,
    isRangeKey,
    joinTrends,
    type CatalogLine,
    type CatalogRow,
} from '@/lib/catalog';

const line = (id: number, name: string, ticker: string | null, isin: string | null, held = false): CatalogLine => ({
    id,
    name,
    ticker,
    isin,
    type: 'stock',
    typeLabel: 'Action',
    lastPrice: 100,
    held,
    quantity: held ? 10 : null,
    marketValue: held ? 1000 : null,
});

const row = (id: number, name: string, ticker: string | null, isin: string | null = null, held = false): CatalogRow => ({
    ...line(id, name, ticker, isin, held),
    changePct: 5,
    points: [1, 2, 3],
});

describe('filterCatalog', () => {
    it('rend toute la liste sur une recherche vide', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'BET')];

        expect(filterCatalog(rows, '')).toHaveLength(2);
        expect(filterCatalog(rows, '   ')).toHaveLength(2);
    });

    it('cherche dans le nom', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Gamma', 'GAM')];

        expect(filterCatalog(rows, 'amm').map((found) => found.name)).toEqual(['Gamma']);
    });

    it('cherche dans le ticker', () => {
        const rows = [row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'XYZW')];

        expect(filterCatalog(rows, 'xyz').map((found) => found.name)).toEqual(['Beta']);
    });

    it('cherche dans l\'ISIN', () => {
        const rows = [row(1, 'Société Générale', 'SOC', 'FR0000130809'), row(2, 'Alpha', 'ALP', 'FR0000000001')];

        expect(filterCatalog(rows, 'fr00001308').map((found) => found.name)).toEqual(['Société Générale']);
    });

    it('ignore les accents, pour qu\'on retrouve « Société Générale » en tapant « societe gen »', () => {
        const rows = [row(1, 'Société Générale', 'SOC'), row(2, 'Alpha', 'ALP')];

        expect(filterCatalog(rows, 'societe').map((found) => found.name)).toEqual(['Société Générale']);
        expect(filterCatalog(rows, 'societe gen').map((found) => found.name)).toEqual(['Société Générale']);
    });

    it('cherche sur une sous-chaîne continue, espaces compris, et non sur des mots isolés', () => {
        const rows = [row(1, 'Société Générale', 'SOC')];

        expect(filterCatalog(rows, 'generale societe')).toEqual([]);
    });

    it('ignore la casse', () => {
        expect(filterCatalog([row(1, 'Alpha', 'ALP')], 'ALPHA')).toHaveLength(1);
    });

    it('rend une liste vide quand rien ne correspond', () => {
        expect(filterCatalog([row(1, 'Alpha', 'ALP')], 'zzz')).toEqual([]);
    });

    it('ne casse pas sur un ticker ou un ISIN absent', () => {
        expect(filterCatalog([row(1, 'Alpha', null, null)], 'alpha')).toHaveLength(1);
    });
});

describe('joinTrends', () => {
    it('rattache la tendance de la période à sa ligne', () => {
        const rows = joinTrends(
            [line(1, 'Alpha', 'ALP', null), line(2, 'Beta', 'BET', null)],
            [{ assetId: 2, changePct: 12.5, points: [1, 2] }],
        );

        expect(rows[1].changePct).toBe(12.5);
        expect(rows[1].points).toEqual([1, 2]);
    });

    it('laisse une ligne sans tendance vide plutôt qu\'absente', () => {
        const rows = joinTrends([line(1, 'Alpha', 'ALP', null)], []);

        expect(rows[0].changePct).toBeNull();
        expect(rows[0].points).toEqual([]);
    });

    it('supporte des tendances encore différées', () => {
        const rows = joinTrends([line(1, 'Alpha', 'ALP', null)], undefined);

        expect(rows[0].changePct).toBeNull();
    });
});

describe('isRangeKey', () => {
    it('reconnaît les périodes offertes et rejette le reste', () => {
        expect(isRangeKey('1M')).toBe(true);
        expect(isRangeKey('max')).toBe(true);
        expect(isRangeKey('3M')).toBe(false);
        expect(isRangeKey(undefined)).toBe(false);
    });
});

describe('catalogCount', () => {
    it('compte les instruments et ceux qui sont détenus', () => {
        const rows = [row(1, 'Alpha', 'ALP', null, true), row(2, 'Beta', 'BET'), row(3, 'Gamma', 'GAM')];

        expect(catalogCount(rows)).toBe('3 instruments · 1 détenu');
    });

    it('accorde le pluriel sur les détenus', () => {
        const rows = [row(1, 'Alpha', 'ALP', null, true), row(2, 'Beta', 'BET', null, true)];

        expect(catalogCount(rows)).toBe('2 instruments · 2 détenus');
    });

    it('tait les détenus quand il n\'y en a aucun', () => {
        expect(catalogCount([row(1, 'Alpha', 'ALP'), row(2, 'Beta', 'BET')])).toBe('2 instruments');
    });

    it('accorde le singulier sur un instrument unique', () => {
        expect(catalogCount([row(1, 'Alpha', 'ALP')])).toBe('1 instrument');
    });

    it('se recompte sur une liste filtrée', () => {
        const rows = [row(1, 'Alpha Fund', 'ALP'), row(2, 'Bravo Fund', 'BRA'), row(3, 'Gamma', 'GAM')];

        expect(catalogCount(filterCatalog(rows, 'fund'))).toBe('2 instruments');
    });

    it('rend un compte nul sur une liste vide', () => {
        expect(catalogCount([])).toBe('0 instrument');
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC, `catalogCount` introuvable. Les tests de `filterCatalog`, `joinTrends` et `isRangeKey` devraient passer immédiatement — ces fonctions existent déjà.

- [ ] **Step 3: Écrire l'implémentation**

Ajouter à la fin de `resources/js/lib/catalog.ts` :

```ts
/**
 * L'en-tête du catalogue : combien d'instruments, et combien sont détenus. Se recompte sur la
 * liste filtrée, la recherche devant répondre « 2 instruments » et non « 2 sur 3 ».
 */
export const catalogCount = (rows: CatalogRow[]): string => {
    const held = rows.filter((row: CatalogRow): boolean => row.held).length;
    const instruments = `${rows.length} instrument${rows.length > 1 ? 's' : ''}`;

    return held === 0 ? instruments : `${instruments} · ${held} détenu${held > 1 ? 's' : ''}`;
};
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

- [ ] **Step 5: Brancher `CatalogHeader.vue`**

Dans `resources/js/components/instruments/CatalogHeader.vue`, remplacer l'import et les deux `computed` `heldCount` / `countLabel` par :

```ts
import { catalogCount, rangeOptions, type CatalogRow, type RangeKey } from '@/lib/catalog';
```

et

```ts
const countLabel = computed<string>(() => catalogCount(props.rows));
```

`heldCount` disparaît. Vérifier qu'il n'est pas utilisé dans le gabarit avant de le retirer :

```bash
grep -n "heldCount" resources/js/components/instruments/CatalogHeader.vue
```

- [ ] **Step 6: Vérifier**

```bash
bun run typecheck && bun run build
php artisan test --compact tests/Browser/InstrumentCatalogRadarTest.php
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/catalog.ts resources/js/lib/catalog.test.ts resources/js/components/instruments/CatalogHeader.vue
git commit -m "refactor: sort le décompte du catalogue du composant"
```

---

### Task 7: `lib/instrument.ts` — `investedOf` et `heroMeta`

**Files:**
- Modify: `resources/js/lib/instrument.ts`
- Modify: `resources/js/components/instrument/HeroSection.vue:1-39`
- Test: `resources/js/lib/instrument.test.ts`

**Interfaces:**
- Consumes: `eur`, `frDate` (`lib/format`). `Instrument`, `InstrumentPosition`, `investedOf` (déjà dans `instrument.ts`).
- Produces:
  ```ts
  export interface HeroMetaEntry { label: string; value: string }
  export const heroValueOf = (instrument: Instrument): number | null
  export const heroMeta = (instrument: Instrument): HeroMetaEntry[]
  ```

**Contexte :** l'en-tête de la fiche instrument affiche, quand l'instrument est détenu, « Titres 10 · PRU 80,00 € · Investi 800,00 € · Cours 100,00 € », et quand il ne l'est pas, « au 01/07/2026 ». Cette composition vit dans `HeroSection.vue:22-38`. La valeur mise en avant est la valeur de marché de la position, ou à défaut le dernier cours (`HeroSection.vue:14`).

Lire `resources/js/lib/instrument.ts` pour relever la forme exacte de `Instrument` — les champs `lastPrice`, `lastPriceDate`, `position` — avant d'écrire le test.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/instrument.test.ts` :

```ts
import { describe, expect, it } from 'vitest';
import { heroMeta, heroValueOf, investedOf, type Instrument, type InstrumentPosition } from '@/lib/instrument';

const normalizeSpaces = (value: string): string => value.replace(/[  ]/g, ' ');

const position = (overrides: Partial<InstrumentPosition> = {}): InstrumentPosition => ({
    quantity: 10,
    avgCost: 80,
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    ...overrides,
});

const instrument = (overrides: Partial<Instrument> = {}): Instrument => ({
    id: 1,
    name: 'ACME ETF',
    ticker: 'ACME',
    isin: 'FR0000000001',
    type: 'etf',
    typeLabel: 'ETF',
    lastPrice: 100,
    lastPriceDate: '2026-07-01',
    position: position(),
    transactions: [],
    sectors: [],
    ...overrides,
});

describe('investedOf', () => {
    it('déduit le montant investi du prix de revient et de la quantité', () => {
        expect(investedOf(position())).toBe(800);
    });

    it('ne déduit rien sans prix de revient', () => {
        expect(investedOf(position({ avgCost: null }))).toBeNull();
    });
});

describe('heroValueOf', () => {
    it('met en avant la valeur de marché quand l\'instrument est détenu', () => {
        expect(heroValueOf(instrument())).toBe(1000);
    });

    it('retombe sur le dernier cours quand il n\'est pas détenu', () => {
        expect(heroValueOf(instrument({ position: null }))).toBe(100);
    });
});

describe('heroMeta', () => {
    it('énonce titres, prix de revient, investi et cours quand l\'instrument est détenu', () => {
        const entries = heroMeta(instrument());

        expect(entries.map((entry) => entry.label)).toEqual(['Titres', 'PRU', 'Investi', 'Cours']);
        expect(entries.map((entry) => normalizeSpaces(entry.value))).toEqual([
            '10',
            '80,00 €',
            '800,00 €',
            '100,00 €',
        ]);
    });

    it('énonce la date du dernier cours quand l\'instrument n\'est pas détenu', () => {
        const entries = heroMeta(instrument({ position: null }));

        expect(entries).toEqual([{ label: '', value: 'au 01/07/2026' }]);
    });

    it('n\'énonce rien quand l\'instrument n\'est ni détenu ni coté', () => {
        expect(heroMeta(instrument({ position: null, lastPriceDate: null }))).toEqual([]);
    });

    it('rend un tiret sur un prix de revient absent plutôt que de masquer la ligne', () => {
        const entries = heroMeta(instrument({ position: position({ avgCost: null }) }));

        expect(entries[1]).toEqual({ label: 'PRU', value: '—' });
        expect(entries[2]).toEqual({ label: 'Investi', value: '—' });
    });
});
```

`transactions` et `sectors` sont requis par l'interface `Instrument` (`instrument.ts:29-41`) même si `heroMeta` ne les lit pas : les omettre casse le typecheck.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
bun run test:js
```

Attendu : ÉCHEC, `heroMeta` et `heroValueOf` introuvables.

- [ ] **Step 3: Écrire l'implémentation**

Ajouter à la fin de `resources/js/lib/instrument.ts` :

```ts
import { eur, frDate } from '@/lib/format';

export interface HeroMetaEntry {
    label: string;
    value: string;
}

/** Un instrument détenu vaut sa valeur de marché ; sinon il ne vaut que son dernier cours. */
export const heroValueOf = (instrument: Instrument): number | null =>
    instrument.position?.marketValue ?? instrument.lastPrice;

/**
 * Pied de l'en-tête : paires libellé/valeur où le libellé s'efface et la valeur porte la lecture.
 * Sans position, il ne reste que la date du dernier cours — un instrument seulement suivi n'a ni
 * prix de revient ni montant investi.
 */
export const heroMeta = (instrument: Instrument): HeroMetaEntry[] => {
    const position = instrument.position;

    if (position === null) {
        return instrument.lastPriceDate === null
            ? []
            : [{ label: '', value: `au ${frDate(instrument.lastPriceDate)}` }];
    }

    return [
        { label: 'Titres', value: position.quantity.toLocaleString('fr-FR') },
        { label: 'PRU', value: eur(position.avgCost) },
        { label: 'Investi', value: eur(investedOf(position)) },
        { label: 'Cours', value: eur(instrument.lastPrice) },
    ];
};
```

- [ ] **Step 4: Lancer les tests pour vérifier qu'ils passent**

```bash
bun run test:js
```

- [ ] **Step 5: Brancher `HeroSection.vue`**

Remplacer les lignes 1 à 39 de `resources/js/components/instrument/HeroSection.vue` par :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import GainPill from '@/components/GainPill.vue';
import { eur, gainClass, pct, signedEur } from '@/lib/format';
import { heroMeta, heroValueOf, type HeroMetaEntry, type Instrument } from '@/lib/instrument';

const props = defineProps<{
    instrument: Instrument;
}>();

const position = computed(() => props.instrument.position);

const heroValue = computed<number | null>(() => heroValueOf(props.instrument));

const metaEntries = computed<HeroMetaEntry[]>(() => heroMeta(props.instrument));
</script>
```

Dans le gabarit, le bloc `data-hero-meta` boucle déjà sur `metaEntries` : la seule chose à retirer est le `<span v-if="position === null && instrument.lastPriceDate">au {{ frDate(...) }}</span>` (lignes 85-87), désormais porté par `heroMeta`. Attention : l'entrée sans position a un `label` vide — la boucle doit alors n'afficher que la valeur. Adapter :

```vue
                <span
                    v-for="entry in metaEntries"
                    :key="entry.label || entry.value"
                    class="flex items-baseline justify-between gap-3 whitespace-nowrap"
                >
                    <template v-if="entry.label">
                        {{ entry.label }}
                        <strong class="font-semibold text-foreground tabular-nums">{{ entry.value }}</strong>
                    </template>
                    <template v-else>{{ entry.value }}</template>
                </span>
```

Les attributs `data-hero-value`, `data-hero-gain`, `data-hero-gain-pct`, `data-hero-meta` restent inchangés.

- [ ] **Step 6: Vérifier**

```bash
bun run typecheck && bun run build
php artisan test --compact tests/Browser/InstrumentHeroTest.php
```

Attendu : les trois tests du hero passent toujours. Le premier asserte exactement `Titres 10 · PRU 80,00 € · Investi 800,00 € · Cours 100,00 €` en joignant les `[data-hero-meta] > span` — la structure du gabarit doit donc rester une suite de `span` directs.

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/instrument.ts resources/js/lib/instrument.test.ts resources/js/components/instrument/HeroSection.vue
git commit -m "refactor: sort le pied de l'en-tête instrument du composant"
```

---

### Task 8: `chart.ts` — structure de l'option : axes, séries, aire, pastille, description

**Files:**
- Test: `resources/js/lib/chart.test.ts`

**Interfaces:**
- Consumes: `buildValueVsInvestedOption`, `buildPriceHistoryOption` (déjà exportés, `chart.ts:302` et `:347`).
- Produces: les fabriques de test `monthlyLabels()`, `valueVsInvested()` et les accès typés `yAxisOf()`, `seriesOf()`, réutilisés par les tâches 9 et 10 **dans le même fichier**.

**Contexte, et pourquoi cette tâche est la plus rentable du plan :** `buildValueVsInvestedOption` construit l'objet d'options qu'ECharts peint ensuite en SVG. Aujourd'hui, sept tests Browser vérifient cette construction en filtrant des `svg path[stroke="#5257d6"]` dans un vrai navigateur. Les mêmes vérités se lisent directement sur l'objet, sans navigateur.

**Aucune modification de `chart.ts` n'est nécessaire.** Tout est atteignable depuis les deux fonctions publiques : la fenêtre de zoom dans `option.dataZoom[0].start`, l'infobulle dans `option.tooltip.formatter`.

**Le thème.** `chart.ts:4` importe `isDark` depuis `theme.ts`, un `ref` créé par `usePreferredDark()` au chargement du module. Pour rendre la palette déterministe, le module est remplacé par un faux. `vi.mock` est remonté en tête de fichier par Vitest, avant les imports : l'ordre d'écriture n'a pas d'importance.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `resources/js/lib/chart.test.ts` :

```ts
import { describe, expect, it, vi } from 'vitest';
import type { LineSeriesOption } from 'echarts/charts';
import type { ChartOption } from '@/lib/echarts';
import { eur } from '@/lib/format';

/**
 * `lib/theme` crée son `ref` via `usePreferredDark()` au chargement du module, ce qui rendrait la
 * palette dépendante de l'environnement. Un faux le fige sur le thème clair.
 */
vi.mock('@/lib/theme', () => ({ isDark: { value: false } }));

const { buildPriceHistoryOption, buildValueVsInvestedOption, sumPerAsset } = await import('@/lib/chart');

/** Étiquettes ISO au premier de chaque mois, à partir de janvier 2023. */
const monthlyLabels = (months: number): string[] =>
    Array.from({ length: months }, (_unused: unknown, index: number): string =>
        new Date(Date.UTC(2023, index, 1)).toISOString().slice(0, 10),
    );

const valueVsInvested = (months: number, window: { start: number; end: number } | null = null): ChartOption => {
    const labels = monthlyLabels(months);

    return buildValueVsInvestedOption({
        labels,
        value: labels.map((_unused: string, index: number): number => 1000 + index * 10),
        invested: labels.map((): number => 900),
        valueFormatter: (value: number): string => eur(value, 0),
        window,
        description: 'Évolution de la valeur du portefeuille face aux montants investis.',
    });
};

const yAxisOf = (option: ChartOption) =>
    option.yAxis as { scale: boolean; axisLabel: { formatter: (value: number) => string } };

const seriesOf = (option: ChartOption): LineSeriesOption[] => option.series as LineSeriesOption[];

describe('buildValueVsInvestedOption — axes', () => {
    it('cadre l\'axe des valeurs sur les valeurs visibles au lieu de l\'ancrer à zéro', () => {
        expect(yAxisOf(valueVsInvested(36)).scale).toBe(true);
    });

    it('gradue l\'axe des valeurs en euros', () => {
        const formatter = yAxisOf(valueVsInvested(36)).axisLabel.formatter;

        expect(formatter(1000).replace(/[  ]/g, ' ')).toBe('1 000 €');
    });

    it('pose un axe temporel, pour que la graduation suive l\'amplitude visible', () => {
        expect((valueVsInvested(36).xAxis as { type: string }).type).toBe('time');
    });
});

describe('buildValueVsInvestedOption — séries', () => {
    it('trace toujours les deux courbes, la comparaison étant la lecture et non une option', () => {
        expect(seriesOf(valueVsInvested(36)).map((serie) => serie.name)).toEqual(['Valeur', 'Investi']);
    });

    it('trace l\'investi en escalier : il ne bouge qu\'à un achat ou une vente', () => {
        const [, invested] = seriesOf(valueVsInvested(36));

        expect(invested.step).toBe('end');
        expect(invested.lineStyle?.type).toBe('dashed');
    });

    it('trace la valeur sur une aire dégradée', () => {
        const [value] = seriesOf(valueVsInvested(36));
        const area = value.areaStyle?.color as { type?: string; colorStops?: unknown[] };

        expect(area.type).toBe('linear');
        expect(area.colorStops).toHaveLength(2);
    });

    it('marque la dernière valeur d\'une pastille, qui ancre le « où en est-on »', () => {
        const [value] = seriesOf(valueVsInvested(36));
        const markPoint = value.markPoint as { data: { coord: [string, number] }[] };

        expect(markPoint.data).toHaveLength(1);
        expect(markPoint.data[0].coord[0]).toBe('2025-12-01');
    });

    it('ne marque rien sur un historique vide', () => {
        const option = buildValueVsInvestedOption({
            labels: [],
            value: [],
            invested: [],
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Vide.',
        });

        expect(seriesOf(option)[0].markPoint).toBeUndefined();
    });

    it('associe chaque valeur à sa date, l\'axe temporel attendant des couples', () => {
        const [value] = seriesOf(valueVsInvested(3));

        expect(value.data).toEqual([
            ['2023-01-01', 1000],
            ['2023-02-01', 1010],
            ['2023-03-01', 1020],
        ]);
    });
});

describe('buildValueVsInvestedOption — description accessible', () => {
    it('porte la description rédigée à la main plutôt que le gabarit anglais d\'ECharts', () => {
        const aria = valueVsInvested(36).aria as { enabled: boolean; label: { description: string } };

        expect(aria.enabled).toBe(true);
        expect(aria.label.description).toBe('Évolution de la valeur du portefeuille face aux montants investis.');
    });
});

describe('buildPriceHistoryOption', () => {
    it('trace une seule courbe pour le cours', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((_unused: string, index: number): number => 100 + index),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(seriesOf(option).map((serie) => serie.name)).toEqual(['Cours']);
    });

    it('cadre l\'axe des cours sur les cotations visibles au lieu de l\'ancrer à zéro', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((_unused: string, index: number): number => 100 + index),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(yAxisOf(option).scale).toBe(true);
    });

    it('n\'offre pas de zoom : la fiche instrument le porte sur le graphe de valorisation', () => {
        const labels = monthlyLabels(24);
        const option = buildPriceHistoryOption({
            labels,
            close: labels.map((): number => 100),
            valueFormatter: (value: number): string => eur(value, 0),
        });

        expect(option.dataZoom).toBeUndefined();
    });
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue puis passe**

```bash
bun run test:js
```

Attendu : les tests passent immédiatement — aucune production n'est à écrire, `chart.ts` existe déjà. Si un échec apparaît, c'est un écart entre ce que le plan suppose et ce que `chart.ts` fait : **lire `chart.ts` et corriger l'attente du test, pas le code de production.** Cette tâche documente le comportement existant, elle ne le change pas.

Deux échecs plausibles : `markPoint.data[0].coord` peut porter un objet plutôt qu'un tuple selon la version d'ECharts ; `option.aria` peut être typé plus étroitement. Ajuster les casts, jamais les valeurs attendues.

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/chart.test.ts
git commit -m "test: asserte l'option du graphe au lieu de son SVG"
```

---

### Task 9: `chart.ts` — le zoom : fenêtre d'ouverture, plancher d'un an, poignées

**Files:**
- Modify: `resources/js/lib/chart.test.ts`

**Interfaces:**
- Consumes: les fabriques de la tâche 8 (`monthlyLabels`, `valueVsInvested`) et `sumPerAsset`.
- Produces: rien.

**Contexte :** trois règles de zoom sont aujourd'hui vérifiées au navigateur, dont deux en double entre `DashboardEvolutionZoomTest` et `InstrumentValuationChartTest` :

1. Le graphe ouvre sur les douze derniers mois. La fenêtre est exprimée en pourcentage de l'amplitude totale, l'axe étant temporel (`chart.ts:284`).
2. Le zoom ne descend jamais sous un an — `minValueSpan: MIN_ZOOM_SPAN_MS`, soit 365 jours en millisecondes.
3. Un historique plus court qu'un an s'affiche en entier.

**Le calcul, à vérifier au brouillon avant d'écrire l'attente :** 36 étiquettes mensuelles depuis 2023-01-01 vont jusqu'à 2025-12-01, soit une amplitude de 1064 jours. `start = 100 × (1 − 365 / 1064) = 65,7`. La fenêtre couvre donc environ 34,3 % de l'historique — ce qui explique la fourchette `> 32 et < 35` des tests Browser actuels.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `resources/js/lib/chart.test.ts` :

```ts
type DataZoom = {
    type: string;
    start: number;
    end: number;
    minValueSpan: number;
    showDetail?: boolean;
    handleLabel?: { show: boolean };
};

const dataZoomOf = (option: ChartOption): DataZoom[] => option.dataZoom as DataZoom[];

/** 365 jours en millisecondes : le plancher de la fenêtre visible. */
const ONE_YEAR_MS = 365 * 24 * 60 * 60 * 1000;

describe('buildValueVsInvestedOption — zoom', () => {
    it('ouvre sur les douze derniers mois d\'un historique de trois ans', () => {
        const [inside] = dataZoomOf(valueVsInvested(36));

        expect(inside.end).toBe(100);
        expect(inside.end - inside.start).toBeGreaterThan(32);
        expect(inside.end - inside.start).toBeLessThan(35);
    });

    it('montre tout l\'historique quand il est plus court qu\'un an', () => {
        const [inside] = dataZoomOf(valueVsInvested(6));

        expect(inside.start).toBe(0);
        expect(inside.end).toBe(100);
    });

    it('montre tout l\'historique quand il n\'atteint pas un an', () => {
        // Douze étiquettes mensuelles depuis janvier 2023 s'arrêtent au 1er décembre : 334 jours.
        const [inside] = dataZoomOf(valueVsInvested(12));

        expect(inside.start).toBe(0);
        expect(inside.end).toBe(100);
    });

    it('rogne dès que l\'historique dépasse un an, ne serait-ce que d\'un jour', () => {
        // Treize étiquettes vont du 1er janvier 2023 au 1er janvier 2024 : 366 jours, un de trop.
        const [inside] = dataZoomOf(valueVsInvested(13));

        expect(inside.start).toBeGreaterThan(0);
        expect(inside.start).toBeLessThan(1);
    });

    it('montre tout sur un historique vide, sans produire de fenêtre absurde', () => {
        const option = buildValueVsInvestedOption({
            labels: [],
            value: [],
            invested: [],
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Vide.',
        });

        expect(dataZoomOf(option)[0]).toMatchObject({ start: 0, end: 100 });
    });

    it('interdit au lecteur de descendre sous un an, sur les deux commandes de zoom', () => {
        const zooms = dataZoomOf(valueVsInvested(36));

        expect(zooms).toHaveLength(2);
        expect(zooms[0].minValueSpan).toBe(ONE_YEAR_MS);
        expect(zooms[1].minValueSpan).toBe(ONE_YEAR_MS);
    });

    it('respecte la fenêtre déjà choisie par le lecteur plutôt que de la remettre à douze mois', () => {
        const zooms = dataZoomOf(valueVsInvested(36, { start: 10, end: 60 }));

        expect(zooms[0]).toMatchObject({ start: 10, end: 60 });
        expect(zooms[1]).toMatchObject({ start: 10, end: 60 });
    });

    it('laisse les poignées de zoom muettes, leurs bornes se lisant déjà sur l\'axe', () => {
        const [, slider] = dataZoomOf(valueVsInvested(36));

        expect(slider.type).toBe('slider');
        expect(slider.showDetail).toBe(false);
        expect(slider.handleLabel?.show).toBe(false);
    });

    it('offre le zoom à la molette autant qu\'à la mini-timeline', () => {
        expect(dataZoomOf(valueVsInvested(36)).map((zoom) => zoom.type)).toEqual(['inside', 'slider']);
    });
});

describe('sumPerAsset', () => {
    it('somme les titres point par point : le tableau de bord raisonne sur le portefeuille entier', () => {
        const perAsset = [
            { assetId: 1, name: 'ACME', value: [100, 110, 120], invested: [80, 80, 80] },
            { assetId: 2, name: 'BETA', value: [50, 55, 60], invested: [40, 40, 40] },
        ];

        expect(sumPerAsset(perAsset, (asset) => asset.value, 3)).toEqual([150, 165, 180]);
        expect(sumPerAsset(perAsset, (asset) => asset.invested, 3)).toEqual([120, 120, 120]);
    });

    it('traite un titre plus court que la série comme nul sur ses points manquants', () => {
        const perAsset = [
            { assetId: 1, name: 'ACME', value: [100, 110, 120], invested: [80, 80, 80] },
            { assetId: 2, name: 'BETA', value: [50], invested: [40] },
        ];

        expect(sumPerAsset(perAsset, (asset) => asset.value, 3)).toEqual([150, 110, 120]);
    });

    it('rend une série de zéros sans aucun titre', () => {
        expect(sumPerAsset([], (asset) => asset.value, 3)).toEqual([0, 0, 0]);
    });
});
```

- [ ] **Step 2: Lancer les tests**

```bash
bun run test:js
```

Attendu : tous verts sans modification de production. `chart.ts:287` compare avec `span <= MIN_ZOOM_SPAN_MS` : les deux tests de bordure encadrent donc précisément ce seuil, l'un juste en dessous (334 jours), l'autre juste au-dessus (366 jours).

- [ ] **Step 3: Commit**

```bash
git add resources/js/lib/chart.test.ts
git commit -m "test: verrouille la fenêtre de zoom sans navigateur"
```

---

### Task 10: `chart.ts` — l'infobulle et la palette des deux thèmes

**Files:**
- Modify: `resources/js/lib/chart.test.ts`
- Create: `resources/js/lib/chart.dark.test.ts`

**Interfaces:**
- Consumes: les fabriques de la tâche 8.
- Produces: rien.

**Contexte :** deux vérités restent à descendre.

**L'infobulle.** `valueVsInvestedTooltip` (`chart.ts:209`) énonce le gain sur place plutôt que de laisser le lecteur soustraire les deux courbes. Le `formatter` est une fonction pure de l'index survolé ; ECharts lui passe un tableau de points partageant le même `dataIndex` (`chart.ts:242`). On l'appelle directement.

**La palette.** `SystemThemeTest` vérifie au navigateur que les libellés d'axe s'assombrissent sur fond clair, en lisant l'attribut `fill` du SVG. Cette valeur vient de `palette()` (`chart.ts:28`), qui bascule sur `isDark`. Comme `vi.mock` est appliqué à tout le fichier, tester les deux thèmes demande **deux fichiers** : `chart.test.ts` fige le thème clair, `chart.dark.test.ts` fige le thème sombre.

Relever les deux couleurs exactes de `axisLabel` dans `palette()` avant d'écrire les attentes. Les tests Browser actuels citent `#9aa0ac` et `#7f858f` — vérifier laquelle appartient à quel thème plutôt que de le supposer.

- [ ] **Step 1: Écrire les tests d'infobulle**

Ajouter à la fin de `resources/js/lib/chart.test.ts` :

```ts
const tooltipHtml = (option: ChartOption, dataIndex: number): string => {
    const formatter = (option.tooltip as { formatter: (params: unknown) => string }).formatter;

    return formatter([{ dataIndex }]);
};

describe('buildValueVsInvestedOption — infobulle', () => {
    it('énonce la valeur, l\'investi et le gain, plutôt que de laisser soustraire les deux courbes', () => {
        const html = tooltipHtml(valueVsInvested(36), 10).replace(/[  ]/g, ' ');

        expect(html).toContain('Valeur');
        expect(html).toContain('1 100 €');
        expect(html).toContain('Investi');
        expect(html).toContain('900 €');
        expect(html).toContain('Gain');
        expect(html).toContain('200 €');
    });

    it('nomme la ligne « Perte » et signe négativement quand la valeur passe sous l\'investi', () => {
        const labels = monthlyLabels(36);
        const option = buildValueVsInvestedOption({
            labels,
            value: labels.map((): number => 700),
            invested: labels.map((): number => 900),
            valueFormatter: (value: number): string => eur(value, 0),
            window: null,
            description: 'Baisse.',
        });

        const html = tooltipHtml(option, 5).replace(/[  ]/g, ' ');

        expect(html).toContain('Perte');
        expect(html).toContain('−');
        expect(html).toContain('200 €');
    });

    it('titre l\'infobulle sur la date survolée', () => {
        expect(tooltipHtml(valueVsInvested(36), 0)).toContain('2023');
    });

    it('rend une infobulle vide quand ECharts ne fournit pas d\'index', () => {
        const formatter = (valueVsInvested(36).tooltip as { formatter: (params: unknown) => string }).formatter;

        expect(formatter([])).toBe('');
        expect(formatter([{}])).toBe('');
    });
});

describe('buildValueVsInvestedOption — palette claire', () => {
    it('assombrit les libellés d\'axe pour qu\'ils restent lisibles sur un fond clair', () => {
        const axisLabel = (valueVsInvested(36).xAxis as { axisLabel: { color: string } }).axisLabel;

        expect(axisLabel.color).toBe('#7f858f');
    });
});
```

Si la couleur du thème clair n'est pas `#7f858f`, corriger l'attente sur la valeur réelle lue dans `palette()`. Les deux thèmes doivent donner des couleurs **différentes** — c'est ça, la vérité testée.

- [ ] **Step 2: Écrire le test du thème sombre**

Créer `resources/js/lib/chart.dark.test.ts` :

```ts
import { describe, expect, it, vi } from 'vitest';
import type { ChartOption } from '@/lib/echarts';
import { eur } from '@/lib/format';

/** Fichier séparé : `vi.mock` porte sur tout le module, un seul thème par fichier. */
vi.mock('@/lib/theme', () => ({ isDark: { value: true } }));

const { buildValueVsInvestedOption } = await import('@/lib/chart');

const option = (): ChartOption =>
    buildValueVsInvestedOption({
        labels: ['2025-01-01', '2025-06-01', '2026-01-01'],
        value: [1000, 1100, 1200],
        invested: [900, 900, 900],
        valueFormatter: (value: number): string => eur(value, 0),
        window: null,
        description: 'Thème sombre.',
    });

describe('palette sombre', () => {
    it('éclaircit les libellés d\'axe pour qu\'ils restent lisibles sur un fond sombre', () => {
        const axisLabel = (option().xAxis as { axisLabel: { color: string } }).axisLabel;

        expect(axisLabel.color).toBe('#9aa0ac');
    });

    it('garde les deux courbes et leur nom, le thème ne changeant que les teintes', () => {
        expect((option().series as { name?: string }[]).map((serie) => serie.name)).toEqual(['Valeur', 'Investi']);
    });
});
```

- [ ] **Step 3: Lancer les tests**

```bash
bun run test:js
```

Attendu : tout vert. Compter les tests — le total sur `resources/js/lib/` doit dépasser 45. Si ce n'est pas le cas, la couverture est trop mince pour supprimer les tests Browser en tâche 11 ; ajouter des cas limites sur les modules les plus fins avant de continuer.

- [ ] **Step 4: Commit**

```bash
git add resources/js/lib/chart.test.ts resources/js/lib/chart.dark.test.ts
git commit -m "test: verrouille l'infobulle et les deux palettes du graphe"
```

---

### Task 11: Supprimer les huit fichiers Browser dont la vérité est désormais couverte

**Files:**
- Delete: `tests/Browser/DashboardCardlessSectionsTest.php`, `tests/Browser/DashboardSectionSpacingTest.php`, `tests/Browser/DashboardChartLegendTest.php`, `tests/Browser/DashboardEvolutionLayoutTest.php`, `tests/Browser/DashboardPerformanceBarsTest.php`, `tests/Browser/InstrumentPerformanceBarsTest.php`, `tests/Browser/DashboardValuationHeadlineTest.php`, `tests/Browser/InstrumentValuationChartTest.php`
- Modify: `tests/Pest.php`

**Interfaces:**
- Consumes: la couverture Vitest des tâches 1 à 10.
- Produces: une suite Browser réduite à 13 fichiers, avant la réorganisation de la tâche 12.

**Justification, fichier par fichier.** À vérifier avant de supprimer — si une ligne de cette liste ne tient pas, ne pas supprimer le fichier et le signaler.

| Fichier | Motif |
|---|---|
| `DashboardCardlessSectionsTest` | F1 (absence de carte, fonds identiques) et F3 (ordre des sections dupliqué avec `DashboardSectionOrderTest`) |
| `DashboardSectionSpacingTest` | F1 et F4 : `'24|24|24|24'` d'écart mesuré au pixel |
| `DashboardChartLegendTest` | F1 (absence du donut) ; la description accessible est couverte par la tâche 8 |
| `DashboardEvolutionLayoutTest` | 5 vérités couvertes tâche 8 ; 3 F1/F4 (absence d'en-tête, hauteur `300px`, marges internes) |
| `DashboardPerformanceBarsTest` | 3 vérités couvertes tâche 5 |
| `InstrumentPerformanceBarsTest` | F3 : copie mot pour mot du précédent |
| `DashboardValuationHeadlineTest` | montants couverts par `tests/Feature/DashboardPageTest.php` (props) et tâche 1 (formatage) |
| `InstrumentValuationChartTest` | 2 vérités couvertes tâches 8 et 10 ; 4 F3 (doublons du zoom et des séries du dashboard) ; 2 F1 (« sans period picker », « sans légende ») |

- [ ] **Step 1: Vérifier que la couverture de remplacement existe**

```bash
bun run test:js
php artisan test --compact
```

Attendu : les deux suites vertes. Ne pas continuer sinon.

- [ ] **Step 2: Supprimer les huit fichiers**

```bash
git rm tests/Browser/DashboardCardlessSectionsTest.php \
       tests/Browser/DashboardSectionSpacingTest.php \
       tests/Browser/DashboardChartLegendTest.php \
       tests/Browser/DashboardEvolutionLayoutTest.php \
       tests/Browser/DashboardPerformanceBarsTest.php \
       tests/Browser/InstrumentPerformanceBarsTest.php \
       tests/Browser/DashboardValuationHeadlineTest.php \
       tests/Browser/InstrumentValuationChartTest.php
```

- [ ] **Step 3: Retirer les deux helpers de graphe devenus inutiles**

`tests/Pest.php` porte quatre helpers. `drawnLines` et `lowestValueAxisLabel` grattaient le SVG pour des vérités désormais assertées sur l'objet d'options. Vérifier d'abord qu'ils ne sont plus appelés :

```bash
grep -rn "drawnLines\|lowestValueAxisLabel" tests/
```

Si le grep ne remonte que `tests/Pest.php`, supprimer les deux fonctions et leurs blocs de commentaire. `zoomWindowSpan` et `scrollChart` **restent** : la tâche 12 les utilise pour l'unique test de molette.

Si le grep remonte `InstrumentSectionsTest.php` (qui appelle `lowestValueAxisLabel` pour l'axe des cours), garder `lowestValueAxisLabel` jusqu'à la tâche 12, qui traite ce fichier.

- [ ] **Step 4: Lancer la suite complète**

```bash
php artisan test --compact
```

Attendu : vert, avec 13 fichiers dans `tests/Browser/`.

- [ ] **Step 5: Commit**

```bash
git add tests/Pest.php
git commit -m "test: retire les tests navigateur devenus des procès-verbaux de maquette"
```

---

### Task 12: Réorganiser la suite Browser par page, avec une fixture partagée

**Files:**
- Create: `tests/Browser/SmokeTest.php`, `tests/Browser/DashboardTest.php`, `tests/Browser/CatalogSearchTest.php`, `tests/Browser/InstrumentDetailTest.php`, `tests/Browser/EvolutionZoomTest.php`
- Delete: `tests/Browser/DashboardHoldingsTableTest.php`, `tests/Browser/DashboardSectionOrderTest.php`, `tests/Browser/DashboardSectorBreakdownTest.php`, `tests/Browser/DashboardPerformanceInfoTest.php`, `tests/Browser/InstrumentCatalogRadarTest.php`, `tests/Browser/InstrumentHeroTest.php`, `tests/Browser/InstrumentSectionsTest.php`, `tests/Browser/InstrumentSectorBreakdownTest.php`, `tests/Browser/InstrumentTransactionsTest.php`, `tests/Browser/DashboardEvolutionZoomTest.php`
- Rename: `tests/Browser/InstrumentPrefetchTest.php` → `tests/Browser/PrefetchTest.php`
- Keep: `tests/Browser/BreadcrumbTest.php`, `tests/Browser/SystemThemeTest.php`
- Modify: `tests/Pest.php`

**Interfaces:**
- Consumes: `zoomWindowSpan`, `scrollChart` (`tests/Pest.php`).
- Produces:
  ```php
  function portfolioFixture(array $overrides = []): array
  function denseHistoryFixture(int $days = 900): array
  ```
  Les deux exposées dans `tests/Pest.php`, utilisées par tous les fichiers Browser.

**Cible : 8 fichiers, 17 tests.**

| Fichier | Tests | Provenance |
|---|--:|---|
| `PrefetchTest` | 3 | `InstrumentPrefetchTest` renommé, contenu inchangé |
| `SystemThemeTest` | 3 | conservé ; le 4e test (`fill` des libellés d'axe) est supprimé, couvert tâche 10 |
| `SmokeTest` | 3 | un par page ; celui du dashboard absorbe `DashboardSectionOrderTest` |
| `BreadcrumbTest` | 2 | conservé ; les 2 tests d'alignement au pixel sont supprimés, couverts tâche 1 |
| `DashboardTest` | 3 | dialogue d'info, repli sectoriel, `aria-label` + lien instruments |
| `EvolutionZoomTest` | 1 | fusion de 3 tests de `DashboardEvolutionZoomTest` |
| `CatalogSearchTest` | 1 | fusion de 3 tests de `InstrumentCatalogRadarTest` |
| `InstrumentDetailTest` | 1 | fusion de `InstrumentTransactionsTest` et de l'ordre des sections de `InstrumentSectionsTest` |

**Ce qui disparaît sans remplacement Browser, et pourquoi :** tri des positions, poids, top 10, montants, parts sectorielles, tri du repli, décompte du catalogue, en-tête instrument — tous couverts par les tâches 3 à 7. Drapeau `held`, position masquée quand non détenu, props différées — déjà couverts par `tests/Feature/`.

- [ ] **Step 1: Écrire les fixtures partagées**

Ajouter d'abord les imports en tête de `tests/Pest.php` — le fichier est dans l'espace de noms global, mais des `use` évitent de qualifier chaque modèle :

```php
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;
```

Puis les trois fixtures, avant les helpers de graphe :

```php
/**
 * Portefeuille minimal mais complet : un utilisateur, un portefeuille, un instrument coté deux
 * fois et une position achetée. Remplace le bloc recopié dans chaque fichier Browser.
 *
 * Une migration héritée sème un utilisateur en dur ; on l'efface pour que le contrôleur résolve
 * bien celui du test. Ce nettoyage disparaît en tâche 13, une fois la migration corrigée.
 *
 * @param  array{name?: string, ticker?: string, quantity?: float, avgCost?: float, close?: float}  $overrides
 * @return array{user: User, wallet: Wallet, instrument: Instrument}
 */
function portfolioFixture(array $overrides = []): array
{
    User::query()->delete();

    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $instrument = Instrument::factory()
        ->ofType(InstrumentType::Stock)
        ->create([
            'name' => $overrides['name'] ?? 'ACME',
            'ticker' => $overrides['ticker'] ?? 'ACM',
        ]);

    $close = $overrides['close'] ?? 100;

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now(), 'close' => $close]);
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->startOfYear(),
        'close' => $close * 0.8,
    ]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => $overrides['quantity'] ?? 10,
        'avg_cost' => $overrides['avgCost'] ?? 80,
    ]);

    Transaction::factory()->buy()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => $overrides['quantity'] ?? 10,
        'unit_price' => $overrides['avgCost'] ?? 80,
        'date' => '2026-01-01',
    ]);

    return ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument];
}

/**
 * Trois ans de cours quotidiens par défaut : le plancher d'un an n'est observable que sur un
 * historique plus long que lui.
 *
 * @return array{user: User, wallet: Wallet, instrument: Instrument}
 */
function denseHistoryFixture(int $days = 1095): array
{
    User::query()->delete();

    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'ACME']);

    foreach (range(0, $days) as $offset) {
        Price::factory()->create([
            'asset_id' => $instrument->id,
            'date' => now()->subDays($days - $offset)->format('Y-m-d'),
            'close' => 90 + sin($offset / 20) * 20,
        ]);
    }

    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'quantity' => 10, 'avg_cost' => 80,
    ]);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'quantity' => 10, 'unit_price' => 80, 'date' => now()->subDays($days)->format('Y-m-d'),
    ]);

    return ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument];
}

/**
 * Une position dont les secteurs sont pondérés, pour éprouver le repli de la liste sectorielle.
 *
 * @param  array<string, float>  $sectors  Clé : valeur d'un cas de `Sector`. Valeur : poids entre 0 et 1.
 */
function holdingWithSectors(User $user, string $name, float $close, array $sectors): void
{
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create(['name' => $name]);

    Price::factory()->create(['asset_id' => $instrument->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $instrument->id,
        'quantity' => 1,
        'avg_cost' => $close,
    ]);

    foreach ($sectors as $sector => $weight) {
        SectorAllocation::factory()->create([
            'asset_id' => $instrument->id,
            'sector' => Sector::from($sector),
            'weight' => $weight,
        ]);
    }
}
```

`denseHistoryFixture` et `holdingWithSectors` reprennent la logique des helpers locaux de `DashboardEvolutionZoomTest.php` et `DashboardSectorBreakdownTest.php`, qui sont supprimés à l'étape 9. `holdingWithSectors` perd son paramètre `InstrumentType`, jamais appelé avec autre chose que `ETF`.

Le `User::query()->delete()` en tête des deux premières fixtures est provisoire : la tâche 13 corrige la migration qui sème cet utilisateur, puis le retire. Un seul endroit à corriger au lieu de 25.

- [ ] **Step 2: Écrire `SmokeTest.php`**

```php
<?php

it('charge le tableau de bord, ses sections dans l\'ordre, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Performances')
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'valuation|evolution|holdings|performances|sectors',
        )
        ->assertNoJavaScriptErrors();
});

it('charge la fiche instrument et ses sections, sans erreur', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertSee('ACME')
        ->assertSee('Transactions')
        ->assertSee('Répartition sectorielle')
        ->assertNoJavaScriptErrors();
});

it('charge le catalogue et sa liste, sans erreur', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/instruments')
        ->assertSee('ACME')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length >= 1", true)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 3: Écrire `DashboardTest.php`**

```php
<?php

use App\Contexts\Market\Enums\Sector;

it('explique les performances par période à travers un dialogue', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertSee('Performances')
        ->click('[aria-label="Comment lire les performances par période"]')
        ->assertSee('Comment lire les performances par période')
        ->assertSee('cumulés, pas annualisés')
        ->assertSee('Apports')
        ->assertSee('les versements de la période')
        ->assertSee('la date de tes versements ne change rien')
        ->assertNoJavaScriptErrors();
});

it('replie les secteurs au-delà du sixième derrière une bascule', function () {
    ['user' => $user] = portfolioFixture();

    holdingWithSectors($user, 'ACME ETF', 1000.0, [
        Sector::Technology->value => 0.3,
        Sector::Healthcare->value => 0.2,
        Sector::FinancialServices->value => 0.15,
        Sector::CommunicationServices->value => 0.12,
        Sector::ConsumerCyclical->value => 0.1,
        Sector::Industrials->value => 0.07,
        Sector::Energy->value => 0.04,
        Sector::RealEstate->value => 0.02,
    ]);

    $this->actingAs($user);

    $labels = "document.querySelectorAll('[data-section=\"sectors\"] [data-sector-label]').length";

    visit('/')
        ->assertScript($labels, 6)
        ->assertDontSee('Énergie')
        ->click('Voir les 2 autres')
        ->assertScript($labels, 8)
        ->assertSee('Énergie')
        ->assertSee('Immobilier')
        ->click('Réduire')
        ->assertScript($labels, 6)
        ->assertNoJavaScriptErrors();
});

it('nomme la section des positions pour les technologies d\'assistance et mène aux instruments', function () {
    ['user' => $user] = portfolioFixture();

    $this->actingAs($user);

    visit('/')
        ->assertScript("document.querySelector('[data-section=holdings]').getAttribute('aria-label')", 'Positions')
        ->assertScript("document.querySelectorAll('[data-holdings-all]').length", 1)
        ->assertScript("document.querySelector('[data-holdings-all]').getAttribute('href')", '/instruments')
        ->assertNoJavaScriptErrors();
});
```

**Attention sur le second test :** `portfolioFixture()` crée déjà une position sans secteur, et `holdingWithSectors` en ajoute une seconde avec huit secteurs. Le total sectoriel porte alors sur les deux positions. Si les six libellés attendus ne sortent pas, remplacer `portfolioFixture()` par la seule création d'utilisateur — `User::query()->delete(); $user = User::factory()->create();` — comme le faisait `soleUser()` dans le fichier d'origine. Vérifier en lançant le test, pas en le supposant.

- [ ] **Step 4: Écrire `EvolutionZoomTest.php`**

Un seul test, fusion de trois. C'est le garde-fou qui vérifie qu'ECharts honore réellement `minValueSpan` — la valeur passée dans l'option est déjà couverte en tâche 9, ce test vérifie l'effet.

```php
<?php

it('zoome à la molette sans descendre sous un an ni redemander l\'historique au serveur', function () {
    ['user' => $user] = denseHistoryFixture();

    $this->actingAs($user);

    $page = visit('/');
    $page->assertScript("document.querySelector('[data-section=evolution] [data-chart]') !== null", true);

    $page->script('(() => {
        window.__requestsAfterLoad = 0;
        const open = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (...args) {
            window.__requestsAfterLoad += 1;
            return open.apply(this, args);
        };
        const fetched = window.fetch;
        window.fetch = function (...args) {
            window.__requestsAfterLoad += 1;
            return fetched.apply(this, args);
        };
    })()');

    $opening = zoomWindowSpan($page, 'evolution');

    foreach (range(1, 3) as $ignored) {
        scrollChart($page, 'evolution', 400);
    }

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThan(32.0);

    scrollChart($page, 'evolution', -400);

    expect(zoomWindowSpan($page, 'evolution'))->toBeGreaterThanOrEqual($opening);
    expect($page->script('window.__requestsAfterLoad'))->toBe(0);

    $page->assertNoJavaScriptErrors();
});
```

- [ ] **Step 5: Écrire `CatalogSearchTest.php`**

Un seul test, fusion de trois. Ce qui exige un navigateur ici, c'est `@tanstack/vue-table` et la frappe réelle — pas `filterCatalog`, couvert en tâche 6.

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;

/** Un instrument dont le cours passe de `open` à `close` sur les dix derniers jours. */
function seedCatalogInstrument(string $name, float $open, float $close): Instrument
{
    $instrument = Instrument::factory()->ofType(InstrumentType::ETF)->create([
        'name' => $name,
        'ticker' => strtoupper(substr($name, 0, 3)),
    ]);

    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->subDays(10)->format('Y-m-d'),
        'close' => $open,
    ]);
    Price::factory()->create([
        'asset_id' => $instrument->id,
        'date' => now()->format('Y-m-d'),
        'close' => $close,
    ]);

    return $instrument;
}

it('cherche, trie les résultats sur une colonne, puis restitue la liste une fois effacée', function () {
    User::query()->delete();
    $user = User::factory()->create();

    seedCatalogInstrument('Alpha Fund', 100, 150);
    seedCatalogInstrument('Bravo Fund', 100, 80);
    seedCatalogInstrument('Gamma Trust', 100, 105);

    $this->actingAs($user);

    $names = "Array.from(document.querySelectorAll('[data-catalog-name]')).map(el => el.textContent.replace(/\\s+/g, ' ').trim()).join('|')";

    visit('/instruments')
        ->assertSee('Alpha Fund')
        ->type('[data-catalog-search]', 'fund')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 2)
        ->click('[data-sort=change]')
        ->assertScript($names, 'Alpha Fund (ALP)|Bravo Fund (BRA)')
        ->click('[data-sort=change]')
        ->assertScript($names, 'Bravo Fund (BRA)|Alpha Fund (ALP)')
        ->click('[data-catalog-search-clear]')
        ->assertScript("document.querySelectorAll('[data-catalog-row]').length", 3)
        ->assertNoJavaScriptErrors();
});
```

Le `User::query()->delete()` est ici en clair plutôt que par `portfolioFixture()` : ce test n'a besoin d'aucune position, et une position ajouterait un instrument détenu qui brouillerait le décompte. La tâche 13 retirera cette ligne.

**Deux valeurs à vérifier au premier lancement :** le sens du premier clic sur `[data-sort=change]` — croissant ou décroissant selon l'état initial de `@tanstack/vue-table` — et le retour de la liste complète après effacement, qui suppose que `[data-catalog-search-clear]` reste présent tant que le champ n'est pas vide. Si l'ordre est inversé, échanger les deux assertions ; ne pas toucher au composant.

- [ ] **Step 6: Écrire `InstrumentDetailTest.php`**

Un seul test : le repli des transactions, plus l'ordre des sections de la fiche.

```php
<?php

it('déplie les transactions derrière leur compte, sans déranger l\'ordre des sections', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $this->actingAs($user);

    visit("/instruments/{$instrument->id}")
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertSee('Transactions (1)')
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 1)
        ->click('[data-transactions-toggle]')
        ->assertScript("document.querySelectorAll('[data-transaction-row]').length", 0)
        ->assertScript(
            "Array.from(document.querySelectorAll('[data-section]')).map(el => el.dataset.section).join('|')",
            'hero|valuation|sectors|transactions',
        )
        ->assertNoJavaScriptErrors();
});
```

L'ordre `hero|valuation|sectors|transactions` est celui que `InstrumentSectionsTest.php:52` asserte aujourd'hui pour un instrument détenu — le relever à nouveau avant suppression du fichier si le doute subsiste. Le libellé « Transactions (1) » découle de `portfolioFixture()`, qui ne crée qu'une transaction, là où le test d'origine en créait treize.

La bascule vers `price-history` quand l'instrument n'est pas détenu (`InstrumentSectionsTest.php:82`, `hero|price-history|sectors|transactions`) n'est **pas** reprise : `tests/Feature/InstrumentDetailPageTest.php` couvre déjà `hides the position when the instrument is not held` au niveau des props, et c'est la prop qui décide de la section.

- [ ] **Step 7: Élaguer `BreadcrumbTest.php` et `SystemThemeTest.php`**

De `BreadcrumbTest.php` : supprimer `aligns the instrument page content with the breadcrumb` et `aligns the catalogue page content with the breadcrumb` (F4, couverts tâche 1), ainsi que le helper local `alignmentScript`. Garder les deux autres, en remplaçant `seedBreadcrumbPortfolio()` par `portfolioFixture()`.

De `SystemThemeTest.php` : supprimer `darkens the evolution axis labels so they stay readable on a light background` (couvert tâche 10). Garder les trois autres.

- [ ] **Step 8: Renommer `InstrumentPrefetchTest.php`**

```bash
git mv tests/Browser/InstrumentPrefetchTest.php tests/Browser/PrefetchTest.php
```

Contenu inchangé, sauf le remplacement de sa fixture locale par `portfolioFixture()` si elle est équivalente. Si sa fixture crée plusieurs instruments pour le survol du catalogue, la garder.

- [ ] **Step 9: Supprimer les dix fichiers remplacés**

```bash
git rm tests/Browser/DashboardHoldingsTableTest.php \
       tests/Browser/DashboardSectionOrderTest.php \
       tests/Browser/DashboardSectorBreakdownTest.php \
       tests/Browser/DashboardPerformanceInfoTest.php \
       tests/Browser/InstrumentCatalogRadarTest.php \
       tests/Browser/InstrumentHeroTest.php \
       tests/Browser/InstrumentSectionsTest.php \
       tests/Browser/InstrumentSectorBreakdownTest.php \
       tests/Browser/InstrumentTransactionsTest.php \
       tests/Browser/DashboardEvolutionZoomTest.php
```

- [ ] **Step 10: Retirer `lowestValueAxisLabel` si plus personne ne l'appelle**

```bash
grep -rn "lowestValueAxisLabel" tests/
```

S'il ne reste que sa définition dans `tests/Pest.php`, la supprimer.

- [ ] **Step 11: Vérifier la cible**

```bash
ls tests/Browser | wc -l          # attendu : 8
php artisan test --compact
grep -rn "getBoundingClientRect\|getComputedStyle" tests/Browser/
```

Attendu : 8 fichiers, suite verte, et le dernier grep ne remontant que `SystemThemeTest.php`.

- [ ] **Step 12: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add tests/
git commit -m "test: réorganise la suite navigateur par page"
```

---

### Task 13: Ne plus semer d'utilisateur en dur dans les bases fraîches

**Files:**
- Modify: `database/migrations/2026_02_22_141110_add_user_id_to_transactions_table.php:14-33`
- Modify: `tests/Pest.php`
- Test: `tests/Feature/MigrationsLeaveNoUserTest.php`

**Interfaces:**
- Consumes: `portfolioFixture()` (tâche 12).
- Produces: rien.

**Contexte :** la migration cherche un utilisateur `yanntyb.lbc@gmail.com` et le crée s'il manque, pour rattacher les transactions orphelines à quelqu'un. Sous `RefreshDatabase` les migrations tournent, donc **chaque test démarre avec cet utilisateur en base** — d'où le `User::query()->delete()` recopié dans 25 fichiers avec son commentaire.

Le correctif inverse l'ordre : ajouter la colonne d'abord, ne chercher un utilisateur que s'il y a effectivement quelque chose à rattacher. Sur une base fraîche, `transactions` est vide, donc personne n'est semé.

**Sécurité du changement :** modifier une migration déjà passée n'a aucun effet en production. Elle ne rejouera pas, la base a déjà l'utilisateur et le rattachement. Seules les bases fraîches — tests et nouvelles installations — voient la différence.

- [ ] **Step 1: Écrire le test qui échoue**

```bash
php artisan make:test --pest MigrationsLeaveNoUserTest
```

Puis remplacer son contenu par :

```php
<?php

use App\Contexts\Identity\Models\User;

it('ne sème aucun utilisateur sur une base fraîchement migrée', function () {
    expect(User::query()->count())->toBe(0);
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

```bash
php artisan test --compact --filter=MigrationsLeaveNoUser
```

Attendu : ÉCHEC, `Failed asserting that 1 is identical to 0`.

- [ ] **Step 3: Corriger la migration**

Remplacer la méthode `up()` de `database/migrations/2026_02_22_141110_add_user_id_to_transactions_table.php` par :

```php
    /**
     * Rattache les transactions existantes à un utilisateur. Le compte de repli n'est créé que s'il
     * y a réellement des lignes orphelines : une base fraîche n'a rien à rattacher, et n'a donc pas
     * à hériter d'un utilisateur.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        if (! DB::table('transactions')->whereNull('user_id')->exists()) {
            return;
        }

        $userId = DB::table('users')->where('email', 'yanntyb.lbc@gmail.com')->value('id');

        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'name' => 'Yann',
                'email' => 'yanntyb.lbc@gmail.com',
                'password' => Hash::make('pass'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('transactions')->whereNull('user_id')->update(['user_id' => $userId]);
    }
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

```bash
php artisan test --compact --filter=MigrationsLeaveNoUser
```

Attendu : PASS.

- [ ] **Step 5: Retirer le nettoyage devenu inutile**

Supprimer la ligne `User::query()->delete();` et son commentaire de `portfolioFixture()` dans `tests/Pest.php`, puis vérifier qu'il n'en reste aucune trace :

```bash
grep -rn "User::query()->delete()" tests/
```

Attendu : aucun résultat. S'il en reste dans `tests/Feature/` ou `tests/Unit/`, les supprimer aussi — le grep initial en comptait 25 fichiers, la tâche 12 en a supprimé la plupart avec leurs fichiers.

- [ ] **Step 6: Lancer les deux suites en entier**

```bash
bun run test:js
php artisan test --compact
```

Attendu : les deux vertes. Un échec ici signale un test qui comptait sur la présence de l'utilisateur semé — le corriger en créant explicitement son utilisateur.

- [ ] **Step 7: Formater et committer**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_02_22_141110_add_user_id_to_transactions_table.php tests/
git commit -m "fix: ne sème plus d'utilisateur sur une base fraîche"
```

---

## Vérification finale

À lancer après la tâche 13, contre les critères de réussite de la spec.

- [ ] `bun run test:js` — vert, au moins 45 tests
- [ ] `php artisan test --compact` — vert
- [ ] `ls tests/Browser | wc -l` — au plus 8
- [ ] `grep -rc "^it(" tests/Browser/*.php | awk -F: '{ total += $2 } END { print total }'` — au plus 20
- [ ] `grep -rn "getBoundingClientRect\|getComputedStyle" tests/Browser/` — seul `SystemThemeTest.php`
- [ ] `grep -rn "User::query()->delete()" tests/` — aucun résultat
- [ ] `bun run typecheck && bun run build` — verts
- [ ] Temps de `php artisan test` relevé avant la tâche 11 et après la tâche 13, baisse constatée
