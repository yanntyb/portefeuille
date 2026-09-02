<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Identity\Models\User;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetAccountBreakdown;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Wealth\Datas\WalletClassSliceData;
use App\Contexts\Wealth\Datas\WealthAccountData;
use App\Contexts\Wealth\Datas\WealthHoldingData;
use App\Contexts\Wealth\Ports\AccountsPort;

/**
 * Les deux actions sont injectées, jamais construites : elles consomment toutes deux
 * `GetPortfolioOverview`, liée en `scoped` et mémoïsée par utilisateur, si bien que la lecture du
 * tableau de bord et celle d'une enveloppe partagent le même instantané du portefeuille.
 */
class PortfolioAccounts implements AccountsPort
{
    public function __construct(
        private GetAccountBreakdown $breakdown,
        private GetPortfolioOverview $overview,
    ) {}

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
                cost: $line->cost,
                gain: $line->gain,
                gainPct: $line->gainPct,
                realizedGain: $line->realizedGain,
                ageInYears: $line->ageInYears,
                maturityYears: $line->maturityYears,
                taxRegimeLabel: $line->taxRegimeLabel,
                ineligibleAssetNames: $line->ineligibleAssetNames,
                cashBalance: $line->cashBalance,
            ),
            ($this->breakdown)($user),
        );
    }

    public function accountFor(int $userId, int $walletId): ?WealthAccountData
    {
        foreach ($this->accountsFor($userId) as $account) {
            if ($account->walletId === $walletId) {
                return $account;
            }
        }

        /**
         * Rien plutôt qu'une enveloppe vide : `GetAccountBreakdown` ne rend que les comptes du
         * porteur, si bien que l'enveloppe d'autrui et celle qui n'existe pas se confondent ici —
         * et la page doit répondre 404 aux deux, sans dire laquelle des deux elle a rencontré.
         */
        return null;
    }

    /** @return list<WealthHoldingData> */
    public function positionsFor(int $userId, int $walletId): array
    {
        return array_map(
            fn (HoldingLineData $line): WealthHoldingData => new WealthHoldingData(
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
            $this->linesOf($userId, $walletId),
        );
    }

    /** @return list<WalletClassSliceData> */
    public function breakdownFor(int $userId, int $walletId): array
    {
        $lines = $this->linesOf($userId, $walletId);

        /** @var array<string, float> $byClass */
        $byClass = [];
        $total = 0.0;

        foreach ($lines as $line) {
            $value = $line->marketValue ?? 0.0;
            $byClass[$line->assetClass->value] = ($byClass[$line->assetClass->value] ?? 0.0) + $value;
            $total += $value;
        }

        /**
         * Une enveloppe sans valeur ne se ventile pas : diviser par zéro donnerait des parts
         * infinies, et une part de zéro pour cent sur chaque classe n'apprendrait rien.
         */
        if ($total <= 0.0) {
            return [];
        }

        $slices = array_map(
            fn (string $class, float $value): WalletClassSliceData => new WalletClassSliceData(
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
            fn (WalletClassSliceData $left, WalletClassSliceData $right): int => $right->value <=> $left->value,
        );

        return $slices;
    }

    /**
     * Les lignes de l'enveloppe, tirées de l'aperçu du portefeuille plutôt que d'une requête à
     * elles : l'action est liée en `scoped` et mémoïse ses lignes par utilisateur, une seconde
     * lecture paierait deux fois le même portefeuille.
     *
     * @return list<HoldingLineData>
     */
    private function linesOf(int $userId, int $walletId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            ($this->overview)($user)->holdings,
            fn (HoldingLineData $line): bool => $line->walletId === $walletId,
        ));
    }
}
