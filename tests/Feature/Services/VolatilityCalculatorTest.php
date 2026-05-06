<?php

use App\Models\Security;
use App\Models\SecurityPrice;
use App\Models\Wallet;
use App\Services\VolatilityCalculator;

describe('VolatilityCalculator', function () {
    it('returns null if less than 30 prices', function () {
        $prices = collect(array_fill(0, 29, 100.0));
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->annualizedVolatility($prices);

        expect($result)->toBeNull();
    });

    it('returns float volatility with valid prices', function () {
        $prices = collect(range(100, 129)); // 30 prices: 100, 101, ..., 129
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->annualizedVolatility($prices);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });

    it('returns null for security without prices', function () {
        $security = Security::factory()->create();
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->forSecurity($security);

        expect($result)->toBeNull();
    });

    it('returns volatility for security with sufficient prices', function () {
        $security = Security::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            SecurityPrice::factory()
                ->for($security)
                ->create(['close' => 100.0 + $i]);
        }

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forSecurity($security);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });

    it('returns default 15.0 for wallet without securities', function () {
        $wallet = Wallet::factory()->create();
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->forWallet($wallet);

        expect($result)->toBe(15.0);
    });

    it('returns weighted volatility for wallet with securities', function () {
        $wallet = Wallet::factory()->create();

        $security = Security::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            SecurityPrice::factory()
                ->for($security)
                ->create(['close' => 100.0 + $i]);
        }

        \App\Models\Transaction::factory()
            ->for($wallet)
            ->for($security)
            ->create(['quantity' => 10, 'unit_price' => 115.0]);

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forWallet($wallet);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });

    it('respects shownSecurityIds filter', function () {
        $wallet = Wallet::factory()->create();

        $security1 = Security::factory()->create();
        $security2 = Security::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            SecurityPrice::factory()->for($security1)->create(['close' => 100.0 + $i]);
            SecurityPrice::factory()->for($security2)->create(['close' => 200.0 + $i]);
        }

        \App\Models\Transaction::factory()
            ->for($wallet)
            ->for($security1)
            ->create(['quantity' => 10, 'unit_price' => 115.0]);

        \App\Models\Transaction::factory()
            ->for($wallet)
            ->for($security2)
            ->create(['quantity' => 5, 'unit_price' => 215.0]);

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forWallet($wallet, [$security1->id]);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });
});
