<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Market\Enums\InstrumentType;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\PortfolioView\Actions\GetHoldingTrends;
use App\Contexts\PortfolioView\Datas\HoldingTrendData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** @param list<HoldingTrendData> $trends */
function trendFor(array $trends, int $assetId): HoldingTrendData
{
    foreach ($trends as $trend) {
        if ($trend->assetId === $assetId) {
            return $trend;
        }
    }

    throw new RuntimeException("No trend for asset {$assetId}.");
}

/** Un instrument détenu par l'utilisateur : seuls ceux-là portent une tendance. */
function heldInstrument(User $user): Instrument
{
    $instrument = Instrument::factory()->create();

    Holding::factory()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::factory()->for($user)->create()->id,
        'asset_id' => $instrument->id,
        'quantity' => 10,
        'avg_cost' => 80,
    ]);

    return $instrument;
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('computes the change percentage between the first and the last price held', function () {
    $asset = heldInstrument($this->user);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 120]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(20.0);
});

/** La tendance n'a plus de fenêtre : le cours le plus ancien compte autant que le dernier. */
it('counts the oldest price in, however far back it sits', function () {
    $asset = heldInstrument($this->user);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subMonths(6), 'close' => 10]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subDays(10), 'close' => 100]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 120]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(1100.0);
});

it('keeps the whole history, years back included', function () {
    $asset = heldInstrument($this->user);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now()->subYears(4), 'close' => 50]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect(trendFor($trends, $asset->id)->changePct)->toBe(100.0);
});

it('leaves the change percentage null when there are less than two prices', function () {
    $asset = heldInstrument($this->user);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect(trendFor($trends, $asset->id)->changePct)->toBeNull()
        ->and(trendFor($trends, $asset->id)->points)->toBe([100.0]);
});

it('returns a trend for every held instrument, even without any price', function () {
    $withPrices = heldInstrument($this->user);
    $withoutPrice = heldInstrument($this->user);
    Price::factory()->create(['asset_id' => $withPrices->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect($trends)->toHaveCount(2)
        ->and(trendFor($trends, $withoutPrice->id)->changePct)->toBeNull()
        ->and(trendFor($trends, $withoutPrice->id)->points)->toBe([]);
});

it('leaves out the instruments the user does not hold', function () {
    $held = heldInstrument($this->user);
    $catalogued = Instrument::factory()->create();
    Price::factory()->create(['asset_id' => $catalogued->id, 'date' => Carbon::now(), 'close' => 100]);

    $trends = app(GetHoldingTrends::class)($this->user->id);

    expect($trends)->toHaveCount(1)
        ->and($trends[0]->assetId)->toBe($held->id);
});

it('leaves out the positions of another user', function () {
    $other = User::factory()->create();
    heldInstrument($other);

    expect(app(GetHoldingTrends::class)($this->user->id))->toBe([]);
});

it('reads the prices of every position without one query per instrument', function () {
    foreach (range(1, 5) as $offset) {
        $asset = heldInstrument($this->user);
        Price::factory()->create([
            'asset_id' => $asset->id,
            'date' => Carbon::now()->subDays($offset)->format('Y-m-d'),
            'close' => 100,
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    app(GetHoldingTrends::class)($this->user->id);

    // Quatre requêtes fixes, pas une par instrument : les enveloppes, les derniers cours et les
    // ventes (la position que `Portfolio` calcule est valorisée et porte son gain réalisé, même
    // si cette action n'en garde que la quantité), puis la série de cours de la tendance.
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(4);
});

it('downsamples a long history while keeping the first and the last price', function () {
    $asset = heldInstrument($this->user);
    $day = Carbon::now()->subDays(200);
    $close = 100.0;

    while ($day->lte(Carbon::now())) {
        Price::factory()->create(['asset_id' => $asset->id, 'date' => $day->format('Y-m-d'), 'close' => $close]);
        $day = $day->copy()->addDay();
        $close += 1;
    }

    $trend = trendFor(app(GetHoldingTrends::class)($this->user->id), $asset->id);

    expect(count($trend->points))->toBeLessThanOrEqual(24)
        ->and(count($trend->points))->toBeGreaterThan(1)
        ->and($trend->points[0])->toBe(100.0)
        ->and($trend->points[count($trend->points) - 1])->toBe(300.0);
});

it('keeps only the trends of the exposures it is given', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    $gold = Instrument::factory()->create(['type' => InstrumentType::Commodity]);
    $stock = Instrument::factory()->create(['type' => InstrumentType::Stock]);

    foreach ([$gold, $stock] as $instrument) {
        Price::factory()->create(['asset_id' => $instrument->id, 'close' => 10.0]);
        Holding::factory()->create([
            'asset_id' => $instrument->id, 'wallet_id' => $wallet->id,
            'user_id' => $user->id, 'quantity' => 1, 'avg_cost' => 5.0,
        ]);
    }

    $trends = app(GetHoldingTrends::class)($user->id, [AssetClass::Commodity]);

    expect(array_map(fn ($trend): int => $trend->assetId, $trends))->toBe([$gold->id]);
});
