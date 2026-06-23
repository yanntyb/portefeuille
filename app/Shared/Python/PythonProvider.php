<?php

namespace App\Shared\Python;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\ServiceProvider;

class PythonProvider extends ServiceProvider
{
    /**
     * @param  class-string<PythonRunner>  $pythonRunner
     */
    public static function registers(Application $app, string $pythonRunner): void
    {
        $app->singleton(
            PythonRunner::class,
            fn (Application $app) => $app->make($pythonRunner, [
                'process' => $app->make(ProcessFactory::class),
                'bin' => config('python.bin'),
                'defaultTimeout' => config()->integer('python.timeout'),
            ]));
    }
}
