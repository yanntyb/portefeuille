<?php

namespace App\Contexts\Income\Sources\Rent\Ports;

use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;

/** Loyers encaissés et loyer projeté, vus depuis le contexte immobilier. */
interface RentSchedulePort
{
    /** @return list<RentReceiptData> */
    public function receiptsFor(int $userId): array;

    public function projectedAnnualFor(int $userId): float;
}
