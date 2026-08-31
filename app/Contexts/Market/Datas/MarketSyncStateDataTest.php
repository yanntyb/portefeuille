<?php

use App\Contexts\Market\Datas\MarketSyncStateData;
use App\Contexts\Market\Enums\MarketSyncStatus;

it('serializes its status as the value the client reads', function () {
    $state = new MarketSyncStateData(
        status: MarketSyncStatus::Succeeded,
        startedAt: 1_756_000_000,
        finishedAt: 1_756_000_120,
        summary: '2 cours, 1 secteur, 1 dividende',
    );

    expect($state->jsonSerialize())->toBe([
        'status' => 'succeeded',
        'startedAt' => 1_756_000_000,
        'finishedAt' => 1_756_000_120,
        'summary' => '2 cours, 1 secteur, 1 dividende',
        'error' => null,
    ]);
});

it('survives a round trip through the cache', function () {
    $state = new MarketSyncStateData(
        status: MarketSyncStatus::Failed,
        startedAt: 1_756_000_000,
        finishedAt: 1_756_000_030,
        error: 'boom',
    );

    expect(MarketSyncStateData::fromArray($state->toArray()))->toEqual($state);
});

it('falls back to idle when the stored status is unknown', function () {
    expect(MarketSyncStateData::fromArray(['status' => 'whatever'])->status)
        ->toBe(MarketSyncStatus::Idle)
        ->and(MarketSyncStateData::fromArray([])->status)->toBe(MarketSyncStatus::Idle);
});
