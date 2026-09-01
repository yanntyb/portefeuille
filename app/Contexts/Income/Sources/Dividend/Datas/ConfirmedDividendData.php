<?php

namespace App\Contexts\Income\Sources\Dividend\Datas;

/**
 * Un détachement encaissé : la transaction de dividende fait foi, plus le calcul. Sa clé
 * `(assetId, walletId, exDate)` sert à écarter le reçu dérivé qu'elle remplace.
 */
readonly class ConfirmedDividendData
{
    public function __construct(
        public int $assetId,
        public int $walletId,
        public string $exDate,
        public float $amount,
    ) {}
}
