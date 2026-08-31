<?php

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Datas\CashMovementData;
use App\Contexts\Portfolio\Services\CashLedger;

function cashMovement(string $date, float $delta, int $walletId = 1, ?AssetClass $exposure = null, bool $isDeposit = false, bool $isWithdrawal = false): CashMovementData
{
    return new CashMovementData(
        date: $date,
        walletId: $walletId,
        delta: $delta,
        exposure: $exposure,
        isDeposit: $isDeposit,
        auto: false,
        isWithdrawal: $isWithdrawal,
    );
}

it('somme les mouvements d\'une enveloppe jusqu\'à une date', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-10', -400.0),
        cashMovement('2026-03-10', -100.0),
    ];

    expect((new CashLedger)->balanceAt($movements, 1, '2026-02-15'))->toBe(600.0);
});

it('ignore les mouvements d\'une autre enveloppe', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, walletId: 1, isDeposit: true),
        cashMovement('2026-01-11', 5000.0, walletId: 2, isDeposit: true),
    ];

    expect((new CashLedger)->balanceAt($movements, 1, '2026-12-31'))->toBe(1000.0);
});

it('déduit le versement manquant d\'un achat non financé', function () {
    expect((new CashLedger)->missingDeposits([cashMovement('2026-03-03', -1000.0)]))
        ->toBe([['walletId' => 1, 'date' => '2026-03-03', 'amount' => 1000.0]]);
});

it('ne déduit rien quand le cash existant couvre l\'achat', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-03-03', -600.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))->toBe([]);
});

it('ne déduit que le manque quand le cash couvre en partie', function () {
    $movements = [
        cashMovement('2026-01-10', 400.0, isDeposit: true),
        cashMovement('2026-03-03', -1000.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))
        ->toBe([['walletId' => 1, 'date' => '2026-03-03', 'amount' => 600.0]]);
});

it('laisse une vente financer un achat postérieur', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-01', -1000.0),
        cashMovement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        cashMovement('2026-08-15', -1200.0),
    ];

    expect((new CashLedger)->missingDeposits($movements))->toBe([]);
});

it('déduit par enveloppe, sans jamais financer l\'une par l\'autre', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, walletId: 1, isDeposit: true),
        cashMovement('2026-02-01', -300.0, walletId: 2),
    ];

    expect((new CashLedger)->missingDeposits($movements))
        ->toBe([['walletId' => 2, 'date' => '2026-02-01', 'amount' => 300.0]]);
});

it('tient l\'invariant : jamais de solde négatif une fois les versements déduits', function () {
    $movements = [
        cashMovement('2026-03-03', -453.0),
        cashMovement('2026-03-17', -248.25),
        cashMovement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        cashMovement('2026-09-01', -2000.0),
    ];

    $ledger = new CashLedger;
    $complete = [...$movements];

    foreach ($ledger->missingDeposits($movements) as $deposit) {
        $complete[] = cashMovement($deposit['date'], $deposit['amount'], $deposit['walletId'], isDeposit: true);
    }

    foreach (['2026-03-03', '2026-03-17', '2026-08-01', '2026-09-01'] as $day) {
        expect($ledger->balanceAt($complete, 1, $day))->toBeGreaterThanOrEqual(0.0);
    }
});

it('impute le cash restant à l\'origine qui l\'a produit', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        cashMovement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
    ];

    expect((new CashLedger)->compositionAt($movements, '2026-12-31'))
        ->toBe(['deposits' => 0.0, 'exposures' => ['equity' => 1285.5]]);
});

it('consomme les crédits du plus ancien au plus récent', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-01', 500.0, exposure: AssetClass::Equity),
        cashMovement('2026-03-01', -1200.0, exposure: AssetClass::Crypto),
    ];

    expect((new CashLedger)->compositionAt($movements, '2026-12-31'))
        ->toBe(['deposits' => 0.0, 'exposures' => ['equity' => 300.0]]);
});

it('ne crée aucun apport quand une vente finance un rachat', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        cashMovement('2026-08-01', 1285.5, exposure: AssetClass::Equity),
        cashMovement('2026-08-15', -1200.0, exposure: AssetClass::Equity),
    ];

    expect((new CashLedger)->netContributions($movements))
        ->toBe(['total' => 1000.0, 'byExposure' => ['equity' => 1000.0]]);
});

it('impute l\'apport à l\'exposition de l\'achat qu\'il finance', function () {
    $movements = [
        cashMovement('2026-02-01', -1000.0, exposure: AssetClass::Equity),
        cashMovement('2026-03-01', -500.0, exposure: AssetClass::Crypto),
    ];

    $deposits = (new CashLedger)->missingDeposits($movements);
    $complete = [...$movements];

    foreach ($deposits as $deposit) {
        $complete[] = cashMovement($deposit['date'], $deposit['amount'], $deposit['walletId'], isDeposit: true);
    }

    expect((new CashLedger)->netContributions($complete))
        ->toBe(['total' => 1500.0, 'byExposure' => ['equity' => 1000.0, 'crypto' => 500.0]]);
});

it('retranche les retraits des apports nets', function () {
    $movements = [
        cashMovement('2026-01-10', 1000.0, isDeposit: true),
        cashMovement('2026-02-01', -300.0, isWithdrawal: true),
    ];

    expect((new CashLedger)->netContributions($movements)['total'])->toBe(700.0);
});
