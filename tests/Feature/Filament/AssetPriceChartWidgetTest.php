<?php

use App\Infrastructure\Filament\Widgets\AssetPriceChartWidget;
use Carbon\Carbon;

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
