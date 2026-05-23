<?php

use App\Domains\Asset\Models\Assets\Stock;
use App\Infrastructure\Filament\Resources\Pages\ListAssets;
use App\Infrastructure\Filament\Resources\Pages\ViewAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AssetResource', function () {
    test('ListAssets page mounts successfully', function () {
        $page = new ListAssets;
        $page->mount();

        expect($page)->toBeInstanceOf(ListAssets::class);
    });

    test('ViewAsset uses custom view', function () {
        $page = new ViewAsset;

        expect($page->getView())->toBe('asset-filament::view-asset');
    });

    test('ViewAsset passes assetId to widgets', function () {
        $asset = Stock::factory()->create();

        $page = new ViewAsset;
        $page->record = $asset;

        $data = $page->getWidgetData();

        expect($data['assetId'])->toBe($asset->id);
    });
});
