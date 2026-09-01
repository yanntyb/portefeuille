<?php

namespace App\Contexts\Income\Sources\Dividend\Ports;

use App\Contexts\Income\Sources\Dividend\Datas\ConfirmedDividendData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionRecordData;
use App\Contexts\Income\Sources\Dividend\Datas\PositionSnapshotData;

interface PositionHistoryPort
{
    /**
     * Tous les mouvements de position de l'utilisateur, ordre chronologique.
     *
     * @return list<PositionRecordData>
     */
    public function transactionsFor(int $userId): array;

    /**
     * Actifs que l'utilisateur a mouvementés au moins une fois : le périmètre à interroger côté
     * marché, une position soldée ayant pu percevoir un dividende avant sa vente.
     *
     * @return list<int>
     */
    public function assetIdsFor(int $userId): array;

    /** Position courante, toutes enveloppes confondues. */
    public function positionFor(int $userId, int $assetId): ?PositionSnapshotData;

    /**
     * Positions courantes de l'utilisateur, indexées par actif.
     *
     * Le pendant de `positionFor()` à l'échelle du portefeuille : une projection de revenu porte
     * sur tout ce qui est détenu aujourd'hui, pas sur un actif choisi.
     *
     * @return array<int, PositionSnapshotData>
     */
    public function positionsFor(int $userId): array;

    /**
     * Détachements déjà encaissés par une transaction de dividende : ils sortent de la
     * dérivation, `DividendIncomeSource` lisant la transaction à leur place.
     *
     * @return list<ConfirmedDividendData>
     */
    public function confirmedDividendsFor(int $userId): array;
}
