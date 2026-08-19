<?php

namespace App\Contexts\Income\Sources\Dividend\Services;

use App\Contexts\Income\Sources\Dividend\Datas\DividendRecordData;
use Illuminate\Support\Carbon;

class DividendProjector
{
    /**
     * Revenu de dividende attendu sur les douze prochains mois, actif par actif.
     *
     * L'estimation extrapole le passé récent : ce qu'une action a détaché depuis `$since`,
     * multiplié par la quantité détenue aujourd'hui. Aucune fréquence n'est déduite, la fenêtre
     * glissante d'un an contient déjà autant de détachements que l'émetteur en verse.
     *
     * `DividendCalculator` reste séparé : lui croise chaque détachement avec la quantité détenue
     * à sa date, quand la projection ne regarde que la position du jour.
     *
     * @param  list<DividendRecordData>  $dividends
     * @param  array<int, float>  $quantities  quantité détenue aujourd'hui, indexée par actif
     * @return array<int, float> estimation annuelle indexée par actif, actifs sans revenu attendu exclus
     */
    public function annualEstimates(array $dividends, array $quantities, Carbon $since): array
    {
        $perShare = [];

        foreach ($dividends as $dividend) {
            if ($dividend->exDate->lt($since)) {
                continue;
            }

            $perShare[$dividend->assetId] = ($perShare[$dividend->assetId] ?? 0.0) + $dividend->amountPerShare;
        }

        $estimates = [];

        foreach ($perShare as $assetId => $amountPerShare) {
            $quantity = $quantities[$assetId] ?? 0.0;

            if ($quantity <= 0.0 || $amountPerShare <= 0.0) {
                continue;
            }

            $estimates[$assetId] = round($quantity * $amountPerShare, 2);
        }

        return $estimates;
    }
}
