# Fin du jumelage, volet front (F1) — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Retirer les copies mécaniques du front : un seul bloc différé, un seul journal, un seul résumé, une seule ventilation sectorielle, un seul dialecte « pas encore arrivé », et des types TS alignés sur les Datas que le back envoie désormais.

**Architecture:** Un composant `DeferredBlock` enveloppe `<Deferred>` avec son squelette et son message hors-ligne. Les trois sections de journal, les deux résumés et les quatre ventilations fusionnent chacune en un composant paramétré par des props, pas par la page. `TransactionLine` devient l'unique type d'opération, avec `assetId` et `assetName` nullables que le back envoie sur toutes les pages depuis le volet back. `null` signifie « pas encore arrivé », un tableau vide « rien à montrer ».

**Tech Stack:** Vue 3.5, TypeScript 5, Inertia v3 (`@inertiajs/vue3`), Pinia, Vitest 4 + happy-dom, Tailwind v4, `vue-tsc`.

**Spec:** `docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md` (section « Front »). Prérequis : le plan back (`2026-09-03-fin-du-jumelage-back.md`) est exécuté, le serveur envoie `assetId`/`assetName` sur chaque ligne de journal, `assetId` sur une position, `netContributions` sur un aperçu.

## Global Constraints

- Lire `.ai/rules/index.md` et les règles couvrant `resources/js/components/transactions/**` (`transactions.md`), `resources/js/components/ui/**` (`ui.md`), `resources/js/pwa/**` (`pwa.md`) avant d'éditer.
- Aucune dépendance ajoutée (`package.json` intact).
- Tout texte visible est en français.
- Après chaque tâche : `bun run typecheck && bun run test:js`. Les deux doivent être verts avant le commit.
- Les attributs `data-*` que les tests navigateur lisent ne changent pas : `data-section="valuation|wealth-summary|wealth-transactions|class-transactions|wallet-transactions|transactions|wealth-sectors|sectors|analysis|wallet-breakdown|instruments"`, `data-portfolio-value`, `data-portfolio-gain-pct`, `data-portfolio-meta`, `data-wealth-value`, `data-sectors-block`, `data-transaction-add`, `data-transaction-year`, `data-transaction-row`, `data-catalog-link`, `data-instrument-empty`.
- Aucun changement de rendu visible, hors : le squelette du bloc sectoriel de l'analyse devient trois lignes (au lieu d'un rectangle de 280 px), la pastille de gain d'une exposition se cache quand le pourcentage est nul (règle déjà appliquée au tableau de bord), et une enveloppe sans position dit « Aucune position dans cette enveloppe. » dans sa répartition au lieu d'une liste vide.
- Un commit par tâche, message en français, préfixe `feat:`/`refactor:`/`test:`/`docs:`, terminé par ces deux lignes exactes :
  ```
  Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
  Claude-Session: https://claude.ai/code/session_01K9NHPz4UqL4C63dHokq2j3
  ```
- Suppressions de tests autorisées : celles des composants fusionnés (leurs cas sont repris par le test du composant fusionné). Rien d'autre.

---

## Carte des fichiers

| Fichier | Sort |
| --- | --- |
| `resources/js/components/DeferredBlock.vue` (+ `.test.ts`) | créé (tâche 1) ; 19 sites `<Deferred>` basculent dessus |
| `resources/js/lib/instrument.ts`, `wealth.ts`, `portfolio.ts`, `snapshotContract.ts`, `components/transactions/TransactionYearList.vue`, tests associés | types alignés (2) |
| `resources/js/components/transactions/TransactionsSection.vue` (+ `.test.ts`) | créé ; `instrument/TransactionsSection.vue`, `instruments/TransactionsSection.vue`, `dashboard/WealthTransactionsSection.vue` (+ `.test.ts`) supprimés (3) |
| `resources/js/components/PortfolioSummarySection.vue` (+ `.test.ts`) | créé ; `instruments/ValuationSection.vue`, `dashboard/WealthSummarySection.vue` supprimés (4) |
| `resources/js/lib/sector.ts` (+ `.test.ts`), `resources/js/components/SectorsSection.vue` (+ `.test.ts`) | convertisseurs ajoutés, composant créé ; `instrument/SectorsSection.vue`, `instruments/SectorsBlock.vue`, `dashboard/WealthSectorsSection.vue` supprimés (5) |
| `resources/js/components/instruments/InstrumentsSection.vue` (+ `.test.ts`), `Pages/Wallet/Show.vue` (+ `.test.ts`) | `loaded` retiré, `null` seul dialecte (6) |
| `Pages/Dashboard.vue`, `Pages/AssetClass/Index.vue`, `Pages/Asset/Show.vue`, `Pages/Wallet/Show.vue`, `components/instruments/AnalysisSection.vue` | consommateurs mis à jour (3, 4, 5, 6) |
| `.ai/rules/` (nouvelle règle front), spec | (7) |

---

### Task 1: `DeferredBlock.vue` et ses dix-neuf sites

**Files:**
- Create: `resources/js/components/DeferredBlock.vue`
- Test: `resources/js/components/DeferredBlock.test.ts`
- Modify: les 19 fichiers listés à l'étape 4

