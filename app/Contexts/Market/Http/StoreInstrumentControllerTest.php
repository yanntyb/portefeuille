<?php

use App\Contexts\Identity\Http\AuthenticateDefaultUser;
use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Jobs\SyncInstrumentJob;
use App\Contexts\Market\Models\Instrument;
use Illuminate\Support\Facades\Bus;

/**
 * `authorize()` retourne `auth()->check()` : `AuthenticateDefaultUser` ne connecte personne tant
 * qu'aucun utilisateur n'existe en base, ce que `RefreshDatabase` laisse vide par défaut.
 */
beforeEach(function () {
    User::factory()->create();
});

function instrumentPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'NVIDIA Corp.',
        'ticker' => 'NVDA',
        'isin' => null,
        'type' => InstrumentType::Stock->value,
        'assetClass' => AssetClass::Equity->value,
    ], $overrides);
}

it('crée l\'instrument et met sa synchronisation en file', function () {
    Bus::fake();

    $response = $this->postJson('/instruments', instrumentPayload())->assertCreated();

    $instrument = Instrument::query()->firstOrFail();

    expect($instrument->name)->toBe('NVIDIA Corp.')
        ->and($instrument->ticker)->toBe('NVDA')
        ->and($instrument->isin)->toBeNull()
        ->and($instrument->type)->toBe(InstrumentType::Stock)
        ->and($instrument->asset_class)->toBe(AssetClass::Equity);

    $response->assertJsonPath('id', $instrument->id)
        ->assertJsonPath('typeLabel', 'Action')
        ->assertJsonPath('assetClassLabel', AssetClass::Equity->getLabel())
        ->assertJsonPath('assetClassSlug', AssetClass::Equity->slug());

    Bus::assertDispatched(
        SyncInstrumentJob::class,
        fn (SyncInstrumentJob $job): bool => $job->instrumentId === $instrument->id,
    );
});

it('garde l\'exposition envoyée plutôt que le défaut du type', function () {
    Bus::fake();

    /** Un ETF obligataire : le défaut du type dirait « Actions », l'utilisateur a dit autrement. */
    $this->postJson('/instruments', instrumentPayload([
        'name' => 'iShares Core Global Aggregate Bond',
        'ticker' => 'AGGH.L',
        'type' => InstrumentType::ETF->value,
        'assetClass' => AssetClass::Bond->value,
    ]))->assertCreated();

    expect(Instrument::query()->firstOrFail()->asset_class)->toBe(AssetClass::Bond);
});

it('refuse un corps incomplet ou un enum inconnu', function (array $payload, string $field) {
    Bus::fake();

    $this->postJson('/instruments', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    expect(Instrument::query()->count())->toBe(0);
    Bus::assertNotDispatched(SyncInstrumentJob::class);
})->with([
    'sans nom' => [instrumentPayload(['name' => '']), 'name'],
    'sans ticker' => [instrumentPayload(['ticker' => '']), 'ticker'],
    'type inconnu' => [instrumentPayload(['type' => 'warrant']), 'type'],
    'exposition inconnue' => [instrumentPayload(['assetClass' => 'nft']), 'assetClass'],
]);

it('accepte un ISIN et le stocke', function () {
    Bus::fake();

    $this->postJson('/instruments', instrumentPayload(['isin' => 'US67066G1040']))->assertCreated();

    expect(Instrument::query()->firstOrFail()->isin)->toBe('US67066G1040');
});

it('refuse d\'écrire sur une base sans utilisateur connecté', function () {
    Bus::fake();

    /**
     * Le `beforeEach` a bien créé un utilisateur, donc le middleware d'auto-connexion en
     * trouverait un ; c'est la déconnexion explicite qui met `authorize()` à l'épreuve.
     */
    auth()->logout();

    $this->withoutMiddleware(AuthenticateDefaultUser::class)
        ->postJson('/instruments', instrumentPayload())
        ->assertForbidden();

    expect(Instrument::query()->count())->toBe(0);
    Bus::assertNotDispatched(SyncInstrumentJob::class);
});
