<?php

use App\Contexts\Wealth\Actions\GetWealthOverview;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\HoldingsPort;

function fakeHoldings(float $value, float $invested): void
{
    app()->bind(HoldingsPort::class, fn (): HoldingsPort => new class($value, $invested) implements HoldingsPort
    {
        public function __construct(private float $value, private float $invested) {}

        public function snapshotFor(int $userId): ClassSnapshotData
        {
            return new ClassSnapshotData($this->value, $this->invested);
        }
    });
}

it('additionne les deux classes d\'actif', function () {
    fakeHoldings(184200.0, 160000.0);
    ['user' => $user] = propertyFixture();

    $overview = app(GetWealthOverview::class)($user->id);

    expect($overview->totalValue)->toBe(round($overview->securities->value + $overview->realEstate->value, 2))
        ->and($overview->totalInvested)->toBe(round($overview->securities->invested + $overview->realEstate->invested, 2))
        ->and($overview->totalGain)->toBe(round($overview->totalValue - $overview->totalInvested, 2));
});

it('rend un pourcentage de gain nul quand rien n\'a été investi', function () {
    fakeHoldings(0.0, 0.0);

    $overview = app(GetWealthOverview::class)(999);

    expect($overview->totalInvested)->toBe(0.0)
        ->and($overview->totalGainPct)->toBeNull();
});

it('rend le patrimoine des titres seuls quand aucun bien n\'existe', function () {
    fakeHoldings(1000.0, 800.0);

    $overview = app(GetWealthOverview::class)(999);

    expect($overview->totalValue)->toBe(1000.0)
        ->and($overview->realEstate->value)->toBe(0.0)
        ->and($overview->securities->gainPct)->toBe(25.0);
});
