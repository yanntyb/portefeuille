<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\PortfolioView\Pages\WalletPage;

it('rend null pour un porteur inconnu, une enveloppe inconnue ou celle d\'un autre', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();
    $etranger = Wallet::factory()->create(['name' => 'Ailleurs']);

    $page = app(WalletPage::class);

    expect($page->for(0, $wallet->id))->toBeNull()
        ->and($page->for($user->id, 999999))->toBeNull()
        ->and($page->for($user->id, $etranger->id))->toBeNull();
});

it('porte l\'en-tête en sync et une différée par section, chacune dans son groupe', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $page = app(WalletPage::class)->for($user->id, $wallet->id);

    expect(array_keys($page->sync))->toBe(['account'])
        ->and($page->sync['account']->walletId)->toBe($wallet->id)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'positions' => 'positions',
            'breakdown' => 'repartition',
            'evolution' => 'evolution',
            'performances' => 'performances',
            'basketAnalysis' => 'analyse',
            'sectorBreakdown' => 'secteurs',
            'transactions' => 'transactions',
        ]);
});

it('ne résout que ce que l\'enveloppe tient', function () {
    ['user' => $user, 'wallet' => $wallet, 'instrument' => $instrument] = portfolioFixture();
    $autre = Wallet::factory()->for($user)->create(['name' => 'Second compte']);
    $voisin = Instrument::factory()->create(['name' => 'Voisin', 'ticker' => 'VOI']);
    Price::factory()->create(['asset_id' => $voisin->id, 'date' => now(), 'close' => 50]);
    Holding::factory()->create([
        'user_id' => $user->id, 'wallet_id' => $autre->id, 'asset_id' => $voisin->id, 'quantity' => 10, 'avg_cost' => 50,
    ]);

    $props = app(WalletPage::class)->for($user->id, $wallet->id)->resolve();

    expect(array_column($props['positions'], 'assetId'))->toBe([$instrument->id])
        ->and(array_column($props['breakdown'], 'key'))->toBe(['equity'])
        /**
         * `array_unique` et non l'array brut : l'achat de portfolioFixture() n'est pas préfinancé,
         * `RecomputeCashDeposits` lui adjoint donc un versement déduit sur la même enveloppe (voir
         * GetTransactionJournalTest « porte les versements que le système a déduits, marqués auto »)
         * — deux lignes légitimes, toutes deux sur cette enveloppe, aucune sur la voisine.
         */
        ->and(array_unique(array_column($props['transactions'], 'walletId')))->toBe([$wallet->id])
        ->and($props['basketAnalysis']->instruments[0]->label)->toBe($instrument->ticker);
});