**Interfaces:**
- Produces: `<DeferredBlock :data="clé | clés" :lines="3" line-class="h-8">` avec slot `#fallback` optionnel (remplace le squelette) et slot par défaut optionnel (défaut `<span />`, la sentinelle qu'Inertia exige). Rend toujours le slot `#rescue` « Données indisponibles hors-ligne. ». Les tâches 3, 5, 6 l'emploient.

- [ ] **Step 1: Écrire le test qui échoue**

```ts
import { describe, expect, it, vi } from 'vitest';
import { createApp, h, type VNode } from 'vue';

/**
 * `Deferred` demande un routeur monté. Son double rend ses trois slots côte à côte, chacun sous un
 * marqueur, pour que le test lise ce que le bloc met dans chacun.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: {
        props: { data: { type: [String, Array], required: true } },
        setup: (props: { data: string | string[] }, { slots }: { slots: Record<string, () => VNode[]> }) => () =>
            h('div', { 'data-deferred': String(props.data) }, [
                h('div', { 'data-slot': 'fallback' }, slots.fallback?.()),
                h('div', { 'data-slot': 'rescue' }, slots.rescue?.()),
                h('div', { 'data-slot': 'default' }, slots.default?.()),
            ]),
    },
}));

const { default: DeferredBlock } = await import('@/components/DeferredBlock.vue');

function mountBlock(props: Record<string, unknown>, slots: Record<string, () => VNode[]> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp({ render: () => h(DeferredBlock, props, slots) }).mount(host);

    return host;
}

describe('bloc différé', () => {
    it('nomme la prop attendue et pose trois lignes de squelette par défaut', () => {
        const host = mountBlock({ data: 'transactions' });

        expect(host.querySelector('[data-deferred="transactions"]')).not.toBeNull();

        const lines = host.querySelectorAll('[data-slot="fallback"] .animate-pulse');
        expect(lines).toHaveLength(3);
        expect(lines[0].classList.contains('h-8')).toBe(true);
    });

    it('adapte le nombre et la hauteur des lignes', () => {
        const lines = mountBlock({ data: 'analysis', lines: 6, lineClass: 'h-6' })
            .querySelectorAll('[data-slot="fallback"] .animate-pulse');

        expect(lines).toHaveLength(6);
        expect(lines[0].classList.contains('h-6')).toBe(true);
    });

    it('laisse un appelant fournir son propre squelette', () => {
        const host = mountBlock({ data: 'series' }, { fallback: () => [h('div', { 'data-chart-skeleton': '' })] });

        expect(host.querySelector('[data-slot="fallback"] [data-chart-skeleton]')).not.toBeNull();
        expect(host.querySelector('[data-slot="fallback"] .animate-pulse')).toBeNull();
    });

    it('dit une seule fois que les données sont indisponibles hors-ligne, et pose la sentinelle', () => {
        const host = mountBlock({ data: 'income' });

        expect(host.querySelector('[data-slot="rescue"]')?.textContent?.trim()).toBe('Données indisponibles hors-ligne.');
        expect(host.querySelector('[data-slot="default"] span')).not.toBeNull();
    });
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `bun run test:js -- resources/js/components/DeferredBlock.test.ts`
Expected: FAIL, module introuvable.

- [ ] **Step 3: Écrire `DeferredBlock.vue`**

```vue
<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';

/**
 * Le bloc d'attente d'une prop différée : le squelette pendant le chargement, le message hors-ligne
 * quand la prop est rescapée sans valeur, et la sentinelle vide qu'Inertia exige en contenu — le
 * vrai contenu est rendu par l'appelant dans sa branche `v-if`, une fois la prop arrivée.
 *
 * Dix-neuf sections recopiaient ces quinze lignes. Un appelant qui a son propre squelette (un
 * graphe) le passe par le slot `fallback`.
 */
withDefaults(
    defineProps<{
        /** La ou les props Inertia attendues, telles que `<Deferred :data>` les prend. */
        data: string | string[];
        /** Lignes du squelette par défaut. */
        lines?: number;
        /** Hauteur d'une ligne : `h-8` pour une liste, `h-6` pour une grille serrée, `h-16` pour des cartes. */
        lineClass?: string;
    }>(),
    { lines: 3, lineClass: 'h-8' },
);
</script>

<template>
    <Deferred :data="data">
        <template #fallback>
            <slot name="fallback">
                <div class="flex flex-col gap-2">
                    <div
                        v-for="n in lines"
                        :key="n"
                        class="w-full animate-pulse rounded-md bg-muted"
                        :class="lineClass"
                    ></div>
                </div>
            </slot>
        </template>

        <template #rescue>
            <p data-deferred-rescue class="py-8 text-center text-sm text-muted-foreground">
                Données indisponibles hors-ligne.
            </p>
        </template>

        <slot><span /></slot>
    </Deferred>
</template>
```

Run: `bun run test:js -- resources/js/components/DeferredBlock.test.ts` → 4 passed.

- [ ] **Step 4: Basculer les dix-neuf sites**

Dans chaque fichier, remplacer le bloc `<Deferred v-else ...> … </Deferred>` (du `<Deferred` au `</Deferred>` inclus) par la ligne indiquée, et ajouter `import DeferredBlock from '@/components/DeferredBlock.vue';`. Retirer `Deferred` de l'import `@inertiajs/vue3` quand plus rien ne l'utilise dans le fichier. `ChartSkeleton` reste importé là où il passe dans le slot `fallback`.

| Fichier | Remplacement |
| --- | --- |
| `components/dashboard/WealthTransactionsSection.vue` | `<DeferredBlock v-else data="transactions" />` |
| `components/dashboard/WealthSectorsSection.vue` | `<DeferredBlock v-else data="sectors" />` |
| `components/dashboard/WealthIncomeSection.vue` — le SECOND `Deferred` (celui du corps, ligne ~72) | `<DeferredBlock v-else data="income" />` |
| `components/dashboard/WealthAccountsSection.vue` | `<DeferredBlock v-else data="accounts" :lines="2" line-class="h-16" />` |
| `components/dashboard/WealthEvolutionSection.vue` | `<DeferredBlock v-else data="series"><template #fallback><div class="px-6"><ChartSkeleton /></div></template></DeferredBlock>` |
| `components/instruments/TransactionsSection.vue` | `<DeferredBlock v-else data="transactions" />` |
| `components/instruments/InstrumentsSection.vue` | `<DeferredBlock v-else :data="props.deferKey ?? 'positions'" />` |
| `components/instruments/IncomeSection.vue` | `<DeferredBlock v-else data="income" />` |
| `components/instruments/AnalysisSection.vue` — premier bloc (`basketAnalysis`, ~l.117) | `<DeferredBlock v-else data="basketAnalysis" :lines="6" line-class="h-6" />` |
| `components/instruments/AnalysisSection.vue` — second bloc (`performances`, ~l.155) | `<DeferredBlock v-else data="performances" :lines="5" />` |
| `components/instruments/SectorsBlock.vue` | `<DeferredBlock v-else data="sectorBreakdown"><template #fallback><div class="h-[280px] w-full animate-pulse rounded-md bg-muted"></div></template></DeferredBlock>` |
| `components/instrument/AnalysisSection.vue` | `<DeferredBlock v-else data="analysis" :lines="8" line-class="h-6" />` |
| `components/instrument/PriceHistorySection.vue` | `<DeferredBlock v-else data="priceHistory"><template #fallback><ChartSkeleton /></template></DeferredBlock>` |
| `components/instrument/InstrumentChart.vue` | `<DeferredBlock v-else :data="deferKey"><template #fallback><div class="px-6"><ChartSkeleton /></div></template></DeferredBlock>` |
| `components/ValueVsInvestedChart.vue` | `<DeferredBlock v-else :data="deferKey"><template #fallback><div class="px-6"><ChartSkeleton /></div></template></DeferredBlock>` |
| `components/property/PropertyLoanSection.vue` | `<DeferredBlock v-else data="amortization" :lines="6" line-class="h-6" />` |
| `components/properties/ProfitabilitySection.vue` | `<DeferredBlock v-else data="profitability" />` |
| `components/properties/RentalIncomeSection.vue` | `<DeferredBlock v-else data="income" />` |
| `Pages/Wallet/Show.vue` (bloc `breakdown`) | `<DeferredBlock v-else data="breakdown" />` |

Ne PAS toucher au premier `Deferred` de `WealthIncomeSection.vue` (slot `#value`, ligne ~40) : son squelette est un `span` en ligne et son rescue un tiret, ce n'est pas le même bloc.

- [ ] **Step 5: Vérifier**

```bash
grep -rn 'Données indisponibles hors-ligne' resources/js --include='*.vue' | wc -l   # attendu : 1
grep -rln '<Deferred ' resources/js --include='*.vue'                                # attendu : DeferredBlock.vue et WealthIncomeSection.vue seulement
bun run typecheck && bun run test:js
```
Expected: 1 ; deux fichiers ; tout vert. Les tests existants qui mockent `Deferred` à vide (`WealthTransactionsSection.test.ts`, `InstrumentsSection.test.ts`, `Pages/Wallet/Show.test.ts`, `WealthAccountsSection.test.ts`…) restent verts : `DeferredBlock` rend `Deferred`, donc rien, comme avant.

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "refactor: DeferredBlock, un seul squelette et un seul message hors-ligne pour dix-neuf sections"
```

---

### Task 2: Un seul type d'opération, et les deux clés que le back envoie désormais

**Files:**
- Modify: `resources/js/lib/instrument.ts`, `resources/js/lib/wealth.ts`, `resources/js/lib/portfolio.ts`, `resources/js/lib/snapshotContract.ts`
- Modify: `resources/js/components/transactions/TransactionYearList.vue`
- Modify: `resources/js/components/dashboard/WealthTransactionsSection.vue`, `resources/js/components/instruments/TransactionsSection.vue` (types seulement ; supprimés à la tâche 3)
- Modify: `resources/js/Pages/Dashboard.vue`, `Pages/AssetClass/Index.vue`, `Pages/Wallet/Show.vue` (types des props)
- Modify (tests) : `stores/transactionDialog.test.ts`, `components/transactions/TransactionYearList.test.ts`, `components/dashboard/WealthTransactionsSection.test.ts`, `lib/instrument.test.ts`, `lib/transactionForm.test.ts`, et tout fixture que `vue-tsc` signale

**Interfaces:**
- Produces: `TransactionLine` porte `assetId: number | null` et `assetName: string | null`. `NamedTransactionLine` et `WealthTransactionLine` n'existent plus. `transactionLabelOf(line: TransactionLine)`. `InstrumentPosition.assetId: number`. `PortfolioOverview.netContributions: number`.

- [ ] **Step 1: `lib/instrument.ts`**

Dans `TransactionLine`, après `date: string;` :

```ts
    /**
     * L'actif de la ligne, nul sur un versement ou un retrait qui n'en ont pas. Porté par toutes
     * les pages depuis que le serveur sert un seul journal : hors de sa fiche, une quantité ne dit
     * pas de quoi elle est la quantité.
     */
    assetId: number | null;
    assetName: string | null;
```

Supprimer l'interface `NamedTransactionLine` et son docblock. `transactionLabelOf` prend un `TransactionLine`. Dans `InstrumentPosition`, ajouter en tête `assetId: number;`.

- [ ] **Step 2: `lib/wealth.ts`, `lib/portfolio.ts`, `lib/snapshotContract.ts`**

- `wealth.ts` : supprimer `export type WealthTransactionLine = NamedTransactionLine;`, son docblock et `import type { NamedTransactionLine } from '@/lib/instrument';`.
- `portfolio.ts`, dans `PortfolioOverview` après `totalRealizedGain` :
  ```ts
      /** Apports nets du périmètre : versements moins retraits, ce à quoi l'investi se compare. */
      netContributions: number;
  ```
- `snapshotContract.ts` : remplacer `NamedTransactionLine` et `WealthTransactionLine` par `TransactionLine` (importé depuis `./instrument`), retirer les imports devenus inutiles.

- [ ] **Step 3: Composants et pages**

- `TransactionYearList.vue` : `const assetNameOf = (line: TransactionLine): string | null => line.assetName;` et retirer `NamedTransactionLine` de l'import.
- `WealthTransactionsSection.vue`, `instruments/TransactionsSection.vue` : `WealthTransactionLine`/`NamedTransactionLine` → `TransactionLine` (import depuis `@/lib/instrument`), et les `as …` deviennent inutiles : `@edit="dialog.openEdit($event)"`, `@delete="dialog.askDeleteLine($event, transactionLabelOf($event))"`.
- `Pages/Dashboard.vue`, `Pages/AssetClass/Index.vue`, `Pages/Wallet/Show.vue` : la prop `transactions?: …[]` est typée `TransactionLine[]`, imports ajustés.

- [ ] **Step 4: Tests et fixtures**

Run: `bun run typecheck`
Expected: des erreurs sur les fixtures qui construisent un `TransactionLine` sans `assetId`/`assetName`, un `InstrumentPosition` sans `assetId`, un `PortfolioOverview` sans `netContributions`. Pour chacune :
- `TransactionLine` nu (variante `bare`) : ajouter `assetId: null, assetName: null` ou l'actif réel si le test le nomme ;
- `NamedTransactionLine` → `TransactionLine` dans `stores/transactionDialog.test.ts`, `TransactionYearList.test.ts`, `lib/instrument.test.ts`, `lib/transactionForm.test.ts`, `WealthTransactionsSection.test.ts` (qui perd aussi `WealthTransactionLine`) ;
- `InstrumentPosition` : ajouter `assetId: <id du test>` ;
- `PortfolioOverview` : ajouter `netContributions: 0` (ou la valeur qu'un test attend).

Relancer jusqu'à zéro erreur, puis `bun run test:js`.

- [ ] **Step 5: Vérifier**

```bash
grep -rn 'NamedTransactionLine\|WealthTransactionLine' resources/js   # attendu : vide
bun run typecheck && bun run test:js
```

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "refactor: un seul type TransactionLine, nommant son actif sur toutes les pages"
```

---

### Task 3: Un seul `TransactionsSection`

**Files:**
- Create: `resources/js/components/transactions/TransactionsSection.vue`
- Move: `resources/js/components/dashboard/WealthTransactionsSection.test.ts` → `resources/js/components/transactions/TransactionsSection.test.ts`
- Delete: `resources/js/components/instrument/TransactionsSection.vue`, `resources/js/components/instruments/TransactionsSection.vue`, `resources/js/components/dashboard/WealthTransactionsSection.vue`
- Modify: `Pages/Dashboard.vue`, `Pages/AssetClass/Index.vue`, `Pages/Wallet/Show.vue`, `Pages/Asset/Show.vue`

**Interfaces:**
- Consumes: `DeferredBlock` (1), `TransactionLine` (2), `TransactionYearList` (`lines`, `variant: 'named' | 'bare'`, `emptyLabel`, `editable`, émet `edit`/`delete`), `AddTransactionButton` (`assetId?`, `assetName?`, `walletId?`, `walletName?`), `useTransactionDialogStore().openEdit(line, asset?)` et `.askDeleteLine(line, label)`, `transactionLabelOf`.
- Produces: `<TransactionsSection :transactions="lines | null | undefined" section="…" empty-label="…" variant="named|bare" defer-key="transactions" :asset="{id,name}" :wallet="{id,name}" />`. Défauts : `section: 'transactions'`, `emptyLabel: "Aucune transaction pour l'instant."`, `variant: 'named'`, `deferKey: 'transactions'`.

- [ ] **Step 1: Déplacer et étendre le test**

```bash
git mv resources/js/components/dashboard/WealthTransactionsSection.test.ts resources/js/components/transactions/TransactionsSection.test.ts
```

Dans le fichier déplacé :
- l'import devient `const { default: TransactionsSection } = await import('@/components/transactions/TransactionsSection.vue');`
- `mountSection` prend des props : 
  ```ts
  function mountSection(transactions: TransactionLine[] | null, props: Record<string, unknown> = {}): HTMLElement {
      const host = document.createElement('div');
      document.body.append(host);

      createApp(TransactionsSection, {
          transactions,
          section: 'wealth-transactions',
          emptyLabel: 'Aucune transaction pour l\'instant.',
          ...props,
      }).use(createPinia()).mount(host);

      return host;
  }
  ```
- les `describe` existants restent tels quels (ils lisent `data-section="wealth-transactions"`, le libellé vide, le bouton d'ajout, la confirmation de suppression).
- ajouter en fin de fichier :

```ts
describe('variante nue d\'une fiche', () => {
    it('impose l\'actif de la fiche à la saisie et masque la colonne de l\'actif', async () => {
        const host = mountSection([line()], {
            variant: 'bare',
            section: 'transactions',
            asset: { id: 7, name: 'Bitcoin' },
            emptyLabel: 'Aucune transaction sur cet actif.',
        });

        await click(host.querySelector('[data-section-toggle]'));
        await click(host.querySelector('[data-transaction-year]'));

        expect(host.querySelector('[data-transaction-asset]')).toBeNull();

        await click(host.querySelector('[data-transaction-add]'));

        const dialog = useTransactionDialogStore();
        expect(dialog.mode).toBe('create');
        expect(dialog.lockedAssetId).toBe(7);
    });
});

describe('journal pas encore arrivé', () => {
    it('ne dit rien : ni année, ni libellé vide, le squelette seul attend', async () => {
        const host = mountSection(null);

        await click(host.querySelector('[data-section-toggle]'));

        expect(host.querySelector('[data-transaction-year]')).toBeNull();
        expect(host.textContent).not.toContain('Aucune transaction');
    });
});
```

Adapter le type de la fixture `line()` à `TransactionLine` (déjà fait à la tâche 2). Si `lockedAssetId` n'est pas le nom du champ du store, lire `stores/transactionDialog.ts` (`lockedAssetId`, `lockedAssetName` d'après `openCreate`) et corriger l'assertion, pas le composant.

- [ ] **Step 2: Vérifier l'échec**

Run: `bun run test:js -- resources/js/components/transactions/TransactionsSection.test.ts`
Expected: FAIL, module introuvable.

- [ ] **Step 3: Écrire le composant fusionné**

```vue
<script setup lang="ts">
import DeferredBlock from '@/components/DeferredBlock.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import AddTransactionButton from '@/components/transactions/AddTransactionButton.vue';
import TransactionYearList from '@/components/transactions/TransactionYearList.vue';
import { transactionLabelOf, type TransactionLine } from '@/lib/instrument';
import { useTransactionDialogStore } from '@/stores/transactionDialog';

/**
 * Le journal d'opérations d'une page : tableau de bord, exposition, enveloppe ou fiche, c'est la
 * même section. Repliée à l'arrivée, et la prop différée ne part qu'au dépli : le `DeferredBlock`
 * vit sous le pli de `CollapsibleSection`, qui ne monte son contenu qu'une fois ouvert — la page ne
 * paie l'historique que pour qui le demande. Une fiche sert ses lignes en synchrone et les passe
 * directement.
 *
 * `null` ou `undefined` : pas encore arrivé, squelette. `[]` : rien à montrer, `emptyLabel`.
 */
const props = withDefaults(
    defineProps<{
        transactions?: TransactionLine[] | null;
        /** Ce que nomme `data-section` : la lecture du DOM et les tests veulent savoir de quelle page il s'agit. */
        section?: string;
        emptyLabel?: string;
        /** `bare` sur une fiche, dont chaque ligne porte le même actif ; `named` partout ailleurs. */
        variant?: 'named' | 'bare';
        deferKey?: string;
        /** L'actif d'une fiche : imposé à la modale, on saisit ce qu'on regarde. */
        asset?: { id: number; name: string };
        /** L'enveloppe d'une page d'enveloppe : la saisie ouverte d'ici la reprend telle quelle. */
        wallet?: { id: number; name: string };
    }>(),
    {
        transactions: null,
        section: 'transactions',
        emptyLabel: 'Aucune transaction pour l\'instant.',
        variant: 'named',
        deferKey: 'transactions',
    },
);

const dialog = useTransactionDialogStore();
</script>

<template>
    <CollapsibleSection :section="props.section" title="Transactions">
        <!--
            Dans le slot `aside`, dont la bascule de dépli est une couche sœur : le clic sur « + »
            n'ouvre pas la section, et le bouton reste atteignable pli fermé.
        -->
        <template #aside>
            <AddTransactionButton
                :asset-id="props.asset?.id"
                :asset-name="props.asset?.name"
                :wallet-id="props.wallet?.id"
                :wallet-name="props.wallet?.name"
            />
        </template>

        <TransactionYearList
            v-if="props.transactions !== null && props.transactions !== undefined"
            :lines="props.transactions"
            :variant="props.variant"
            :empty-label="props.emptyLabel"
            editable
            @edit="dialog.openEdit($event, props.asset)"
            @delete="dialog.askDeleteLine($event, transactionLabelOf($event))"
        />

        <DeferredBlock v-else :data="props.deferKey" />
    </CollapsibleSection>
</template>
```

- [ ] **Step 4: Basculer les quatre pages, supprimer les trois anciens composants**

- `Pages/Dashboard.vue` : `import TransactionsSection from '@/components/transactions/TransactionsSection.vue';` et
  ```vue
  <TransactionsSection :transactions="transactions" section="wealth-transactions" />
  ```
- `Pages/AssetClass/Index.vue` :
  ```vue
  <TransactionsSection :transactions="transactions" section="class-transactions" empty-label="Aucune transaction sur cette classe." />
  ```
- `Pages/Wallet/Show.vue` :
  ```vue
  <TransactionsSection
      :transactions="props.transactions"
      section="wallet-transactions"
      empty-label="Aucune transaction sur cette enveloppe."
      :wallet="{ id: props.account.walletId, name: title }"
  />
  ```
- `Pages/Asset/Show.vue` :
  ```vue
  <TransactionsSection
      :transactions="props.instrument.transactions"
      variant="bare"
      empty-label="Aucune transaction sur cet actif."
      :asset="{ id: props.instrument.id, name: props.instrument.name }"
  />
  ```
  (`section` garde son défaut `transactions`, celui que la fiche portait.)

```bash
git rm resources/js/components/instrument/TransactionsSection.vue resources/js/components/instruments/TransactionsSection.vue resources/js/components/dashboard/WealthTransactionsSection.vue
```

- [ ] **Step 5: Vérifier**

```bash
grep -rn "instrument/TransactionsSection\|instruments/TransactionsSection\|WealthTransactionsSection" resources/js   # attendu : vide
bun run typecheck && bun run test:js
```
Expected: vide ; tout vert, dont `Pages/Wallet/Show.test.ts` « impose son enveloppe à la saisie ouverte depuis la section transactions ».

- [ ] **Step 6: Commit**

```bash
git add -A resources/js
git commit -m "refactor: un seul TransactionsSection pour le tableau de bord, les expositions, les enveloppes et les fiches"
```

---

### Task 4: Un seul résumé, `PortfolioSummarySection`

**Files:**
- Create: `resources/js/components/PortfolioSummarySection.vue`
- Test: `resources/js/components/PortfolioSummarySection.test.ts`
- Delete: `resources/js/components/instruments/ValuationSection.vue`, `resources/js/components/dashboard/WealthSummarySection.vue`
- Modify: `Pages/AssetClass/Index.vue`, `Pages/Dashboard.vue`

**Interfaces:**
- Consumes: `GainPill` (`value`, `label`), `InvestedGainMeta` (`invested`, `gain`, `realizedGain`, `originCash?`, `digits`), `eur(value, digits)`, `pct(value)` de `@/lib/format`.
- Produces: `<PortfolioSummarySection prefix="portfolio|wealth" section="…" :total-value :total-gain :total-gain-pct :invested :realized-gain :origin-cash? />`. Attributs rendus : `data-{prefix}-value`, `data-{prefix}-gain-pct`, `data-{prefix}-meta`.

- [ ] **Step 1: Écrire le test qui échoue**

```ts
import { describe, expect, it } from 'vitest';
import { createApp } from 'vue';
import PortfolioSummarySection from '@/components/PortfolioSummarySection.vue';

function mountSummary(props: Record<string, unknown> = {}): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(PortfolioSummarySection, {
        prefix: 'portfolio',
        section: 'valuation',
        totalValue: 1000,
        totalGain: 200,
        totalGainPct: 25,
        invested: 800,
        realizedGain: 0,
        ...props,
    }).mount(host);

    return host;
}

const squeeze = (text: string | null | undefined): string => (text ?? '').replace(/[\s ]+/g, ' ').trim();

describe('résumé d\'un portefeuille', () => {
    it('porte la valeur, la pastille et le détail sous les attributs du préfixe', () => {
        const host = mountSummary();

        expect(host.querySelector('[data-section="valuation"]')).not.toBeNull();
        expect(squeeze(host.querySelector('[data-portfolio-value]')?.textContent)).toBe('1 000 €');
        expect(squeeze(host.querySelector('[data-portfolio-gain-pct]')?.textContent)).toContain('25');
        expect(host.querySelector('[data-portfolio-meta]')).not.toBeNull();
    });

    it('change de préfixe pour le tableau de bord', () => {
        const host = mountSummary({ prefix: 'wealth', section: 'wealth-summary' });

        expect(host.querySelector('[data-wealth-value]')).not.toBeNull();
        expect(host.querySelector('[data-portfolio-value]')).toBeNull();
    });

    it('cache la pastille quand le pourcentage n\'existe pas', () => {
        expect(mountSummary({ totalGainPct: null }).querySelector('[data-portfolio-gain-pct]')).toBeNull();
    });
});
```

- [ ] **Step 2: Vérifier l'échec**

Run: `bun run test:js -- resources/js/components/PortfolioSummarySection.test.ts`
Expected: FAIL, module introuvable.

- [ ] **Step 3: Écrire le composant**

```vue
<script setup lang="ts">
import GainPill from '@/components/GainPill.vue';
import InvestedGainMeta from '@/components/InvestedGainMeta.vue';
import { eur, pct } from '@/lib/format';

/**
 * Le grand chiffre d'une page et ce qui l'explique : valeur, pastille de gain, investi et gain
 * détaillés. Le tableau de bord et les pages d'exposition en avaient chacun une copie à 95 %.
 * La pastille se cache sans pourcentage : un gain sans mise à laquelle le rapporter n'a pas de
 * pourcentage, et « 0 % » mentirait.
 */
const props = defineProps<{
    /** Préfixe des attributs `data-*` que les tests navigateur lisent : `portfolio` sur une exposition, `wealth` au tableau de bord. */
    prefix: 'portfolio' | 'wealth';
    section: string;
    totalValue: number;
    totalGain: number;
    totalGainPct: number | null;
    invested: number;
    realizedGain: number;
    /** Le cash d'origine d'une exposition ; sans objet au tableau de bord. */
    originCash?: number;
}>();
</script>

<template>
    <section :data-section="props.section" class="flex shrink-0 flex-col gap-1.5 px-6">
        <div class="flex flex-wrap items-baseline gap-3">
            <p v-bind="{ [`data-${props.prefix}-value`]: '' }" class="text-4xl font-bold tracking-[-0.02em] tabular-nums">
                {{ eur(props.totalValue, 0) }}
            </p>
            <GainPill
                v-if="props.totalGainPct !== null"
                v-bind="{ [`data-${props.prefix}-gain-pct`]: '' }"
                :value="props.totalGain"
                :label="pct(props.totalGainPct)"
            />
        </div>

        <InvestedGainMeta
            v-bind="{ [`data-${props.prefix}-meta`]: '' }"
            :invested="props.invested"
            :gain="props.totalGain"
            :realized-gain="props.realizedGain"
            :origin-cash="props.originCash"
            :digits="0"
        />
    </section>
</template>
```

Si `eur` n'accepte pas un second argument, lire sa signature dans `lib/format.ts` (`ValuationSection` appelait `formatEur(value, 0)` : elle l'accepte).

