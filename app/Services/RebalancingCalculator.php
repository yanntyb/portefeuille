<?php

namespace App\Services;

class RebalancingCalculator
{
    /**
     * @param  array<int, array{security_id: int, name: string, price: float, quantity: float, target_percentage: float}>  $securities
     * @return array{items: array<int, array{security_id: int, name: string, price: float, quantity_held: float, current_value: float, current_percentage: float, target_percentage: float, shares_to_buy: int, buy_cost: float, new_value: float, new_percentage: float}>, remainder: float, total_invested: float}
     */
    public function calculate(array $securities, float $amountToInvest): array
    {
        $currentValues = [];
        $totalCurrentValue = 0;

        foreach ($securities as $index => $security) {
            $value = $security['quantity'] * $security['price'];
            $currentValues[$index] = $value;
            $totalCurrentValue += $value;
        }

        $totalAfterInvestment = $totalCurrentValue + $amountToInvest;

        $sharesToBuy = [];
        foreach ($securities as $index => $security) {
            $targetValue = $totalAfterInvestment * ($security['target_percentage'] / 100);
            $amountNeeded = $targetValue - $currentValues[$index];
            $sharesToBuy[$index] = max(0, (int) floor($amountNeeded / $security['price']));
        }

        $totalSpent = 0;
        foreach ($securities as $index => $security) {
            $totalSpent += $sharesToBuy[$index] * $security['price'];
        }

        while ($totalSpent > $amountToInvest) {
            $worstIndex = null;
            $worstExcess = -PHP_FLOAT_MAX;

            foreach ($securities as $index => $security) {
                if ($sharesToBuy[$index] <= 0) {
                    continue;
                }

                $newValue = $currentValues[$index] + ($sharesToBuy[$index] * $security['price']);
                $currentPercentage = $totalAfterInvestment > 0
                    ? ($newValue / $totalAfterInvestment) * 100
                    : 0;
                $excess = $currentPercentage - $security['target_percentage'];

                if ($excess > $worstExcess) {
                    $worstExcess = $excess;
                    $worstIndex = $index;
                }
            }

            if ($worstIndex === null) {
                break;
            }

            $sharesToBuy[$worstIndex]--;
            $totalSpent -= $securities[$worstIndex]['price'];
        }

        $remainder = $amountToInvest - $totalSpent;

        $this->allocateRemainder($securities, $sharesToBuy, $currentValues, $totalAfterInvestment, $remainder);

        $totalSpent = 0;
        foreach ($securities as $index => $security) {
            $totalSpent += $sharesToBuy[$index] * $security['price'];
        }
        $remainder = $amountToInvest - $totalSpent;

        $totalNewValue = 0;
        $newValues = [];
        foreach ($securities as $index => $security) {
            $newValue = $currentValues[$index] + ($sharesToBuy[$index] * $security['price']);
            $newValues[$index] = $newValue;
            $totalNewValue += $newValue;
        }

        $items = [];
        foreach ($securities as $index => $security) {
            $currentPercentage = $totalCurrentValue > 0
                ? ($currentValues[$index] / $totalCurrentValue) * 100
                : 0;

            $newPercentage = $totalNewValue > 0
                ? ($newValues[$index] / $totalNewValue) * 100
                : 0;

            $items[] = [
                'security_id' => $security['security_id'],
                'name' => $security['name'],
                'price' => $security['price'],
                'quantity_held' => $security['quantity'],
                'current_value' => round($currentValues[$index], 2),
                'current_percentage' => round($currentPercentage, 2),
                'target_percentage' => $security['target_percentage'],
                'shares_to_buy' => $sharesToBuy[$index],
                'buy_cost' => round($sharesToBuy[$index] * $security['price'], 2),
                'new_value' => round($newValues[$index], 2),
                'new_percentage' => round($newPercentage, 2),
            ];
        }

        return [
            'items' => $items,
            'remainder' => round($remainder, 2),
            'total_invested' => round($totalSpent, 2),
        ];
    }

    /**
     * @param  array<int, array{security_id: int, name: string, price: float, quantity: float, target_percentage: float}>  $securities
     * @param  array<int, int>  $sharesToBuy
     * @param  array<int, float>  $currentValues
     */
    private function allocateRemainder(array $securities, array &$sharesToBuy, array $currentValues, float $totalAfterInvestment, float &$remainder): void
    {
        $changed = true;

        while ($changed && $remainder > 0) {
            $changed = false;
            $bestIndex = null;
            $bestGap = -PHP_FLOAT_MAX;

            foreach ($securities as $index => $security) {
                if ($security['price'] > $remainder) {
                    continue;
                }

                $newValue = $currentValues[$index] + ($sharesToBuy[$index] * $security['price']);
                $currentPercentage = ($newValue / $totalAfterInvestment) * 100;
                $gap = $security['target_percentage'] - $currentPercentage;

                if ($gap > $bestGap) {
                    $bestGap = $gap;
                    $bestIndex = $index;
                }
            }

            if ($bestIndex !== null) {
                $sharesToBuy[$bestIndex]++;
                $remainder -= $securities[$bestIndex]['price'];
                $changed = true;
            }
        }
    }
}
