<?php

use App\Contexts\Market\Models\Instrument;
use App\Contexts\Market\Models\Price;
use App\Contexts\PortfolioView\Pages\AssetPage;

it('rend null sur un instrument inconnu', function () {
    ['user' => $user] = portfolioFixture();

    expect(app(AssetPage::class)->for($user->id, 999999))->toBeNull();
});

it('porte la fiche, les performances et les dividendes en sync, trois différées par défaut', function () {
    ['user' => $user, 'instrument' => $instrument] = portfolioFixture();

    $page = app(AssetPage::class)->for($user->id, $instrument->id);

    expect(array_keys($page->sync))->toBe(['instrument', 'performances', 'dividends'])
        ->and($page->sync['instrument']->id)->toBe($instrument->id)
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'priceHistory' => 'default',
            'valuation' => 'default',
            'analysis' => 'default',
        ]);
});

it('retient les dividendes à la crypto', function () {
    ['user' => $user, 'crypto' => $crypto] = cryptoFixture();

    $page = app(AssetPage::class)->for($user->id, $crypto->id);

    expect($page->sync)->not->toHaveKey('dividends');
});

it('résout l\'historique de cours en tableaux parallèles, du plus ancien au plus récent', function () {
    ['user' => $user] = portfolioFixture();
    $asset = Instrument::factory()->create(['name' => 'Seul', 'ticker' => 'SEU']);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-02', 'close' => 11]);
    Price::factory()->create(['asset_id' => $asset->id, 'date' => '2026-01-01', 'close' => 10]);

    $props = app(AssetPage::class)->for($user->id, $asset->id)->resolve();

    expect($props['priceHistory']->labels)->toBe(['2026-01-01', '2026-01-02'])
        ->and($props['priceHistory']->close)->toBe([10.0, 11.0])
        ->and($props['analysis'])->toBeNull();
});
