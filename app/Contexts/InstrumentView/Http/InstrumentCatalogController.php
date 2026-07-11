<?php

namespace App\Contexts\InstrumentView\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\InstrumentView\Actions\GetInstrumentCatalog;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentCatalogController
{
    public function __construct(private GetInstrumentCatalog $getCatalog) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        return Inertia::render('Instruments/Index', [
            'catalog' => ($this->getCatalog)($user?->id ?? 0),
        ]);
    }
}
