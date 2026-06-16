<?php

use App\Shared\Python\ProcessPythonRunner;
use App\Shared\Python\PythonProcessException;
use App\Shared\Python\PythonResult;
use Illuminate\Support\Facades\Process;

it('decodes a successful envelope into a PythonResult', function () {
    $factory = Process::fake([
        '*' => Process::result(json_encode(['status' => 'ok', 'data' => [['close' => 1.5]]])),
    ]);
    $runner = new ProcessPythonRunner($factory, '/usr/bin/python', 30);

    $result = $runner->run(__FILE__, ['ticker' => 'AAPL']);

    expect($result)->toBeInstanceOf(PythonResult::class)
        ->and($result->ok())->toBeTrue()
        ->and($result->data)->toBe([['close' => 1.5]]);
});

it('returns an error envelope without throwing on a successful process', function () {
    $factory = Process::fake([
        '*' => Process::result(json_encode(['status' => 'error', 'error' => 'bad ticker'])),
    ]);
    $runner = new ProcessPythonRunner($factory, '/usr/bin/python', 30);

    $result = $runner->run(__FILE__);

    expect($result->ok())->toBeFalse()
        ->and($result->error)->toBe('bad ticker');
});

it('throws when the script file is missing', function () {
    $runner = new ProcessPythonRunner(Process::fake(), '/usr/bin/python', 30);

    $runner->run('/nonexistent/does_not_exist.py');
})->throws(PythonProcessException::class, 'Python script not found');

it('throws when the process exits non-zero', function () {
    $factory = Process::fake([
        '*' => Process::result(output: '', errorOutput: 'Traceback ...', exitCode: 1),
    ]);
    $runner = new ProcessPythonRunner($factory, '/usr/bin/python', 30);

    $runner->run(__FILE__);
})->throws(PythonProcessException::class, 'Python script failed');

it('throws on invalid JSON output', function () {
    $factory = Process::fake([
        '*' => Process::result('not json at all'),
    ]);
    $runner = new ProcessPythonRunner($factory, '/usr/bin/python', 30);

    $runner->run(__FILE__);
})->throws(PythonProcessException::class, 'Invalid JSON');

it('runs the configured binary against the script path', function () {
    $factory = Process::fake([
        '*' => Process::result(json_encode(['status' => 'ok', 'data' => []])),
    ]);
    $runner = new ProcessPythonRunner($factory, '/custom/python', 30);

    $runner->run(__FILE__, ['ticker' => 'MSFT']);

    Process::assertRan(fn ($process) => str_contains($process->command, '/custom/python')
        && str_contains($process->command, __FILE__));
});