- [ ] **Step 4: Basculer les deux pages, supprimer les deux anciens composants**

- `Pages/AssetClass/Index.vue` :
  ```vue
  <PortfolioSummarySection
      v-if="overview.holdings.length"
      prefix="portfolio"
      section="valuation"
      :total-value="overview.totalValue"
      :total-gain="overview.totalGain"
      :total-gain-pct="overview.totalGainPct"
      :invested="overview.totalCost"
      :realized-gain="overview.totalRealizedGain"
      :origin-cash="overview.cash"
  />
  ```
- `Pages/Dashboard.vue` :
  ```vue
  <PortfolioSummarySection
      prefix="wealth"
      section="wealth-summary"
      :total-value="props.overview.totalValue"
      :total-gain="props.overview.totalGain"
      :total-gain-pct="props.overview.totalGainPct"
      :invested="props.overview.totalInvested"
      :realized-gain="props.overview.totalRealizedGain"
  />
  ```

```bash
git rm resources/js/components/instruments/ValuationSection.vue resources/js/components/dashboard/WealthSummarySection.vue
```

- [ ] **Step 5: Vérifier**

```bash
grep -rn "ValuationSection\|WealthSummarySection" resources/js   # attendu : vide
grep -rn "formatEur(value, 0)\|formatEur(v, 0)" resources/js/components   # attendu : au plus RealEstateSummarySection (hors périmètre)
bun run typecheck && bun run test:js
```

