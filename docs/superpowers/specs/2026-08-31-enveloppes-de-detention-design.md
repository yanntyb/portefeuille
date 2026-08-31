# Enveloppes de détention

Un même titre peut être détenu sur plusieurs comptes : une action dans un CTO, un ETF dans un
PEA. La base le supporte déjà — `holdings_projection` a pour clé primaire `(asset_id, wallet_id)`,
et `wallets` porte une ligne par enveloppe. Rien de tout cela ne remonte à l'écran : `wallets`
n'a que `user_id` et `name`, `HoldingLineData` ne porte aucun champ de wallet, aucune route ni
aucun composant ne mentionne l'enveloppe.

Conséquence visible aujourd'hui : la règle `.ai/rules/portfolio.md` promet « `HoldingLineData` =
une ligne par enveloppe », et c'est vrai en SQL, mais un titre détenu sur deux comptes s'affiche
en deux lignes visuellement identiques que rien ne distingue.

Ce chantier rend l'enveloppe visible et lui attache ses règles fiscales, **déclarées et
affichées, jamais calculées**. Aucune estimation d'impôt, aucun net après impôt, aucune aide à
l'arbitrage.

## Périmètre

Dedans :

- typage des enveloppes (`AccountType`), date d'ouverture, règles déclaratives attachées ;
- l'enveloppe sur chaque ligne de position, affichée en badge sur les pages liste ;
- une section « Enveloppes » sur le tableau de bord ;
- alerte d'éligibilité : une position dont la classe d'actif n'est pas admise par l'enveloppe.

Dehors, explicitement :

- **le plafond de versement, sous toutes ses formes** — ni montant consommé, ni jauge, ni même
  le plafond légal en libellé. `TransactionType` n'a que `Buy`/`Sell`, aucun mouvement d'espèces :
  le plafond PEA porte sur les versements et non sur les achats, un cumul de flux nets afficherait
  donc un chiffre faux, et un cas `Deposit`/`Withdrawal` traverserait `ProjectHolding`,
  `TransactionObserver`, `CalculateRealizedGain`, `PortfolioLedger` et `ValuationCalculator` pour
  une seule jauge. Afficher le plafond seul, sans ce qu'il en reste, ne renseigne sur rien.
  `AccountType` ne porte aucune méthode de plafond.
- imposition estimée, coût fiscal d'une vente, recommandation d'enveloppe ;
- écran de création ou d'édition des enveloppes : le type vient du seeder ;
- déclinaison des séries d'évolution et des performances par enveloppe ;
- filtre global par compte, page `/comptes` dédiée, consolidation dépliable des lignes.

## Cas d'enveloppe

`Pea` et `Cto` uniquement — les deux seuls types présents en base. `PeaPme`, `AssuranceVie` et
`Per` s'ajouteront quand un compte du type existera : coder des règles jamais exercées revient à
écrire des tests contre une spécification non vérifiée.

## Données

Deux colonnes sur `wallets`, une migration :

| Colonne | Type | Rôle |
| --- | --- | --- |
| `account_type` | `string`, indexée, défaut `'cto'` | cas de `AccountType` |
| `opened_at` | `date`, nullable | ancienneté et seuil de maturité |

`opened_at` est nullable : le dump n'a pas la date d'ouverture réelle des comptes, seulement le
`created_at` de la ligne (mars 2026), qui est la date d'import et non celle du compte. Une
ancienneté fausse serait pire qu'absente — quand `opened_at` est `null`, l'ancienneté et le seuil
de maturité ne s'affichent pas.

Le défaut `'cto'` porte la migration des lignes existantes : le CTO n'a ni restriction
d'éligibilité ni maturité, c'est l'enveloppe la moins affirmative. Le seeder corrige ensuite les
PEA.

## AccountType

`app/Contexts/Portfolio/Enums/AccountType.php`, sur le modèle de `Market\Enums\AssetClass` : les
règles vivent dans l'enum, en `match` sur `$this`. Une règle fiscale est une constante, pas de la
configuration.

```php
enum AccountType: string
{
    case Pea = 'pea';
    case Cto = 'cto';

    public function getLabel(): string;

    /** Régime d'imposition, texte informatif. Jamais un calcul. */
    public function taxRegimeLabel(): string;

    /** Années de détention avant le régime favorable ; null quand il n'y en a pas. */
    public function maturityYears(): ?int;

    /**
     * Classes d'actifs admises ; null = toutes.
     *
     * @return ?list<AssetClass>
     */
    public function allowedAssetClasses(): ?array;

    public function admits(AssetClass $class): bool;
}
```

Valeurs :

| | `Pea` | `Cto` |
| --- | --- | --- |
| `getLabel()` | `PEA` | `Compte-titres` |
| `taxRegimeLabel()` | `Exonéré après 5 ans, prélèvements sociaux 17,2 %` | `Flat tax 30 %` |
| `maturityYears()` | `5` | `null` |
| `allowedAssetClasses()` | `[AssetClass::Equity]` | `null` |

`admits()` rend `true` quand `allowedAssetClasses()` est `null`. Le PEA n'admet que les actions :
`AssetClass` ne distingue pas la zone géographique, la restriction UE n'est donc pas
représentable ici et n'est pas prétendue.

## Read models

