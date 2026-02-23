<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PythonScriptCaller
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function call(string $script, array $input = [], int $timeout = 30): array
    {
        $scriptPath = storage_path("python/scripts/{$script}");

        if (! file_exists($scriptPath)) {
            throw new RuntimeException("Python script not found: {$scriptPath}");
        }

        $pythonBin = base_path('.venv/bin/python');

        $result = Process::timeout($timeout)
            ->env(['PYTHONUNBUFFERED' => '1'])
            ->input(json_encode($input))
            ->run("{$pythonBin} {$scriptPath}");

        if (! $result->successful()) {
            throw new RuntimeException("Python script failed: {$result->errorOutput()}");
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON returned from Python script: '.json_last_error_msg());
        }

        return $decoded;
    }
}
