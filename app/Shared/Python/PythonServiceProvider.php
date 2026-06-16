<?php

namespace App\Shared\Python;

use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\ServiceProvider;

class PythonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var class-string<PythonRunner> $runner */
        $runner = config('python.runner', ProcessPythonRunner::class);

        $this->app->singleton(PythonRunner::class, fn ($app) => $app->make($runner, [
            'process' => $app->make(ProcessFactory::class),
            'bin' => config('python.bin'),
            'scriptsPath' => config('python.scripts_path'),
            'defaultTimeout' => (int) config('python.timeout'),
        ]));
    }
}
