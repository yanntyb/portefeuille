<?php

namespace App\Contexts\Income\Sources\Rent;

use App\Contexts\Income\Datas\IncomeReceiptData;
use App\Contexts\Income\Enums\IncomeSource;
use App\Contexts\Income\Ports\IncomeSourcePort;
use App\Contexts\Income\Sources\Rent\Datas\RentReceiptData;
use App\Contexts\Income\Sources\Rent\Ports\RentSchedulePort;
use Illuminate\Support\Carbon;

class RentIncomeSource implements IncomeSourcePort
{
    public function __construct(private RentSchedulePort $schedule) {}

    public function source(): IncomeSource
    {
        return IncomeSource::Rent;
    }

    /** @return list<IncomeReceiptData> */
    public function receiptsFor(int $userId): array
    {
        return array_map(
            fn (RentReceiptData $receipt): IncomeReceiptData => new IncomeReceiptData(
                source: IncomeSource::Rent,
                date: Carbon::parse($receipt->month),
                amount: $receipt->amount,
                assetId: null,
                label: $receipt->propertyName,
            ),
            $this->schedule->receiptsFor($userId),
        );
    }

    public function projectedAnnualFor(int $userId): float
    {
        return $this->schedule->projectedAnnualFor($userId);
    }
}
