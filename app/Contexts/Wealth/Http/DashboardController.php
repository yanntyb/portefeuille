<?php

namespace App\Contexts\Wealth\Http;

use App\Contexts\Identity\Models\User;
use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Actions\GetWealthSectors;
use App\Contexts\Wealth\Actions\GetWealthTransactions;
use App\Contexts\Wealth\Datas\WealthIncomeData;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Contexts\Wealth\Datas\WealthSeriesData;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    public function __construct(private GetWealthOverview $overview) {}

    public function __invoke(): Response
    {
        $user = auth()->user() ?? User::query()->first();

        return Inertia::render('Dashboard', [
            /**
             * Synchrone : c'est le grand chiffre, et le différer le ferait sauter à l'arrivée.
             * C'est aussi ce qui le place dans le document initial, donc dans le cache du service
             * worker, donc lisible hors-ligne.
             */
            'overview' => $user !== null
                ? ($this->overview)($user->id)
                : WealthOverviewData::empty(),
            'series' => Inertia::defer(fn () => $user !== null
                ? app(BuildWealthSeries::class)($user->id)
                : WealthSeriesData::empty(), 'evolution'),
            'income' => Inertia::defer(fn () => $user !== null
                ? app(GetWealthIncome::class)($user->id)
                : WealthIncomeData::empty(), 'revenus'),
            /** Repliée à l'arrivée, comme les revenus : sa part dominante suffit au premier coup d'œil. */
            'sectors' => Inertia::defer(fn (): array => $user !== null
                ? app(GetWealthSectors::class)($user->id)
                : [], 'secteurs'),
            /** Repliée à l'arrivée : l'historique entier ne se charge que pour qui le déplie. */
            'transactions' => Inertia::defer(fn (): array => $user !== null
                ? app(GetWealthTransactions::class)($user->id)
                : [], 'transactions'),
        ]);
    }
}
