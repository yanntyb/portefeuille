<?php

namespace App\Contexts\Wealth\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\Wealth\Actions\BuildWealthSeries;
use App\Contexts\Wealth\Actions\GetWealthIncome;
use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Actions\GetWealthSectors;
use App\Contexts\Wealth\Datas\WealthOverviewData;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * Le tableau de bord : le résumé du patrimoine en sync, chaque section différée dans son groupe.
 * Les cartes d'enveloppes et le journal viennent de Portfolio directement : ce sont ses Datas que
 * le front lit. L'état de synchronisation (`sync`) n'est pas une donnée de page, le contrôleur
 * l'ajoute seul et le snapshot ne le porte pas.
 *
 * `GetWealthOverview` compose une ligne par classe du registre même sans porteur ; `accounts`
 * exige l'objet `User` ; les trois autres actions rendent leur forme vide sur un porteur inconnu.
 */
class DashboardPage
{
    public function __construct(
        private GetWealthOverview $overview,
        private BuildWealthSeries $series,
        private GetWealthIncome $income,
        private GetWealthSectors $sectors,
        private GetTransactionJournal $journal,
        private GetAccountBreakdown $accounts,
    ) {}

    public function for(int $userId): PageProps
    {
        $user = User::query()->find($userId);

        return new PageProps(
            sync: ['overview' => $user === null ? WealthOverviewData::empty() : ($this->overview)($userId)],
            deferred: [
                'series' => new DeferredProp(fn () => ($this->series)($userId), 'evolution'),
                'income' => new DeferredProp(fn () => ($this->income)($userId), 'revenus'),
                'sectors' => new DeferredProp(fn (): array => ($this->sectors)($userId), 'secteurs'),
                'transactions' => new DeferredProp(fn (): array => ($this->journal)($userId), 'transactions'),
                'accounts' => new DeferredProp(
                    fn (): array => $user === null ? [] : ($this->accounts)($user), 'enveloppes',
                ),
            ],
        );
    }
}
