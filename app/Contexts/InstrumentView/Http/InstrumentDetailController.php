<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentDetail;
use App\Contexts\InstrumentView\Ports\MarketDataPort;
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

        return Inertia::render('Instruments/Show', [
            'instrument' => $detail,
            'priceHistory' => Inertia::defer(
                fn () => $this->market->priceHistory($id, Carbon::now()->subMonths(12))
            ),
        ]);
    }
}
