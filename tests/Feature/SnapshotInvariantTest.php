<?php

use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\RealEstate\Models\Lease;
use App\Contexts\RealEstate\Models\Loan;
use App\Contexts\RealEstate\Models\Property;
use App\Contexts\RealEstate\Models\PropertyValuation;
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
const SNAPSHOT_VERSION = 'a2e12f62919d83a3068231941c589532b8fe9664';

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

    $start = Carbon::now()->startOfMonth()->subMonthsNoOverflow(20)->toDateString();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'name' => 'T2 Lyon 7e',
        'address' => '12 rue Garibaldi, Lyon',
        'acquisition_date' => $start,
        'acquisition_price' => 100000,
        'acquisition_fees' => 8000,
    ]);

    Lease::factory()->create([
        'property_id' => $property->id,
        'monthly_rent' => 600,
        'start_date' => $start,
        'end_date' => null,
    ]);

    PropertyValuation::factory()->create([
        'property_id' => $property->id,
        'date' => Carbon::now()->toDateString(),
        'value' => 150000,
    ]);

    Loan::factory()->create([
        'property_id' => $property->id,
        'principal' => 80000,
        'annual_rate' => 0.0,
        'term_months' => 240,
        'start_date' => $start,
        'monthly_insurance' => 0,
    ]);
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
