<?php

use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;

/**
 * Le filet du chantier de simplification : l'instantané hors-ligne réunit les quatre contextes —
 * `dashboard` (Wealth), `classes` et `assets` (MarketView), `properties` (RealEstate, fiches et
 * échéanciers compris). Un hash inchangé prouve que les six pages rendent le même JSON, ordre des
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
 */
const SNAPSHOT_VERSION = 'd3ad184036987dfeaaee6ea0f2be2adde49b1887';

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
    $second = Wallet::factory()->for($user)->create();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $second->id,
        'asset_id' => $stock->asset_id,
        'quantity' => 4,
        'avg_cost' => 95,
    ]);

    /**
     * Un cours intermédiaire plus haut que le dernier : sans lui, la série n'a jamais reculé et
     * `drawdown.maxDepth` reste à zéro, non couvert par le filet.
     */
    Price::factory()->create(['asset_id' => $stock->asset_id, 'date' => '2026-04-01', 'close' => 150]);

    /**
     * `propertyFixture()` crée son propre utilisateur : rattacher le bien à celui du
     * portefeuille, seul à porter tout le jeu de données (voir la docblock de la fonction).
     */
    propertyFixture(['loan' => true])['property']->update(['user_id' => $user->id]);
}

it('rend un instantané hors-ligne identique au hash de référence', function () {
    Carbon::setTestNow('2026-08-29 12:00:00');

    seedSnapshotFixture();

    $response = $this->getJson('/instantane')->assertOk();

    $body = $response->json();
    unset($body['version']);

    $hash = sha1(json_encode(normalizeSnapshotBody($body)));

    expect($hash)->toBe(SNAPSHOT_VERSION);
});
