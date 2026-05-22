<?php

use App\Domains\Asset\Models\Assets\Stock;
use App\Infrastructure\Filament\Pages\AssetPricePage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('asset price page returns 404 for non-existent asset', function () {
    $page = new AssetPricePage;

    expect(fn () => $page->mount(999999))->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

test('asset price page mounts with valid asset', function () {
    $asset = Stock::factory()->create([
        'name' => 'Test Company',
    ]);

    $page = new AssetPricePage;
    $page->mount($asset->id);

    expect($page->assetId)->toBe($asset->id);
    expect($page->meta->name)->toBe('Test Company');
});

test('asset price page displays asset metadata', function () {
    $asset = Stock::factory()->create([
        'name' => 'Test Company',
    ]);

    $asset->infos()->create([
        'ticker' => 'TEST',
        'isin' => 'US0000000001',
    ]);

    $page = new AssetPricePage;
    $page->mount($asset->id);

    expect($page->meta->ticker)->toBe('TEST');
    expect($page->meta->isin)->toBe('US0000000001');
});
