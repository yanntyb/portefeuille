# Enveloppes de détention — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre l'enveloppe de détention (PEA, compte-titres) visible sur chaque position et
attacher à chaque compte ses règles fiscales déclaratives.

**Architecture:** `wallets` gagne un type (`AccountType`) et une date d'ouverture. Le type porte
ses règles dans l'enum, sur le modèle de `Market\Enums\AssetClass`. La ligne de position
(`Portfolio\HoldingLineData`, jumelée par `MarketView\HoldingRowData`) transporte le wallet
jusqu'au front, qui l'affiche en badge. Une action `GetAccountBreakdown` regroupe les lignes par
enveloppe et alimente une nouvelle section du tableau de bord, par un port `Wealth` comme toutes
les autres lectures du tableau de bord.

**Tech Stack:** Laravel 12 / PHP 8.5, Pest, Inertia v3 + Vue 3 (`resources/js`), Vitest,
Tailwind, Pint.

**Spec:** `docs/superpowers/specs/2026-08-31-enveloppes-de-detention-design.md`

## Global Constraints

- Tout texte visible par l'utilisateur est en **français**, accents compris.
- `vendor/bin/pint --dirty --format agent` après toute modification PHP, avant de committer.
- Tests : `php artisan test --compact --filter=...`. Front : `bun run test -- <fichier>`.
- `.ai/rules` fait loi. En particulier :
  - **`.ai/rules/market-view.md`** : « Toute clé ajoutée à `Portfolio\HoldingLineData` doit
    l'être ici aussi » (`MarketView\Datas\HoldingRowData`), **au même rang**, l'ordre des clés
    JSON étant l'empreinte du blob hors-ligne.
  - **`.ai/rules/portfolio.md`** : `HoldingValuator` est le seul site du calcul de gain ;
    `gainPct` rend `null`, jamais `0.0`, sur un coût nul.
  - **`.ai/rules/services.md`** : un `Services/` ne connaît ni Eloquent ni conteneur. Les enums
    et Datas de ce plan n'en contiennent pas non plus.
  - **`.ai/rules/factories.md`** : tout nouvel `Instrument::factory()->create()` versé dans un jeu
    de `SnapshotInvariantTest` doit fixer son `ticker`.
- **Aucune notion de plafond de versement**, sous aucune forme : ni colonne, ni méthode d'enum,
  ni champ de Data, ni libellé. Décision motivée en tête de spec.
- `tests/Feature/SnapshotInvariantTest.php` porte un hash figé du corps de l'instantané. Toute
  tâche qui change le JSON servi le déplace : le hash se recalcule depuis l'échec du test et une
  ligne de commentaire s'ajoute au bloc « Modifié une Nième fois » du fichier. Ne jamais
  « corriger » ce hash sans écrire pourquoi il bouge.

---

## Structure de fichiers

**Créés**

| Fichier | Responsabilité |
| --- | --- |
| `database/migrations/2026_08_31_000000_add_account_type_to_wallets_table.php` | `account_type`, `opened_at` sur `wallets` |
| `app/Contexts/Portfolio/Enums/AccountType.php` | le type d'enveloppe et ses règles déclaratives |
| `app/Contexts/Portfolio/Enums/AccountTypeTest.php` | test co-localisé de l'enum |
| `app/Contexts/Portfolio/Datas/AccountLineData.php` | une enveloppe, telle que la lit le tableau de bord |
| `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php` | regroupe les lignes de l'aperçu par enveloppe |
| `app/Contexts/Portfolio/Actions/GetAccountBreakdownTest.php` | test co-localisé de l'action |
| `app/Contexts/Wealth/Ports/AccountsPort.php` | le contrat que le tableau de bord consomme |
| `app/Contexts/Wealth/Datas/WealthAccountData.php` | jumelle de `AccountLineData` côté Wealth |
| `app/Contexts/Wealth/Actions/GetWealthAccounts.php` | lecture patrimoniale des enveloppes |
| `app/Contexts/Wealth/Actions/GetWealthAccountsTest.php` | test co-localisé |
| `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php` | adaptateur vers `GetAccountBreakdown` |
| `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php` | test co-localisé |
| `resources/js/components/dashboard/WealthAccountsSection.vue` | la section « Enveloppes » |
| `resources/js/components/dashboard/WealthAccountsSection.test.ts` | test Vitest de la section |

**Modifiés**

| Fichier | Modification |
| --- | --- |
| `app/Contexts/Portfolio/Models/Wallet.php` | cast `account_type`, `opened_at` + PHPDoc |
| `app/Contexts/Portfolio/Factories/WalletFactory.php` | `account_type`, `opened_at` + états `pea()`/`cto()` |
| `app/Contexts/Portfolio/Datas/HoldingLineData.php` | 3 propriétés wallet, 4 clés JSON |
| `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php` | jointure `wallets`, alimente les champs |
| `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php` | assertions wallet + deux enveloppes |
| `app/Contexts/MarketView/Datas/HoldingRowData.php` | mêmes 3 propriétés, mêmes 4 clés, même rang |
| `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php` | recopie les champs wallet |
| `app/Contexts/Wealth/WealthProvider.php` | lie `AccountsPort` |
| `app/Providers/AppServiceProvider.php` | passe `PortfolioAccounts` à `WealthProvider::registers()` |
| `app/Contexts/Wealth/Http/DashboardController.php` | prop différée `accounts` |
| `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php` | sixième clé `accounts` |
| `database/seeders/BackupSeeder.php` | déduit `account_type` du nom du dump |
| `tests/Feature/BackupSeederTest.php` | assertion sur le type déduit |
| `tests/Feature/DashboardPageTest.php` | la prop `accounts` et son contenu |
| `tests/Feature/SnapshotInvariantTest.php` | hash + commentaire (deux fois : tâches 3 et 8) |
| `resources/js/lib/portfolio.ts` | `HoldingLine` gagne les champs wallet |
| `resources/js/lib/wealth.ts` | type `WealthAccount` |
| `resources/js/lib/instrumentList.ts` | clé de ligne par actif **et** enveloppe |
| `resources/js/lib/instrumentList.test.ts` | *(créé s'il n'existe pas — vérifier)* cas deux enveloppes |
| `resources/js/components/InstrumentList.vue` | badge d'enveloppe, `:key` composite |
| `resources/js/components/InstrumentList.test.ts` | assertions badge |
| `resources/js/lib/snapshotContract.ts` | `accounts` dans `DashboardSnapshot` |
| `resources/js/Pages/Dashboard.vue` | prop, `aheadOfNetwork`, section |

---

## Task 1: AccountType et les colonnes de wallets

**Files:**
- Create: `app/Contexts/Portfolio/Enums/AccountType.php`
- Create: `app/Contexts/Portfolio/Enums/AccountTypeTest.php`
- Create: `database/migrations/2026_08_31_000000_add_account_type_to_wallets_table.php`
- Modify: `app/Contexts/Portfolio/Models/Wallet.php`
- Modify: `app/Contexts/Portfolio/Factories/WalletFactory.php`

**Interfaces:**
- Consomme : `App\Contexts\Market\Enums\AssetClass` (cas `Equity`, `Bond`, `Commodity`, `Crypto`).
- Produit : `AccountType::Pea`, `AccountType::Cto`, et les méthodes `getLabel(): string`,
  `taxRegimeLabel(): string`, `maturityYears(): ?int`, `allowedAssetClasses(): ?array`,
  `admits(AssetClass $class): bool`, `values(): list<string>`. `Wallet::$account_type` est casté
  en `AccountType`, `Wallet::$opened_at` en date. `WalletFactory` expose `pea()` et `cto()`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `app/Contexts/Portfolio/Enums/AccountTypeTest.php` — test co-localisé, sans base de données,
comme `TransactionTypeTest.php` à côté :

```php
<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Enums\AccountType;

it('rend les valeurs de tous les cas', function () {
    expect(AccountType::values())->toBe(['pea', 'cto']);
});

it('nomme chaque enveloppe en français', function () {
    expect(AccountType::Pea->getLabel())->toBe('PEA')
        ->and(AccountType::Cto->getLabel())->toBe('Compte-titres');
});

it('affiche un régime d\'imposition par enveloppe', function () {
    expect(AccountType::Pea->taxRegimeLabel())->toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %')
        ->and(AccountType::Cto->taxRegimeLabel())->toBe('Flat tax 30 %');
});

it('ne donne une maturité qu\'aux enveloppes qui en ont une', function () {
    expect(AccountType::Pea->maturityYears())->toBe(5)
        ->and(AccountType::Cto->maturityYears())->toBeNull();
});

it('restreint le PEA aux actions et n\'admet rien d\'autre', function () {
    expect(AccountType::Pea->allowedAssetClasses())->toBe([AssetClass::Equity])
        ->and(AccountType::Pea->admits(AssetClass::Equity))->toBeTrue()
        ->and(AccountType::Pea->admits(AssetClass::Bond))->toBeFalse()
        ->and(AccountType::Pea->admits(AssetClass::Commodity))->toBeFalse()
        ->and(AccountType::Pea->admits(AssetClass::Crypto))->toBeFalse();
});

it('n\'impose aucune restriction au compte-titres', function () {
    expect(AccountType::Cto->allowedAssetClasses())->toBeNull();

    foreach (AssetClass::cases() as $class) {
        expect(AccountType::Cto->admits($class))->toBeTrue();
    }
});
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=AccountType`
Expected: FAIL — `Class "App\Contexts\Portfolio\Enums\AccountType" not found`.