- [ ] **Step 6: Commit**

```bash
git add -A resources/js
git commit -m "refactor: PortfolioSummarySection, le résumé partagé du tableau de bord et des expositions"
```

---

### Task 5: Une seule ventilation sectorielle, convertie dans `lib/sector.ts`

**Files:**
- Modify: `resources/js/lib/sector.ts`, `resources/js/lib/sector.test.ts`
- Create: `resources/js/components/SectorsSection.vue`
- Test: `resources/js/components/SectorsSection.test.ts`
- Delete: `resources/js/components/instrument/SectorsSection.vue`, `resources/js/components/instruments/SectorsBlock.vue`, `resources/js/components/dashboard/WealthSectorsSection.vue`
- Modify: `resources/js/components/instruments/AnalysisSection.vue`, `Pages/Dashboard.vue`, `Pages/Asset/Show.vue`, `Pages/Wallet/Show.vue`

**Interfaces:**
- Consumes: `SectorBreakdownList` (`rows`, `collapsible`), `CollapsibleSection`, `DeferredBlock`, types `SectorWeight` (`lib/instrument`), `SectorSlice`/`WealthSector` (`label, value, pct`), `WalletClassSlice` (`label, value, share`).
- Produces: `rowsFromWeights(sectors: SectorWeight[], marketValue: number | null): SectorBreakdownRow[]`, `rowsFromSlices(slices: { label: string; value: number; pct: number }[]): SectorBreakdownRow[]`, `rowsFromShares(slices: { label: string; value: number; share: number }[]): SectorBreakdownRow[]`. `<SectorsSection :rows="rows | null" variant="section|block" section="…" title="Secteurs" defer-key="…" :collapsible="false" empty-label="…" />`. Défauts : `variant: 'section'`, `title: 'Secteurs'`, `deferKey: 'sectors'`, `collapsible: false`, `emptyLabel: 'Pas encore de données sectorielles.'`.

