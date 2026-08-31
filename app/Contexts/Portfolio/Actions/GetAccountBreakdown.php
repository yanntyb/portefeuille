<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\HoldingValuator;

/**
 * Le portefeuille vu par enveloppe : une ligne par compte détenant au moins une position.
 *
 * Elle consomme `GetPortfolioOverview`, liée en `scoped` et mémoïsée par utilisateur, plutôt que
 * de relire les positions : le tableau de bord appelle déjà l'aperçu, une seconde lecture paierait
 * deux fois les mêmes lignes. Le regroupement se fait donc en mémoire, comme le découpage par
 * exposition de l'aperçu lui-même.
 *
 * Elle ne calcule aucune valorisation : les totaux passent par `HoldingValuator`, seul site du
 * gain du contexte.
 */
class GetAccountBreakdown
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private HoldingValuator $valuator,
    ) {}

    /** @return list<AccountLineData> */
    public function __invoke(User $user): array
    {
        $lines = ($this->overview)($user)->holdings;

        /** @var array<int, list<HoldingLineData>> $byWallet */
        $byWallet = [];

        foreach ($lines as $line) {
            $byWallet[$line->walletId][] = $line;
        }

        if ($byWallet === []) {
            return [];
        }

        $openedAt = Wallet::query()
            ->whereIn('id', array_keys($byWallet))
            ->pluck('opened_at', 'id');

        $accounts = [];

        foreach ($byWallet as $walletId => $walletLines) {
            $accountType = $walletLines[0]->accountType;
            $totals = $this->valuator->totals($walletLines);
            $opened = $openedAt[$walletId] ?? null;

            $ineligible = [];

            foreach ($walletLines as $line) {
                if (! $accountType->admits($line->assetClass)) {
                    $ineligible[] = $line->assetName;
                }
            }

            $accounts[] = new AccountLineData(
                walletId: $walletId,
                walletName: $walletLines[0]->walletName,
                accountType: $accountType,
                marketValue: $totals['totalValue'],
                gain: $totals['totalGain'],
                gainPct: $totals['totalGainPct'],
                // diffInYears() rend un float depuis Carbon 3 ; on tronque, on n'arrondit pas.
                ageInYears: $opened === null ? null : (int) $opened->diffInYears(now()),
                maturityYears: $accountType->maturityYears(),
                taxRegimeLabel: $accountType->taxRegimeLabel(),
                ineligibleAssetNames: $ineligible,
            );
        }

        usort(
            $accounts,
            fn (AccountLineData $left, AccountLineData $right): int => $right->marketValue <=> $left->marketValue,
        );

        return $accounts;
    }
}
