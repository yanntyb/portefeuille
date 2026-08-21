<?php

namespace App\Contexts\RealEstate\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Datas\RealEstateOverviewData;
use Inertia\Inertia;
use Inertia\Response;

class PropertiesController
{
    public function __construct(private GetRealEstateOverview $overview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        /**
         * Synchrone et non différé : c'est le grand chiffre de la page, et le différer le ferait
         * sauter à l'arrivée.
         */
        return Inertia::render('Properties/Index', [
            'realEstate' => $user !== null
                ? ($this->overview)($user->id)
                : RealEstateOverviewData::empty(),
        ]);
    }
}
