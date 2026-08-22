<?php

namespace App\Contexts\RealEstate\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\RealEstate\Actions\BuildRealEstateSeries;
use App\Contexts\RealEstate\Actions\GetRealEstateIncome;
use App\Contexts\RealEstate\Actions\GetRealEstateOverview;
use App\Contexts\RealEstate\Actions\GetRealEstateProfitability;
use App\Contexts\RealEstate\Datas\RealEstateIncomeData;
use App\Contexts\RealEstate\Datas\RealEstateOverviewData;
use App\Contexts\RealEstate\Datas\RealEstateSeriesData;
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
            /**
             * Un groupe par section, comme sur la page Actions : chaque squelette se remplit à son
             * rythme au lieu d'attendre le plus lent de la page.
             *
             * Historique complet : la fenêtre visible est choisie côté client par le zoom du graphe.
             */
            'series' => Inertia::defer(fn () => $user !== null
                ? app(BuildRealEstateSeries::class)($user->id)
                : RealEstateSeriesData::empty(), 'evolution'),
            'profitability' => Inertia::defer(fn () => $user !== null
                ? app(GetRealEstateProfitability::class)($user->id)
                : [], 'rentabilité'),
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetRealEstateIncome::class)($user->id)
                : RealEstateIncomeData::empty(), 'revenus'),
        ]);
    }
}
