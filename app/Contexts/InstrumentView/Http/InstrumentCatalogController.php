<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetCatalogTrends;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use App\Contexts\Valuation\Enums\ValuationRange;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentCatalogController
{
    public function __construct(
        private GetInstrumentCatalog $getCatalog,
        private GetCatalogTrends $getTrends,
    ) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $range = ValuationRange::fromRequest(request()->query('range'));

        return Inertia::render('Instruments/Index', [
            'catalog' => ($this->getCatalog)($user?->id ?? 0),
            'catalogRange' => $range->value,
            'trends' => Inertia::defer(fn () => ($this->getTrends)($range)),
        ]);
    }
}
