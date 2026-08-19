<?php

use App\Contexts\Market\Contracts\DividendRepositoryContract;
use App\Contexts\Market\Datas\DividendData;
use App\Contexts\Market\Models\Dividend;
use App\Contexts\Market\Models\Instrument;

it('rend le dernier détachement connu d\'un actif', function () {
    $instrument = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-03-05']);
    Dividend::factory()->create(['asset_id' => $instrument->id, 'ex_date' => '2026-06-04']);

    $latest = app(DividendRepositoryContract::class)->latestForAsset($instrument->id);

    expect($latest->ex_date->format('Y-m-d'))->toBe('2026-06-04');
});

it('écrase le montant d\'un détachement déjà stocké', function () {
    $instrument = Instrument::factory()->create();
    $repository = app(DividendRepositoryContract::class);

    $repository->upsertForAsset($instrument->id, [new DividendData('2026-03-05', 0.51)]);
    $written = $repository->upsertForAsset($instrument->id, [new DividendData('2026-03-05', 0.62)]);

    expect($written)->toBe(1)
        ->and(Dividend::query()->where('asset_id', $instrument->id)->count())->toBe(1)
        ->and((float) Dividend::query()->where('asset_id', $instrument->id)->value('amount_per_share'))->toBe(0.62);
});

it('n\'écrit rien et rend zéro sans détachement', function () {
    $instrument = Instrument::factory()->create();

    expect(app(DividendRepositoryContract::class)->upsertForAsset($instrument->id, []))->toBe(0)
        ->and(Dividend::query()->count())->toBe(0);
});

it('rend les détachements de plusieurs actifs, triés par date croissante', function () {
    $first = Instrument::factory()->create();
    $second = Instrument::factory()->create();
    Dividend::factory()->create(['asset_id' => $first->id, 'ex_date' => '2026-06-04', 'amount_per_share' => 0.62]);
    Dividend::factory()->create(['asset_id' => $first->id, 'ex_date' => '2026-03-05', 'amount_per_share' => 0.51]);
    Dividend::factory()->create(['asset_id' => $second->id, 'ex_date' => '2026-04-02', 'amount_per_share' => 1.25]);

    $rows = app(DividendRepositoryContract::class)->forAssets([$first->id, $second->id]);

    expect($rows)->toHaveCount(3)
        ->and($rows[0])->toBe(['assetId' => $first->id, 'exDate' => '2026-03-05', 'amountPerShare' => 0.51])
        ->and($rows[1]['exDate'])->toBe('2026-06-04')
        ->and($rows[2]['assetId'])->toBe($second->id);
});

it('rend un tableau vide sans actif demandé', function () {
    expect(app(DividendRepositoryContract::class)->forAssets([]))->toBe([])
        ->and(app(DividendRepositoryContract::class)->namesFor([]))->toBe([]);
});

it('rend le nom des instruments demandés, indexé par identifiant', function () {
    $instrument = Instrument::factory()->create(['name' => 'Amundi MSCI World']);

    expect(app(DividendRepositoryContract::class)->namesFor([$instrument->id]))
        ->toBe([$instrument->id => 'Amundi MSCI World']);
});
