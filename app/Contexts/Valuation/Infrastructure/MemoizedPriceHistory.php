<?php

namespace App\Contexts\Valuation\Infrastructure;

use App\Contexts\Valuation\Datas\PriceRecordData;
use App\Contexts\Valuation\Ports\PriceHistoryPort;
use Illuminate\Support\Carbon;

/**
 * Même rôle que MemoizedTransactionHistory, sur l'historique de prix : c'est la lecture la plus
 * lourde du tableau de bord, et les propriétés différées la redemandent à l'identique.
 *
 * Enregistré en `scoped()` : la mémoire ne doit pas franchir la frontière d'une requête.
 */
class MemoizedPriceHistory implements PriceHistoryPort
{
    /** @var array<string, list<PriceRecordData>> */
    private array $byQuery = [];

    public function __construct(private PriceHistoryPort $inner) {}

    /**
     * @param  list<int>  $assetIds
     * @return list<PriceRecordData>
     */
    public function forAssetsSince(array $assetIds, Carbon $since): array
    {
        return $this->byQuery[$this->key($assetIds, $since)] ??= $this->inner->forAssetsSince($assetIds, $since);
    }

    /**
     * L'ordre des identifiants ne change pas le jeu lu : la clé les trie pour ne pas dédoubler la mémoire.
     *
     * @param  list<int>  $assetIds
     */
    private function key(array $assetIds, Carbon $since): string
    {
        sort($assetIds);

        return implode(',', $assetIds).'@'.$since->toDateString();
    }
}
