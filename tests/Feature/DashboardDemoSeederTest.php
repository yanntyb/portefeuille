<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use Database\Seeders\DashboardDemoSeeder;

it('builds the demo portfolio from transactions', function () {
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    $overview = app(GetPortfolioOverview::class)($user);

    expect(Transaction::query()->count())->toBeGreaterThan(0)
        ->and(Holding::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0)
        ->and($overview->totalValue)->toBeGreaterThan(0.0)
        ->and($overview->holdings)->not->toBeEmpty();

    // a sell exists with a realized gain recorded
    expect(Transaction::query()->where('type', TransactionType::Sell)->whereNotNull('realized_gain')->exists())->toBeTrue();

    // the instrument without a price surfaces as a holding without market value
    $withoutPrice = collect($overview->holdings)->first(fn ($line) => $line->marketValue === null);
    expect($withoutPrice)->not->toBeNull();
});

it('is idempotent', function () {
    User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);
    $holdingCount = Holding::query()->count();
    $txCount = Transaction::query()->count();

    $this->seed(DashboardDemoSeeder::class);

    expect(Holding::query()->count())->toBe($holdingCount)
        ->and(Transaction::query()->count())->toBe($txCount);
});

it('seeds a price history that yields a non-flat valuation curve', function () {
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    // multiple distinct price dates were seeded
    expect(Price::query()->distinct()->count('date'))->toBeGreaterThan(1);

    $series = app(BuildEvolutionSeries::class)($user->id);
    $values = collect($series->perAsset)->flatMap(fn ($line): array => $line->value)->all();

    // several points, and the curve actually moves
    expect(count($series->labels))->toBeGreaterThan(1)
        ->and(count(array_unique($values)))->toBeGreaterThan(1);
});
