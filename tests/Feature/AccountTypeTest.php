<?php

use App\Enums\AccountType;
use Filament\Support\Icons\Heroicon;

it('returns the correct label for each case', function (AccountType $case, string $expectedLabel) {
    expect($case->getLabel())->toBe($expectedLabel);
})->with([
    'PEA' => [AccountType::Pea, 'PEA'],
    'CTO' => [AccountType::Cto, 'CTO'],
    'Livret' => [AccountType::Livret, 'Livret'],
]);

it('returns the correct color for each case', function (AccountType $case, string $expectedColor) {
    expect($case->getColor())->toBe($expectedColor);
})->with([
    'PEA' => [AccountType::Pea, 'success'],
    'CTO' => [AccountType::Cto, 'warning'],
    'Livret' => [AccountType::Livret, 'info'],
]);

it('returns the correct icon for each case', function (AccountType $case, Heroicon $expectedIcon) {
    expect($case->getIcon())->toBe($expectedIcon);
})->with([
    'PEA' => [AccountType::Pea, Heroicon::OutlinedChartBar],
    'CTO' => [AccountType::Cto, Heroicon::OutlinedBuildingLibrary],
    'Livret' => [AccountType::Livret, Heroicon::OutlinedBanknotes],
]);

it('has the correct string value for each case', function (AccountType $case, string $expectedValue) {
    expect($case->value)->toBe($expectedValue);
})->with([
    'PEA' => [AccountType::Pea, 'pea'],
    'CTO' => [AccountType::Cto, 'cto'],
    'Livret' => [AccountType::Livret, 'livret'],
]);
