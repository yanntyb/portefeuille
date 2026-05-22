<?php

use App\Domains\AssetView\DTOs\PriceHistoryDTO;
use App\Domains\AssetView\Ports\AssetPriceViewPort;
use App\Infrastructure\Filament\Widgets\AssetPriceChartWidget;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2024-12-31'));
});

test('widget has correct default period', function () {
    $widget = new AssetPriceChartWidget;
    expect($widget->period)->toBe('1Y');
});

test('setPeriod updates period property', function () {
    $widget = new AssetPriceChartWidget;
    $widget->setPeriod('1M');
    expect($widget->period)->toBe('1M');
});

test('setCustomPeriod sets period to custom', function () {
    $widget = new AssetPriceChartWidget;
    $widget->setCustomPeriod();
    expect($widget->period)->toBe('custom');
});

test('getData returns Chart.js structure with empty data when no history', function () {
    $widget = new AssetPriceChartWidget;
    $widget->assetId = 1;

    $this->mock(AssetPriceViewPort::class, function ($mock) {
        $mock->shouldReceive('getPriceHistory')->andReturn(collect());
    });

    $data = $widget->getData();

    expect($data)->toHaveKeys(['datasets', 'labels'])
        ->and($data['labels'])->toBe([])
        ->and($data['datasets'])->toBe([]);
});

test('getData returns Chart.js structure with price history', function () {
    $widget = new AssetPriceChartWidget;
    $widget->assetId = 1;

    $priceHistory = collect([
        new PriceHistoryDTO(
            date: '2024-12-30',
            close: 100.50,
            open: 100.00,
            high: 101.00,
            low: 99.00,
        ),
        new PriceHistoryDTO(
            date: '2024-12-31',
            close: 101.00,
            open: 100.50,
            high: 101.50,
            low: 100.25,
        ),
    ]);

    $this->mock(AssetPriceViewPort::class, function ($mock) use ($priceHistory) {
        $mock->shouldReceive('getPriceHistory')->andReturn($priceHistory);
    });

    $data = $widget->getData();

    expect($data)->toHaveKeys(['datasets', 'labels'])
        ->and($data['labels'])->toBe(['2024-12-30', '2024-12-31'])
        ->and($data['datasets'])->toHaveCount(4)
        ->and($data['datasets'][0]['label'])->toBe('Clôture')
        ->and($data['datasets'][0]['data'])->toBe([100.50, 101.00]);
});
