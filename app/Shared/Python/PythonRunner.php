<?php

namespace App\Shared\Python;

interface PythonRunner
{
    /**
     * Exécute un script Python en lui passant $input en JSON sur stdin
     * et décode son stdout JSON.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws PythonProcessException script manquant, process en échec, ou JSON invalide
     */
    public function run(string $script, array $input = [], ?int $timeout = null): PythonResult;
}
