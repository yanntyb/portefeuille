<?php

namespace App\Contexts\Wealth\Infrastructure;

use App\Contexts\Wealth\Datas\ClassSectorData;
use App\Contexts\Wealth\Datas\ClassSeriesData;
use App\Contexts\Wealth\Datas\ClassSnapshotData;
use App\Contexts\Wealth\Ports\AssetClassPort;
use App\Contexts\Wealth\Ports\CashPort;

/**
 * Les liquidités : ce que chaque enveloppe détient en espèces, toutes confondues. Ni un
 * portefeuille tenu par le marché ni un parc immobilier — écrite à la main comme `RealEstateClass`,
 * `PortfolioAssetClass` ne couvrant que les expositions de `AssetClass`.
 */
class CashClass implements AssetClassPort
{
    public function __construct(private CashPort $cash) {}

    public function key(): string
    {
        return 'cash';
    }

    public function label(): string
    {
        return 'Liquidités';
    }

    /**
     * Aucune page ne détaille les liquidités : ce que chaque enveloppe tient en espèces se lit
     * déjà sur le tableau de bord même. Un lien vers `/` y renvoyait la page sur elle-même.
     */
    public function href(): ?string
    {
        return null;
    }

    public function color(): string
    {
        return 'cash';
    }

    /**
     * `null` délibérément : les dividendes appartiennent à l'exposition qui les verse. En déclarer
     * une origine ici les compterait deux fois — une fois côté action, une fois côté caisse où ils
     * dorment en attendant d'être replacés. `WealthInvariantTest` garde cette règle.
     */
    public function incomeLabel(): ?string
    {
        return null;
    }

    public function snapshotFor(int $userId): ClassSnapshotData
    {
        return $this->cash->snapshotFor($userId);
    }

    /**
     * Une tranche unique à son nom : les liquidités n'ont pas de secteur boursier, mais pèsent
     * dans la ventilation du patrimoine et doivent s'y montrer, comme `RealEstateClass`.
     *
     * @return list<ClassSectorData>
     */
    public function sectorSlicesFor(int $userId): array
    {
        return [new ClassSectorData(label: $this->label(), value: $this->cash->snapshotFor($userId)->value)];
    }

    public function seriesFor(int $userId): ClassSeriesData
    {
        return $this->cash->seriesFor($userId);
    }

    public function monthlyIncomeFor(int $userId): float
    {
        return 0.0;
    }
}
