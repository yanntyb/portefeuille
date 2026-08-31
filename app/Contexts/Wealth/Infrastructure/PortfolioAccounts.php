<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * L'action est injectée, jamais construite : elle consomme `GetPortfolioOverview`, liée en
 * `scoped` et mémoïsée par utilisateur, si bien que la lecture du tableau de bord et celle des
 * enveloppes partagent le même instantané du portefeuille.
 */
class PortfolioAccounts implements AccountsPort
{
    public function __construct(private GetAccountBreakdown $breakdown) {}

    /** @return list<WealthAccountData> */
    public function accountsFor(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return array_map(
            fn (AccountLineData $line): WealthAccountData => new WealthAccountData(
                walletId: $line->walletId,
                walletName: $line->walletName,
                broker: $line->broker,
                accountType: $line->accountType->value,
                accountTypeLabel: $line->accountType->getLabel(),
                marketValue: $line->marketValue,
                gain: $line->gain,
                gainPct: $line->gainPct,
                ageInYears: $line->ageInYears,
                maturityYears: $line->maturityYears,
                taxRegimeLabel: $line->taxRegimeLabel,
                ineligibleAssetNames: $line->ineligibleAssetNames,
            ),
            ($this->breakdown)($user),
        );
    }
}
