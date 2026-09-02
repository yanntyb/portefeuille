<?php

namespace App\Contexts\PortfolioView\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\PortfolioView\Datas\AccountRowData;
use App\Contexts\PortfolioView\Ports\AccountsPort;

/**
 * L'action est injectée, jamais construite : elle consomme `GetPortfolioOverview`, liée en `scoped`
 * et mémoïsée par utilisateur, si bien que l'en-tête d'une enveloppe et ses positions partagent le
 * même instantané du portefeuille.
 *
 * Aucun calcul ici, seulement une traduction : `AccountType` devient ses deux libellés rendus, la
 * page ne connaissant pas l'enum.
 */
class PortfolioAccounts implements AccountsPort
{
    public function __construct(private GetAccountBreakdown $breakdown) {}

    public function accountFor(int $userId, int $walletId): ?AccountRowData
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return null;
        }

        foreach (($this->breakdown)($user) as $line) {
            if ($line->walletId === $walletId) {
                return $this->rowOf($line);
            }
        }

        /**
         * Rien plutôt qu'une enveloppe vide : `GetAccountBreakdown` ne rend que les comptes du
         * porteur, si bien que l'enveloppe d'autrui et celle qui n'existe pas se confondent ici —
         * et la page doit répondre 404 aux deux, sans dire laquelle des deux elle a rencontrée.
         */
        return null;
    }

    private function rowOf(AccountLineData $line): AccountRowData
    {
        return new AccountRowData(
            walletId: $line->walletId,
            walletName: $line->walletName,
            broker: $line->broker,
            accountType: $line->accountType->value,
            accountTypeLabel: $line->accountType->getLabel(),
            marketValue: $line->marketValue,
            cost: $line->cost,
            gain: $line->gain,
            gainPct: $line->gainPct,
            realizedGain: $line->realizedGain,
            ageInYears: $line->ageInYears,
            maturityYears: $line->maturityYears,
            taxRegimeLabel: $line->taxRegimeLabel,
            ineligibleAssetNames: $line->ineligibleAssetNames,
            cashBalance: $line->cashBalance,
        );
    }
}
