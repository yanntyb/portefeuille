# La page d'une enveloppe de détention

## Ce qu'on construit

Une page par enveloppe : `/enveloppes/{id}`. Elle montre ce qu'un compte tient — ses positions
toutes classes confondues, sa valeur dans le temps, sa répartition par classe d'actif, son
historique d'opérations — plus l'en-tête fiscal que le tableau de bord affiche déjà en carte
repliée.

C'est le dépli d'une carte existante, pas une entrée neuve : `WealthAccountsSection` liste les
enveloppes depuis le 31 août, chaque carte devient un lien vers sa page.

### Une page par wallet, pas par type d'enveloppe

La page porte sur une ligne de `wallets`, pas sur un cas de `AccountType`. La base compte
aujourd'hui trois PEA distincts chez le même courtier : les fusionner rendrait l'ancienneté et le
seuil des cinq ans ininterprétables — trois dates d'ouverture, un seul chiffre à afficher. Le
compte espèces est lui aussi tenu par wallet, jamais par type.

### Ce qui n'en fait pas partie

- **L'instantané hors-ligne.** `BuildMarketViewSnapshot` reste intouché : le blob grossirait d'une
  entrée par wallet pour une lecture fiscale rarement consultée sans réseau. Les sections
  différées afficheront « Données indisponibles hors-ligne ». À reprendre quand l'usage le
  demandera.
- **Le journal détaillé des espèces.** L'en-tête donne le solde ; les mouvements ligne à ligne
  restent hors périmètre.
- **La vue cumulée d'un type d'enveloppe.** Pas de page `/pea` agrégeant les trois PEA.

## Architecture

### Le contexte propriétaire est Wealth

L'« enveloppe » est déjà le vocabulaire de Wealth : `WealthAccountData`, `GetWealthAccounts`,
`AccountsPort`, la section du tableau de bord. La page s'y installe.

MarketView aurait offert sa mécanique de pages différées et son instantané, mais tous ses ports
sont clés par `AssetClass` — l'enveloppe n'est pas sa langue. Portfolio servant la page
directement casserait la règle hexagonale de `.ai/rules/contexts.md`.

### Route

```php
Route::get('/enveloppes/{id}', WalletController::class)
    ->whereNumber('id')
    ->name('wallets.show');
```

`whereNumber` n'est pas décoratif : `bootstrap/app.php` renvoie les URL non matchées vers
l'accueil, un identifiant non numérique donnerait une redirection silencieuse au lieu d'un 404.

### Contrôleur

`Wealth\Http\WalletController`, calqué sur `AssetClassController` : l'en-tête synchrone, tout le
reste différé, un groupe par section.

| Prop | Mode | Groupe |
| --- | --- | --- |
| `account` | synchrone | — |
| `positions` | différée | `positions` |
| `breakdown` | différée | `repartition` |
| `evolution` | différée | `evolution` |
| `transactions` | différée | `transactions` |

Le contrôleur n'appelle pas les ports directement : une action mince par section — `GetWalletAccount`,
`GetWalletPositions`, `GetWalletBreakdown`, `GetWalletSeries`, `GetWalletTransactions` — comme
`GetWealthAccounts` enveloppe déjà `AccountsPort` pour le tableau de bord.

`abort(404)` quand `accountFor()` rend `null` : le port ne distingue pas le wallet inexistant du
wallet d'un autre utilisateur, et la page n'a pas à le faire non plus — une page vide renseignerait
sur l'existence du compte.

### Ports

Trois ports touchés, un neuf. Un port par rôle, comme les quatre existants.

| Port | Méthodes ajoutées | Adaptateur |
| --- | --- | --- |
| `AccountsPort` | `accountFor(int $userId, int $walletId): ?WealthAccountData`<br>`positionsFor(int $userId, int $walletId): list<WealthHoldingData>`<br>`breakdownFor(int $userId, int $walletId): list<WalletClassSliceData>` | `PortfolioAccounts` |
| `TransactionsPort` | `transactionsForWallet(int $userId, int $walletId): list<WealthTransactionLineData>` | `PortfolioLedger` |
| `ValuationPort` (neuf) | `seriesForWallet(int $userId, int $walletId): ClassSeriesData` | `WalletValuation` (neuf) |

`positionsFor` et `breakdownFor` composent `GetPortfolioOverview` — liée en `scoped` et mémoïsée
par utilisateur — et filtrent sur `walletId` en mémoire. Aucune action Portfolio neuve, aucune
seconde lecture du portefeuille : c'est le même idiome que `GetAccountBreakdown`, qui groupe déjà
l'aperçu par wallet.

`breakdownFor` agrège ces positions par `AssetClass` et rend libellés et parts déjà calculés : le
front n'agrège rien, conformément à la règle du dépôt sur les libellés rendus côté serveur.

`transactionsForWallet` filtre en SQL, pas en mémoire : le journal d'un wallet n'a pas à charger
l'historique complet de l'utilisateur.

### La jumelle `WealthHoldingData`

Troisième jumelle de `Portfolio\HoldingLineData`, après `MarketView\HoldingRowData`. Dix-sept
clés pour quatorze propriétés : `typeLabel`, `assetClassLabel` et `accountTypeLabel` se dérivent de
leur enum au moment de sérialiser — exactement le piège que `.ai/rules/market-view.md` signale.

