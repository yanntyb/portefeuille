<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Binaire Python
    |--------------------------------------------------------------------------
    |
    | Chemin de l'interpréteur. Défaut = .venv du projet. Les scripts sont
    | résolus par chaque context (chemins co-localisés), pas ici.
    |
    */

    'bin' => env('PYTHON_BIN', base_path('.venv/bin/python')),

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
