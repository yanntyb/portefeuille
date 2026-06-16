<?php

return [
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
