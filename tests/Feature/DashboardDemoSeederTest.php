<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Models\Holding;
use Database\Seeders\DashboardDemoSeeder;

it('seeds a demo portfolio for the first user', function () {
    // A legacy data migration seeds a hardcoded user; clear it so the first user is ours.
    User::query()->delete();
    $user = User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);

    $overview = app(GetPortfolioOverview::class)($user);

    expect(Holding::query()->where('user_id', $user->id)->count())->toBeGreaterThan(0)
        ->and($overview->totalValue)->toBeGreaterThan(0.0)
        ->and($overview->allocation)->not->toBeEmpty();

    $withoutPrice = collect($overview->holdings)->first(fn ($line) => $line->marketValue === null);
    expect($withoutPrice)->not->toBeNull();
});

it('is idempotent', function () {
    User::query()->delete();
    User::factory()->create();

    $this->seed(DashboardDemoSeeder::class);
    $countAfterFirstRun = Holding::query()->count();

    $this->seed(DashboardDemoSeeder::class);

    expect(Holding::query()->count())->toBe($countAfterFirstRun);
});
