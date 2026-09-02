<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Enums\Sector;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Market\Models\SectorAllocation;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;

function holdingWorth(User $user, float $value, InstrumentType $type = InstrumentType::ETF): Instrument
{
    $wallet = Wallet::factory()->for($user)->create();
    $asset = Instrument::factory()->ofType($type)->create();
    Price::factory()->create(['asset_id' => $asset->id, 'date' => now(), 'close' => $value]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $asset->id,
        'quantity' => 1,
        'avg_cost' => $value,
    ]);

    return $asset;
}

/**
 * @param  array<string, float>  $weights
 */
function withSectorWeights(Instrument $asset, array $weights): void
{
    foreach ($weights as $sector => $weight) {
        SectorAllocation::factory()->create([
            'asset_id' => $asset->id,
            'sector' => Sector::from($sector),
            'weight' => $weight,
        ]);
    }
}

it('assigns to each slice the label and color from Sector', function () {
    $user = User::factory()->create();
    $etf = holdingWorth($user, 1000.0);
    withSectorWeights($etf, [
        Sector::Technology->value => 0.6,
        Sector::Healthcare->value => 0.4,
    ]);

    $slices = collect(app(GetSectorBreakdown::class)($user))->keyBy('label');

    expect($slices)->toHaveCount(2)
        ->and($slices['Technologie']->color)->toBe(Sector::Technology->getColor())
        ->and($slices['Santé']->color)->toBe(Sector::Healthcare->getColor());
});

it('puts the assets without any sector into the other bucket', function () {
    $user = User::factory()->create();
    holdingWorth($user, 250.0, InstrumentType::Crypto);

    $slices = app(GetSectorBreakdown::class)($user);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->label)->toBe(Sector::Other->getLabel())
        ->and($slices[0]->value)->toBe(250.0)
        ->and($slices[0]->pct)->toBe(100.0);
});

it('aggregates the same sector across several assets', function () {
    $user = User::factory()->create();
    $first = holdingWorth($user, 600.0);
    $second = holdingWorth($user, 400.0);
    withSectorWeights($first, [Sector::Technology->value => 1.0]);
    withSectorWeights($second, [
        Sector::Technology->value => 0.5,
        Sector::Energy->value => 0.5,
    ]);

    $slices = collect(app(GetSectorBreakdown::class)($user))->keyBy('label');

    expect($slices['Technologie']->value)->toBe(800.0)
        ->and($slices['Technologie']->pct)->toBe(80.0)
        ->and($slices['Énergie']->value)->toBe(200.0);
});

it('sorts the slices by decreasing value', function () {
    $user = User::factory()->create();
    $etf = holdingWorth($user, 1000.0);
    withSectorWeights($etf, [
        Sector::Energy->value => 0.2,
        Sector::Technology->value => 0.5,
        Sector::Healthcare->value => 0.3,
    ]);

    $labels = collect(app(GetSectorBreakdown::class)($user))->pluck('label')->all();

    expect($labels)->toBe(['Technologie', 'Santé', 'Énergie']);
});

it('ignores the holdings without a known price', function () {
    $user = User::factory()->create();
    $valued = holdingWorth($user, 1000.0);
    withSectorWeights($valued, [Sector::Technology->value => 1.0]);

    $wallet = Wallet::factory()->for($user)->create();
    $noPrice = Instrument::factory()->ofType(InstrumentType::ETF)->create();
    withSectorWeights($noPrice, [Sector::Energy->value => 1.0]);
    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'asset_id' => $noPrice->id,
        'quantity' => 5,
        'avg_cost' => 90,
    ]);

    $slices = app(GetSectorBreakdown::class)($user);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->label)->toBe('Technologie');
});

it('ignores the holdings of the other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = holdingWorth($user, 1000.0);
    withSectorWeights($mine, [Sector::Technology->value => 1.0]);
    $theirs = holdingWorth($other, 500.0);
    withSectorWeights($theirs, [Sector::Energy->value => 1.0]);

    $slices = app(GetSectorBreakdown::class)($user);

    expect($slices)->toHaveCount(1)
        ->and($slices[0]->value)->toBe(1000.0);
});

it('returns nothing when the user has no holding', function () {
    expect(app(GetSectorBreakdown::class)(User::factory()->create()))->toBe([]);
});

it('restricts the breakdown to the requested exposures', function () {
    $user = User::factory()->create();

    $etf = holdingWorth($user, 1000.0);
    withSectorWeights($etf, [Sector::Technology->value => 1.0]);

    $coin = holdingWorth($user, 500.0, InstrumentType::Crypto);
    withSectorWeights($coin, [Sector::FinancialServices->value => 1.0]);

    $equityOnly = app(GetSectorBreakdown::class)($user, HoldingScope::ofClasses([AssetClass::Equity]));

    // La part se calcule sur l'exposition retenue, pas sur le portefeuille entier : la ligne
    // actions pèse 100 % des actions, même si elle ne fait que deux tiers du portefeuille.
    expect($equityOnly)->toHaveCount(1)
        ->and($equityOnly[0]->label)->toBe(Sector::Technology->getLabel())
        ->and($equityOnly[0]->value)->toBe(1000.0)
        ->and($equityOnly[0]->pct)->toBe(100.0);

    expect(app(GetSectorBreakdown::class)($user, HoldingScope::all()))->toHaveCount(2);
});
