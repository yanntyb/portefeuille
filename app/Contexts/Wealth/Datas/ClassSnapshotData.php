<?php

namespace App\Contexts\Wealth\Datas;

/** Ce qu'une classe d'actif vaut, et ce qu'elle a coûté en cash. */
readonly class ClassSnapshotData
{
    public function __construct(
        public float $value,
        public float $invested,
        /** Gain déjà encaissé sur la classe. Nul pour une classe qui ne se vend pas par lignes. */
        public float $realized = 0.0,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0);
    }
}