- [ ] **Step 3: Écrire l'enum**

Créer `app/Contexts/Portfolio/Enums/AccountType.php` :

```php
<?php

namespace App\Contexts\Portfolio\Enums;

use App\Contexts\Market\Enums\AssetClass;

/**
 * L'enveloppe de détention : le compte sur lequel une position est tenue. `AssetClass` dit à quoi
 * le porteur est exposé, cet enum dit sous quel régime il la détient — un même ETF vaut la même
 * chose dans un PEA et dans un compte-titres, il ne s'impose pas pareil.
 *
 * Les règles vivent ici et nulle part ailleurs : ce sont des constantes légales, pas de la
 * configuration. Elles sont déclarées et affichées, jamais appliquées à un calcul — l'application
 * n'estime aucun impôt.
 *
 * Le plafond de versement en est délibérément absent : `TransactionType` n'a pas de mouvement
 * d'espèces, aucun montant versé n'est donc calculable, et un plafond sans son solde ne renseigne
 * sur rien.
 */
enum AccountType: string
{
    case Pea = 'pea';
    case Cto = 'cto';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pea => 'PEA',
            self::Cto => 'Compte-titres',
        };
    }

    /** Texte informatif : ce que dit la loi, jamais ce que l'application calcule. */
    public function taxRegimeLabel(): string
    {
        return match ($this) {
            self::Pea => 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
            self::Cto => 'Flat tax 30 %',
        };
    }

    /** Années de détention avant le régime favorable ; null quand l'enveloppe n'en a pas. */
    public function maturityYears(): ?int
    {
        return match ($this) {
            self::Pea => 5,
            self::Cto => null,
        };
    }

    /**
     * Les expositions que l'enveloppe admet ; `null` quand elle admet tout.
     *
     * Le PEA n'accueille que des actions. `AssetClass` ne dit pas la zone géographique : la
     * restriction aux titres de l'Union n'est pas représentable ici, elle n'est donc pas
     * prétendue.
     *
     * @return ?list<AssetClass>
     */
    public function allowedAssetClasses(): ?array
    {
        return match ($this) {
            self::Pea => [AssetClass::Equity],
            self::Cto => null,
        };
    }

    public function admits(AssetClass $class): bool
    {
        $allowed = $this->allowedAssetClasses();

        return $allowed === null || in_array($class, $allowed, true);
    }
}
```

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=AccountType`
Expected: PASS (6 tests).

- [ ] **Step 5: Écrire la migration**

Créer `database/migrations/2026_08_31_000000_add_account_type_to_wallets_table.php`, calqué sur
`2026_08_24_000000_add_asset_class_to_assets_table.php` :

```php
<?php

use App\Contexts\Portfolio\Enums\AccountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'enveloppe de détention, à côté du nom du compte.
     *
     * Le défaut est le compte-titres : c'est l'enveloppe la moins affirmative — ni restriction
     * d'exposition, ni maturité —, et un compte typé à tort en PEA lèverait des alertes
     * d'éligibilité fausses là où l'inverse n'affirme rien. `BackupSeeder` corrige ensuite les
     * comptes qu'il reconnaît.
     *
     * `opened_at` reste nullable : le dump ne porte que la date d'import de la ligne, pas celle
     * d'ouverture du compte. Une ancienneté fausse serait pire qu'absente, l'affichage l'omet
     * donc tant que la colonne est nulle.
     */
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('account_type')->default(AccountType::Cto->value)->index();
            $table->date('opened_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn(['account_type', 'opened_at']);
        });
    }
};
```

- [ ] **Step 6: Caster les colonnes sur le modèle**

Dans `app/Contexts/Portfolio/Models/Wallet.php`, compléter le PHPDoc de classe et ajouter la
méthode `casts()` (le modèle n'en a pas encore) :

```php
/**
 * @property-read int $id
 * @property int $user_id
 * @property string $name
 * @property AccountType $account_type
 * @property ?Carbon $opened_at
 */
```

```php
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'opened_at' => 'date',
        ];
    }
```

Ajouter les `use App\Contexts\Portfolio\Enums\AccountType;` et `use Illuminate\Support\Carbon;`
en tête.

- [ ] **Step 7: Compléter la factory**

Dans `app/Contexts/Portfolio/Factories/WalletFactory.php` :

```php
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'account_type' => AccountType::Cto,
            'opened_at' => null,
        ];
    }

    /** Un PEA ouvert il y a plus de cinq ans : l'enveloppe restreinte, sa maturité franchie. */
    public function pea(): static
    {
        return $this->state(fn (): array => [
            'name' => 'PEA',
            'account_type' => AccountType::Pea,
            'opened_at' => now()->subYears(7)->toDateString(),
        ]);
    }

    public function cto(): static
    {
        return $this->state(fn (): array => [
            'name' => 'CTO',
            'account_type' => AccountType::Cto,
            'opened_at' => null,
        ]);
    }
```

Ajouter `use App\Contexts\Portfolio\Enums\AccountType;` en tête.

Le défaut reste `Cto` : les jeux de test existants n'attendent aucune restriction d'exposition,
et un défaut `Pea` ferait apparaître des alertes d'éligibilité dans des tests qui ne parlent pas
d'enveloppes.

- [ ] **Step 8: Lancer les tests du contexte Portfolio**

Run: `php artisan test --compact --filter=Portfolio`
Expected: PASS — la migration et les casts ne changent aucun comportement lu.

- [ ] **Step 9: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio database/migrations/2026_08_31_000000_add_account_type_to_wallets_table.php
git commit -m "feat: type les enveloppes de detention"
```

---

## Task 2: Le wallet sur la ligne de position

**Files:**
- Modify: `app/Contexts/Portfolio/Datas/HoldingLineData.php`
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php:57-90`
- Modify: `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php`

**Interfaces:**
- Consomme : `AccountType` (tâche 1), `Wallet::$account_type`.
- Produit : `HoldingLineData` gagne, **après `assetClass` et avant `quantity`**, les propriétés
  `public int $walletId`, `public string $walletName`, `public AccountType $accountType` ; et,
  au même rang dans `jsonSerialize()`, les clés `walletId`, `walletName`, `accountType`,
  `accountTypeLabel`. Tout appelant construisant un `HoldingLineData` doit les passer.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `app/Contexts/Portfolio/Actions/GetPortfolioOverviewTest.php` :

```php
it('porte l\'enveloppe de détention sur chaque ligne', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $line = app(GetPortfolioOverview::class)($user)->holdings[0];

    expect($line->walletId)->toBe($wallet->id)
        ->and($line->walletName)->toBe('PEA')
        ->and($line->accountType)->toBe(AccountType::Pea)
        ->and($line->jsonSerialize()['accountTypeLabel'])->toBe('PEA');
});

it('rend deux lignes distinctes pour un même actif tenu dans deux enveloppes', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);

    foreach ([$pea, $cto] as $wallet) {
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => 5,
            'avg_cost' => 80,
        ]);
    }

    $holdings = app(GetPortfolioOverview::class)($user)->holdings;

    expect($holdings)->toHaveCount(2)
        ->and(array_map(fn ($line): int => $line->walletId, $holdings))
        ->toEqualCanonicalizing([$pea->id, $cto->id]);
});
```

Ajouter `use App\Contexts\Portfolio\Enums\AccountType;` aux imports du fichier de test.

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=GetPortfolioOverview`
Expected: FAIL — `Undefined property: ...HoldingLineData::$walletId`.

- [ ] **Step 3: Ajouter les propriétés à la Data**

Dans `app/Contexts/Portfolio/Datas/HoldingLineData.php`, insérer les trois propriétés après
`assetClass` et les quatre clés au même rang dans `jsonSerialize()` :

```php
        public AssetClass $assetClass,
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
        public float $quantity,
```

```php
            'assetClassLabel' => $this->assetClass->getLabel(),
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
            'quantity' => $this->quantity,
```

Ajouter `use App\Contexts\Portfolio\Enums\AccountType;`. Le rang compte : `MarketView\HoldingRowData`
doit reproduire ce JSON à l'octet près (tâche 3).

- [ ] **Step 4: Charger le wallet dans l'action**