- [ ] **Step 1: Tests des convertisseurs**

À la fin de `resources/js/lib/sector.test.ts` (ajouter `rowsFromShares, rowsFromSlices, rowsFromWeights` à l'import) :

```ts
describe('conversion vers les lignes de la liste sectorielle', () => {
    it('répartit la valeur détenue sur des poids entre 0 et 1', () => {
        expect(rowsFromWeights([{ label: 'Tech', weight: 0.25 }], 1000)).toEqual([
            { label: 'Tech', share: 25, amount: 250 },
        ]);
    });

    it('laisse le montant nul quand rien n\'est détenu', () => {
        expect(rowsFromWeights([{ label: 'Tech', weight: 0.25 }], null)).toEqual([
            { label: 'Tech', share: 25, amount: null },
        ]);
    });

    it('lit une tranche déjà pesée, en pourcentage ou en part', () => {
        expect(rowsFromSlices([{ label: 'Santé', value: 300, pct: 30 }])).toEqual([{ label: 'Santé', share: 30, amount: 300 }]);
        expect(rowsFromShares([{ label: 'Actions', value: 600, share: 60 }])).toEqual([{ label: 'Actions', share: 60, amount: 600 }]);
    });
});
```

- [ ] **Step 2: Convertisseurs dans `lib/sector.ts`**

Après les interfaces :

```ts
import type { SectorWeight } from '@/lib/instrument';

/** Les secteurs d'un instrument, des poids entre 0 et 1 : seule une position détenue a une valeur à répartir. */
export const rowsFromWeights = (sectors: SectorWeight[], marketValue: number | null): SectorBreakdownRow[] =>
    sectors.map((sector: SectorWeight): SectorBreakdownRow => ({
        label: sector.label,
        share: sector.weight * 100,
        amount: marketValue === null ? null : marketValue * sector.weight,
    }));

/** Une tranche déjà pesée par le serveur, en pourcentage (`pct`) : portefeuille entier ou patrimoine. */
export const rowsFromSlices = (slices: { label: string; value: number; pct: number }[]): SectorBreakdownRow[] =>
    slices.map((slice): SectorBreakdownRow => ({ label: slice.label, share: slice.pct, amount: slice.value }));

/** Une tranche déjà pesée par le serveur, en part (`share`) : la répartition d'une enveloppe. */
export const rowsFromShares = (slices: { label: string; value: number; share: number }[]): SectorBreakdownRow[] =>
    slices.map((slice): SectorBreakdownRow => ({ label: slice.label, share: slice.share, amount: slice.value }));
```

Run: `bun run test:js -- resources/js/lib/sector.test.ts` → vert.

- [ ] **Step 3: Test du composant**

```ts
import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { SectorBreakdownRow } from '@/lib/sector';

/** `Deferred` demande un routeur monté ; le bloc d'attente ne rend rien ici, c'est ce qu'on vérifie. */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
}));

const { default: SectorsSection } = await import('@/components/SectorsSection.vue');

const rows: SectorBreakdownRow[] = [
    { label: 'Technologie', share: 60, amount: 600 },
    { label: 'Santé', share: 40, amount: 400 },
];

function mountSection(props: Record<string, unknown>): HTMLElement {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(SectorsSection, { section: 'wealth-sectors', deferKey: 'sectors', ...props }).mount(host);

    return host;
}

const open = async (host: HTMLElement): Promise<void> => {
    host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
    await nextTick();
};

describe('section sectorielle', () => {
    it('se replie sous un titre et liste les secteurs au dépli', async () => {
        const host = mountSection({ rows });

        expect(host.querySelector('[data-section="wealth-sectors"]')).not.toBeNull();
        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(0);

        await open(host);

        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(2);
    });

    it('dit l\'absence de secteurs quand la liste est arrivée vide', async () => {
        const host = mountSection({ rows: [] });
        await open(host);

        expect(host.textContent).toContain('Pas encore de données sectorielles.');
    });

    it('ne dit rien tant que la liste n\'est pas arrivée', async () => {
        const host = mountSection({ rows: null });
        await open(host);

        expect(host.querySelector('[data-sector-label]')).toBeNull();
        expect(host.textContent).not.toContain('Pas encore');
    });

    it('se rend en bloc étiqueté, sans pli, dans une analyse', () => {
        const host = mountSection({ rows, variant: 'block', deferKey: 'sectorBreakdown' });

        expect(host.querySelector('[data-sectors-block]')).not.toBeNull();
        expect(host.querySelector('[data-section-toggle]')).toBeNull();
        expect(host.querySelectorAll('[data-sector-label]')).toHaveLength(2);
        expect(host.querySelector('[data-sector-toggle]')).toBeNull();
    });
});
```

Si `SectorBreakdownList` n'attribue pas `data-sector-label` à chaque ligne, lire son template et employer son marqueur de ligne (les tests navigateur lisent `[data-sector-label]` et `[data-sector-toggle]`, ils existent).

- [ ] **Step 4: Écrire `SectorsSection.vue`**

```vue
<script setup lang="ts">
import DeferredBlock from '@/components/DeferredBlock.vue';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import SectorBreakdownList from '@/components/SectorBreakdownList.vue';
import type { SectorBreakdownRow } from '@/lib/sector';

/**
 * La ventilation d'une page : secteurs d'un instrument, du portefeuille ou du patrimoine, classes
 * d'une enveloppe — la même liste, des lignes déjà converties par `lib/sector.ts`. Quatre copies
 * du même `map` vivaient dans quatre composants.
 *
 * `section` : une section repliable à part entière. `block` : un bloc étiqueté au sein d'une
 * section qui se replie déjà (l'analyse d'une poche), sans second pli.
 */
const props = withDefaults(
    defineProps<{
        /** `null` : pas encore arrivée ; `[]` : rien à montrer. */
        rows: SectorBreakdownRow[] | null;
        variant?: 'section' | 'block';
        /** Ce que nomme `data-section` en variante `section`. */
        section?: string;
        title?: string;
        deferKey?: string;
        /** Replier la liste au-delà de six lignes : utile sur une répartition longue, inutile derrière un pli. */
        collapsible?: boolean;
        emptyLabel?: string;
    }>(),
    {
        variant: 'section',
        section: 'sectors',
        title: 'Secteurs',
        deferKey: 'sectors',
        collapsible: false,
        emptyLabel: 'Pas encore de données sectorielles.',
    },
);
</script>

<template>
    <CollapsibleSection v-if="props.variant === 'section'" :section="props.section" :title="props.title">
        <template v-if="props.rows !== null">
            <SectorBreakdownList v-if="props.rows.length" :rows="props.rows" :collapsible="props.collapsible" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ props.emptyLabel }}</p>
        </template>

        <DeferredBlock v-else :data="props.deferKey" />
    </CollapsibleSection>

    <div v-else data-sectors-block class="flex flex-col gap-2">
        <span class="text-xs font-semibold text-muted-foreground uppercase">{{ props.title }}</span>

        <template v-if="props.rows !== null">
            <SectorBreakdownList v-if="props.rows.length" :rows="props.rows" :collapsible="props.collapsible" />
            <p v-else class="py-8 text-center text-sm text-muted-foreground">{{ props.emptyLabel }}</p>
        </template>

        <DeferredBlock v-else :data="props.deferKey" />
    </div>
</template>
```

Run: `bun run test:js -- resources/js/components/SectorsSection.test.ts` → 4 passed.

- [ ] **Step 5: Basculer les quatre consommateurs, supprimer les trois anciens composants**

- `components/instruments/AnalysisSection.vue` : remplacer l'import de `SectorsBlock` par `import SectorsSection from '@/components/SectorsSection.vue';` et `import { rowsFromSlices, type SectorSlice } from '@/lib/sector';`, ajouter
  ```ts
  /** `undefined` comme `null` valent « pas encore arrivé » : une prop Inertia non servie vaut `undefined`. */
  const sectorRows = computed<SectorBreakdownRow[] | null>(() =>
      props.slices === null || props.slices === undefined ? null : rowsFromSlices(props.slices),
  );
  ```
  (importer le type `SectorBreakdownRow`) et remplacer `<SectorsBlock v-if="props.hasSectors" :slices="props.slices" />` par
  ```vue
  <SectorsSection v-if="props.hasSectors" variant="block" defer-key="sectorBreakdown" :rows="sectorRows" />
  ```
- `Pages/Dashboard.vue` : `import SectorsSection from '@/components/SectorsSection.vue';`, `import { rowsFromSlices, type SectorBreakdownRow } from '@/lib/sector';`,
  ```ts
  const sectorRows = computed<SectorBreakdownRow[] | null>(() => (sectors.value === null ? null : rowsFromSlices(sectors.value)));
  ```
  (ajouter `computed` à l'import de `vue`) et
  ```vue
  <SectorsSection section="wealth-sectors" defer-key="sectors" :rows="sectorRows" />
  ```
  (`aheadOfNetwork` rend un `ComputedRef<T | null>`, d'où le `.value` dans le script.)
- `Pages/Asset/Show.vue` : `import SectorsSection from '@/components/SectorsSection.vue';`, `import { rowsFromWeights } from '@/lib/sector';` et
  ```vue
  <!-- Un secteur unique se lit en étiquette dans l'en-tête : sa section n'aurait qu'une ligne à 100 %. -->
  <SectorsSection
      v-if="props.instrument.sectors.length > 1"
      section="sectors"
      :rows="rowsFromWeights(props.instrument.sectors, props.instrument.position?.marketValue ?? null)"
  />
  ```
- `Pages/Wallet/Show.vue` : remplacer `breakdownRows`, `breakdownLoaded` et tout le bloc `<CollapsibleSection section="wallet-breakdown" …>…</CollapsibleSection>` par
  ```ts
  const breakdownRows = computed<SectorBreakdownRow[] | null>(() =>
      props.breakdown === undefined ? null : rowsFromShares(props.breakdown),
  );
  ```
  ```vue
  <SectorsSection
      section="wallet-breakdown"
      title="Répartition"
      defer-key="breakdown"
      :rows="breakdownRows"
      collapsible
      empty-label="Aucune position dans cette enveloppe."
  />
  ```
  Retirer les imports devenus inutiles (`CollapsibleSection`, `SectorBreakdownList`, `Deferred`, `DeferredBlock`).

```bash
git rm resources/js/components/instrument/SectorsSection.vue resources/js/components/instruments/SectorsBlock.vue resources/js/components/dashboard/WealthSectorsSection.vue
```

- [ ] **Step 6: Vérifier**

```bash
grep -rn "SectorsBlock\|WealthSectorsSection\|instrument/SectorsSection" resources/js   # attendu : vide
grep -rn "share: slice\.\|share: sector\." resources/js/components resources/js/Pages     # attendu : vide (la conversion vit dans lib)
bun run typecheck && bun run test:js
```
Expected: vide ; tout vert, dont `Pages/Wallet/Show.test.ts` « n'affiche pas une ventilation vide tant qu'elle n'est pas arrivée » et « dit l'absence de performances et de secteurs quand les deux sont arrivés vides ».

- [ ] **Step 7: Commit**

```bash
git add -A resources/js
git commit -m "refactor: SectorsSection, une seule ventilation convertie dans lib/sector"
```

---

### Task 6: `null` seul dialecte : `InstrumentsSection` sans `loaded`

**Files:**
- Modify: `resources/js/components/instruments/InstrumentsSection.vue`, `resources/js/components/instruments/InstrumentsSection.test.ts`
- Modify: `Pages/Wallet/Show.vue`, `Pages/Wallet/Show.test.ts` (si nécessaire)

**Interfaces:**
- Produces: `InstrumentsSection` prend `holdings: HoldingLine[] | null` (`null` = positions différées pas encore arrivées) ; la prop `loaded` disparaît. `deferKey` reste. `ValueVsInvestedChart` garde `loaded` : il distingue un objet présent à série vide, ce que `null` ne dit pas — décision consignée au spec (tâche 7).

- [ ] **Step 1: Adapter les tests**

Dans `InstrumentsSection.test.ts` : `grep -n loaded` ; remplacer chaque `loaded: false` par `holdings: null` et chaque `loaded: true` par rien (défaut). Dans `Pages/Wallet/Show.test.ts`, le cas « n'affirme pas l'absence de position tant qu'elles ne sont pas arrivées » monte avec `positions: undefined` et attend qu'aucun `[data-instrument-empty]` n'apparaisse : il reste tel quel. Ajouter dans `InstrumentsSection.test.ts` :

```ts
it('n\'affirme pas l\'absence de position tant qu\'elles ne sont pas arrivées', async () => {
    const host = mountSection({ holdings: null });

    host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
    await nextTick();

    expect(host.querySelector('[data-instrument-empty]')).toBeNull();
    expect(host.querySelector('[data-instrument-row]')).toBeNull();
});
```

Run: `bun run typecheck` → erreur attendue sur `holdings: null` (le type n'admet pas encore `null`).

- [ ] **Step 2: Modifier `InstrumentsSection.vue`**

- Props : `holdings: HoldingLine[] | null;` avec le docblock « `null` : les positions différées ne sont pas encore arrivées ; la page d'exposition, qui sert `overview` en synchrone, passe toujours un tableau. » ; supprimer la prop `loaded` et son docblock ; supprimer `{ loaded: true }` des défauts (garder `withDefaults` seulement s'il reste un défaut, sinon `defineProps` nu).
- `const rows = computed<InstrumentRow[]>(() => holdingRows(props.holdings ?? [], props.trends));`
- Template : `<template v-if="props.holdings !== null">` à la place de `v-if="props.loaded"`.

- [ ] **Step 3: `Pages/Wallet/Show.vue`**

- `:holdings="props.positions ?? null"` à la place de `:holdings="props.positions ?? []"` et `:loaded="positionsLoaded"` ; supprimer `positionsLoaded` et son docblock.
- Le long commentaire sur `?? null` au-dessus d'`AnalysisSection` se réduit à une ligne : `<!-- \`?? null\` : une prop Inertia non arrivée vaut \`undefined\`, et la section lit \`null\` comme « pas encore ». -->`.
- Retirer l'import `computed` s'il ne sert plus (il sert encore à `title` et `breakdownRows`).

- [ ] **Step 4: Vérifier**

```bash
grep -rn ':loaded=\|loaded?: boolean\|loaded: boolean' resources/js --include='*.vue'   # attendu : ValueVsInvestedChart.vue et ses appelants seulement
bun run typecheck && bun run test:js
```

- [ ] **Step 5: Commit**

```bash
git add resources/js
git commit -m "refactor: InstrumentsSection lit null comme « pas encore arrivé », plus de prop loaded"
```

---

### Task 7: Règle front et note au spec

**Files:**
- Create (via `record-rule` si disponible, sinon à la main) : `.ai/rules/components.md` avec `paths: ['resources/js/components/**', 'resources/js/Pages/**']`, et sa ligne dans `.ai/rules/index.md`
- Modify: `docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md` (section Front)

- [ ] **Step 1: La règle**

Titre : « Une prop différée s'attend par DeferredBlock, et `null` veut dire pas encore ». Note :

```markdown
Toute section qui attend une prop différée rend `<DeferredBlock :data="clé">` dans sa branche `v-else` : squelette, message « Données indisponibles hors-ligne. » et sentinelle y sont écrits une fois. Un squelette particulier (graphe) passe par le slot `fallback`. Ne pas réécrire un `<Deferred>` à la main — dix-neuf copies ont existé.

Le dialecte du chargement est unique : `null` (ou `undefined`, ce que vaut une prop Inertia non servie) = pas encore arrivé, squelette ; tableau ou objet vide = rien à montrer, libellé vide. Pas de prop `loaded` booléenne — `ValueVsInvestedChart` est l'exception assumée, un objet présent pouvant porter une série vide.

Les sections partagées sont `TransactionsSection` (journal, variantes `named`/`bare`), `PortfolioSummarySection` (grand chiffre, préfixe `portfolio`/`wealth` des `data-*`) et `SectorsSection` (ventilation, variantes `section`/`block`) ; leurs lignes se convertissent dans `lib/sector.ts` (`rowsFromWeights`, `rowsFromSlices`, `rowsFromShares`), jamais dans un composant. Une nouvelle page assemble ces trois-là avec ses props ; elle n'en copie pas une.
```

Ajouter la ligne `| resources/js/components/**, resources/js/Pages/** | .ai/rules/components.md |` dans `.ai/rules/index.md` si `record-rule` ne l'a pas fait.

- [ ] **Step 2: Note au spec**

Dans la section « Front » du spec, paragraphe « Dialecte unique du chargement », remplacer « La prop `loaded` disparaît où `null` suffit. » par « La prop `loaded` disparaît d'`InstrumentsSection` ; `ValueVsInvestedChart` la garde, un objet présent pouvant porter une série vide, ce que `null` ne dirait pas. » Dans le paragraphe « Une ventilation », ajouter : « Le squelette du bloc sectoriel de l'analyse devient trois lignes, comme partout. »

- [ ] **Step 3: Commit**

```bash
git add .ai/rules docs/superpowers/specs/2026-09-03-fin-du-jumelage-design.md
git commit -m "docs: DeferredBlock, null et les trois sections partagées deviennent la règle du front"
```

---

## Vérification finale

- [ ] `bun run typecheck && bun run test:js` : verts.
- [ ] `grep -rn 'Données indisponibles hors-ligne' resources/js --include='*.vue' | wc -l` rend 1.
- [ ] `ls resources/js/components/instrument resources/js/components/instruments resources/js/components/dashboard` : plus de `TransactionsSection.vue`, `ValuationSection.vue`, `WealthSummarySection.vue`, `SectorsSection.vue`, `SectorsBlock.vue`, `WealthSectorsSection.vue`, `WealthTransactionsSection.vue`.
- [ ] `bun run build`, puis `php artisan test --compact tests/Browser` si l'environnement lance les tests navigateur ; sinon ouvrir `/`, `/actions`, `/asset/{id}`, `/enveloppes/{id}` et vérifier : même écran, sections repliées, dépli qui charge, journal avec le « + » qui impose actif ou enveloppe.
