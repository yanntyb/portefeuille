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
    fakeRealEstate(128200.0, 100000.0);

    $overview = app(GetWealthOverview::class)(999);

    /**
     * Chaque nombre attendu est calculé à la main, pas relu sur l'objet produit : 184 200 + 128 200,
     * 160 000 + 100 000, et l'écart entre les deux totaux. Les deux classes gardent en plus leur
     * propre valeur pour qu'une permutation entre titres et immobilier fasse échouer le test.
     */
    expect($overview->totalValue)->toBe(312400.0)
        ->and($overview->totalInvested)->toBe(260000.0)
        ->and($overview->totalGain)->toBe(52400.0)
        ->and($overview->totalGainPct)->toBe(20.15)
        ->and($overview->securities->value)->toBe(184200.0)
        ->and($overview->securities->invested)->toBe(160000.0)
        ->and($overview->realEstate->value)->toBe(128200.0)
        ->and($overview->realEstate->invested)->toBe(100000.0);
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

it('rend un pourcentage de gain nul quand la mise immobilière est négative', function () {
    fakeHoldings(0.0, 0.0);
    fakeRealEstate(50000.0, -20000.0);

    $overview = app(GetWealthOverview::class)(999);

    /** Un bien financé à plus de 100 % : le gain se calcule, le pourcentage n'a rien à quoi se rapporter. */
    expect($overview->realEstate->gainPct)->toBeNull()
        ->and($overview->realEstate->gain)->toBe(70000.0);
});