Dans `app/Contexts/Portfolio/Actions/GetPortfolioOverview.php`, méthode `readLines()` : ajouter
`'wallet'` au `with()` et alimenter les trois champs.

```php
        $holdings = Holding::query()
            ->with(['asset', 'wallet'])
            ->where('user_id', $user->id)
            ->get();
```

```php
                assetClass: $holding->asset->asset_class,
                walletId: (int) $holding->wallet_id,
                walletName: $holding->wallet->name,
                accountType: $holding->wallet->account_type,
                quantity: $quantity,
```

`with()` et non une jointure : `Holding` a une clé primaire composite et un `belongsTo('wallet')`
déjà déclaré, l'`eager load` tient en une requête supplémentaire pour tout le portefeuille — la
mémoïsation `scoped` de l'action la paie une seule fois par requête HTTP.

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=GetPortfolioOverview`
Expected: PASS.

- [ ] **Step 6: Lancer la suite entière pour relever les appelants cassés**

Run: `php artisan test --compact`
Expected: échecs attendus là où un `HoldingLineData` est construit à la main ou où le JSON est
figé — `MarketView\Infrastructure\PortfolioTotals` (tâche 3) et `SnapshotInvariantTest` (tâche 3).
Noter la liste ; ne rien corriger hors du périmètre de cette tâche si l'échec appartient à la
tâche 3.

- [ ] **Step 7: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio
git commit -m "feat: porte l'enveloppe sur chaque ligne de position"
```

---

## Task 3: Jumeler la ligne côté MarketView

**Files:**
- Modify: `app/Contexts/MarketView/Datas/HoldingRowData.php`
- Modify: `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php:44-68`
- Modify: `tests/Feature/SnapshotInvariantTest.php`
- Test: `tests/Feature/InstrumentsPageTest.php` (assertion ajoutée)

**Interfaces:**
- Consomme : `HoldingLineData::$walletId`, `$walletName`, `$accountType` (tâche 2).
- Produit : `HoldingRowData` porte les trois mêmes propriétés au même rang et sérialise les
  quatre mêmes clés au même rang — c'est ce JSON que reçoit la page d'exposition sous
  `overview.holdings[]`.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `tests/Feature/InstrumentsPageTest.php` (suivre le style des `it(...)` du fichier ; le
jeu de données existant y crée déjà un utilisateur, un wallet et des positions — réutiliser la
fixture du fichier plutôt que d'en écrire une nouvelle) :

```php
it('nomme l\'enveloppe de chaque position de la liste', function () {
    Carbon::setTestNow('2026-08-21');
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create(['ticker' => 'TTE.PA']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/actions')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.holdings.0.walletName', 'PEA')
            ->where('overview.holdings.0.accountType', 'pea')
            ->where('overview.holdings.0.accountTypeLabel', 'PEA')
        );
});
```

Compléter les imports du fichier de test si `Wallet`, `Price`, `Holding`, `Instrument`,
`InstrumentType`, `Carbon` ou `Assert` en manquent.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=InstrumentsPage`
Expected: FAIL — la clé `overview.holdings.0.walletName` est absente (et une erreur d'argument
manquant sur `HoldingRowData` peut la précéder).

- [ ] **Step 3: Jumeler la Data**

Dans `app/Contexts/MarketView/Datas/HoldingRowData.php`, ajouter les trois propriétés après
`assetClass`, les quatre clés après `assetClassLabel` — **exactement le même code et le même
ordre que dans `HoldingLineData`** :

```php
        public AssetClass $assetClass,
        public int $walletId,
        public string $walletName,
        public AccountType $accountType,
        public float $quantity,
```

```php
            'assetClassLabel' => $this->assetClass->getLabel(),
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
            'quantity' => $this->quantity,
```

Ajouter `use App\Contexts\Portfolio\Enums\AccountType;`. Mettre à jour le PHPDoc de classe :
« Treize clés pour onze propriétés » devient « Dix-sept clés pour quatorze propriétés ».

`AccountType` est un enum de Portfolio importé dans une Data de MarketView : c'est la même
entorse que `AssetClass` et `InstrumentType`, qui viennent déjà de Market. La règle
`market-view.md` interdit les **actions et Datas** du voisin hors de `Infrastructure/`, pas ses
enums — sans quoi `HoldingRowData` ne pourrait pas non plus porter `AssetClass`.

- [ ] **Step 4: Recopier les champs dans l'adaptateur**

Dans `app/Contexts/MarketView/Infrastructure/PortfolioTotals.php`, méthode `overviewFor()`,
compléter le `new HoldingRowData(...)` :

```php
                    assetClass: $line->assetClass,
                    walletId: $line->walletId,
                    walletName: $line->walletName,
                    accountType: $line->accountType,
                    quantity: $line->quantity,
```

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=InstrumentsPage`
Expected: PASS.

- [ ] **Step 6: Déplacer le hash de l'instantané**

Run: `php artisan test --compact --filter=SnapshotInvariant`
Expected: FAIL, avec le hash obtenu et le hash attendu dans le message.

Remplacer le hash figé du fichier par celui obtenu, puis ajouter au bloc de commentaire une ligne
qui dit pourquoi il bouge, dans la forme des précédentes :

```
 * Modifié une treizième fois : chaque ligne de position nomme son enveloppe de détention, donc
 * l'aperçu de chaque exposition gagne `walletId`, `walletName`, `accountType` et
 * `accountTypeLabel` par position.
```

Relancer : `php artisan test --compact --filter=SnapshotInvariant` → PASS.

- [ ] **Step 7: Lancer la suite entière**

Run: `php artisan test --compact`
Expected: PASS intégral. Un échec restant signale un autre constructeur de `HoldingLineData` ou
`HoldingRowData` non mis à jour — le corriger ici.

- [ ] **Step 8: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/MarketView tests/Feature
git commit -m "feat: jumelle l'enveloppe dans la ligne servie aux pages d'exposition"
```

---

## Task 4: GetAccountBreakdown

**Files:**
- Create: `app/Contexts/Portfolio/Datas/AccountLineData.php`
- Create: `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php`
- Create: `app/Contexts/Portfolio/Actions/GetAccountBreakdownTest.php`

**Interfaces:**
- Consomme : `GetPortfolioOverview` (liée en `scoped`, mémoïsée par utilisateur), `HoldingLineData`
  avec ses champs wallet (tâche 2), `AccountType` (tâche 1), `Wallet::$opened_at`.
- Produit : `GetAccountBreakdown::__invoke(User $user): array` rendant une
  `list<AccountLineData>`, triée par valeur de marché décroissante. `AccountLineData` porte
  `walletId`, `walletName`, `accountType`, `marketValue`, `gain`, `gainPct`, `ageInYears`,
  `maturityYears`, `taxRegimeLabel`, `ineligibleAssetNames`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `app/Contexts/Portfolio/Actions/GetAccountBreakdownTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Enums\AccountType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

function holdIn(Wallet $wallet, InstrumentType $type, float $close, float $qty, float $avgCost): Instrument
{
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
    Holding::factory()->create([
        'user_id' => $wallet->user_id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => $qty,
        'avg_cost' => $avgCost,
    ]);

    return $asset;
}

it('regroupe les positions par enveloppe et totalise chacune', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 50, qty: 4, avgCost: 50);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->walletId)->toBe($pea->id)
        ->and($lines[0]->marketValue)->toBe(1000.0)
        ->and($lines[0]->gain)->toBe(200.0)
        ->and($lines[0]->gainPct)->toBe(25.0)
        ->and($lines[0]->accountType)->toBe(AccountType::Pea)
        ->and($lines[0]->taxRegimeLabel)->toBe(AccountType::Pea->taxRegimeLabel())
        ->and($lines[1]->walletId)->toBe($cto->id)
        ->and($lines[1]->marketValue)->toBe(200.0)
        ->and($lines[1]->gain)->toBe(0.0);
});

it('rend un pourcentage nul, et non zéro, sur une enveloppe à coût nul', function () {
    $user = User::factory()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $cto->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => null,
    ]);

    expect(app(GetAccountBreakdown::class)($user)[0]->gainPct)->toBeNull();
});

it('compte l\'ancienneté et la maturité du PEA, et rien sans date d\'ouverture', function () {
    Carbon::setTestNow('2026-08-31');
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create(['opened_at' => '2019-06-01']);
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    holdIn($cto, InstrumentType::Stock, close: 10, qty: 1, avgCost: 10);

    $lines = app(GetAccountBreakdown::class)($user);

    expect($lines[0]->ageInYears)->toBe(7)
        ->and($lines[0]->maturityYears)->toBe(5)
        ->and($lines[1]->ageInYears)->toBeNull()
        ->and($lines[1]->maturityYears)->toBeNull();
});

