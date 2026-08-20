<?php

namespace App\Contexts\RealEstate\Services;

use App\Contexts\RealEstate\Datas\LeaseTermData;
use App\Contexts\RealEstate\Datas\RentExceptionData;
use App\Contexts\RealEstate\Datas\RentMonthData;
use Illuminate\Support\Carbon;

/**
 * Loyers mois par mois depuis les baux : mois couvert = loyer plein sauf exception, mois sans
 * bail = vacance à zéro. Un bail couvre chaque mois que son intervalle touche, même
 * partiellement — un départ le 10 laisse le loyer du mois dû.
 */
class RentScheduleCalculator
{
    /**
     * @param  list<LeaseTermData>  $leases
     * @param  list<RentExceptionData>  $exceptions
     * @return list<RentMonthData>
     */
    public function months(array $leases, array $exceptions, Carbon $until): array
    {
        if ($leases === []) {
            return [];
        }

        $overrides = [];
        foreach ($exceptions as $exception) {
            $overrides[$exception->month] = $exception->amountOverride;
        }

        $starts = array_map(fn (LeaseTermData $lease): string => $lease->start, $leases);
        $cursor = Carbon::parse(min($starts))->startOfMonth();
        $lastMonth = $until->copy()->startOfMonth();

        $months = [];

        while ($cursor <= $lastMonth) {
            $key = $cursor->toDateString();
            $expected = $this->rentFor($leases, $cursor);
            $effective = $expected > 0 ? ($overrides[$key] ?? $expected) : 0.0;

            $months[] = new RentMonthData(month: $key, expected: $expected, effective: $effective);

            $cursor = $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /** @param list<LeaseTermData> $leases */
    public function projectedAnnual(array $leases, Carbon $today): float
    {
        $active = $this->leaseCovering($leases, $today->copy()->startOfMonth());

        return $active === null ? 0.0 : round($active->monthlyRent * 12, 2);
    }

    /** @param list<LeaseTermData> $leases */
    private function rentFor(array $leases, Carbon $month): float
    {
        $lease = $this->leaseCovering($leases, $month);

        return $lease?->monthlyRent ?? 0.0;
    }

    /** Bail dont l'intervalle touche le mois donné (comparé au premier jour du mois). */
    private function leaseCovering(array $leases, Carbon $month): ?LeaseTermData
    {
        foreach ($leases as $lease) {
            $startMonth = Carbon::parse($lease->start)->startOfMonth();
            $endMonth = $lease->end === null ? null : Carbon::parse($lease->end)->startOfMonth();

            if ($startMonth <= $month && ($endMonth === null || $month <= $endMonth)) {
                return $lease;
            }
        }

        return null;
    }
}
