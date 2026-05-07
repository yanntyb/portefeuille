<?php

use App\Domains\Asset\Contracts\AssetRepositoryInterface;
use App\Domains\Asset\Enums\AssetType;
use App\Domains\Asset\Models\Stock;
use App\Domains\Portfolio\Models\Transaction;
use App\Domains\Portfolio\Models\Wallet;
use App\Domains\User\Models\User;

it('findById returns Stock for stock-type asset', function (): void {
    $stock = Stock::factory()->create(['type' => AssetType::Stock]);

    $repository = app(AssetRepositoryInterface::class);
    $found = $repository->findById($stock->id);

    expect($found)->toBeInstanceOf(Stock::class)
        ->and($found->id)->toBe($stock->id);
});

it('forWallet returns only securities in wallet', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $wallet = Wallet::factory()->for($user)->create(['name' => 'Wallet 1']);
    $otherWallet = Wallet::factory()->for($user)->create(['name' => 'Wallet 2']);

    $stock1 = Stock::factory()->create(['type' => AssetType::Stock]);
    $stock2 = Stock::factory()->create(['type' => AssetType::Stock]);
    $stock3 = Stock::factory()->create(['type' => AssetType::Stock]);

    Transaction::factory()->for($wallet)->for($stock1, 'security')->create();
    Transaction::factory()->for($wallet)->for($stock2, 'security')->create();
    Transaction::factory()->for($otherWallet)->for($stock3, 'security')->create();

    $repository = app(AssetRepositoryInterface::class);
    $securities = $repository->forWallet($wallet->id);

    expect($securities)->toHaveCount(2)
        ->and($securities->pluck('id')->sort()->values()->all())->toBe([
            $stock1->id,
            $stock2->id,
        ]);
});

it('findByType returns only stocks', function (): void {
    Stock::factory()->create(['type' => AssetType::Stock]);
    Stock::factory()->create(['type' => AssetType::Stock]);

    $repository = app(AssetRepositoryInterface::class);
    $stocks = $repository->findByType(AssetType::Stock);

    expect($stocks)->toHaveCount(2);
    $stocks->each(function ($stock): void {
        expect($stock->type)->toBe(AssetType::Stock);
    });
});
