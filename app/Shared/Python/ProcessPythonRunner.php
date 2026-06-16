<?php

namespace App\Shared\Python;

use Illuminate\Process\Factory as ProcessFactory;

class ProcessPythonRunner implements PythonRunner
{
    public function __construct(
        private readonly ProcessFactory $process,
        private readonly string $bin,
        private readonly int $defaultTimeout,
    ) {}

    public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
    {
        if (! file_exists($script)) {
            throw PythonProcessException::scriptNotFound($script);
        }

        $result = $this->process
            ->timeout($timeout ?? $this->defaultTimeout)
            ->env(['PYTHONUNBUFFERED' => '1'])
            ->input(json_encode($input))
            ->run("{$this->bin} {$script}");

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
