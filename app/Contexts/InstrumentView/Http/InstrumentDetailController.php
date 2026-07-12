<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
use App\Contexts\Valuation\Actions\BuildAssetPerformances;
use App\Contexts\Valuation\Actions\BuildAssetValuationSeries;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Contexts\Valuation\Enums\ValuationRange;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentDetailController
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

        $range = ValuationRange::fromRequest(request()->query('range'));
        $granularity = ValuationGranularity::fromRequest(request()->query('granularity'));

        return Inertia::render('Instruments/Show', [
            'instrument' => $detail,
            'performances' => app(BuildAssetPerformances::class)($userId, $id),
            'valuationRange' => $range->value,
            'valuationGranularity' => $granularity->value,
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
            'valuation' => Inertia::defer(
                fn () => app(BuildAssetValuationSeries::class)($userId, $id, $range, $granularity)
            ),
        ]);
    }
}
