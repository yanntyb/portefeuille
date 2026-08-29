<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\MarketView\Actions\GetInstrumentDetail;
use App\Contexts\MarketView\Ports\IncomePort;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\MarketView\Ports\ValuationPort;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La fiche d'un actif, quelle que soit son exposition. Une seule adresse par actif : deux
 * contrôleurs se renvoyaient autrefois 404 l'un l'autre pour éviter qu'un même actif réponde à
 * deux fils d'Ariane contradictoires. Le fil se déduit maintenant de l'exposition portée par
 * l'actif lui-même.
 */
class AssetController
{
    public function __construct(
        private GetInstrumentDetail $getDetail,
        private MarketDataPort $market,
        private ValuationPort $valuation,
        private IncomePort $income,
    ) {}

    public function __invoke(int $id): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;

        $detail = ($this->getDetail)($userId, $id);

        if ($detail === null) {
            abort(404);
        }

        $props = [
            'instrument' => $detail,
            'performances' => $this->valuation->assetPerformancesFor($userId, $id),
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
            'valuation' => Inertia::defer(fn () => $this->valuation->assetSeriesFor($userId, $id)),
        ];

        /**
         * Non différée : la visibilité de la section dépend de la donnée elle-même, et un
         * squelette qui disparaît sur chaque actif capitalisant coûterait plus qu'il ne rapporte.
         */
        if ($this->income->supportsExposure($detail->assetClass)) {
            $props['dividends'] = $this->income->assetHistoryFor($userId, $id);
        }

        return Inertia::render('Asset/Show', $props);
    }
}
