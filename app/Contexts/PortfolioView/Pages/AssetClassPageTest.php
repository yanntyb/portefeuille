<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Pages\AssetClassPage;

it('porte l\'exposition et l\'aperçu en sync, une différée par section dans son groupe', function () {
    ['user' => $user] = portfolioFixture();

    $page = app(AssetClassPage::class)->for($user->id, AssetClass::Equity);

    expect(array_keys($page->sync))->toBe(['assetClass', 'overview'])
        ->and($page->sync['assetClass'])->toBe([
            'key' => 'equity', 'label' => AssetClass::Equity->getLabel(), 'slug' => AssetClass::Equity->slug(), 'hasSectors' => true,
        ])
        ->and($page->sync['overview']->totalValue)->toBe(1000.0)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'trends' => 'tendances',
            'evolutionSeries' => 'evolution',
            'performances' => 'performances',
            'basketAnalysis' => 'analyse',
            'transactions' => 'transactions',
            'sectorBreakdown' => 'secteurs',
        ]);
});

it('retient les secteurs aux expositions qui n\'en ont pas', function () {
    ['user' => $user] = cryptoFixture();

    $page = app(AssetClassPage::class)->for($user->id, AssetClass::Crypto);

    expect($page->deferred)->not->toHaveKey('sectorBreakdown')
        ->and($page->sync['assetClass']['hasSectors'])->toBeFalse();
});

it('rend une page vide, résolue sans erreur, à un porteur inconnu', function () {
    User::factory()->create();

    $props = app(AssetClassPage::class)->for(0, AssetClass::Equity)->resolve();

    expect($props['overview']->totalValue)->toBe(0.0)
        ->and($props['overview']->holdings)->toBe([])
        ->and($props['trends'])->toBe([])
        ->and($props['transactions'])->toBe([])
        ->and($props['sectorBreakdown'])->toBe([])
        ->and($props['basketAnalysis']->instruments)->toBe([]);
});
