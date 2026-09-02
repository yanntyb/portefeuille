<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Ports\IncomePort;

beforeEach(function () {
    $this->income = app(IncomePort::class);
});

it('ne reconnaît que les expositions qui rapportent un revenu', function () {
    expect($this->income->supportsExposure(AssetClass::Equity))->toBeTrue()
        ->and($this->income->supportsExposure(AssetClass::Crypto))->toBeFalse();
});

it('résume le revenu perçu sur une exposition', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $summary = $this->income->summaryFor($user->id, AssetClass::Equity);

    expect($summary->totalReceived)->toBe(13.0)
        ->and($summary->last12Months)->toBe(8.0)
        ->and($summary->bySource)->toBe(['dividend' => 13.0]);
});

it('ventile le revenu par année civile, la plus ancienne en tête', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    $years = $this->income->annualFor($user->id, AssetClass::Equity);

    expect($years)->toHaveCount(2);
    expect($years[0]->year)->toBe(2025);
    expect($years[0]->total)->toBe(5.0);
    expect($years[1]->year)->toBe(2026);
    expect($years[1]->total)->toBe(8.0);
});

it('rend les détachements d\'un actif, avec ce qu\'ils pèsent', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user, 'instrument' => $instrument] = dividendFixture();

    $history = $this->income->assetHistoryFor($user->id, $instrument->id);

    expect($history->receipts)->toHaveCount(2)
        ->and($history->totalReceived)->toBe(13.0)
        ->and($history->last12Months)->toBe(8.0)
        ->and($history->receipts[0]->assetId)->toBe($instrument->id);
});

it('rend un historique de détachements vide pour un actif qui ne distribue pas', function () {
    ['user' => $user] = dividendFixture();

    $history = $this->income->assetHistoryFor($user->id, 999);

    expect($history->receipts)->toBe([])
        ->and($history->totalReceived)->toBe(0.0)
        ->and($history->yieldOnCost)->toBeNull();
});

it('rend un revenu vide sur une exposition qui n\'en rapporte aucun', function () {
    $this->travelTo('2026-08-19 10:00:00');
    ['user' => $user] = dividendFixture();

    expect($this->income->summaryFor($user->id, AssetClass::Crypto)->totalReceived)->toBe(0.0)
        ->and($this->income->annualFor($user->id, AssetClass::Crypto))->toBe([]);
});
