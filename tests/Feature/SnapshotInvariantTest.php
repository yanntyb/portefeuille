<?php

use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Le filet du chantier de simplification : l'instantané hors-ligne réunit les quatre contextes —
 * `dashboard` (Wealth), `classes` et `assets` (MarketView), `properties` (RealEstate, fiches et
 * échéanciers compris). Un hash inchangé prouve que les cinq pages rendent le même JSON, ordre des
 * clés compris.
 *
 * L'horloge est gelée : presque tout le code lit `Carbon::now()` — fenêtres glissantes,
 * échéanciers, projections — et un hash figé sur l'heure réelle casserait dès le lendemain.
 *
 * Le jeu couvre les deux pièges du chantier : un actif tenu dans deux enveloppes (la moyenne
 * pondérée) et un bien avec prêt (`loanSummary()` et l'échéancier).
 *
 * Le hash ne porte pas sur `version` (le champ que renvoie `SnapshotController`) mais sur le corps
 * normalisé : `isin` vient de `fake()->optional()->regexify(...)` dans `InstrumentFactory`, sans
 * état figé par le test, donc il change à chaque lancement et fait varier `version` avec lui. Ce
 * n'est ni un identifiant auto-incrémenté ni un ordre non déterministe, mais la même catégorie de
 * fuite dans le JSON — la parade prévue par le cahier des charges s'applique : comparer un tableau
 * normalisé plutôt que le hash brut du contrôleur.
 */
/**
 * Modifié une fois : gainPct rend null, et non 0.0, sur un total à coût nul.
 * Modifié une seconde fois : `seedSnapshotFixture()` posait un bien sans charges, recopié à la
 * main depuis `propertyFixture(['loan' => true])` en en omettant les deux `PropertyExpense`.
 * `expenseYears()` rendait alors `[]` et `ExpenseGrouper` ne traversait jamais le hash. L'appel à
 * la fixture ajoute les deux charges au jeu de données, ce qui déplace le hash.
 * Modifié une troisième fois : secteurs, performances et revenus quittent la composition liste
 * pour la page analyse, et le blob gagne une clé `analyses` par exposition.
 * Modifié une quatrième fois : le jeu ne posait aucun `Dividend` sur le titre détenu, donc le
 * groupe `revenus` de la page analyse restait figé sur du vide (`bySource` en tableau plutôt
 * qu'en objet une fois garni — la seule différence de forme JSON invisible tant que le groupe est
 * vide). Deux détachements ajoutés sur le titre déjà détenu couvrent réellement ce groupe.
 * Modifié une cinquième fois : le jeu ne posait qu'un seul instrument actions, si bien que la
 * concentration et les contributions restaient figées sur leur cas dégénéré (top1 = top3 = top5 =
 * 100 %, HHI = 1, une seule ligne de contribution). Un second titre, de valeur différente,
 * fait maintenant traverser le hash par le tri par contribution décroissante et le cumul du top 3.
 * Modifié une sixième fois : la page analyse ne garde que performances et secteurs, donc le bloc
 * `analyses` du blob perd `analysis`, `drawdown`, `income` et `annualIncome`.
 * Modifié une septième fois : le tableau de bord gagne une section transactions, donc `dashboard`
 * une quatrième clé — l'historique des opérations, tous actifs confondus.
 * Modifié une huitième fois : le gain réalisé se sépare du gain latent, donc chaque aperçu gagne
 * `totalRealizedGain` et chaque position `realizedGain`.
 * Modifié une neuvième fois : le tableau de bord gagne une section secteurs, donc `dashboard` une
 * cinquième clé — le patrimoine ventilé par secteur, l'immobilier compris.
 * Modifié une dixième fois : la part sans secteur d'une exposition prend le nom de celle-ci, si
 * bien que la tranche « Autre » du jeu de données s'appelle désormais « Actions ».
 * Modifié une onzième fois : l'immobilier porte enfin un gain réalisé — le cash rendu par les mois
 * excédentaires — là où il valait zéro, donc `realizedGain` de sa classe et le `totalRealizedGain`
 * du tableau de bord cessent d'ignorer les loyers encaissés.
 * Modifié une douzième fois : le dividende encaissé rejoint lui aussi le gain réalisé — classe du
 * tableau de bord, résumé de la page liste et position de la fiche.
 * Modifié une treizième fois : performances et secteurs reviennent de la page analyse vers la page
 * d'exposition, en sections repliées. Le blob perd sa clé `analyses` et chaque entrée de `classes`
 * gagne `performances`, plus `sectorBreakdown` pour les expositions qui en ont.
 * Modifié une quatorzième fois : chaque page actif porte ses repères d'analyse — prix de revient,
 * moyenne longue, RSI, amplitude vraie, drawdown et poids dans le portefeuille.
 * Modifié une quinzième fois : la section Analyse se resserre sur sept repères — la moyenne
 * longue, son écart, le RSI et l'amplitude vraie quittent la fiche et le dernier cours l'y
 * rejoint, en tête, donc le blob suit dans les deux sens.
 * Modifié une seizième fois : chaque page liste porte l'analyse de son exposition — la chute
 * maximale de la poche, sa distance au plus-haut et la matrice de corrélations de ses huit plus
 * grosses lignes.
 * Modifié une dix-septième fois : chaque page d'exposition porte ses opérations — l'historique de
 * la poche, tous ses actifs confondus, chaque ligne nommant le sien.
 * Modifié une dix-huitième fois : chaque ligne de position nomme son enveloppe de détention, donc
 * l'aperçu de chaque exposition gagne `walletId`, `walletName`, `accountType` et
 * `accountTypeLabel` par position. `walletName` expose au passage un nom de portefeuille jusque-là
 * tiré au sort par `WalletFactory::definition()` dans les fixtures qui alimentent ce test —
 * `portfolioFixture()`, `cryptoFixture()` et le second portefeuille de `seedSnapshotFixture()`
 * fixent désormais leur nom, comme `.ai/rules/factories.md` le demande déjà pour `ticker`.
 * Modifié une dix-neuvième fois : le tableau de bord gagne une section enveloppes, donc `dashboard`
 * une sixième clé — le portefeuille regroupé par compte de détention, avec le régime déclaré de
 * chacun.
 * Modifié une vingtième fois : chaque enveloppe nomme l'établissement qui tient le compte, donc
 * `broker` s'ajoute à la ligne d'enveloppe, à côté du nom du portefeuille.
 * Modifié une vingt-et-unième fois : le compte-titres se nomme « CTO », son sigle d'usage, pour
 * tenir dans le badge d'enveloppe de la liste d'instruments comme « PEA » y tient déjà.
 * Modifié une vingt-deuxième fois : chaque ligne d'opération porte son identifiant et son
 * enveloppe, pour que l'édition et la suppression puissent partir de la liste — sans `id` on ne
 * sait pas quelle ligne réécrire, sans `walletId` on la réécrirait sur le mauvais compte.
 *
 * Nouvelle fragilité que cette clé introduit : `id` est un auto-incrément, donc l'ORDRE
 * d'insertion des transactions des fixtures alimente désormais le hash. Il est stable ici (SQLite
 * en mémoire sous `RefreshDatabase`, séquence remise à zéro à chaque test), mais réordonner les
 * créations de `portfolioFixture()`, `cryptoFixture()` ou `seedSnapshotFixture()` déplacera le
 * hash pour une raison étrangère à la forme du JSON. `.ai/rules/tests.md` le consigne.
 * Modifié une vingt-troisième fois (chantier liquidités, tâches 1 à 13) : le solde d'espèces par
 * enveloppe entre dans le blob. Une classe `cash` (« Liquidités ») s'ajoute en dernier aux classes
 * de patrimoine du tableau de bord et de la page classes, avec sa propre série de tendance ; chaque
 * enveloppe du tableau de bord gagne `cashBalance` ; chaque aperçu et position d'exposition gagne
 * `cash` ; chaque ligne d'opération (dashboard, exposition, actif) gagne `type` (le nom de
 * `TransactionType`) et `auto` (vrai pour un versement déduit par `RecomputeCashDeposits`, jamais
 * pour une ligne saisie). Le journal du portefeuille de test gagne aussi une ligne : le versement de
 * 800 € déduit pour financer l'achat ACME, seule opération de la fixture à ne porter aucun actif
 * (`assetId` et `assetName` à `null`). Enfin `totalRealizedGain` (classe Actions et position ACME)
 * retombe de 13 à 0 : c'était le double comptage des dividendes théoriques d'`IncomePort` corrigé en
 * tâche 8, sans lien avec les liquidités elles-mêmes — voir `task-8-report.md`.
 */
const SNAPSHOT_VERSION = 'dbc2d74d78445c1ad52cd7d1e6916545498bb9d9';

/**
 * Retire récursivement les clés `isin` du corps de l'instantané : seul champ non déterministe
 * du jeu de données (voir le commentaire de `SNAPSHOT_VERSION`).
 *
 * @param  array<array-key, mixed>  $value
 * @return array<array-key, mixed>
 */
function normalizeSnapshotBody(array $value): array
{
    unset($value['isin']);

    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = normalizeSnapshotBody($item);
        }
    }

    return $value;
}

