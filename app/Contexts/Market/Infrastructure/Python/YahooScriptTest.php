<?php

use App\Contexts\Market\Infrastructure\Python\YahooScript;
use Illuminate\Support\Facades\Process;

/**
 * Exécute un script de prix contre le stub yfinance de tests/Fixtures/python.
 *
 * @param  array<string, mixed>  $input
 */
function runPriceScript(YahooScript $script, array $input): string
{
    $result = Process::env([
        'PYTHONPATH' => base_path('tests/Fixtures/python'),
        'PYTHONUNBUFFERED' => '1',
    ])
        ->input(json_encode($input))
        ->run(config('python.bin').' '.$script->path());

    expect($result->exitCode())->toBe(0, $result->errorOutput());

    return $result->output();
}

it('émet du JSON décodable quand la dernière barre est incomplète', function () {
    $output = runPriceScript(YahooScript::Prices, [
        'ticker' => 'CW8.PA',
        'start_date' => '2026-08-14',
        'end_date' => '2026-08-18',
    ]);

    expect(json_decode($output, true))->not->toBeNull(json_last_error_msg());
});

it('écarte la barre incomplète du flux unitaire', function () {
    $output = runPriceScript(YahooScript::Prices, [
        'ticker' => 'CW8.PA',
        'start_date' => '2026-08-14',
        'end_date' => '2026-08-18',
    ]);

    $payload = json_decode($output, true);

    expect($payload['status'])->toBe('ok')
        ->and($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['date'])->toBe('2026-08-14')
        ->and($payload['data'][0]['close'])->toBe(100.5);
});

it('émet du JSON décodable en lot mono-ticker malgré une barre incomplète', function () {
    $output = runPriceScript(YahooScript::PricesBulk, [
        'tickers' => [
            ['ticker' => 'CW8.PA', 'start_date' => '2026-08-14', 'end_date' => '2026-08-18'],
        ],
    ]);

    $payload = json_decode($output, true);

    expect($payload)->not->toBeNull(json_last_error_msg())
        ->and($payload['data']['CW8.PA'])->toHaveCount(1)
        ->and($payload['data']['CW8.PA'][0]['date'])->toBe('2026-08-14');
});

it('émet du JSON décodable en lot multi-tickers malgré une barre incomplète', function () {
    $output = runPriceScript(YahooScript::PricesBulk, [
        'tickers' => [
            ['ticker' => 'CW8.PA', 'start_date' => '2026-08-14', 'end_date' => '2026-08-18'],
            ['ticker' => 'MEUD.PA', 'start_date' => '2026-08-14', 'end_date' => '2026-08-18'],
        ],
    ]);

    $payload = json_decode($output, true);

    expect($payload)->not->toBeNull(json_last_error_msg())
        ->and($payload['data'])->toHaveKeys(['CW8.PA', 'MEUD.PA'])
        ->and($payload['data']['CW8.PA'])->toHaveCount(1)
        ->and($payload['data']['MEUD.PA'])->toHaveCount(1);
});
