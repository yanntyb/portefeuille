# Investissement locatif — contexte RealEstate

Date : 2026-08-20
Statut : design validé (brainstorming). Suivi **rétrospectif** d'un bien détenu : loyers,
charges, crédit, valeur estimée. La simulation d'achat et la fiscalité feront l'objet de leurs
propres specs si le besoin se confirme.

## Context

L'application suit un patrimoine boursier (contexts `Market`, `Portfolio`, `Valuation`,
`Income`, `InstrumentView`) et ignore tout de l'immobilier. Ce qui existe déjà et sert de socle :

- **`Income` est multi-sources par design.** Son noyau agrège des `IncomeReceiptData` sans
  connaître leur origine ; le commentaire de `IncomeSourcePort` annonce explicitement le loyer
  (« un loyer les lira »). `IncomeReceiptData::$assetId` est nullable, `label` porte alors seul
  l'identité du revenu. Ajouter les loyers = un cas d'enum + une source taguée
  `income.sources`, zéro modification du noyau.
- **Le patron « contexte autonome » est établi.** Chaque contexte déclare ses ports vers ses
  voisins, les câble par un `Provider::registers()` appelé depuis `AppServiceProvider`, et ne
  dépend d'aucun adaptateur étranger.
- **`PersonalAsset` est mort.** `PersonalAssetType::RealEstate` existe mais aucun code
  fonctionnel ne l'utilise : un commentaire dans `PortfolioProvider`, un test d'isolation de
  scope dans `InstrumentTest`, un commentaire de migration. Ce vestige est supprimé par ce
  sous-projet.
- **Le web est 100 % lecture.** Aucune route POST : les données boursières entrent par seeder,
  tinker ou commandes de sync. L'immobilier suit le même régime.

## Décisions (validées)

- **Nouveau bounded context `app/Contexts/RealEstate/`.** Ni extension de `Portfolio` (qui
  deviendrait fourre-tout bourse + immo), ni détournement de la table `transactions`
  (`quantity`/`unit_price` sans sens pour un loyer). Le bien immobilier n'est pas un instrument
  de marché : il ne touche pas à la table `assets`.
- **Suppression complète de `PersonalAsset`** : modèle, enum, factory et leurs tests.
  `InstrumentTest` garde son test d'isolation en insérant directement une ligne `assets` de type
  hors marché. La table `assets` et sa colonne `type` restent (référentiel Market).
- **Loyers et mensualités jamais stockés.** La source de vérité est le paramétrage : le bail
  (loyer, dates) et le prêt (capital, taux, durée). L'échéancier d'amortissement et les loyers
  attendus sont calculés ; seules les **exceptions** sont persistées (impayé, loyer partiel).
  La vacance locative est un trou entre deux baux, pas une donnée.
- **Révision de loyer = nouveau bail.** Un bail porte un loyer constant de `start_date` à
  `end_date` (`null` = en cours). Augmenter le loyer, c'est clore le bail et en ouvrir un autre.
- **Charges saisies ligne à ligne.** Taxe foncière, copro, assurance, gestion, travaux : des
  écritures ponctuelles datées, pas de récurrence générée. Une taxe foncière = une ligne par an.
- **Valeur du bien saisie à la main**, révisable : une table d'estimations datées, la dernière
  fait foi. Le patrimoine net immobilier = dernière estimation − capital restant dû.
- **Fiscalité hors scope.** Tous les rendements sont avant impôt. Les régimes français
  (micro-foncier, réel, LMNP) forment un sous-projet entier, à bâtir plus tard sur ces
  fondations.
- **Remboursement anticipé hors scope v1.** L'échéancier est pur : capital, taux, durée, point
  final. Extension prévue (table `loan_exceptions`) sans casser le calculateur.
- **Pas de fusion dans le graphe d'évolution ni dans `PortfolioOverviewData`.** Le dashboard
  gagne une carte immobilière séparée. Intégrer le bien aux séries temporelles de `Valuation`
  est une extension notée, pas un besoin v1.
- **Saisie par seeder/tinker**, comme le reste. Aucune route d'écriture, aucun formulaire.

## Suppression de PersonalAsset

Fichiers supprimés :

- `app/Contexts/Portfolio/Models/PersonalAsset.php` + `PersonalAssetTest.php`
- `app/Contexts/Portfolio/Enums/PersonalAssetType.php` + `PersonalAssetTypeTest.php`
- `app/Contexts/Portfolio/Factories/PersonalAssetFactory.php`

Retouches :

- `InstrumentTest:23` : remplace `PersonalAsset::factory()->create()` par une insertion directe
  d'une ligne `assets` avec `type = 'real_estate'` (valeur hors `InstrumentType::values()`).
  Le test prouve toujours que le scope `market` exclut les types inconnus.
- Commentaire de `PortfolioProvider::registers()` et de la migration `create_assets_table`
  ajustés.

## Modèle de données

Six tables, toutes propriété exclusive du contexte `RealEstate` :

```
properties            id, user_id, name, address (nullable), acquisition_date,
                      acquisition_price, acquisition_fees
property_valuations   id, property_id, date, value
leases                id, property_id, monthly_rent, start_date, end_date (nullable)
rent_exceptions       id, lease_id, month (date, 1er du mois), amount_override, note (nullable)
loans                 id, property_id, principal, annual_rate, term_months,
                      start_date, monthly_insurance
property_expenses     id, property_id, date, amount, category, label (nullable)
```