/**
 * Un seul utilisateur porte tout : les titres et la crypto de `cryptoFixture()`, un bien avec
 * prêt, et une seconde enveloppe sur le titre déjà détenu.
 */
function seedSnapshotFixture(): void
{
    ['user' => $user, 'crypto' => $crypto] = cryptoFixture();

    /** Le même titre dans une deuxième enveloppe, à un autre prix de revient. */
    $stock = Holding::query()->where('user_id', $user->id)->where('asset_id', '!=', $crypto->id)->first();
    /** Nom figé, comme les deux autres portefeuilles du jeu : un nom tiré au sort ferait dériver le hash. */
    $second = Wallet::factory()->for($user)->create(['name' => 'Compte-titres 2']);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $second->id,
        'asset_id' => $stock->asset_id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    /**
     * Un second titre, de valeur différente du premier : sans lui la concentration et les
     * contributions restent figées sur leur cas dégénéré (une seule position mesurable, donc
     * top1 = top3 = top5 = 100 % et HHI = 1). Le ticker est fixé explicitement — `InstrumentFactory`
     * le tire au sort et `normalizeSnapshotBody()` ne neutralise qu'`isin` (voir `.ai/rules/factories.md`).
     */
    $secondInstrument = Instrument::factory()->ofType(InstrumentType::Stock)->create([
        'name' => 'Globex',
        'ticker' => 'GBX',
    ]);

    Price::factory()->create(['asset_id' => $secondInstrument->id, 'date' => '2026-08-29', 'close' => 50]);

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $second->id,
        'asset_id' => $secondInstrument->id,
        'quantity' => 5,
        'avg_cost' => 40,
    ]);

    /**
     * Un cours intermédiaire plus haut que le dernier : sans lui, la série n'a jamais reculé et
     * `drawdown.maxDepth` reste à zéro, non couvert par le filet.
     */
    Price::factory()->create(['asset_id' => $stock->asset_id, 'date' => '2026-04-01', 'close' => 150]);

    /**
     * Deux détachements sur le titre déjà détenu : sans eux, le groupe `revenus` de la page
     * analyse reste figé sur du vide (`income` à zéro, `annualIncome` à `[]`), et `bySource` sort
     * en tableau plutôt qu'en objet — la différence de forme JSON que le filet ne peut voir que si
     * elle est réellement exercée.
     */
    Dividend::factory()->create(['asset_id' => $stock->asset_id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);
    Dividend::factory()->create(['asset_id' => $stock->asset_id, 'ex_date' => '2026-06-05', 'amount_per_share' => 0.8]);

    /**
     * `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du
     * portefeuille, seul à porter tout le jeu de données (voir la docblock de la fonction).
     */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);
}

it('porte les repères d\'analyse de chaque actif détenu', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');

    seedSnapshotFixture();

    $assets = $this->getJson('/instantane')->assertOk()->json('assets');

    expect($assets)->not->toBeEmpty();

    foreach ($assets as $page) {
        expect($page)->toHaveKey('analysis')
            ->and($page['analysis'])->toHaveKey('pru');
    }
});

it('rend un instantané hors-ligne identique au hash de référence', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');

    seedSnapshotFixture();

    $response = $this->getJson('/instantane')->assertOk();

    $body = $response->json();
    unset($body['version']);

    $hash = sha1(json_encode(normalizeSnapshotBody($body)));

    expect($hash)->toBe(SNAPSHOT_VERSION);
});
