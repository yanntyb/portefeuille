# Liquidités

Une vente fait aujourd'hui disparaître de l'argent. `ValuationCalculator` calcule la valeur d'une
position comme quantité restante × cours, et l'investi comme le coût des titres encore détenus :
vendre 15 titres à 85,70 € fait donc tomber la courbe de valeur de 1 285,50 € et la courbe
d'investi de 1 195,05 €, sans que le produit de la vente n'apparaisse nulle part. Le gain réalisé,
lui, est bien capté — colonne `transactions.realized_gain`, lue par `GetRealizedGains` — mais il
vit à côté des séries, jamais dedans.

Le trou est structurel : l'application n'a aucune notion d'espèces. `TransactionType` n'a que
`Buy`/`Sell`, aucune table ne porte de solde, et `.ai/rules/portfolio.md` en tire explicitement
l'absence du plafond de versement PEA.

Ce chantier introduit les liquidités comme un fait de première classe : un solde d'espèces par
enveloppe, alimenté par les versements, les ventes et les dividendes, consommé par les achats et
les retraits. Il change au passage le sens de « Investi », qui devient les **apports nets** — ce
que le porteur a réellement sorti de sa poche — et non plus le coût des titres détenus.

## Périmètre

Dedans :

- trois types de mouvement — `Deposit`, `Withdrawal`, `Dividend` — sur la table `transactions` ;
- un solde d'espèces par enveloppe, dérivé des transactions, jamais stocké ;
- des versements déduits automatiquement quand un achat n'est pas financé, écrits en base et
  régénérés en grappe ;
- l'imputation d'origine de chaque euro de cash, pour la lecture par exposition ;
- « Investi » = apports nets, au tableau de bord comme sur les pages d'exposition ;
- une classe de patrimoine « Liquidités » ;
- la validation des dividendes attendus en transactions encaissées.

Dehors, explicitement :

- **toute fiscalité** — pas de flat tax, pas de net après impôt, pas de coût fiscal d'une vente.
  La règle du dépôt tient : les règles d'enveloppe sont déclaratives, jamais appliquées ;
- **le plafond de versement PEA.** Il devient calculable pour la première fois — les versements
  existent enfin — mais l'afficher est un chantier à part, avec sa propre lecture (versements
  bruts cumulés, jamais les flux nets). `.ai/rules/portfolio.md` sera à corriger sur la raison de
  son absence, pas sur son absence ;
- **le dividende réinvesti automatiquement** : un DRIP se saisit comme un dividende puis un achat ;
- **les comptes d'épargne hors portefeuille** (livret A, compte courant) comme classe patrimoniale.
  Le registre est extensible, ils s'ajouteront sans rien casser ;
- **la correction du biais des cours ajustés** (voir « Angle mort assumé ») ;
- un écran global des dividendes à encaisser : la liste vit sur la fiche de l'actif.

## Décisions et leur raison

**Investi = apports nets.** Le sens actuel — coût des titres détenus — rend la performance fausse
dès qu'on vend : l'investi retombe, donc le pourcentage remonte, donc un aller-retour améliore
artificiellement le rendement affiché. Avec les apports nets, `+15,8 %` mesure ce que l'argent
a fait, pas ce qui reste engagé. Au niveau d'un instrument, l'investi garde naturellement son sens
de coût des titres : il n'y en a pas d'autre possible, la distinction n'a pas à être codée.

**Le cash appartient à l'enveloppe.** C'est le fait réel — le compte espèces du PEA. Le cash d'une
enveloppe est fongible ; il n'y a qu'un solde par enveloppe, et c'est lui qui fait foi, qui plafonne
les retraits, qui s'affiche sur la page Comptes.

**Mais chaque euro garde son origine.** Une exposition traverse les enveloppes : « le cash issu
des actions » n'est pas un solde, c'est une lecture. Chaque crédit porte donc l'origine qui l'a
produit, et le FIFO de consommation permet de dire, à tout instant, de quoi le solde restant est
fait. Une seule quantité d'argent, deux façons de la lire.

**Règle d'or.** Un euro est compté une fois dans la **valeur**, là où il est détenu, et une fois
dans le **gain**, là où il a été produit. Ce sont deux ventilations distinctes du même patrimoine ;
elles ne s'additionnent jamais entre elles. L'argent d'une vente d'actions est en valeur dans les
Liquidités et en gain dans les Actions.