it('signale une position que l\'enveloppe n\'admet pas', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    holdIn($pea, InstrumentType::Stock, close: 100, qty: 10, avgCost: 80);
    $crypto = holdIn($pea, InstrumentType::Crypto, close: 50, qty: 1, avgCost: 40);
    holdIn($cto, InstrumentType::Crypto, close: 50, qty: 1, avgCost: 40);

    $lines = app(GetAccountBreakdown::class)($user);
    $byWallet = array_column(
        array_map(fn ($line): array => [$line->walletId, $line], $lines),
        1,
        0,
    );

    expect($byWallet[$pea->id]->ineligibleAssetNames)->toBe([$crypto->name])
        ->and($byWallet[$cto->id]->ineligibleAssetNames)->toBe([]);
});

it('ne rend aucune ligne sans position', function () {
    $user = User::factory()->create();
    Wallet::factory()->for($user)->cto()->create();

    expect(app(GetAccountBreakdown::class)($user))->toBe([]);
});
```

Vérifier que `Instrument::factory()->ofType(InstrumentType::Crypto)` produit bien un actif de
classe `AssetClass::Crypto` (`AssetClass::defaultForType()` le garantit) ; sinon poser
`asset_class` explicitement dans le `create()`.

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter=GetAccountBreakdown`
Expected: FAIL — `Class "App\Contexts\Portfolio\Actions\GetAccountBreakdown" not found`.

- [ ] **Step 3: Écrire la Data**

Créer `app/Contexts/Portfolio/Datas/AccountLineData.php` :

```php
<?php

namespace App\Contexts\Portfolio\Datas;

use App\Contexts\Portfolio\Enums\AccountType;
use JsonSerializable;

/**
 * Une enveloppe de détention et ce qu'elle tient. Les règles qu'elle affiche sont déclaratives :
 * elles viennent de `AccountType`, aucune n'entre dans un calcul.
 *
 * `gainPct` rend `null`, jamais `0.0`, quand le coût est nul — la règle du contexte, ici comme sur
 * la ligne et sur le total.
 *
 * `ageInYears` est `null` quand l'enveloppe n'a pas de date d'ouverture connue : une ancienneté
 * fausse serait pire qu'absente.
 */
readonly class AccountLineData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames actifs que l'enveloppe n'admet pas */
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

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType->value,
            'accountTypeLabel' => $this->accountType->getLabel(),
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'ageInYears' => $this->ageInYears,
            'maturityYears' => $this->maturityYears,
            'taxRegimeLabel' => $this->taxRegimeLabel,
            'ineligibleAssetNames' => $this->ineligibleAssetNames,
        ];
    }
}
```

- [ ] **Step 4: Écrire l'action**

Créer `app/Contexts/Portfolio/Actions/GetAccountBreakdown.php` :

```php
<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\HoldingValuator;

/**
 * Le portefeuille vu par enveloppe : une ligne par compte détenant au moins une position.
 *
 * Elle consomme `GetPortfolioOverview`, liée en `scoped` et mémoïsée par utilisateur, plutôt que
 * de relire les positions : le tableau de bord appelle déjà l'aperçu, une seconde lecture paierait
 * deux fois les mêmes lignes. Le regroupement se fait donc en mémoire, comme le découpage par
 * exposition de l'aperçu lui-même.
 *
 * Elle ne calcule aucune valorisation : les totaux passent par `HoldingValuator`, seul site du
 * gain du contexte.
 */
class GetAccountBreakdown
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private HoldingValuator $valuator,
    ) {}

    /** @return list<AccountLineData> */
    public function __invoke(User $user): array
    {
        $lines = ($this->overview)($user)->holdings;

        /** @var array<int, list<HoldingLineData>> $byWallet */
        $byWallet = [];

        foreach ($lines as $line) {
            $byWallet[$line->walletId][] = $line;
        }

        if ($byWallet === []) {
            return [];
        }

        $openedAt = Wallet::query()
            ->whereIn('id', array_keys($byWallet))
            ->pluck('opened_at', 'id');

        $accounts = [];

        foreach ($byWallet as $walletId => $walletLines) {
            $accountType = $walletLines[0]->accountType;
            $totals = $this->valuator->totals($walletLines);
            $opened = $openedAt[$walletId] ?? null;

            $ineligible = [];

            foreach ($walletLines as $line) {
                if (! $accountType->admits($line->assetClass)) {
                    $ineligible[] = $line->assetName;
                }
            }

            $accounts[] = new AccountLineData(
                walletId: $walletId,
                walletName: $walletLines[0]->walletName,
                accountType: $accountType,
                marketValue: $totals['totalValue'],
                gain: $totals['totalGain'],
                gainPct: $totals['totalGainPct'],
                ageInYears: $opened?->diffInYears(now()),
                maturityYears: $accountType->maturityYears(),
                taxRegimeLabel: $accountType->taxRegimeLabel(),
                ineligibleAssetNames: $ineligible,
            );
        }

        usort(
            $accounts,
            fn (AccountLineData $left, AccountLineData $right): int => $right->marketValue <=> $left->marketValue,
        );

        return $accounts;
    }
}
```

Vérifier la forme réelle du retour de `HoldingValuator::totals()` (clés `totalValue`, `totalCost`,
`totalGain`, `totalGainPct`) et l'ajuster si elle diffère. `$opened?->diffInYears(now())` suppose
que le cast `date` du modèle rend un `Carbon` ; le `pluck` sur `opened_at` passe par le cast, mais
le confirmer au premier lancement et, à défaut, faire `Carbon::parse()`.

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter=GetAccountBreakdown`
Expected: PASS (5 tests).

- [ ] **Step 6: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Portfolio
git commit -m "feat: lit le portefeuille par enveloppe de detention"
```

---

## Task 5: Le port Wealth et son adaptateur

**Files:**
- Create: `app/Contexts/Wealth/Ports/AccountsPort.php`
- Create: `app/Contexts/Wealth/Datas/WealthAccountData.php`
- Create: `app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php`
- Create: `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthAccounts.php`
- Create: `app/Contexts/Wealth/Actions/GetWealthAccountsTest.php`
- Modify: `app/Contexts/Wealth/WealthProvider.php`
- Modify: `app/Providers/AppServiceProvider.php:117-121`

**Interfaces:**
- Consomme : `GetAccountBreakdown` et `AccountLineData` (tâche 4).
- Produit : `AccountsPort::accountsFor(int $userId): array` rendant une `list<WealthAccountData>` ;
  `GetWealthAccounts::__invoke(int $userId): array` de même ; `WealthAccountData` sérialise les
  mêmes onze clés que `AccountLineData`, dans le même ordre.
  `WealthProvider::registers()` gagne un paramètre nommé `accounts: class-string<AccountsPort>`.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `app/Contexts/Wealth/Infrastructure/PortfolioAccountsTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Ports\AccountsPort;

it('traduit les enveloppes du portefeuille sans rien recalculer', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $pea->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $accounts = app(AccountsPort::class)->accountsFor($user->id);

    expect($accounts)->toHaveCount(1)
        ->and($accounts[0]->walletName)->toBe('PEA')
        ->and($accounts[0]->accountTypeLabel)->toBe('PEA')
        ->and($accounts[0]->marketValue)->toBe(1000.0)
        ->and($accounts[0]->gainPct)->toBe(25.0);
});

it('ne rend aucune enveloppe pour un utilisateur inconnu', function () {
    expect(app(AccountsPort::class)->accountsFor(9999))->toBe([]);
});
```

Créer `app/Contexts/Wealth/Actions/GetWealthAccountsTest.php` :

```php
<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Wealth\Actions\GetWealthAccounts;

it('rend les enveloppes de l\'utilisateur, la plus grosse en tête', function () {
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $cto = Wallet::factory()->for($user)->cto()->create();

    foreach ([[$pea, 100.0, 10.0], [$cto, 50.0, 2.0]] as [$wallet, $close, $qty]) {
        $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
        Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $close]);
        Holding::factory()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'asset_id' => $asset->id,
            'quantity' => $qty,
            'avg_cost' => $close,
        ]);
    }

    $accounts = app(GetWealthAccounts::class)($user->id);

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]->walletName)->toBe('PEA')
        ->and($accounts[1]->walletName)->toBe('CTO');
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `php artisan test --compact --filter="PortfolioAccounts|GetWealthAccounts"`
Expected: FAIL — `Target class [App\Contexts\Wealth\Ports\AccountsPort] does not exist`.

- [ ] **Step 3: Écrire la Data, le port, l'adaptateur et l'action**

`app/Contexts/Wealth/Datas/WealthAccountData.php` :

```php
<?php

namespace App\Contexts\Wealth\Datas;

use JsonSerializable;

