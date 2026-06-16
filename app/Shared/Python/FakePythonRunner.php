<?php

namespace App\Shared\Python;

class FakePythonRunner implements PythonRunner
{
    /** @var array<string, PythonResult> */
    private array $results = [];

    /** @var list<array{script: string, input: array<string, mixed>}> */
    public array $calls = [];

    public function withResult(string $script, PythonResult $result): self
    {
        $this->results[$script] = $result;

        return $this;
    }

    public function run(string $script, array $input = [], ?int $timeout = null): PythonResult
    {
        $this->calls[] = ['script' => $script, 'input' => $input];

        return $this->results[$script] ?? new PythonResult(status: 'ok', data: []);
    }
}