- `acquisition_fees` : notaire, agence, dossier — tout ce qui s'ajoute au prix pour former le
  coût d'acquisition total.
- `rent_exceptions.amount_override` : loyer effectif du mois. `0` = impayé total, un montant
  partiel est possible. Absence de ligne = loyer plein du bail.
- `property_expenses.category` : enum `ExpenseCategory` — `PropertyTax`, `CoOwnership`,
  `Insurance`, `Management`, `Works`, `Other`. Labels français via `getLabel()`.
- Un bien peut porter zéro prêt (achat comptant) ou un seul en v1 ; le modèle (FK sur
  `property_id`) n'interdit pas plusieurs prêts, les calculs v1 les additionnent.

Modèles Eloquent dans `RealEstate/Models`, factories dans `RealEstate/Factories`, enum dans
`RealEstate/Enums`. Un seeder d'exemple peuple un bien complet.

## Services de calcul

Trois calculateurs purs dans `RealEstate/Services`, sans I/O, unit-testés contre des valeurs
vérifiées à la main :

**`LoanAmortizationCalculator`** — échéancier français à mensualité constante :
`M = P·t/12 ÷ (1 − (1 + t/12)^−n)`. Produit `list<AmortizationLineData>` : mois, mensualité,
part d'intérêts, part de capital, assurance, capital restant dû. Expose `remainingAt(date)`
et le coût total du crédit. Taux zéro géré (`M = P/n`).

**`RentScheduleCalculator`** — depuis les baux et leurs exceptions, produit les loyers
effectifs mois par mois du premier bail à aujourd'hui (`RentReceiptData` : mois, attendu,
effectif). Mois couvert par un bail = loyer plein sauf exception ; mois sans bail = vacance,
0 €. Projection 12 mois = loyer du bail actif × 12, 0 si aucun bail en cours.

**`PropertyMetricsCalculator`** — indicateurs, tous avant impôt :

- rendement brut = loyer annuel courant ÷ (prix + frais d'acquisition)
- rendement net = (loyers effectifs 12 derniers mois − charges 12 derniers mois) ÷ (prix + frais)
- cash-flow annuel = loyers − charges − mensualités, sur 12 mois glissants
- cash-on-cash = cash-flow annuel ÷ apport, avec apport = prix + frais − principal emprunté
  (indicateur absent si apport ≤ 0)
- LTV = capital restant dû ÷ dernière valeur estimée

## Actions et restitution

**`GetRealEstateOverview(userId)`** → carte dashboard. Par bien : nom, valeur courante, capital
restant dû, patrimoine net, cash-flow mensuel (cash-flow annuel des 12 mois glissants ÷ 12) ;
plus les totaux. Servie en prop Inertia
différée (groupe `immobilier`) par `DashboardController`, carte avec squelette animé, lien vers
la page du bien. Ne touche ni `PortfolioOverviewData` ni le graphe d'évolution.

**`GetPropertyDetail(propertyId)`** → page dédiée, route `GET /properties/{id}` nommée
`properties.show`, contrôleur `RealEstate/Http/PropertyDetailController`, page Vue
`resources/js/Pages/Properties/Detail.vue`. Contenu :

- en-tête : nom, adresse, « acquis le X pour Y € (dont Z € de frais) »
- indicateurs du `PropertyMetricsCalculator`
- cash-flow mensuel des 12 derniers mois (loyers − charges − mensualité)
- historique des loyers, impayés et vacance marqués
- charges par année, ventilées par catégorie
- tableau d'amortissement complet, en prop différée avec squelette
- LTV et capital restant dû

UI en français, patterns Tailwind du dashboard existant réutilisés.

## Intégration Income

- Cas `Rent` ajouté à l'enum `IncomeSource`, label « Loyers ».
- `Income/Sources/Rent/RentIncomeSource` implémente `IncomeSourcePort` :
  - `receiptsFor` : loyers effectifs passés, `label` = nom du bien, `assetId` null ;
  - `projectedAnnualFor` : délégué à la projection du `RentScheduleCalculator`.
- Le port `Income/Sources/Rent/Ports/RentSchedulePort` est implémenté par
  `Infrastructure/RealEstateRentSchedule`, adaptateur qui interroge le contexte `RealEstate` —
  même patron que la source Dividend avec Market et Portfolio.
- Câblage : la source s'ajoute à la liste `$sources` de `IncomeProvider::registers`, le port se
  binde au même endroit. Le résumé des revenus (`bySource`) et la vue annuelle absorbent les
  loyers sans autre modification.

## Tests

- **Unit** : les trois calculateurs. Échéancier comparé à des valeurs de référence calculées à
  la main (premier mois, mois médian, dernier mois, somme des capitaux = principal), taux zéro,
  vacance entre deux baux, impayé total et partiel, projection sans bail actif.
- **Feature** : `GetRealEstateOverview`, `GetPropertyDetail`, `PropertyDetailController`
  (rendu Inertia, props différées), `RentIncomeSource` (reçus + projection),
  `IncomeSourceRegistry` sommant dividendes et loyers, `InstrumentTest` adapté.
- Factories utilisées partout, états dédiés si besoin (bail clos, prêt soldé).

## Hors scope, extensions prévues

- Fiscalité (micro-foncier, réel, LMNP, prélèvements sociaux).
- Remboursement anticipé du prêt (`loan_exceptions`).
- Fusion du patrimoine immobilier dans les séries `Valuation` et le graphe d'évolution.
- Simulation d'achat (comparaison d'annonces, point mort).
- Formulaires web de saisie.