`HoldingLineData` gagne `walletId: int`, `walletName: string`, `accountType: AccountType`, et
sérialise en plus `accountTypeLabel`. C'est le trou fonctionnel décrit en tête : la donnée existe
en base et se perd à la construction de la ligne.

`PositionLineData` ne change pas. Il consolide les enveloppes par actif, c'est sa raison d'être
et l'argument de concentration de `portfolio.md` : un titre à 30 % réparti sur deux comptes est
une exposition de 30 %, pas deux de 15 %.

`GetPortfolioOverview` joint `wallets` à sa lecture SQL unique et alimente les nouveaux champs.
La mémoïsation `scoped` et le comptage des requêtes restent inchangés : une jointure de plus sur
la même requête.

## GetAccountBreakdown

`app/Contexts/Portfolio/Actions/GetAccountBreakdown.php` → `list<AccountLineData>`, une ligne par
enveloppe détenue par l'utilisateur.

Elle consomme `GetPortfolioOverview` — déjà liée en `scoped` et mémoïsée par utilisateur — et
regroupe ses `HoldingLineData` par `walletId`. Elle ne rouvre pas le portefeuille : le tableau de
bord appelle déjà l'aperçu, une seconde lecture serait une requête payée deux fois pour les
mêmes lignes.

```php
readonly class AccountLineData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames */
    public function __construct(
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
        public float $marketValue,
        public float $gain,
        public ?float $gainPct,
        public ?int $ageInYears,
        public ?int $maturityYears,
        public string $taxRegimeLabel,
        public array $ineligibleAssetNames,
    ) {}
}
```

`gainPct` suit la règle du contexte : `null` quand le coût est nul, jamais `0.0`. La valorisation
et le gain viennent de `HoldingValuator` par l'intermédiaire de l'aperçu — aucune formule
réécrite ici.

`ageInYears` est `null` quand `opened_at` l'est. `ineligibleAssetNames` liste les actifs dont
`accountType->admits($holding->assetClass)` est faux ; vide dans le cas normal, et toujours vide
pour un CTO.

## Frontend

**Pages liste** (`/actions`, `/obligations`, `/matieres-premieres`, `/crypto`) : un badge par
ligne portant `walletName`, avec `accountTypeLabel` en attribut `title`. Le nom est plus
discriminant que le type — deux CTO chez deux courtiers portent des noms différents et le même
type.

**Tableau de bord** : une section « Enveloppes », repliée à l'arrivée et alimentée par une prop
différée comme les secteurs et l'historique, avec une carte par compte — nom, type, valeur, gain,
régime d'imposition, ancienneté et seuil de maturité s'ils sont connus, et l'alerte d'éligibilité
quand `ineligibleAssetNames` n'est pas vide. Elle se place entre les transactions et les secteurs.

Une section propre, et non un bloc dans une section existante : le tableau de bord n'a pas de
section Analyse — celle-ci n'existe que sur les pages d'exposition —, et une enveloppe traverse
les classes d'actif. La loger dans l'Analyse d'une page classe montrerait un compte amputé de ce
qu'il tient ailleurs.

Tout le texte visible est en français.

## Chaînage vers Wealth

`DashboardController` n'appelle pas le contexte Portfolio directement. Un adaptateur
`Wealth\Infrastructure\PortfolioAccounts` implémente un port `Wealth\Ports\AccountBreakdownPort`
et délègue à `GetAccountBreakdown`, comme `PortfolioAssetClass` et `PortfolioLedger` délèguent
aux leurs. Le contrôleur dépend du port.

## Seeder

`BackupSeeder::seedWallets()` déduit le type du nom du dump. Les noms y sont littéralement `PEA`
et `CTO` ; `Portefeuille Crypto`, créé par le seeder lui-même, est un CTO. Le mapping est une
constante de classe, même précédent que `STOCK_TICKERS` pour déduire `assets.type` d'un dump qui
précède la colonne.

Le défaut reste `AccountType::Cto` pour tout nom non reconnu : un compte typé à tort en PEA
lèverait des alertes d'éligibilité fausses, l'inverse n'affirme rien.

`opened_at` reste `null` — le dump ne porte pas la date d'ouverture réelle.

## Tests

- `AccountTypeTest` : libellés, régime, maturité, `admits()` pour chaque cas et chaque
  `AssetClass` — y compris que le CTO admet tout et que le PEA refuse crypto, obligation et
  matière première.
- `GetPortfolioOverviewTest` : les lignes portent le wallet ; un même actif sur deux wallets rend
  deux lignes distinctes par `walletId` ; le nombre de requêtes ne change pas.
- `GetAccountBreakdownTest` : regroupement par enveloppe, totaux, `gainPct` à `null` sur coût nul,
  `ageInYears` à `null` sans `opened_at`, alerte d'éligibilité levée sur une crypto en PEA et
  vide sur un CTO.
- `PortfolioAccountsTest` : l'adaptateur délègue et ne recalcule rien.
- Test de la page tableau de bord : le bloc Enveloppes est rendu avec ses cartes.

## Règle à enregistrer

Après implémentation, consigner via `record-rule` sur `app/Contexts/Portfolio/**` : l'enveloppe
est une donnée déclarative, `AccountType` en est le site unique, et le plafond de versement n'y
figure volontairement pas : `TransactionType` n'ayant pas de mouvement d'espèces, aucun montant
versé n'est calculable, et un plafond sans son solde ne renseigne sur rien.
