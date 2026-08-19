<?php

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Infrastructure\IncomeSourceRegistry;
use App\Contexts\Income\Sources\Dividend\DividendIncomeSource;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;
use App\Contexts\Portfolio\Models\Transaction;
use App\Contexts\Portfolio\Models\Wallet;

it('rend un reçu générique par dividende perçu, étiqueté du nom de l\'instrument', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    $receipts = app(DividendIncomeSource::class)->receiptsFor($user->id);

    expect(app(DividendIncomeSource::class)->source())->toBe(IncomeSource::Dividend)
        ->and($receipts)->toHaveCount(1)
        ->and($receipts[0]->amount)->toBe(5.0)
        ->and($receipts[0]->assetId)->toBe($instrument->id)
        ->and($receipts[0]->label)->toBe('Amundi MSCI World')
        ->and($receipts[0]->date->format('Y-m-d'))->toBe('2026-03-05');
});

it('ne lit aucun dividende sans mouvement de position', function () {
    $user = User::factory()->create();
    Dividend::factory()->create(['ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    expect(app(DividendIncomeSource::class)->receiptsFor($user->id))->toBe([]);
});

it('est enregistrée dans le registre des sources de revenu', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $instrument = Instrument::factory()->create();
    Transaction::factory()->buy()->create([
        'user_id' => $user->id, 'wallet_id' => $wallet->id, 'asset_id' => $instrument->id,
        'date' => '2026-01-10', 'quantity' => 10, 'unit_price' => 80,
    ]);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    expect(app(IncomeSourceRegistry::class)->receiptsFor($user->id))->toHaveCount(1);
});

it('projette le revenu annuel des positions détenues', function () {
    // 10 titres, 0,80 € détaché dans les douze derniers mois : 8 € attendus sur les douze
    // prochains. Le détachement de 2025 est hors fenêtre.
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    expect(app(DividendIncomeSource::class)->projectedAnnualFor($user->id))->toBe(8.0);
});

it('ne projette rien sans position détenue', function () {
    $user = User::factory()->create();
    Dividend::factory()->create(['ex_date' => '2026-03-05', 'amount_per_share' => 0.5]);

    expect(app(DividendIncomeSource::class)->projectedAnnualFor($user->id))->toBe(0.0);
});
