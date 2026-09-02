<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\PortfolioView\Datas\BasketAnalysisData;
use App\Contexts\PortfolioView\Datas\ExposureSeriesData;
use App\Contexts\PortfolioView\Ports\AccountsPort;
use App\Contexts\PortfolioView\Ports\BasketAnalysisPort;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;
use App\Contexts\PortfolioView\Ports\SectorBreakdownPort;
use App\Contexts\PortfolioView\Ports\TransactionsPort;
use App\Contexts\PortfolioView\Ports\ValuationPort;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La page d'une enveloppe de détention. Même composition qu'`AssetClassController`, au périmètre
 * près : une enveloppe et une exposition sont deux découpes du même portefeuille, et rien ne
 * distingue leurs lectures — d'où les mêmes ports, les mêmes Datas et les mêmes sections.
 *
 * L'en-tête est synchrone pour qu'il ne saute pas à l'arrivée ; chaque autre section attend son
 * propre groupe différé, pour ne pas retarder le premier rendu.
 *
 * L'instantané hors-ligne ne porte pas cette page : ses sections différées afficheront leur état
 * « indisponible hors-ligne », c'est assumé.
 */
class WalletController
{
    public function __construct(
        private AccountsPort $accounts,
        private PortfolioOverviewPort $overview,
        private ValuationPort $valuation,
        private BasketAnalysisPort $basketAnalysis,
        private SectorBreakdownPort $sectors,
        private TransactionsPort $transactions,
    ) {}

    public function __invoke(int $id): Response
    {
        $userId = auth()->id() ?? 0;
        $scope = HoldingScope::ofWallet($id);

        $account = $this->accounts->accountFor($userId, $id);

        /**
         * 404 et non page vide : l'enveloppe inconnue et celle d'un autre porteur se confondent
         * ici, et le code ne doit pas révéler laquelle des deux a été demandée.
         */
        if ($account === null) {
            abort(404);
        }

        return Inertia::render('Wallet/Show', [
            'account' => $account,
            'positions' => Inertia::defer(
                fn (): array => $this->overview->overviewFor($userId, $scope)->holdings, 'positions',
            ),
            'breakdown' => Inertia::defer(
                fn (): array => $this->overview->classBreakdownFor($userId, $scope), 'repartition',
            ),
            'evolution' => Inertia::defer(
                fn (): ExposureSeriesData => $this->valuation->seriesFor($userId, $scope), 'evolution',
            ),
            'performances' => Inertia::defer(
                fn (): array => $this->valuation->performancesFor($userId, $scope), 'performances',
            ),
            'basketAnalysis' => Inertia::defer(
                fn (): BasketAnalysisData => $this->basketAnalysis->analysisFor($userId, $scope), 'analyse',
            ),
            /**
             * Les secteurs d'une enveloppe, et non ceux du portefeuille entier : contrairement à
             * une page d'exposition, dont le bloc sectoriel garde son périmètre large d'origine,
             * cette page-ci n'a jamais rien affiché de sectoriel — elle démarre donc juste.
             */
            'sectorBreakdown' => Inertia::defer(
                fn (): array => $this->sectors->breakdownFor($userId, $scope), 'secteurs',
            ),
            /**
             * Différée comme les groupes voisins : Inertia résout tous les groupes différés dès
             * le remplacement du composant, repliée ou non — seule la section attend le dépli
             * pour afficher ce qui est déjà arrivé. La déférer sert seulement à ne pas retarder
             * le premier rendu de la page derrière le journal complet du compte.
             */
            'transactions' => Inertia::defer(
                fn (): array => $this->transactions->transactionsForScope($userId, $scope), 'transactions',
            ),
        ]);
    }
}
