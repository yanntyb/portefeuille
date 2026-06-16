<?php

namespace App\Shared\Python;

use Illuminate\Process\Factory as ProcessFactory;

class ProcessPythonRunner implements PythonRunner
{
    public function __construct(
        private readonly ProcessFactory $process,
        private readonly string $bin,
        private readonly string $scriptsPath,
        private readonly int $defaultTimeout,
    ) {}

    public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
    {
        $scriptPath = rtrim($this->scriptsPath, '/')."/{$script}";

        if (! file_exists($scriptPath)) {
            throw PythonProcessException::scriptNotFound($scriptPath);
        }

        $result = $this->process
            ->timeout($timeout ?? $this->defaultTimeout)
            ->env(['PYTHONUNBUFFERED' => '1'])
            ->input(json_encode($input))
            ->run("{$this->bin} {$scriptPath}");

        if (! $result->successful()) {
            throw PythonProcessException::processFailed($result->errorOutput());
        }

        $decoded = json_decode($result->output(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw PythonProcessException::invalidJson(json_last_error_msg());
        }

        return PythonResult::fromArray($decoded);
    }
}
