<?php

namespace App\Contexts\Portfolio\Actions;

use App\Contexts\Identity\Models\User;
use App\Contexts\Portfolio\Datas\AccountLineData;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Datas\HoldingLineData;
use App\Contexts\Portfolio\Models\Wallet;
use App\Contexts\Portfolio\Services\CashLedger;
use App\Contexts\Portfolio\Services\HoldingValuator;

/**
 * Le portefeuille vu par enveloppe : une ligne par compte qui détient au moins une position OU un
 * solde d'espèces non nul. Une enveloppe entièrement vide — ni titres ni espèces — n'apprend rien
 * et n'apparaît pas ; une enveloppe créditée mais où rien n'a encore été acheté existe pour de bon
 * et doit se voir, sans quoi de l'argent resterait caché au porteur.
 *
 * Elle consomme `GetPortfolioOverview`, liée en `scoped` et mémoïsée par utilisateur, plutôt que
 * de relire les positions : le tableau de bord appelle déjà l'aperçu, une seconde lecture paierait
 * deux fois les mêmes lignes. Le regroupement se fait donc en mémoire, comme le découpage par
 * exposition de l'aperçu lui-même.
 *
 * Les mouvements d'espèces sont lus une seule fois via `GetCashMovements` — liée en `scoped` et
 * mémoïsée par utilisateur elle aussi — et chaque solde en est dérivé par `CashLedger::balanceAt()`
 * sur ce même jeu : une lecture par enveloppe rouvrirait un N+1 sur l'instantané.
 *
 * Elle ne calcule aucune valorisation : les totaux passent par `HoldingValuator`, seul site du
 * gain du contexte.
 */
class GetAccountBreakdown
{
    public function __construct(
        private GetPortfolioOverview $overview,
        private HoldingValuator $valuator,
        private GetCashMovements $cashMovements,
        private CashLedger $cashLedger,
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

        $movements = ($this->cashMovements)($user->id);
        $today = now()->format('Y-m-d');

        /** @var array<int, float> $cashBalances solde à aujourd'hui, par enveloppe ayant vu au moins un mouvement */
        $cashBalances = [];

        foreach (array_unique(array_map(fn (CashMovementData $movement): int => $movement->walletId, $movements)) as $walletId) {
            $cashBalances[$walletId] = $this->cashLedger->balanceAt($movements, $walletId, $today);
        }

        $walletIds = array_unique([
            ...array_keys($byWallet),
            ...array_keys(array_filter($cashBalances, fn (float $balance): bool => $balance !== 0.0)),
        ]);

        if ($walletIds === []) {
            return [];
        }

        $wallets = Wallet::query()
            ->whereIn('id', $walletIds)
            ->get(['id', 'name', 'account_type', 'opened_at', 'broker'])
            ->keyBy('id');

        $accounts = [];

        foreach ($walletIds as $walletId) {
            $walletLines = $byWallet[$walletId] ?? [];
            $wallet = $wallets[$walletId] ?? null;
            $accountType = $walletLines[0]->accountType ?? $wallet?->account_type;

            if ($accountType === null) {
                continue;
            }

            $totals = $this->valuator->totals($walletLines);
            $opened = $wallet?->opened_at;

            $ineligible = [];

            foreach ($walletLines as $line) {
                if (! $accountType->admits($line->assetClass)) {
                    $ineligible[] = $line->assetName;
                }
            }

            $accounts[] = new AccountLineData(
                walletId: $walletId,
                walletName: $walletLines[0]->walletName ?? $wallet?->name ?? '',
                broker: $wallet?->broker,
                accountType: $accountType,
                marketValue: $totals['totalValue'],
                gain: $totals['totalGain'],
                gainPct: $totals['totalGainPct'],
                // diffInYears() rend un float depuis Carbon 3 ; on tronque, on n'arrondit pas.
                ageInYears: $opened === null ? null : (int) $opened->diffInYears(now()),
                maturityYears: $accountType->maturityYears(),
                taxRegimeLabel: $accountType->taxRegimeLabel(),
                ineligibleAssetNames: $ineligible,
                cashBalance: $cashBalances[$walletId] ?? 0.0,
            );
        }

        usort(
            $accounts,
            fn (AccountLineData $left, AccountLineData $right): int => $right->marketValue <=> $left->marketValue,
        );

        return $accounts;
    }
}
