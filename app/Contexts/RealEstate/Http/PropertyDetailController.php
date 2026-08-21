<?php

namespace App\Contexts\RealEstate\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\BuildPropertyValueSeries;
use App\Contexts\RealEstate\Actions\GetLoanSchedule;
use App\Contexts\RealEstate\Actions\GetPropertyDetail;
use Inertia\Inertia;
use Inertia\Response;

class PropertyDetailController
{
    public function __construct(private GetPropertyDetail $getDetail) {}

    public function __invoke(int $id): Response
    {
        $user = auth()->user() ?? User::query()->first();
        $userId = $user?->id ?? 0;

        $detail = ($this->getDetail)($userId, $id);

        if ($detail === null) {
            abort(404);
        }

        return Inertia::render('Properties/Detail', [
            'property' => $detail,
            /** Long et rarement lu en premier : le tableau d'amortissement arrive après la page. */
            'amortization' => Inertia::defer(fn () => app(GetLoanSchedule::class)($userId, $id)),
            /** Un point par mois depuis l'acquisition : le graphe attend, la fiche s'affiche. */
            'valueSeries' => Inertia::defer(fn () => app(BuildPropertyValueSeries::class)($userId, $id)),
        ]);
    }
}
