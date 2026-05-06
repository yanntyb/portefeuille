<?php

use App\Support\PythonScriptCaller;
use Illuminate\Support\Facades\Process;

test('it calls python script and returns decoded json', function () {
    $expectedOutput = json_encode([
        'status' => 'ok',
        'python_version' => '3.12.0',
        'received_input' => ['hello' => 'world'],
    ]);

    Process::fake([
        '*' => Process::result(output: $expectedOutput),
    ]);

    $result = PythonScriptCaller::call('test.py', ['hello' => 'world']);

    expect($result)
        ->toBeArray()
        ->toHaveKey('status', 'ok')
        ->toHaveKey('python_version', '3.12.0')
        ->toHaveKey('received_input', ['hello' => 'world']);

    Process::assertRan(function ($process) {
        return str_contains($process->command, '.venv/bin/python')
            && str_contains($process->command, 'test.py');
    });
});

test('it throws exception when script does not exist', function () {
    PythonScriptCaller::call('nonexistent.py');
})->throws(RuntimeException::class, 'Python script not found');

test('it throws exception when process fails', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1, errorOutput: 'some error'),
    ]);

    PythonScriptCaller::call('test.py', ['hello' => 'world']);
})->throws(RuntimeException::class, 'Python script failed');

test('it throws exception when python returns invalid json', function () {
    Process::fake([
        '*' => Process::result(output: 'not json'),
    ]);

    PythonScriptCaller::call('test.py', ['hello' => 'world']);
})->throws(RuntimeException::class, 'Invalid JSON returned from Python script');

test('it uses custom timeout when provided', function () {
    Process::fake([
        '*' => Process::result(output: json_encode(['status' => 'ok'])),
    ]);

    PythonScriptCaller::call('test.py', ['hello' => 'world'], timeout: 60);

    Process::assertRan(function ($process) {
        return $process->timeout === 60;
    });
});

test('it uses default 30s timeout', function () {
    Process::fake([
        '*' => Process::result(output: json_encode(['status' => 'ok'])),
    ]);

    PythonScriptCaller::call('test.py');

    Process::assertRan(function ($process) {
        return $process->timeout === 30;
    });
});

test('pythonBin returns the venv python binary path', function () {
    expect(PythonScriptCaller::pythonBin())
        ->toBe(base_path('.venv/bin/python'));
});

test('scriptPath returns the full path to a python script', function () {
    expect(PythonScriptCaller::scriptPath('fetch_prices.py'))
        ->toBe(storage_path('python/scripts/fetch_prices.py'));
});
