<?php

namespace App\Contexts\MarketView\Http;

use App\Contexts\Identity\Models\User;
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
 * La fiche d'une crypto. Même lecture que celle d'un titre, sans les détachements : une crypto
 * n'en verse pas. Un actif qui n'est pas une crypto n'y répond pas — sinon deux adresses
 * mèneraient au même actif, et le fil d'Ariane mentirait sur l'une des deux.
 */
class CryptoDetailController
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

        if ($detail === null || ! $detail->type->isCrypto()) {
            abort(404);
        }

        return Inertia::render('Crypto/Show', [
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
        ]);
    }
}
