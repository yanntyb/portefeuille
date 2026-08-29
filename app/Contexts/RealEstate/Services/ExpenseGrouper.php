<?php

namespace App\Contexts\RealEstate\Services;

/**
 * Les charges d'un bien groupées par année civile, la plus récente en tête, et ventilées par
 * catégorie, la plus grosse en tête. À égalité l'ordre suit la première charge rencontrée (tri
 * stable).
 */
class ExpenseGrouper
{
    /**
     * @param  list<array{year: int, category: string, label: string, amount: float}>  $expenses
     * @return list<array{year: int, total: float, byCategory: list<array{category: string, label: string, amount: float}>}>
     */
    public function byYear(array $expenses): array
    {
        /** @var array<int, array<string, array{category: string, label: string, amount: float}>> $grouped */
        $grouped = [];

        foreach ($expenses as $expense) {
            $year = $expense['year'];
            $category = $expense['category'];

            $grouped[$year][$category] ??= [
                'category' => $category,
                'label' => $expense['label'],
                'amount' => 0.0,
            ];
            $grouped[$year][$category]['amount'] += $expense['amount'];
        }

        krsort($grouped);

        $years = [];

        foreach ($grouped as $year => $byCategory) {
            $entries = array_values($byCategory);

            /**
             * Le total somme les montants bruts, la ventilation arrondit les siens : c'est l'ordre
             * de l'ancien code, et l'inverser décalerait un total d'un centime sur deux charges
             * arrondies dans le même sens.
             */
            $total = round(array_sum(array_column($entries, 'amount')), 2);

            $entries = array_map(
                fn (array $entry): array => [...$entry, 'amount' => round($entry['amount'], 2)],
                $entries,
            );

            usort($entries, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

            $years[] = [
                'year' => $year,
                'total' => $total,
                'byCategory' => $entries,
            ];
        }

        return $years;
    }
}