**Le versement déduit plutôt que l'amorçage.** La base ne contient aucun versement : pris au mot,
le nouvel investi tomberait à 0 € et les soldes à −5 304 €. Plutôt que d'inventer un versement
d'ouverture dont le montant n'existe nulle part, l'invariant « un solde d'espèces n'est jamais
négatif » produit le versement manquant à la date de l'achat. La règle est vraie en soi — un achat
non financé par du cash *a été* financé par un apport — et non un pansement de reprise.

## Données

Deux colonnes sur `transactions`, **dans la migration de création** (règle du dépôt : une seule
migration par table, puis `migrate:fresh` et les seeders) :

| Colonne | Type | Rôle |
| --- | --- | --- |
| `amount` | `decimal(12,2)`, nullable | montant des mouvements d'espèces |
| `auto` | `boolean`, défaut `false`, indexée | ligne déduite par le système |

Aucune table nouvelle. Aucun solde stocké.

`amount` n'est jamais signé : le sens vient du type. Un retrait de −200 € saisi par erreur ne doit
pas pouvoir se comporter comme un versement.

`TransactionType` gagne `Deposit` (« Versement »), `Withdrawal` (« Retrait »), `Dividend`
(« Dividende »). Répartition des colonnes par type :

| Type | `asset_id` | `quantity` / `unit_price` | `amount` | `fees` | `realized_gain` |
| --- | --- | --- | --- | --- | --- |
| `buy` / `sell` | requis | requis | null | oui | posé sur `sell` |
| `deposit` / `withdrawal` | null | null | requis | non | null |
| `dividend` | requis | null | requis (net reçu) | non | null |

Le schéma accueille déjà cette forme : `asset_id`, `quantity` et `unit_price` sont nullables depuis
la création de la table.

## Le solde et son calcul

### `Portfolio\Services\CashLedger`

Service pur — ni Eloquent, ni port, ni conteneur, test co-localisé construit avec `new`, selon
`.ai/rules/services.md`. Il reçoit les mouvements d'un utilisateur triés par date et rend :

- le solde d'espèces d'une enveloppe à une date ;
- la série des soldes dans le temps ;
- la composition du solde par origine ;
- les apports nets, imputés par exposition.

Tout le reste le consomme : `ValuationCalculator` pour les courbes, `PortfolioTotals` et les
instantanés pour le présent.

### `TransactionFlow::cashDelta()`

`TransactionFlow` est déjà le site unique du montant d'une ligne (`.ai/rules/portfolio.md`) ; il
devient le site unique de son signe :

| Type | `cashDelta()` |
| --- | --- |
| `deposit`, `dividend` | `+amount` |
| `withdrawal` | `−amount` |
| `buy` | `−(quantité × prix + frais)` |
| `sell` | `+(quantité × prix − frais)` |

### Financement d'un achat

Un achat débite le cash de **son** enveloppe. Si le solde n'y suffit pas, une transaction `Deposit`
du montant manquant est créée, datée du jour de l'achat, sur la même enveloppe, `auto = true`.

**Invariant, testé comme tel : à aucune date de son historique le solde d'espèces d'une enveloppe
n'est négatif.**

### Imputation d'origine

Chaque crédit porte son origine : `Deposit` → apport ; `Sell` → l'exposition de l'actif vendu ;
`Dividend` → celle de l'actif payeur. Un débit consomme les crédits de l'enveloppe du plus ancien
au plus récent, en épuisant leurs étiquettes.

De là sortent les deux lectures :

- **le cash restant ventilé par origine** — ce qu'affiche une page d'exposition à côté de ses
  titres ;
- **les apports nets** — versements (saisis et auto) moins retraits, imputés à l'exposition de
  l'achat financé. Réinvestir le produit d'une vente d'actions en actions ne crée aucun apport ;
  le sortir vers la crypto déplace l'imputation.

### Retrait

Refusé au-delà du solde de l'enveloppe à cette date, dans `TransactionRequest` — symétrique du
plafond de vente introduit par `cd103ad7`.

## Les lignes `auto`

Une ligne `auto` est la conséquence d'un achat, jamais une saisie. Elle doit donc suivre son
achat : montant corrigé, enveloppe déplacée, achat supprimé. C'est exactement le problème de
`realized_gain`, que `RecomputeRealizedGains` résout en réécrivant la grappe.

