<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Contracts\PriceRepositoryContract;
use App\Contexts\Market\Datas\HoldingScope;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Datas\PortfolioOverviewData;
use App\Contexts\Portfolio\Models\Holding;
use App\Contexts\Portfolio\Services\CashLedger;
use App\Contexts\Portfolio\Services\HoldingValuator;

class GetPortfolioOverview
{
    /**
     * Les lignes de chaque utilisateur, pour la durée de la requête. Cinq classes d'actif
     * lisaient autrefois cinq fois les mêmes positions et les mêmes prix ; le portefeuille tient
     * en quelques dizaines de lignes, le découpage se fait donc en mémoire.
     *
     * @var array<int, list<HoldingLineData>>
     */
    private array $linesByUser = [];

    public function __construct(
        private PriceRepositoryContract $prices,
        private HoldingValuator $valuator,
        private GetRealizedGains $realizedGains,
        private GetCashMovements $cashMovements,
        private CashLedger $cashLedger,
    ) {}

    /**
     * Sans périmètre, tout le portefeuille. Avec, une ou plusieurs expositions — chacune a sa page
     * et sa ligne au patrimoine, et le partage se lit dans `AssetClass`, nulle part ailleurs — ou
     * une enveloppe, pour la page qui la montre.
     */
    public function __invoke(User $user, ?HoldingScope $scope = null): PortfolioOverviewData
    {
        $scope ??= HoldingScope::all();

        $lines = $this->linesByUser[$user->id] ??= $this->readLines($user);

        $lines = array_values(array_filter(
            $lines,
            fn (HoldingLineData $line): bool => $scope->admits($line->assetClass)
                && $scope->admitsWallet($line->walletId),
        ));

        return $this->summarize($lines, $this->realizedGains->totalFor($user->id, $scope), $user->id, $scope);
    }

    /**
     * Toutes les positions de l'utilisateur, valorisées, sans filtrage par exposition : celui-ci
     * se fait en mémoire dans `__invoke()`, sur le résultat mémoïsé de cette méthode.
     *
     * @return list<HoldingLineData>
     */
    private function readLines(User $user): array
    {
        $holdings = Holding::query()
            ->with(['asset', 'wallet'])
            ->where('user_id', $user->id)
            ->get();

        $lastPrices = $this->prices->latestClosesForAssets(
            $holdings->pluck('asset_id')->map(fn ($assetId): int => (int) $assetId)->all(),
        );

        $lines = [];

        foreach ($holdings as $holding) {
            $quantity = (float) $holding->quantity;
            $avgCost = $holding->avg_cost !== null ? (float) $holding->avg_cost : null;
            $lastPrice = $lastPrices[(int) $holding->asset_id] ?? null;

            $valued = $this->valuator->value($quantity, $avgCost, $lastPrice);

            $lines[] = new HoldingLineData(
                assetId: (int) $holding->asset_id,
                assetName: $holding->asset->name,
                ticker: $holding->asset->ticker,
                type: $holding->asset->type,
                assetClass: $holding->asset->asset_class,
                walletId: (int) $holding->wallet_id,
                walletName: $holding->wallet->name,
                accountType: $holding->wallet->account_type,
                quantity: $quantity,
                avgCost: $avgCost,
                lastPrice: $lastPrice,
                marketValue: $valued['marketValue'],
                gain: $valued['gain'],
                gainPct: $valued['gainPct'],
            );
        }

        return $lines;
    }

    /**
     * Le seul totalisage : le total du portefeuille entier et celui d'une exposition passent
     * tous deux par ici, sur les lignes déjà retenues par `__invoke()`.
     *
     * Le gain réalisé arrive de côté : il se lit sur les ventes, pas sur les lignes, un actif
     * soldé n'ayant plus de position à totaliser. Les apports nets et le cash arrivent de même,
     * lus sur les mouvements d'espèces plutôt que sur les positions.
     *
     * @param  list<HoldingLineData>  $lines
     */
    private function summarize(array $lines, float $realizedGain, int $userId, HoldingScope $scope): PortfolioOverviewData
    {
        $totals = $this->valuator->totals($lines);
        $movements = ($this->cashMovements)($userId);
        $contributions = $this->cashLedger->netContributions($movements);

        return new PortfolioOverviewData(
            totalValue: $totals['totalValue'],
            totalCost: $totals['totalCost'],
            totalGain: $totals['totalGain'],
            totalGainPct: $totals['totalGainPct'],
            totalRealizedGain: $realizedGain,
            netContributions: $this->netContributionsFor($contributions, $scope->classes),
            cash: $this->cashBalance($movements, $scope),
            holdings: $lines,
        );
    }

    /**
     * Sans `$classes`, l'apport total, toutes expositions confondues. Avec, la part imputée aux
     * achats de ces expositions par `CashLedger::netContributions()` — un rachat financé par une
     * vente n'y figure pas, il n'a consommé aucun apport.
     *
     * L'apport ne se découpe pas par enveloppe : un périmètre qui n'en nomme qu'une lit donc
     * l'apport entier. Aucun appelant ne lit ce champ dans ce cas — `InvestedCapital` raisonne par
     * exposition — et l'imputation par compte serait un autre travail que le FIFO par exposition.
     *
     * @param  array{total: float, byExposure: array<string, float>}  $contributions
     * @param  ?list<AssetClass>  $classes
     */
    private function netContributionsFor(array $contributions, ?array $classes): float
    {
        if ($classes === null) {
            return $contributions['total'];
        }

        $sum = 0.0;

        foreach ($classes as $class) {
            $sum += $contributions['byExposure'][$class->value] ?? 0.0;
        }

        return round($sum, 2);
    }

    /**
     * Sans `$classes`, le solde d'espèces de l'utilisateur, toutes enveloppes confondues : la
     * somme des soldes que rend `CashLedger::balanceAt()` pour chaque enveloppe qui a vu au moins
     * un mouvement.
     *
     * Avec, le cash **d'origine** de ces expositions — ce que leurs ventes et leurs dividendes ont
     * rapporté sans être encore replacé, que `CashLedger::compositionAt()` sait dire par son FIFO
     * d'imputation. Le solde entier s'affichait autrefois sur les quatre pages d'exposition à la
     * fois : le même euro se lisait quatre fois, à côté d'un « Investi » et d'un « Gain » qui,
     * eux, étaient scopés.
     *
     * D'une enveloppe, en revanche, c'est le solde réel du compte : les espèces y sont tenues, et
     * la page de l'enveloppe les annonce comme telles.
     *
     * @param  list<CashMovementData>  $movements
     */
    private function cashBalance(array $movements, HoldingScope $scope): float
    {
        $today = now()->format('Y-m-d');

        if ($scope->classes !== null) {
            $composition = $this->cashLedger->compositionAt($movements, $today);

            $scoped = 0.0;

            foreach ($scope->classes as $class) {
                $scoped += $composition['exposures'][$class->value] ?? 0.0;
            }

            return round($scoped, 2);
        }

        if ($scope->walletId !== null) {
            return $this->cashLedger->balanceAt($movements, $scope->walletId, $today);
        }

        $walletIds = array_unique(array_map(fn (CashMovementData $movement): int => $movement->walletId, $movements));

        $total = 0.0;

        foreach ($walletIds as $walletId) {
            $total += $this->cashLedger->balanceAt($movements, $walletId, $today);
        }

        return round($total, 2);
    }
}