Le prix se justifie par ce qu'il achète : le front réutilise `InstrumentsSection` et
`InstrumentList` tels quels, avec leurs étincelles, leur tri et leur lien vers `/asset/{id}`.
Un `WealthHoldingDataTest` posé à côté de la Data affirme la parité des clés des trois jumelles,
pour que la triplette ne dérive pas.

Alternative écartée : une ligne de position maigre et un composant de liste dédié. Moins de PHP,
mais la sparkline et le lien vers la fiche seraient à réécrire.

### La série par enveloppe

`BuildExposureSeries` filtre aujourd'hui par `AssetClass`, et `TransactionRecordData` ne porte pas
son wallet. Deux changements :

1. `TransactionRecordData` gagne `public int $walletId` — non nullable, toute transaction a son
   wallet. L'unique adaptateur de `TransactionHistoryPort` et ses tests suivent.
2. `BuildExposureSeries::__invoke(int $userId, ?array $classes = null, ?int $walletId = null)`,
   clé de cache `exposition.enveloppe-{id}`. Le filtre entre dans le nom retenu, comme celui des
   classes : la série agrège avant d'exister, elle ne se découpe pas après coup.

Le filtre wallet est un filtre franc sur toutes les lignes, cash compris — et c'est là qu'il
diffère de son voisin. Le filtre par classe laisse passer tout mouvement sans `asset_id`, sous
peine d'un cash bâti sur les seuls achats de la classe, négatif en permanence. Le cash étant tenu
par wallet, cette exception n'a pas lieu d'être ici : un versement sur le PEA n'appartient pas au
CTO. Le commentaire du code doit dire pourquoi les deux filtres ne se comportent pas pareil, sans
quoi le prochain lecteur alignera l'un sur l'autre.

Alternative écartée : une action `BuildWalletSeries` séparée, qui recopierait `build()`.

## Front

`resources/js/pages/Wallet/Show.vue`, structure de `AssetClass/Index.vue` : `AppPage` en flex-col
gap-6, `AppBottomBar` portant le fil « Tableau de bord › {courtier} ({type}) », `TransactionDialog`
en frère du conteneur et non en enfant — dedans, il ajouterait un écart fantôme au `gap-6`.

| Section | Composant | Origine |
| --- | --- | --- |
| En-tête | `WalletHeaderSection.vue` | neuf, sur `HeroFigures` + `HeroMetaList` |
| Évolution | `WalletEvolutionSection.vue` | neuf, mince : enveloppe `ValueVsInvestedChart` |
| Positions | `InstrumentsSection` | réutilisé tel quel |
| Répartition | `SectorBreakdownList` | réutilisé tel quel |
| Transactions | `TransactionsSection` | réutilisé tel quel |

L'en-tête montre ce que la carte du tableau de bord montre déjà — valeur, gain, espèces, régime
fiscal, ancienneté et seuil, alerte d'éligibilité — mais dépliée : sur sa propre page, la lecture
fiscale ne se demande plus, elle est le sujet.

`WalletEvolutionSection` enveloppe `ValueVsInvestedChart` sur `labels`/`value`/`invested`, et non
`EvolutionSection`, qui attend un `perAsset` que la série d'enveloppe ne produit pas.

Types dans `resources/js/lib/wealth.ts` : `WalletPage`, `WalletClassSlice`. Les positions
réutilisent `HoldingLine` de `lib/portfolio` — le bénéfice de la jumelle.

### Trois modifications de composants existants

- `InstrumentsSection` : `catalogHref` devient optionnelle et la loupe disparaît quand elle manque.
  Une enveloppe n'a pas de catalogue.
- `TransactionsSection` : la valeur `"class-transactions"`, aujourd'hui en dur, passe en prop
  facultative. L'état du pli est un `ref` local, rien n'est partagé entre pages ; c'est le
  `data-section` qui doit nommer sa page, pour les tests comme pour la lecture du DOM.
- `WealthAccountsSection` : chaque carte devient un `<Link prefetch>` vers sa page.

## Tests

TDD, une tranche par commit.

- **`WalletControllerTest`** (Pest, feature) — props servies, noms des groupes différés, 404 sur
  wallet inexistant, 404 sur wallet appartenant à un autre utilisateur.
- **`BuildExposureSeriesTest`** — filtre par enveloppe, et un versement du wallet voisin exclu :
  c'est le cas qui distingue le filtre wallet du filtre classe.
- **`PortfolioAccountsTest`** — `accountFor` trouvé puis autre utilisateur, `positionsFor` ne rend
  que le wallet demandé, `breakdownFor` rend parts et libellés justes.
- **`PortfolioLedgerTest`** — `transactionsForWallet` scope au wallet, plus récente en tête.
- **`WealthHoldingDataTest`** — parité des clés `HoldingLineData` / `HoldingRowData` /
  `WealthHoldingData`.
- **Vitest** — `WalletHeaderSection.test.ts` : alerte d'éligibilité, seuil franchi ou non,
  ancienneté absente quand la date d'ouverture manque. `Show.test.ts` : fil d'Ariane, et absence de
  loupe dans la section des positions.

## Ordre de construction

1. `walletId` dans `TransactionRecordData` et le filtre enveloppe de `BuildExposureSeries`.
2. Les méthodes de port et leurs adaptateurs, `WealthHoldingData` comprise.
3. Route, contrôleur, page nue avec ses cinq sections.
4. Les composants neufs et les trois retouches de composants existants.
5. Les cartes du tableau de bord deviennent des liens.
