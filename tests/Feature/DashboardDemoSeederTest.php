<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Enums\TransactionType;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Transaction;
use Database\Seeders\DashboardDemoSeeder;

it('builds the demo portfolio from transactions', function () {
    User::query()->delete();
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    $overview = app(GetPortfolioOverview::class)($user);

    expect(Transaction::query()->count())->toBeGreaterThan(0)
        ->and(Holding::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0)
        ->and($overview->totalValue)->toBeGreaterThan(0.0)
        ->and($overview->allocation)->not->toBeEmpty();

    // a sell exists with a realized gain recorded
    expect(Transaction::query()->where('type', TransactionType::Sell)->whereNotNull('realized_gain')->exists())->toBeTrue();

    // the instrument without a price surfaces as a holding without market value
    $withoutPrice = collect($overview->holdings)->first(fn ($line) => $line->marketValue === null);
    expect($withoutPrice)->not->toBeNull();
});

it('is idempotent', function () {
    User::query()->delete();
    User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);
    $holdingCount = Holding::query()->count();
    $txCount = Transaction::query()->count();

    $this->seed(DashboardDemoSeeder::class);

    expect(Holding::query()->count())->toBe($holdingCount)
        ->and(Transaction::query()->count())->toBe($txCount);
});