`TransactionObserver` gagne le même mécanisme pour les espèces : à la création, la correction, le
déplacement ou la suppression d'une transaction, les lignes `auto = true` de l'enveloppe touchée
sont effacées, l'historique rejoué, et seul ce qui manque encore est réécrit. Écriture par
`saveQuietly()`, sans quoi l'observateur se rappellerait sans fin.

Une ligne `auto = false` n'est jamais touchée. Corollaire : saisir après coup le vrai virement de
10 000 € fait disparaître d'elles-mêmes les lignes déduites qu'il couvre, sans rien perdre de ce
qui a été saisi à la main.

À l'écran, une ligne `auto` se dit pour ce qu'elle est — « versement déduit » — et ne se modifie
pas : elle serait réécrite au recalcul suivant. Pour la faire disparaître, on saisit le vrai
versement.

## Séries

`ValuationCalculator` gagne une composante `cash` à côté de `valuations` et `invested`, sur la même
grille de labels, au total et par origine. La courbe d'une page d'exposition devient titres + cash
d'origine : plus de marche à la vente.

`LaravelSeriesCache` doit voir les nouvelles données dans son empreinte : `type`, `amount` et `auto`
s'ajoutent aux agrégats existants. Sans `type`, deux jeux de transactions de même cardinalité et de
mêmes sommes serviraient la même série — l'angle mort est aujourd'hui masqué par `max(updated_at)`,
il devient bloquant ici.

Le cas d'une vente datée avant son achat, que `ProjectHolding` autorise déjà en étant aveugle aux
dates, produit un cash cohérent : l'achat génère son apport le jour venu, rien ne passe négatif.

## Dividendes

### Ce qui reste dérivé

`DividendCalculator` continue de produire les détachements attendus — `asset_dividends.amount_per_share`
× quantité détenue à l'`ex_date` — par actif **et par enveloppe** : deux comptes détenant le même
titre reçoivent deux versements. Ils s'affichent comme « à encaisser ».

### Ce qui devient réel

Valider un détachement écrit une transaction `Dividend` : enveloppe détentrice, date = `ex_date`,
`amount` = montant proposé et éditable avant validation, pour saisir le net réellement reçu. Elle
crédite le cash comme tout autre mouvement, et se corrige ou se supprime ensuite comme n'importe
quelle transaction.

### Dédoublonnage

Clé : `(asset_id, wallet_id, date)`. `DividendIncomeSource` exclut de sa dérivation tout détachement
qui a sa transaction et lit la transaction à la place. Un détachement est soit attendu, soit
encaissé, jamais les deux. `projectedAnnualFor()` reste dérivé : il parle de l'avenir.

### Correction requise ailleurs

`MarketView\Infrastructure\PortfolioTotals:88` ajoute aujourd'hui les dividendes d'`Income` au gain
réalisé. Une fois le dividende dans le cash, cette addition le compterait deux fois — une fois dans
le solde, une fois en supplément du gain. **Elle disparaît.** Le gain redevient
`(titres + cash) − apports nets`, et le dividende y entre par le seul cash.

Pas de double comptage avec les cours non plus : un cours décroche du montant détaché à l'ex-date.
Le dividende sort de la valeur des titres et entre dans le cash, sans se créer ni se perdre.

### Angle mort assumé

`fetch_prices.py` appelle `yf.Ticker.history()` sans `auto_adjust=False` : les cours stockés sont
ajustés des dividendes, donc l'historique passé est rabaissé du dividende cumulé. Les valeurs
*passées* des courbes sont sous-estimées. C'est un biais préexistant, indépendant de ce chantier ;
le corriger demanderait de stocker les cours bruts et de gérer les splits à la main. Hors périmètre,
noté ici pour ne pas être redécouvert.

## Patrimoine

### `Wealth\Infrastructure\CashClass`

Écrite à la main comme `RealEstateClass` : `PortfolioAssetClass` est paramétrée par `AssetClass`,
et les liquidités ne sont pas une exposition de marché. Ajoutée **en fin de registre** — l'ordre
est un contrat qui fixe les lignes du résumé et l'empilement des bandes.

| Membre | Valeur |
| --- | --- |
| `key()` | `cash` |
| `label()` | Liquidités |
| `href()` | la page Comptes |
| `color()` | jeton neutre, nouveau |
| `incomeLabel()` | `null` |
| `sectorSlicesFor()` | tranche unique à son nom, comme l'immobilier |

`incomeLabel()` rend `null` délibérément : les dividendes appartiennent à l'exposition qui les
produit. En déclarer une origine ici les compterait deux fois — `WealthInvariantTest` garde cette
règle.

