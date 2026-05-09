<?php

use App\Domains\Analytics\Services\VolatilityCalculator;
use App\Domains\Asset\Models\Stock;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\Asset\Models\AssetPrice;

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
        $security = Stock::factory()->create();
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->forAsset($security);

        expect($result)->toBeNull();
    });

    it('returns volatility for security with sufficient prices', function () {
        $security = Stock::factory()->create();

        for ($i = 0; $i < 30; $i++) {

            AssetPrice::factory()
                ->for($security, 'asset')
                ->create(['close' => 100.0 + $i]);
        }

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forAsset($security);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });

    it('returns default 15.0 for wallet without securities', function () {
        $wallet = Wallet::factory()->create();
        $calculator = app(VolatilityCalculator::class);

        $result = $calculator->forWallet($wallet->id);

        expect($result)->toBe(15.0);
    });

    it('returns weighted volatility for wallet with securities', function () {
        $wallet = Wallet::factory()->create();

        $security = Stock::factory()->create();
        for ($i = 0; $i < 30; $i++) {

            AssetPrice::factory()
                ->for($security, 'asset')
                ->create(['close' => 100.0 + $i]);
        }

        \App\Domains\Portfolio\Models\Transaction::factory()
            ->for($wallet)
            ->for($security, 'asset')
            ->create(['quantity' => 10, 'unit_price' => 115.0]);

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forWallet($wallet->id);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });

    it('respects shownSecurityIds filter', function () {
        $wallet = Wallet::factory()->create();

        $security1 = Stock::factory()->create();
        $security2 = Stock::factory()->create();

        for ($i = 0; $i < 30; $i++) {

            AssetPrice::factory()->for($security1, 'asset')->create(['close' => 100.0 + $i]);

            AssetPrice::factory()->for($security2, 'asset')->create(['close' => 200.0 + $i]);
        }

        \App\Domains\Portfolio\Models\Transaction::factory()
            ->for($wallet)
            ->for($security1, 'asset')
            ->create(['quantity' => 10, 'unit_price' => 115.0]);

        \App\Domains\Portfolio\Models\Transaction::factory()
            ->for($wallet)
            ->for($security2, 'asset')
            ->create(['quantity' => 5, 'unit_price' => 215.0]);

        $calculator = app(VolatilityCalculator::class);
        $result = $calculator->forWallet($wallet->id, [$security1->id]);

        expect($result)->toBeFloat()
            ->toBeGreaterThan(0);
    });
});
