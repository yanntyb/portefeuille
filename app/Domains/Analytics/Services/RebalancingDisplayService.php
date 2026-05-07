<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Data\Simulation\SimulationObject;
use App\Domains\Analytics\Data\Simulation\SimulationScenario;
use App\Domains\Analytics\Data\Simulation\SimulationValue;
use Closure;

class RebalancingDisplayService
{
    /**
     * @param  list<SimulationObject>  $objects
     * @param  list<string>  $hiddenNames
     * @return list<string>
     */
    public function getPipelineObjectNames(array $objects, array $hiddenNames): array
    {
        return collect($objects)
            ->filter(fn (SimulationObject $obj): bool => $obj->pipeline !== null && ! empty($obj->steps))
            ->pluck('nom')
            ->reject(fn (string $name): bool => in_array($name, $hiddenNames))
            ->values()
            ->all();
    }

    /**
     * @param  list<SimulationScenario>  $scenarios
     * @return list<string>
     */
    public function getOverriddenParamNames(array $scenarios): array
    {
        if (empty($scenarios)) {
            return [];
        }

        return collect($scenarios[0]->overrides ?? [])
            ->pluck('param')
            ->unique()
            ->values()
            ->all();
    }

    public function computePercentDiff(string $baseFormatted, string $scenarioFormatted): ?string
    {
        $base = SimulationValue::parse($baseFormatted)->numeric;
        $scenario = SimulationValue::parse($scenarioFormatted)->numeric;

        if ($base === null || $scenario === null || $base == 0) {
            return null;
        }

        $diff = (($scenario - $base) / abs($base)) * 100;

        if (abs($diff) < 0.01) {
            return null;
        }

        $sign = $diff > 0 ? '+' : '';

        return $sign.number_format($diff, 1, ',', ' ').' %';
    }

    /**
     * @param  list<SimulationObject>  $objects
     * @param  list<SimulationScenario>  $scenarios
     * @param  list<array{scenario: string, results: array<string, string>}>  $scenarioResults
     * @param  list<string>  $hiddenNames
     * @param  Closure(string): ?string  $getSourceValue
     * @return list<array<string, mixed>>
     */
    public function buildScenarioTableRecords(
        array $objects,
        array $scenarios,
        array $scenarioResults,
        array $hiddenNames,
        Closure $getSourceValue
    ): array {
        $pipelineNames = $this->getPipelineObjectNames($objects, $hiddenNames);
        $overriddenParams = $this->getOverriddenParamNames($scenarios);

        $baseRecord = ['id' => 'base', 'scenario' => 'Base (actuel)', 'description' => null, '_type' => 'base'];

        foreach ($overriddenParams as $param) {
            $baseRecord[$param] = $getSourceValue($param) ?? '—';
        }

        foreach ($pipelineNames as $name) {
            $baseRecord[$name] = $getSourceValue($name) ?? '—';
        }

        $records = [$baseRecord];

        foreach ($scenarioResults as $key => $result) {
            $description = collect($scenarios[$key]->overrides ?? [])
                ->map(fn (array $o): string => "{$o['param']} {$o['operator']} {$o['value']}")
                ->implode(' · ');

            $record = [
                'id' => "scenario-{$key}",
                'scenario' => $result['scenario'],
                'description' => $description,
                '_type' => 'scenario',
                '_index' => $key,
            ];

            foreach ($overriddenParams as $param) {
                $record[$param] = $result['results'][$param] ?? $baseRecord[$param];
                $record["_diff_{$param}"] = $this->computePercentDiff($baseRecord[$param], $record[$param]);
            }

            foreach ($pipelineNames as $name) {
                $scenarioValue = $result['results'][$name] ?? '—';
                $baseValue = $baseRecord[$name] ?? '—';
                $record[$name] = $scenarioValue;
                $record["_diff_{$name}"] = $this->computePercentDiff($baseValue, $scenarioValue);
            }

            $records[] = $record;
        }

        return $records;
    }
}
