<?php

namespace App\Contexts\PortfolioView\Pages;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetSectorBreakdown;
use App\Contexts\Portfolio\Actions\GetTransactionJournal;
use App\Contexts\PortfolioView\Actions\GetBasketAnalysis;
use App\Contexts\PortfolioView\Services\ClassBreakdown;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Valuation\Actions\BuildPortfolioPerformances;
use App\Contexts\Valuation\Enums\ValuationGranularity;
use App\Shared\Inertia\DeferredProp;
use App\Shared\Inertia\PageProps;

/**
 * La page d'une enveloppe de détention : son en-tête fiscal en sync, tout le reste différé, un
 * groupe par section. Les positions sont les lignes du périmètre, la répartition leur somme par
 * classe ; le journal garde les versements et retraits de l'enveloppe (voir GetTransactionJournal).
 */
class WalletPage
{
    public function __construct(
        private GetAccountBreakdown $accounts,
        private GetPortfolioOverview $overview,
        private ClassBreakdown $classBreakdown,
        private BuildEvolutionSeries $evolution,
        private BuildPortfolioPerformances $performances,
        private GetBasketAnalysis $basketAnalysis,
        private GetSectorBreakdown $sectors,
        private GetTransactionJournal $journal,
    ) {}

    /** `null` quand le porteur ou l'enveloppe n'existe pas, ou qu'elle n'est pas à lui : le contrôleur répond 404. */
    public function for(int $userId, int $walletId): ?PageProps
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return null;
        }

        $scope = HoldingScope::ofWallet($walletId);
        $account = ($this->accounts)($user, $scope)[0] ?? null;

        if ($account === null) {
            return null;
        }

        return new PageProps(
            sync: ['account' => $account],
            deferred: [
                'positions' => new DeferredProp(
                    fn (): array => ($this->overview)($user, $scope)->holdings, 'positions',
                ),
                'breakdown' => new DeferredProp(
                    fn (): array => $this->classBreakdown->of(($this->overview)($user, $scope)->holdings), 'repartition',
                ),
                'evolution' => new DeferredProp(
                    fn () => ($this->evolution)($userId, null, ValuationGranularity::Week, $scope), 'evolution',
                ),
                'performances' => new DeferredProp(
                    fn (): array => ($this->performances)($userId, $scope), 'performances',
                ),
                'basketAnalysis' => new DeferredProp(
                    fn () => ($this->basketAnalysis)($user, $scope), 'analyse',
                ),
                'sectorBreakdown' => new DeferredProp(
                    fn (): array => ($this->sectors)($user, $scope), 'secteurs',
                ),
                'transactions' => new DeferredProp(
                    fn (): array => ($this->journal)($userId, $scope), 'transactions',
                ),
            ],
        );
    }
}