/**
 * Une enveloppe de détention vue du patrimoine : jumelle de `Portfolio\Datas\AccountLineData`,
 * dont elle reproduit le JSON clé pour clé — l'instantané hors-ligne publie
 * `sha1(json_encode($body))`, qu'un ordre différent ferait retélécharger à tous les clients.
 *
 * Les libellés arrivent déjà rendus : le patrimoine ne connaît pas `AccountType`, c'est le
 * travail de l'adaptateur de le traduire.
 */
readonly class WealthAccountData implements JsonSerializable
{
    /** @param list<string> $ineligibleAssetNames */
    public function __construct(
        public int $walletId,
        public string $walletName,
        public string $accountType,
        public string $accountTypeLabel,
        public float $marketValue,
        public float $gain,
        public ?float $gainPct,
        public ?int $ageInYears,
        public ?int $maturityYears,
        public string $taxRegimeLabel,
        public array $ineligibleAssetNames,
    ) {}

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'walletId' => $this->walletId,
            'walletName' => $this->walletName,
            'accountType' => $this->accountType,
            'accountTypeLabel' => $this->accountTypeLabel,
            'marketValue' => $this->marketValue,
            'gain' => $this->gain,
            'gainPct' => $this->gainPct,
            'ageInYears' => $this->ageInYears,
            'maturityYears' => $this->maturityYears,
            'taxRegimeLabel' => $this->taxRegimeLabel,
            'ineligibleAssetNames' => $this->ineligibleAssetNames,
        ];
    }
}
```

`app/Contexts/Wealth/Ports/AccountsPort.php` :

```php
<?php

namespace App\Contexts\Wealth\Ports;

use App\Contexts\Wealth\Datas\WealthAccountData;

interface AccountsPort
{
    /**
     * Les enveloppes de détention de l'utilisateur, la plus grosse en tête. Une enveloppe sans
     * position n'a pas de ligne.
     *
     * @return list<WealthAccountData>
     */
    public function accountsFor(int $userId): array;
}
```

`app/Contexts/Wealth/Infrastructure/PortfolioAccounts.php` :

```php
<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * L'action est injectée, jamais construite : elle consomme `GetPortfolioOverview`, liée en
 * `scoped` et mémoïsée par utilisateur, si bien que la lecture du tableau de bord et celle des
 * enveloppes partagent le même instantané du portefeuille.
 */
class PortfolioAccounts implements AccountsPort
{
    public function __construct(private GetAccountBreakdown $breakdown) {}

    /** @return list<WealthAccountData> */
    public function accountsFor(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return array_map(
            fn (AccountLineData $line): WealthAccountData => new WealthAccountData(
                walletId: $line->walletId,
                walletName: $line->walletName,
                accountType: $line->accountType->value,
                accountTypeLabel: $line->accountType->getLabel(),
                marketValue: $line->marketValue,
                gain: $line->gain,
                gainPct: $line->gainPct,
                ageInYears: $line->ageInYears,
                maturityYears: $line->maturityYears,
                taxRegimeLabel: $line->taxRegimeLabel,
                ineligibleAssetNames: $line->ineligibleAssetNames,
            ),
            ($this->breakdown)($user),
        );
    }
}
```

`app/Contexts/Wealth/Actions/GetWealthAccounts.php`, calquée sur `GetWealthTransactions` :

```php
<?php

namespace App\Contexts\Wealth\Actions;

use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * Les enveloppes de détention du patrimoine. Le tableau de bord les replie : c'est la lecture
 * fiscale, pas le grand chiffre.
 */
class GetWealthAccounts
{
    public function __construct(private AccountsPort $accounts) {}

