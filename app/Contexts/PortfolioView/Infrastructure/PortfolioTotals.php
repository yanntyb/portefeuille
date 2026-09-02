<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioAnalysis;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\Portfolio\Datas\ContributionData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\PortfolioView\Datas\AnalysisData;
use App\Contexts\PortfolioView\Datas\ClassSliceData;
use App\Contexts\PortfolioView\Datas\ConcentrationData;
use App\Contexts\PortfolioView\Datas\ContributionLineData;
use App\Contexts\PortfolioView\Datas\HoldingRowData;
use App\Contexts\PortfolioView\Datas\PortfolioSummaryData;
use App\Contexts\PortfolioView\Datas\PositionData;
use App\Contexts\PortfolioView\Ports\PortfolioOverviewPort;

/**
 * Les trois actions sont injectées, jamais résolues ni construites ici : elles sont liées en
 * `scoped` et mémoïsent leurs lignes par utilisateur, si bien qu'une seule lecture du portefeuille
 * sert les quatre expositions d'une requête. Les reconstruire les relirait une fois par exposition.
 */
class PortfolioTotals implements PortfolioOverviewPort
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private GetPortfolioPositions $positions,
        private GetPortfolioAnalysis $analysis,
    ) {}

    public function overviewFor(int $userId, HoldingScope $scope): PortfolioSummaryData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return PortfolioSummaryData::empty();
        }

        $overview = ($this->overview)($user, $scope);

        return new PortfolioSummaryData(
            totalValue: $overview->totalValue,
            totalCost: $overview->totalCost,
            totalGain: $overview->totalGain,
            totalGainPct: $overview->totalGainPct,
            totalRealizedGain: $overview->totalRealizedGain,
            cash: $overview->cash,
            holdings: array_map(
                fn (HoldingLineData $line): HoldingRowData => new HoldingRowData(
                    assetId: $line->assetId,
                    assetName: $line->assetName,
                    ticker: $line->ticker,
                    type: $line->type,
                    assetClass: $line->assetClass,
                    walletId: $line->walletId,
                    walletName: $line->walletName,
                    accountType: $line->accountType,
                    quantity: $line->quantity,
                    avgCost: $line->avgCost,
                    lastPrice: $line->lastPrice,
                    marketValue: $line->marketValue,
                    gain: $line->gain,
                    gainPct: $line->gainPct,
                ),
                $overview->holdings,
            ),
        );
    }

    /**
     * Le réalisé d'une position ne compte plus le détachement théorique d'`IncomePort` : depuis que
     * le compte espèces de l'enveloppe existe, un dividende réellement perçu y entre déjà comme
     * mouvement de cash. L'additionner ici, en plus, le compterait deux fois — une fois dans le
     * solde, une fois en supplément du gain — sans qu'aucun cours ne baisse en retour côté titres
     * pour compenser (le détachement fait bien décrocher le cours à l'ex-date, mais côté cash, pas
     * ici). Ce compteur ne porte donc plus que les plus-values de cession, comme le total de
     * l'exposition rendu par `overviewFor()`.
     */
    public function positionFor(int $userId, int $assetId): ?PositionData
    {
        $position = ($this->positions)($userId)[$assetId] ?? null;

        return $position === null ? null : new PositionData(
            quantity: $position->quantity,
            avgCost: $position->avgCost,
            marketValue: $position->marketValue,
            gain: $position->gain,
            gainPct: $position->gainPct,
            realizedGain: $position->realizedGain,
        );
    }

    /**
     * Le regroupement se fait en mémoire sur les lignes déjà lues : l'aperçu est mémoïsé par
     * utilisateur, une requête par classe paierait deux fois le même portefeuille.
     *
     * @return list<ClassSliceData>
     */
    public function classBreakdownFor(int $userId, HoldingScope $scope): array
    {
        $lines = $this->overviewFor($userId, $scope)->holdings;

        /** @var array<string, float> $byClass */
        $byClass = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $value = $line->marketValue ?? 0.0;
            $byClass[$line->assetClass->value] = ($byClass[$line->assetClass->value] ?? 0.0) + $value;
            $total += $value;
        }

        /**
         * Un périmètre sans valeur ne se ventile pas : diviser par zéro donnerait des parts
         * infinies, et une part de zéro pour cent sur chaque classe n'apprendrait rien.
         */
        if ($total <= 0.0) {
            return [];
        }

        $slices = array_map(
            fn (string $class, float $value): ClassSliceData => new ClassSliceData(
                key: $class,
                label: AssetClass::from($class)->getLabel(),
                value: $value,
                share: $value / $total * 100,
            ),
            array_keys($byClass),
            array_values($byClass),
        );

        usort(
            $slices,
            fn (ClassSliceData $left, ClassSliceData $right): int => $right->value <=> $left->value,
        );

        return $slices;
    }

    public function analysisFor(int $userId, HoldingScope $scope): AnalysisData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return AnalysisData::empty();
        }

        $analysis = ($this->analysis)($user, $scope);

        return new AnalysisData(
            concentration: new ConcentrationData(
                top1: $analysis->concentration->top1,
                top3: $analysis->concentration->top3,
                top5: $analysis->concentration->top5,
                hhi: $analysis->concentration->hhi,
            ),
            contributions: array_map(
                fn (ContributionData $line): ContributionLineData => new ContributionLineData(
                    assetId: $line->assetId,
                    assetName: $line->assetName,
                    contribution: $line->contribution,
                    weight: $line->weight,
                ),
                $analysis->contributions,
            ),
        );
    }
}
