<?php

use App\Shared\Python\ProcessPythonRunner;

return [

    /*
    |--------------------------------------------------------------------------
    | Runner
    |--------------------------------------------------------------------------
    |
    | Implémentation de App\Shared\Python\PythonRunner liée dans le container.
    | Point d'extension : pointer vers une autre implémentation (fake, runner
    | distant, ...) sans toucher au code consommateur.
    |
    */

    'runner' => env('PYTHON_RUNNER', ProcessPythonRunner::class),

    /*
    |--------------------------------------------------------------------------
    | Binaire & scripts
    |--------------------------------------------------------------------------
    |
    | Chemin de l'interpréteur Python et dossier des scripts. Les défauts
    | reproduisent le comportement historique (.venv du projet + storage).
    |
    */

    'bin' => env('PYTHON_BIN', base_path('.venv/bin/python')),

    'scripts_path' => env('PYTHON_SCRIPTS_PATH', storage_path('python/scripts')),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Durée maximale (secondes) d'exécution d'un script avant interruption.
    |
    */

    'timeout' => (int) env('PYTHON_TIMEOUT', 30),

];
