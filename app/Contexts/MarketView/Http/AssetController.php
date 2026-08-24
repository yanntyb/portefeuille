<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Sources\Dividend\Actions\GetAssetDividendHistory;
use App\Contexts\MarketView\Actions\GetInstrumentDetail;
use App\Contexts\MarketView\Ports\MarketDataPort;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
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
            'performances' => app(BuildAssetPerformances::class)($userId, $id),
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
            /** Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe. */
            'valuation' => Inertia::defer(
                fn () => app(BuildAssetValuationSeries::class)(
                    $userId,
                    $id,
                    ValuationRange::Max,
                    ValuationGranularity::Week,
                )
            ),
        ];

        /**
         * Non différée : la visibilité de la section dépend de la donnée elle-même, et un
         * squelette qui disparaît sur chaque actif capitalisant coûterait plus qu'il ne rapporte.
         */
        if (IncomeSource::forAssetClass($detail->assetClass) !== null) {
            $props['dividends'] = app(GetAssetDividendHistory::class)($userId, $id);
        }

        return Inertia::render('Asset/Show', $props);
    }
}