    /** @return list<WealthAccountData> */
    public function __invoke(int $userId): array
    {
        return $this->accounts->accountsFor($userId);
    }
}
```

- [ ] **Step 4: Lier le port**

Dans `app/Contexts/Wealth/WealthProvider.php`, ajouter le paramètre et la liaison, sur le modèle
de `$transactions` :

```php
     * @param  class-string<TransactionsPort>  $transactions
     * @param  class-string<AccountsPort>  $accounts
     */
    public static function registers(Application $app, array $extra, string $transactions, string $accounts): void
    {
        $app->bind(TransactionsPort::class, $transactions);
        $app->bind(AccountsPort::class, $accounts);
```

Ajouter `use App\Contexts\Wealth\Ports\AccountsPort;`.

Dans `app/Providers/AppServiceProvider.php`, compléter l'appel :

```php
        WealthProvider::registers(
            app: $this->app,
            extra: [RealEstateClass::class],
            transactions: PortfolioLedger::class,
            accounts: PortfolioAccounts::class,
        );
```

Ajouter `use App\Contexts\Wealth\Infrastructure\PortfolioAccounts;`.

- [ ] **Step 5: Lancer les tests pour vérifier qu'ils passent**

Run: `php artisan test --compact --filter="PortfolioAccounts|GetWealthAccounts"`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth app/Providers
git commit -m "feat: expose les enveloppes au patrimoine par un port"
```

---

## Task 6: BackupSeeder déduit le type

**Files:**
- Modify: `database/seeders/BackupSeeder.php` (constante + `seedWallets()`)
- Modify: `tests/Feature/BackupSeederTest.php`

**Interfaces:**
- Consomme : `AccountType` (tâche 1), `Wallet` avec ses nouvelles colonnes.
- Produit : les wallets créés par le seeder portent un `account_type` déduit de leur nom.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `tests/Feature/BackupSeederTest.php`, en suivant le style des tests existants du fichier
(ils sautent quand le dump est absent — reprendre la même garde) :

```php
it('type les enveloppes du dump d\'après leur nom', function () {
    $this->seed(BackupSeeder::class);

    expect(Wallet::query()->where('name', 'PEA')->pluck('account_type')->unique()->all())
        ->toBe([AccountType::Pea])
        ->and(Wallet::query()->where('name', 'CTO')->pluck('account_type')->unique()->all())
        ->toBe([AccountType::Cto])
        ->and(Wallet::query()->where('name', 'Portefeuille Crypto')->value('account_type'))
        ->toBe(AccountType::Cto);
});
```

Compléter les imports (`AccountType`, `Wallet`, `BackupSeeder`) s'ils manquent.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=BackupSeeder`
Expected: FAIL sur le PEA — toutes les enveloppes valent `AccountType::Cto` par défaut de colonne.

- [ ] **Step 3: Déduire le type dans le seeder**

Dans `database/seeders/BackupSeeder.php`, ajouter la constante près de `STOCK_TICKERS` :

```php
    /**
     * Enveloppe de chaque nom de portefeuille du dump, qui précède la colonne `account_type`.
     *
     * Même principe que `STOCK_TICKERS` pour `assets.type` : la colonne se déduit, la
     * correspondance est écrite ici. Tout nom inconnu retombe sur le compte-titres — l'enveloppe
     * la moins affirmative : typer à tort en PEA lèverait des alertes d'éligibilité fausses,
     * l'inverse n'affirme rien.
     *
     * @var array<string, string>
     */
    private const WALLET_ACCOUNT_TYPES = [
        'PEA' => 'pea',
        'CTO' => 'cto',
    ];
```

Dans `seedWallets()`, poser le type au `firstOrCreate()`. La clé métier reste
`user_id` + `name` ; le type va dans les attributs du second argument :

```php
            $wallet = Wallet::query()->firstOrCreate(
                [/* clé métier existante, inchangée */],
                [
                    /* attributs existants, inchangés */
                    'account_type' => self::WALLET_ACCOUNT_TYPES[$name] ?? AccountType::Cto->value,
                ],
            );
```

Lire le code réel de `seedWallets()` (autour de la ligne 214) avant d'éditer : ne pas déplacer la
clé métier, ne pas casser l'idempotence. Si `firstOrCreate` peut retomber sur une ligne existante
créée par un lancement antérieur, poser aussi le type après coup, pour que le seeder reste
rejouable :

```php
            $wallet->fill(['account_type' => self::WALLET_ACCOUNT_TYPES[$name] ?? AccountType::Cto->value])->save();
```

Ajouter `use App\Contexts\Portfolio\Enums\AccountType;` aux imports.

Le portefeuille crypto créé par le seeder lui-même (`CRYPTO_WALLET`) n'est pas dans la
correspondance : il retombe sur `Cto`, ce qui est juste.

- [ ] **Step 4: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=BackupSeeder`
Expected: PASS.

- [ ] **Step 5: Pint puis commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/seeders tests/Feature/BackupSeederTest.php
git commit -m "feat: deduit l'enveloppe des portefeuilles du dump"
```

---

## Task 7: La prop `accounts` du tableau de bord

**Files:**
- Modify: `app/Contexts/Wealth/Http/DashboardController.php`
- Modify: `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php`
- Modify: `tests/Feature/DashboardPageTest.php`
- Modify: `tests/Feature/SnapshotInvariantTest.php`

**Interfaces:**
- Consomme : `GetWealthAccounts` (tâche 5).
- Produit : la page `Dashboard` reçoit une prop différée `accounts` sous le groupe `enveloppes`,
  `list<WealthAccountData>` ; `BuildWealthSnapshot` rend une sixième clé `accounts`.

- [ ] **Step 1: Écrire le test qui échoue**

Ajouter à `tests/Feature/DashboardPageTest.php` :

```php
it('sert les enveloppes de détention au tableau de bord', function () {
    Carbon::setTestNow('2026-08-31');
    $user = User::factory()->create();
    $pea = Wallet::factory()->for($user)->pea()->create();
    $asset = Instrument::factory()->ofType(InstrumentType::Stock)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => 100]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $pea->id,
        'asset_id' => $asset->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('accounts', 1)
            ->where('accounts.0.walletName', 'PEA')
            ->where('accounts.0.accountTypeLabel', 'PEA')
            ->where('accounts.0.marketValue', 1000.0)
            ->where('accounts.0.taxRegimeLabel', 'Exonéré après 5 ans, prélèvements sociaux 17,2 %')
            ->where('accounts.0.maturityYears', 5)
            ->where('accounts.0.ineligibleAssetNames', [])
        );
});
```

Vérifier dans le fichier comment les tests existants forcent la résolution d'une prop différée
(le tableau de bord diffère `series`, `income`, `sectors`, `transactions`) ; reprendre le même
mécanisme, sans quoi `accounts` sera absente de la première réponse. Si les tests du fichier
appellent la page avec un en-tête de rechargement partiel, faire de même.

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run: `php artisan test --compact --filter=DashboardPage`
Expected: FAIL — prop `accounts` absente.

- [ ] **Step 3: Ajouter la prop au contrôleur**

Dans `app/Contexts/Wealth/Http/DashboardController.php`, après `sectors` :

```php
            /**
             * Repliée à l'arrivée : la lecture fiscale des enveloppes ne se charge que pour qui la
             * déplie, comme les secteurs et l'historique.
             */
            'accounts' => Inertia::defer(fn (): array => $user !== null
                ? app(GetWealthAccounts::class)($user->id)
                : [], 'enveloppes'),
```

Ajouter `use App\Contexts\Wealth\Actions\GetWealthAccounts;`.

- [ ] **Step 4: Ajouter la clé à l'instantané**

Dans `app/Contexts/Wealth/Actions/BuildWealthSnapshot.php` : injecter `GetWealthAccounts`,
compléter le PHPDoc de retour d'une entrée
`accounts: list<WealthAccountData>` et le tableau d'une entrée `'accounts' => ($this->accounts)($userId)`,
placée **après `transactions`** — l'ordre des clés est l'empreinte du blob. Mettre à jour la
phrase d'en-tête : « les cinq props du tableau de bord » devient « les six props ».

- [ ] **Step 5: Lancer le test pour vérifier qu'il passe**

Run: `php artisan test --compact --filter=DashboardPage`
Expected: PASS.

- [ ] **Step 6: Déplacer le hash de l'instantané**

Run: `php artisan test --compact --filter=SnapshotInvariant`
Expected: FAIL avec le nouveau hash.

Reporter le hash et ajouter la ligne de commentaire :

```
 * Modifié une quatorzième fois : le tableau de bord gagne une section enveloppes, donc `dashboard`
 * une sixième clé — le portefeuille regroupé par compte de détention, avec le régime déclaré de
 * chacun.
```

Relancer : PASS.

- [ ] **Step 7: Lancer la suite entière, Pint, commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add app/Contexts/Wealth tests/Feature
git commit -m "feat: sert les enveloppes de detention au tableau de bord"
```

---

## Task 8: La section Enveloppes

**Files:**
- Create: `resources/js/components/dashboard/WealthAccountsSection.vue`
- Create: `resources/js/components/dashboard/WealthAccountsSection.test.ts`
- Modify: `resources/js/lib/wealth.ts`
- Modify: `resources/js/lib/snapshotContract.ts`
- Modify: `resources/js/Pages/Dashboard.vue`

**Interfaces:**
- Consomme : la prop `accounts` du contrôleur (tâche 7).
- Produit : `export interface WealthAccount` dans `lib/wealth.ts` ; le composant
  `WealthAccountsSection` prenant `accounts?: WealthAccount[] | null` ;
  `DashboardSnapshot.accounts: WealthAccount[]`.

- [ ] **Step 1: Déclarer le type**

Dans `resources/js/lib/wealth.ts`, à côté de `WealthSector` / `WealthTransactionLine` :

```ts
/** Une enveloppe de détention : ce qu'elle tient, et les règles qu'elle déclare. */
export interface WealthAccount {
    walletId: number;
    walletName: string;
    accountType: string;
    accountTypeLabel: string;
    marketValue: number;
    gain: number;
    gainPct: number | null;
    /** Ancienneté en années ; `null` quand la date d'ouverture est inconnue. */
    ageInYears: number | null;
    /** Années de détention avant le régime favorable ; `null` quand l'enveloppe n'en a pas. */
    maturityYears: number | null;
    taxRegimeLabel: string;
    /** Actifs que l'enveloppe n'admet pas. Vide dans le cas normal. */
    ineligibleAssetNames: string[];
}
```

Dans `resources/js/lib/snapshotContract.ts`, ajouter `accounts: WealthAccount[];` à
`DashboardSnapshot` **après `transactions`** (même ordre que le serveur) et compléter l'import
depuis `./wealth`.

- [ ] **Step 2: Écrire le test qui échoue**

Créer `resources/js/components/dashboard/WealthAccountsSection.test.ts`, calqué sur
`WealthTransactionsSection.test.ts` — même mock d'Inertia, même montage sur un hôte neuf. La
section arrive repliée : chaque test qui lit le contenu doit d'abord cliquer
`[data-section-toggle]`.

```ts
import { describe, expect, it, vi } from 'vitest';
import { createApp, nextTick } from 'vue';
import type { WealthAccount } from '@/lib/wealth';

/**
 * `Deferred` demande un routeur monté ; la section ne le rend que sans données, et les tests lui en
 * donnent toujours. Un composant vide suffit donc à satisfaire l'import.
 */
vi.mock('@inertiajs/vue3', () => ({
    Deferred: { setup: () => () => null },
}));

const { default: WealthAccountsSection } = await import(
    '@/components/dashboard/WealthAccountsSection.vue'
);

const account = (overrides: Partial<WealthAccount> = {}): WealthAccount => ({
    walletId: 1,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    marketValue: 1000,
    gain: 200,
    gainPct: 25,
    ageInYears: 7,
    maturityYears: 5,
    taxRegimeLabel: 'Exonéré après 5 ans, prélèvements sociaux 17,2 %',
    ineligibleAssetNames: [],
    ...overrides,
});

/** Monte la section dépliée : repliée, elle ne rend aucune carte. */
async function mountSection(accounts: WealthAccount[]): Promise<HTMLElement> {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(WealthAccountsSection, { accounts }).mount(host);
    host.querySelector<HTMLElement>('[data-section-toggle]')?.click();
    await nextTick();

    return host;
}

describe('section enveloppes du tableau de bord', () => {
    it('arrive repliée : aucune carte avant le premier clic', () => {
        const host = document.createElement('div');
        document.body.append(host);
        createApp(WealthAccountsSection, { accounts: [account()] }).mount(host);

        expect(host.querySelector('[data-section="wealth-accounts"]')).not.toBeNull();
        expect(host.querySelector('[data-account-card]')).toBeNull();
    });

    it('nomme chaque enveloppe, son type et son régime', async () => {
        const host = await mountSection([account(), account({ walletId: 2, walletName: 'CTO', accountTypeLabel: 'Compte-titres' })]);

        const cards = host.querySelectorAll('[data-account-card]');
        expect(cards).toHaveLength(2);
        expect(cards[0].querySelector('[data-account-name]')?.textContent).toContain('PEA');
        expect(cards[1].querySelector('[data-account-name]')?.textContent).toContain('Compte-titres');
        expect(cards[0].querySelector('[data-account-regime]')?.textContent?.trim())
            .toBe('Exonéré après 5 ans, prélèvements sociaux 17,2 %');
    });

    it('dit l\'ancienneté et le seuil franchi, et les tait sans date d\'ouverture', async () => {
        const known = await mountSection([account()]);
        expect(known.querySelector('[data-account-age]')?.textContent).toContain('7 ans');
        expect(known.querySelector('[data-account-age]')?.textContent).toContain('franchi');

        const unknown = await mountSection([account({ ageInYears: null, maturityYears: null })]);
        expect(unknown.querySelector('[data-account-age]')).toBeNull();
    });

    it('signale les actifs que l\'enveloppe n\'admet pas', async () => {
        const host = await mountSection([account({ ineligibleAssetNames: ['Bitcoin'] })]);

        expect(host.querySelector('[data-account-alert]')?.textContent).toContain('Bitcoin');
    });

    it('le dit plutôt que de rendre une liste vide', async () => {
        const host = await mountSection([]);

        expect(host.textContent).toContain('Aucune enveloppe détenue pour le moment.');
    });
});
```

Vérifier au premier lancement que `CollapsibleSection` pose bien `data-section="wealth-accounts"`
et `data-section-toggle` (c'est ce qu'assertent les tests voisins) ; ajuster les sélecteurs si le
composant a changé.

- [ ] **Step 3: Lancer le test pour vérifier qu'il échoue**

Run: `bun run test -- resources/js/components/dashboard/WealthAccountsSection.test.ts`
Expected: FAIL — le composant n'existe pas.

- [ ] **Step 4: Écrire le composant**

Créer `resources/js/components/dashboard/WealthAccountsSection.vue`, calqué sur
`WealthSectorsSection.vue` pour la structure `CollapsibleSection` + `Deferred` (repli à l'arrivée,
squelette pulsant, `#rescue` hors-ligne) :

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { Deferred } from '@inertiajs/vue3';
import CollapsibleSection from '@/components/instrument/CollapsibleSection.vue';
import GainPill from '@/components/GainPill.vue';
import { eur, pct } from '@/lib/format';
import type { WealthAccount } from '@/lib/wealth';

const props = defineProps<{ accounts?: WealthAccount[] | null }>();

const rows = computed<WealthAccount[]>(() => props.accounts ?? []);

const hasAccounts = computed<boolean>(() => rows.value.length > 0);

/** L'ancienneté ne s'affiche pas sans date d'ouverture : un compte « 0 an » mentirait. */
const age = (account: WealthAccount): string | null =>
    account.ageInYears === null ? null : `${account.ageInYears} ans`;

const maturity = (account: WealthAccount): string | null => {
    if (account.maturityYears === null || account.ageInYears === null) {
        return null;
    }

    return account.ageInYears >= account.maturityYears
        ? `Seuil de ${account.maturityYears} ans franchi`
        : `Seuil de ${account.maturityYears} ans dans ${account.maturityYears - account.ageInYears} ans`;
};
</script>

<template>
    <!-- Repliée à l'arrivée, comme les secteurs : la lecture fiscale se demande. -->
    <CollapsibleSection section="wealth-accounts" title="Enveloppes">
        <template v-if="props.accounts !== null && props.accounts !== undefined">
            <ul v-if="hasAccounts" class="flex flex-col gap-3">
                <li
                    v-for="account in rows"
                    :key="account.walletId"
                    data-account-card
                    class="flex flex-col gap-1.5 rounded-md border border-separator px-3 py-3"
                >
                    <span class="flex items-center gap-3">
                        <span data-account-name class="min-w-0 flex-1 truncate font-semibold">
                            {{ account.walletName }}
                            <span class="text-muted-foreground">({{ account.accountTypeLabel }})</span>
                        </span>

                        <span data-account-value class="shrink-0 font-bold tabular-nums">
                            {{ eur(account.marketValue, 0) }}
                        </span>

                        <!-- `GainPill` prend une valeur (qui décide la teinte) et le texte à rendre. -->
                        <GainPill :value="account.gain" :label="pct(account.gainPct)" />
                    </span>

                    <span data-account-regime class="text-xs text-muted-foreground">
                        {{ account.taxRegimeLabel }}
                    </span>

                    <span
                        v-if="age(account)"
                        data-account-age
                        class="text-xs text-subtle-foreground"
                    >
                        Ouverte depuis {{ age(account) }}<template v-if="maturity(account)"> · {{ maturity(account) }}</template>
                    </span>

                    <span
                        v-if="account.ineligibleAssetNames.length"
                        data-account-alert
                        class="text-xs font-semibold text-negative"
                    >
                        Non éligible à cette enveloppe : {{ account.ineligibleAssetNames.join(', ') }}
                    </span>
                </li>
            </ul>

            <p v-else class="py-8 text-center text-sm text-muted-foreground">
                Aucune enveloppe détenue pour le moment.
            </p>
        </template>

        <Deferred v-else data="accounts">
            <template #fallback>
                <div class="flex flex-col gap-2">
                    <div v-for="n in 2" :key="n" class="h-16 w-full animate-pulse rounded-md bg-muted"></div>
                </div>
            </template>

            <template #rescue>
                <p class="py-8 text-center text-sm text-muted-foreground">
                    Données indisponibles hors-ligne.
                </p>
            </template>

            <span />
        </Deferred>
    </CollapsibleSection>
</template>
```

La classe de teinte de l'alerte : reprendre celle que rend `gainClass()` pour une valeur négative
(`lib/format.ts`) plutôt qu'inventer un jeton Tailwind — `text-negative` est un nom de
remplacement dans ce plan, pas un jeton vérifié du projet.

- [ ] **Step 5: Brancher la section sur la page**

Dans `resources/js/Pages/Dashboard.vue` : ajouter `accounts?: WealthAccount[];` aux props,
la ligne `aheadOfNetwork` correspondante, et la section avant `WealthSectorsSection` :

```ts
const accounts = aheadOfNetwork(() => props.accounts, () => snapshot.dashboard?.accounts);
```

```vue
        <!-- Ce que le patrimoine rapporte, puis ce qui l'a fait bouger. -->
        <WealthTransactionsSection :transactions="transactions" />

        <!-- Sous quel régime tout cela est tenu : la lecture par enveloppe, toutes classes
             confondues — un PEA tient des actions, un compte-titres tient le reste. -->
        <WealthAccountsSection :accounts="accounts" />

        <!-- La lecture la plus fine ferme la page : les secteurs qui traversent les classes. -->
        <WealthSectorsSection :sectors="sectors" />
```

Compléter les imports (`WealthAccountsSection`, `WealthAccount`).

- [ ] **Step 6: Lancer les tests front**

Run: `bun run test -- resources/js/components/dashboard/WealthAccountsSection.test.ts`
Expected: PASS (4 tests).

Puis la suite front entière : `bun run test`
Expected: PASS. Un échec de type sur `DashboardSnapshot` signale un `accounts` manquant dans une
fixture de test — le compléter.

- [ ] **Step 7: Commit**

```bash
git add resources/js
git commit -m "feat: ajoute une section enveloppes au tableau de bord"
```

---

## Task 9: Le badge d'enveloppe sur les listes

**Files:**
- Modify: `resources/js/lib/portfolio.ts`
- Modify: `resources/js/lib/instrumentList.ts`
- Modify: `resources/js/lib/instrumentList.test.ts` *(créer s'il n'existe pas)*
- Modify: `resources/js/components/InstrumentList.vue`
- Modify: `resources/js/components/InstrumentList.test.ts`

**Interfaces:**
- Consomme : les clés `walletId`, `walletName`, `accountType`, `accountTypeLabel` servies sous
  `overview.holdings[]` (tâche 3).
- Produit : `HoldingLine` gagne les quatre champs ; `InstrumentRow` gagne `walletId`,
  `walletName`, `accountTypeLabel` et une clé de rendu `rowKey: string` valant
  `` `${assetId}-${walletId}` ``.

**Bug à corriger dans cette tâche.** `holdingRows()` indexe les positions par `assetId` dans une
`Map`, et `InstrumentList.vue` rend `:key="row.id"`. Un actif tenu dans deux enveloppes produit
deux lignes de même `assetId` : la `Map` n'en garde qu'une, les deux lignes reçoivent donc la même
part, la même barre et le même gain, et Vue voit deux fois la même clé. C'est exactement le cas
que ce chantier rend visible — il se corrige ici, sinon le badge affichera deux enveloppes sur
des chiffres identiques et faux.

- [ ] **Step 1: Écrire les tests qui échouent**

Dans `resources/js/lib/instrumentList.test.ts` (le lire d'abord ; s'il n'existe pas, le créer sur
le modèle de `resources/js/lib/portfolio.test.ts`, dont l'aide de construction d'un `HoldingLine`
est probablement déjà là — la réutiliser plutôt que d'en écrire une seconde) :

```ts
import { describe, expect, it } from 'vitest';
import { holdingRows } from '@/lib/instrumentList';
import type { HoldingLine } from '@/lib/portfolio';

const holdingLine = (overrides: Partial<HoldingLine> = {}): HoldingLine => ({
    assetId: 1,
    assetName: 'Apple',
    ticker: 'AAPL',
    type: 'stock',
    typeLabel: 'Action',
    assetClass: 'equity',
    assetClassLabel: 'Actions',
    walletId: 10,
    walletName: 'PEA',
    accountType: 'pea',
    accountTypeLabel: 'PEA',
    quantity: 3,
    avgCost: 100,
    lastPrice: 200,
    marketValue: 600,
    gain: 300,
    gainPct: 100,
    ...overrides,
});

describe('lignes du portefeuille', () => {
    it('rend une ligne par actif et par enveloppe, chacune avec son propre poids', () => {
        const rows = holdingRows(
            [
                holdingLine({ walletId: 10, walletName: 'PEA', marketValue: 750 }),
                holdingLine({ walletId: 20, walletName: 'CTO', accountTypeLabel: 'Compte-titres', marketValue: 250 }),
            ],
            null,
        );

        expect(rows).toHaveLength(2);
        expect(rows.map((row) => row.rowKey)).toEqual(['1-10', '1-20']);
        expect(rows.map((row) => row.share)).toEqual([75, 25]);
        expect(rows.map((row) => row.walletName)).toEqual(['PEA', 'CTO']);
    });
});
```

Dans `resources/js/components/InstrumentList.test.ts`, le fichier construit une constante `row`
partagée : lui ajouter les trois nouveaux champs (`rowKey: '7-10'`, `walletId: 10`,
`walletName: 'PEA'`, `accountTypeLabel: 'PEA'`), puis ajouter ce test. `mountList()` ne monte
qu'une ligne : écrire un montage local à deux lignes.

```ts
it('affiche l\'enveloppe de chaque position, un même titre tenu deux fois comprise', () => {
    const host = document.createElement('div');
    document.body.append(host);

    createApp(InstrumentList, {
        rows: [
            row,
            { ...row, rowKey: '7-20', walletId: 20, walletName: 'CTO', accountTypeLabel: 'Compte-titres' },
        ],
        loading: false,
        emptyLabel: 'Aucune position.',
    }).mount(host);

    const badges = host.querySelectorAll('[data-instrument-wallet]');
    expect([...badges].map((badge) => badge.textContent?.trim())).toEqual(['PEA', 'CTO']);
    expect(badges[1].getAttribute('title')).toBe('Compte-titres');
    expect(host.querySelectorAll('[data-instrument-row]')).toHaveLength(2);
});
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run: `bun run test -- resources/js/lib/instrumentList.test.ts resources/js/components/InstrumentList.test.ts`
Expected: FAIL — `rowKey` et `walletName` inexistants.

- [ ] **Step 3: Étendre le type de ligne**

Dans `resources/js/lib/portfolio.ts`, ajouter à `HoldingLine`, après `assetClassLabel` :

```ts
    walletId: number;
    walletName: string;
    accountType: string;
    accountTypeLabel: string;
```

- [ ] **Step 4: Indexer par actif et par enveloppe**

Dans `resources/js/lib/instrumentList.ts` :

```ts
export interface InstrumentRow extends CatalogRow {
    /** Gain latent de la position. */
    gain: number | null;
    gainPct: number | null;
    /** Part du portefeuille en pourcentage. */
    share: number | null;
    /** Largeur CSS de la barre de poids. */
    barWidth: string | null;
    /**
     * Clé de rendu : l'actif seul ne suffit pas. Un titre tenu dans deux enveloppes fait deux
     * lignes de même `id`, que Vue confondrait et dont la seconde écraserait le poids de la
     * première.
     */
    rowKey: string;
    walletId: number;
    walletName: string;
    accountTypeLabel: string;
}
```

Remplacer l'indexation par `assetId` par une clé composite. `joinTrends` travaillant sur
`CatalogLine` (indexé par `id`, l'actif), le poids se rattache par position d'index plutôt que par
`id` :

```ts
const keyOf = (line: HoldingLine): string => `${line.assetId}-${line.walletId}`;

export const holdingRows = (
    holdings: HoldingLine[],
    trends: CatalogTrend[] | null | undefined,
): InstrumentRow[] => {
    const weights = holdingWeights(holdings);
    const joined = joinTrends(weights.map(asCatalogLine), trends);

    return joined
        .map((row: CatalogRow, index: number): InstrumentRow => {
            const weight = weights[index];

            return {
                ...row,
                gain: weight?.line.gain ?? null,
                gainPct: weight?.line.gainPct ?? null,
                share: weight?.share ?? null,
                barWidth: weight?.barWidth ?? null,
                rowKey: weight === undefined ? String(row.id) : keyOf(weight.line),
                walletId: weight?.line.walletId ?? 0,
                walletName: weight?.line.walletName ?? '',
                accountTypeLabel: weight?.line.accountTypeLabel ?? '',
            };
        })
        .sort(byMarketValue);
};
```

L'appariement par index est légitime ici : `joinTrends` (`resources/js/lib/catalog.ts`) est un
`lines.map(...)` pur — même ordre, même cardinal, aucun filtrage. Le vérifier tout de même si ce
fichier a changé depuis. `CatalogLine.id` reste l'actif seul : c'est lui qui construit l'URL de la
fiche (`/asset/${row.id}`) et qui apparie les tendances, qui sont par actif et non par enveloppe.

- [ ] **Step 5: Afficher le badge**

Dans `resources/js/components/InstrumentList.vue` : `:key="row.rowKey"` sur le `<li>`, et le badge
sur la seconde ligne, avant la barre de poids :

```vue
                <span class="flex items-center gap-3 text-xs">
                    <span
                        v-if="row.walletName"
                        data-instrument-wallet
                        :title="row.accountTypeLabel"
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 font-semibold text-muted-foreground"
                    >
                        {{ row.walletName }}
                    </span>

                    <span class="h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-separator md:w-28">
```

Le nom plutôt que le type : deux comptes-titres chez deux courtiers portent le même type et des
noms différents, c'est le nom qui discrimine. Le type reste en infobulle.

Vérifier au rendu que la seconde ligne ne déborde pas sur téléphone ; si le badge serre la barre,
retirer `md:w-28` de celle-ci ou raccourcir la barre, pas le badge.

- [ ] **Step 6: Lancer les tests front**

Run: `bun run test`
Expected: PASS. Corriger les fixtures de test qui construisent un `HoldingLine` incomplet.

- [ ] **Step 7: Vérifier au navigateur**

Run: `bun run build` (ou s'assurer que `bun run dev` tourne), puis ouvrir `/actions` et `/`.
Attendu : le badge d'enveloppe sur chaque position, la section Enveloppes repliée en bas du
tableau de bord, dépliable, avec une carte par compte.

- [ ] **Step 8: Commit**

```bash
git add resources/js
git commit -m "feat: affiche l'enveloppe de detention sur les listes de positions"
```

---

## Task 10: Consigner la règle

**Files:**
- Modify: `.ai/rules/portfolio.md` (via l'outil MCP `record-rule`, pas à la main)

- [ ] **Step 1: Enregistrer la règle**

Appeler l'outil Boost `record-rule` avec :

- `glob`: `app/Contexts/Portfolio/**`
- `title`: `AccountType est le seul site des règles d'enveloppe`
- `note`:

```
`Portfolio\Enums\AccountType` porte tout ce que dit une enveloppe de détention : libellé, régime
d'imposition, maturité, expositions admises. Les règles sont déclaratives — affichées, jamais
appliquées à un calcul. L'application n'estime aucun impôt.

Le plafond de versement en est délibérément absent : `TransactionType` n'a que `Buy`/`Sell`, aucun
mouvement d'espèces, donc aucun montant versé n'est calculable, et un plafond sans son solde ne
renseigne sur rien. Un cumul de flux nets serait faux — réinvestir le produit d'une vente ne
consomme pas de plafond.

Une position se lit par actif ET par enveloppe : `holdings_projection` a pour clé primaire
`(asset_id, wallet_id)`, et `HoldingLineData` porte `walletId`. Tout regroupement côté front doit
donc clé sur les deux — `instrumentList.ts` le faisait sur `assetId` seul et confondait les deux
lignes d'un titre tenu dans deux comptes.
```

- [ ] **Step 2: Commit**

```bash
git add .ai/rules
git commit -m "docs: consigne la regle des enveloppes de detention"
```

---

## Notes d'exécution

**Ordre.** Les tâches 1 → 3 sont une chaîne : la 2 casse `PortfolioTotals` et le hash de
l'instantané, que la 3 répare. Ne pas s'arrêter entre les deux sur une suite rouge. Les tâches
4 → 5 → 7 → 8 forment la seconde chaîne. La 6 (seeder) et la 9 (badge) sont indépendantes de la
première une fois la 3 passée.

**Le hash de l'instantané bouge deux fois** : tâche 3 (les lignes de position gagnent quatre
clés) et tâche 7 (le tableau de bord gagne une clé). Deux entrées de commentaire, pas une.

**Ce que ce plan ne fait pas** — et qui n'est pas un oubli : aucune estimation d'impôt, aucun
plafond de versement, aucun écran d'édition des enveloppes, aucun filtre global par compte,
aucune page `/comptes`, aucune déclinaison des séries d'évolution ou des performances par
enveloppe, et `PositionLineData` (page analyse) continue de consolider les enveloppes — c'est sa
raison d'être.