Son instantané : valeur = solde total ; investi = la part du solde encore étiquetée « apport » par
le FIFO ; gain = le reste, c'est-à-dire les plus-values réalisées et dividendes pas encore
replacés. Le gain apparaît là où l'argent dort, sans qu'aucune classe n'invente de rendement.

## Interface

**Tableau de bord.** Une bande « Liquidités » dans le graphe empilé, une ligne dans le résumé. Le
grand chiffre monte du solde et ne fait plus de marche à la vente.

**Page d'exposition.** `InvestedGainMeta` garde ses trois repères, avec le nouveau sens : investi =
apports nets imputés, gain latent = titres, gain réalisé = ventes. La courbe ajoute le cash d'origine
à la valeur des titres, et un repère le dit — « dont 1 285 € à replacer » — affiché seulement s'il
existe, sur le modèle de `hasRealized`.

**Page Comptes.** `GetWealthAccounts` / `PortfolioAccounts` portent le solde d'espèces par enveloppe.
C'est le solde qui fait foi et qui plafonne les retraits.

**Journal des transactions.** Les trois nouveaux types s'affichent avec leur montant signé, les
lignes `auto` portant une marque discrète et un libellé qui dit qu'elles sont déduites. Le formulaire
bascule ses champs sur le type : montant seul pour un versement ou un retrait, quantité et prix pour
un ordre.

**Dividendes à encaisser.** Liste sur la fiche de l'actif — `AssetController` passe déjà une prop
`dividends` — avec montant éditable et validation par ligne.

Tout texte visible reste en français.

## Tests

`CashLedgerTest` (co-localisé, `new`, sans base) porte le gros de la charge :

- solde d'une enveloppe à une date ;
- achat couvert par le cash existant — aucune ligne déduite ;
- achat à découvert — ligne déduite du montant manquant, à la bonne date ;
- versement réel couvrant plusieurs achats postérieurs ;
- vente puis rachat dans la même exposition — aucun apport nouveau ;
- vente d'actions replacée en crypto — imputation déplacée ;
- FIFO d'origine sur des crédits mêlés ;
- vente datée avant son achat ;
- **invariant : le solde n'est négatif à aucune date.**

`TransactionFlowTest` : `cashDelta()` pour les cinq types, signes et frais compris.

Observateur et grappe (feature, base réelle) : correction d'un achat qui réduit sa ligne déduite ;
suppression qui l'efface ; changement d'enveloppe qui la déplace ; saisie d'un versement réel qui
rend caduques plusieurs lignes déduites ; **un versement `auto = false` n'est jamais touché** ; pas
de boucle d'observateur.

`TransactionRequest` : retrait supérieur au solde refusé, champs requis par type.

Dividendes : la validation crée la transaction et crédite le cash ; un détachement validé sort de la
dérivation ; deux enveloppes donnent deux lignes ; le montant édité prime sur le calculé.

Non-régression : un test échoue si `PortfolioTotals` réajoute les dividendes au réalisé.
`CashClass` entre dans `WealthInvariantTest` avec `incomeLabel() → null`.

Séries : `ValuationCalculatorTest` — plus de marche à la vente, cash à la bonne date ;
`LaravelSeriesCacheTest` — un cas par nouvelle composante d'empreinte (`type`, `amount`, `auto`).

**`tests/Feature/SnapshotInvariantTest.php`** compare un hash du JSON complet, que ce chantier
change (classe patrimoniale nouvelle, champs nouveaux). Le hash de référence est régénéré **une
fois, délibérément, en fin de chantier**. Les fixtures ne doivent pas être réordonnées en cours de
route : l'`id` des transactions entre dans le hash, insérer une ligne au milieu décale tout ce qui
suit (`.ai/rules/tests.md`).

## Règles du dépôt à mettre à jour

- `.ai/rules/portfolio.md` — la section `AccountType` justifie l'absence du plafond de versement par
  « `TransactionType` n'a que `Buy`/`Sell`, aucun mouvement d'espèces ». La prémisse tombe ; le
  plafond reste hors périmètre, mais pour une autre raison (versements bruts cumulés ≠ flux nets).
- `.ai/rules/portfolio.md` — `TransactionFlow` devient aussi le site unique du signe.
- Une règle nouvelle sur les lignes `auto` : qui les écrit, qui les régénère, pourquoi elles ne se
  modifient pas à la main.
