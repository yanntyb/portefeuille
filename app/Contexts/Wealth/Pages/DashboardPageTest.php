<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\TransactionLineData;
use App\Contexts\Wealth\Pages\DashboardPage;

it('porte le résumé en sync et cinq différées, chacune dans son groupe', function () {
    ['user' => $user] = portfolioFixture();

    $page = app(DashboardPage::class)->for($user->id);

    expect(array_keys($page->sync))->toBe(['overview'])
        ->and(array_map(fn ($prop) => $prop->group, $page->deferred))->toBe([
            'series' => 'evolution',
            'income' => 'revenus',
            'sectors' => 'secteurs',
            'transactions' => 'transactions',
            'accounts' => 'enveloppes',
        ]);
});

it('résout les enveloppes et le journal depuis Portfolio', function () {
    ['user' => $user, 'wallet' => $wallet] = portfolioFixture();

    $props = app(DashboardPage::class)->for($user->id)->resolve();

    /**
     * L'achat de la fixture n'est couvert par aucun dépôt : `RecomputeCashDeposits` y ajoute un
     * versement déduit, plus récent (même date, identifiant supérieur), donc en tête du journal.
     */
    $buy = collect($props['transactions'])->firstWhere('type', 'buy');

    expect($props['accounts'])->toHaveCount(1)
        ->and($props['accounts'][0])->toBeInstanceOf(AccountLineData::class)
        ->and($props['accounts'][0]->walletId)->toBe($wallet->id)
        ->and($props['transactions'][0])->toBeInstanceOf(TransactionLineData::class)
        ->and($buy->assetName)->toBe('ACME');
});

it('se résout vide sans porteur connu', function () {
    User::factory()->create();

    $props = app(DashboardPage::class)->for(0)->resolve();

    expect($props['overview']->totalValue)->toBe(0.0)
        ->and($props['overview']->classes)->toBe([])
        ->and($props['accounts'])->toBe([])
        ->and($props['transactions'])->toBe([])
        ->and($props['series']->labels)->toBe([])
        ->and($props['series']->classes)->toBe([])
        ->and($props['series']->invested)->toBe([])
        ->and($props['income']->monthlyTotal)->toBe(0.0)
        ->and($props['income']->origins)->toBe([])
        ->and($props['sectors'])->toBe([]);
});
