# Perfs portefeuille en haut du dashboard

## Context

La fiche instrument (`resources/js/Pages/Instruments/Show.vue`) affiche en haut un bloc « Gain / perte » (€ + % inline) suivi d'une row de pills scrollable (YTD, 1 mois, 3 mois, 6 mois, puis une card par année pleine jusqu'au premier investissement). L'utilisateur veut le même bloc en haut du **dashboard**, mais au niveau **portefeuille** (agrégé sur tous les titres).

État actuel du dashboard (`resources/js/Pages/Dashboard.vue`) : header → graphe Évolution → graphe Investi par titre → 3 cards KPI (Valeur totale / Gains-pertes / Rendement) → Positions + Répartition. Les perfs par période n'existent qu'au niveau instrument (`BuildAssetPerformances`).

Décisions validées :
- **Layout** : reproduire le bloc de la fiche en haut du dashboard (au-dessus des graphes) ; **retirer** les 3 cards KPI (redondantes) ; « Valeur totale » devient une petite ligne muted sous le titre.
- **Chargement** : les pills portefeuille sont un prop **déféré** (Inertia) avec skeleton pulsant, comme `valuationSeries` — car le calcul agrège tous les titres. Le headline (Gain/perte + Valeur totale) reste immédiat (vient de `overview`).

## Approche

La logique « périodes depuis une série quotidienne » est identique pour un instrument et pour le portefeuille — seule la construction de la série quotidienne diffère. On l'extrait donc dans le calculateur partagé, et on ajoute une action portefeuille qui construit la série agrégée.

### Backend (contexte Valuation)

1. **Rename** `app/Contexts/Valuation/Datas/AssetPerformanceData.php` → `PerformanceData.php` (DTO générique `{key, label, pct}`, partagé asset + portefeuille). Mettre à jour les références (`BuildAssetPerformances`).

2. **Extraire** dans `app/Contexts/Valuation/Services/ValuationCalculator.php` une méthode publique :
   `public function trailingPerformances(ValuationSeriesData $daily): array` (`list<PerformanceData>`).
   - Si `$daily->labels === []` → `[]`.
   - Ancre = dernier label ; `firstDay` = premier label.
   - YTD : `returnOverWindow($daily, anchor->startOfYear())`, key `YTD`, label `YTD`.
   - Mensuel : 1M/3M/6M (`subMonthsNoOverflow`), labels `1 mois` / `3 mois` / `6 mois`.
   - Annuel dynamique : pour `year` de 1 à `fullYears` (nombre d'années pleines tel que `anchor->subYearsNoOverflow(year) >= firstDay`), key `{year}Y`, label `1 an` / `{year} ans`, `returnOverWindow(anchor->subYearsNoOverflow(year))`.
   - Ordre : `[YTD, 1M, 3M, 6M, 1Y, 2Y, …]`.
   - `returnOverWindow` (déjà existant) est inchangé.

3. **Simplifier** `app/Contexts/Valuation/Actions/BuildAssetPerformances.php` : construit son daily comme aujourd'hui (transactions filtrées par asset, prix de l'asset) puis `return $this->calculator->trailingPerformances($daily);`. Supprimer les constantes/méthodes de période désormais dans le calculateur.

4. **Nouveau** `app/Contexts/Valuation/Actions/BuildPortfolioPerformances.php` (mêmes ports que `BuildPortfolioValuationSeries`) :
   ```php
   $transactions = $this->transactions->forUser($userId);
   if ($transactions === []) return [];
   $since = $transactions[0]->date; // forUser est trié par date
   $assetIds = array_values(array_unique(array_map(fn ($t) => $t->assetId, $transactions)));
   $prices = $this->prices->forAssetsSince($assetIds, $since);
   $daily = $this->calculator->calculateDaily($transactions, $prices);
   return $this->calculator->trailingPerformances($daily);
   ```

5. `app/Contexts/Portfolio/Http/DashboardController.php` : ajouter le prop déféré
   `'performances' => Inertia::defer(fn () => $user !== null ? app(BuildPortfolioPerformances::class)($user->id) : [])`.

### Frontend — `resources/js/Pages/Dashboard.vue`

6. Interface TS `interface Performance { key: string; label: string; pct: number | null }` + prop `performances?: Performance[]`.

7. Nouveau bloc **tout en haut** du `div.mx-auto` (avant le graphe Évolution), `v-if="overview.holdings.length"` :
   ```html
   <section class="flex flex-col gap-3">
       <div class="flex flex-col gap-0.5">
           <p class="text-sm text-muted-foreground">Gain / perte</p>
           <p class="text-2xl font-semibold" :class="gainClass(overview.totalGain)">
               {{ eur(overview.totalGain) }}
               <span class="text-sm">({{ pct(overview.totalGainPct) }})</span>
           </p>
           <p class="text-sm text-muted-foreground">Valeur totale {{ eur(overview.totalValue) }}</p>
       </div>

       <Deferred data="performances">
           <template #fallback>
               <div class="-mx-6 overflow-x-hidden px-6">
                   <div class="flex min-w-max gap-2">
                       <div v-for="n in 6" :key="n" class="h-[52px] w-[64px] animate-pulse rounded-md bg-muted"></div>
                   </div>
               </div>
           </template>

           <div v-if="performances && performances.length" class="-mx-6 overflow-x-auto px-6">
               <div class="flex min-w-max gap-2">
                   <div v-for="perf in performances" :key="perf.key"
                        class="flex min-w-[64px] flex-col gap-0.5 rounded-md border border-border px-3 py-2">
                       <span class="text-xs text-muted-foreground">{{ perf.label }}</span>
                       <span class="text-sm font-medium" :class="gainClass(perf.pct)">{{ pct(perf.pct) }}</span>
                   </div>
               </div>
           </div>
       </Deferred>
   </section>
   ```
   Réutilise les helpers existants `eur()`, `pct()`, `gainClass()` et le pattern full-bleed `-mx-6 overflow-x-auto px-6` déjà présent dans le fichier.

8. **Retirer** la `<section class="grid gap-4 sm:grid-cols-3">` des 3 cards KPI (Valeur totale / Gains-pertes / Rendement).

## Tests

- `app/Contexts/Valuation/Services/ValuationCalculatorTest.php` — ajouter un cas `trailingPerformances` sur un `ValuationSeriesData` fabriqué : vérifie les clés/labels attendus (YTD, mois, années dynamiques) et quelques `pct`, plus `[]` sur série vide.
- `app/Contexts/Valuation/Actions/BuildPortfolioPerformancesTest.php` (nouveau) — 2 titres détenus, historique multi-années : renvoie YTD + mois + années jusqu'au premier invest ; `[]` si aucune transaction. Mirror du setup de `BuildPortfolioValuationSeriesTest`.
- `app/Contexts/Valuation/Actions/BuildAssetPerformancesTest.php` — reste vert sans modification (comportement inchangé).
- `tests/Feature/DashboardPageTest.php` — sur le portefeuille peuplé, `->missing('performances')` puis `->loadDeferredProps(fn ($reload) => $reload->has('performances'))` et première clé `YTD`.

## Vérification

1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test --compact --filter='ValuationCalculator|BuildAssetPerformances|BuildPortfolioPerformances|DashboardPage'`
3. `bun run build`
4. Visuel : dashboard via URL Herd en largeur mobile (390px) — headline Gain/perte + Valeur totale, row de pills scrollable, skeleton au chargement différé, pas de débordement horizontal (`scrollWidth == clientWidth`).

## Commit

Commit unique après tests verts (workflow projet).
