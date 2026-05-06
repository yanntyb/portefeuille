<?php

use App\Enums\CurrencyModificationUnit;
use App\Enums\FrequencyUnit;
use App\Models\Wallet;
use App\Models\WalletFee;

test('un wallet peut avoir plusieurs frais', function () {
    $wallet = Wallet::factory()->create();

    $wallet->fees()->createMany([
        ['name' => 'Flat Tax', 'value' => 30.0, 'unit' => CurrencyModificationUnit::Percentage->value],
        ['name' => 'Frais de gestion', 'value' => 0.5, 'unit' => CurrencyModificationUnit::Percentage->value],
        ['name' => 'Frais tenue de compte', 'value' => 10.0, 'unit' => CurrencyModificationUnit::Currency->value, 'frequency' => FrequencyUnit::Yearly->value],
    ]);

    expect($wallet->fees()->count())->toBe(3);
});

test('la suppression d\'un wallet supprime ses frais en cascade', function () {
    $wallet = Wallet::factory()->create();
    $wallet->fees()->create([
        'name' => 'Flat Tax',
        'value' => 30.0,
        'unit' => CurrencyModificationUnit::Percentage->value,
    ]);

    $feeId = $wallet->fees()->first()->id;
    $wallet->delete();

    expect(WalletFee::find($feeId))->toBeNull();
});

test('les champs value, unit et frequency sont correctement castés', function () {
    $wallet = Wallet::factory()->create();

    $fee = $wallet->fees()->create([
        'name' => 'Frais tenue de compte',
        'value' => '10.0000',
        'unit' => CurrencyModificationUnit::Currency->value,
        'frequency' => FrequencyUnit::Yearly->value,
    ]);

    $fee->refresh();

    expect($fee->unit)->toBe(CurrencyModificationUnit::Currency)
        ->and($fee->frequency)->toBe(FrequencyUnit::Yearly)
        ->and((float) $fee->value)->toBe(10.0);
});

test('formattedValue retourne le bon format pour un pourcentage', function () {
    $wallet = Wallet::factory()->create();

    $fee = $wallet->fees()->create([
        'name' => 'Flat Tax',
        'value' => 30.0,
        'unit' => CurrencyModificationUnit::Percentage->value,
    ]);

    expect($fee->formattedValue())->toContain('30')
        ->and($fee->formattedValue())->toContain('%');
});

test('formattedValue retourne le bon format pour une devise annuelle', function () {
    $wallet = Wallet::factory()->create();

    $fee = $wallet->fees()->create([
        'name' => 'Frais tenue de compte',
        'value' => 10.0,
        'unit' => CurrencyModificationUnit::Currency->value,
        'frequency' => FrequencyUnit::Yearly->value,
    ]);

    $formatted = $fee->formattedValue();

    expect($formatted)->toContain('10')
        ->and($formatted)->toContain('€')
        ->and($formatted)->toContain('Annuel');
});
