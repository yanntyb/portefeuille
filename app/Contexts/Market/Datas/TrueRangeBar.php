<?php

namespace App\Contexts\Market\Datas;

/** Une séance réduite à ce que l'amplitude vraie demande : ses extrêmes et sa clôture. */
readonly class TrueRangeBar
{
    public function __construct(
        public float $high,
        public float $low,
        public float $close,
    ) {}
}
