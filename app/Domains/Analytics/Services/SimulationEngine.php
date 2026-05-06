<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Data\Simulation\ScenarioOverride;
use App\Domains\Analytics\Data\Simulation\ScenarioResult;
use App\Domains\Analytics\Data\Simulation\SimulationObject;
use App\Domains\Analytics\Data\Simulation\SimulationScenario;
use App\Domains\Analytics\Data\Simulation\SimulationStep;
use App\Domains\Analytics\Data\Simulation\SimulationValue;

class SimulationEngine
{
    /**
     * @param  list<SimulationObject>  $objects
     * @return list<SimulationObject>
     */
    public function computeObjects(array $objects): array
    {
        $valuesByName = [];
        foreach ($objects as $object) {
            if ($object->nom !== '' && empty($object->steps)) {
                $valuesByName[$object->nom] = $object->value->numeric;
            }
        }

        $changed = true;
        $maxIterations = 10;

        while ($changed && $maxIterations-- > 0) {
            $changed = false;

            foreach ($objects as $index => $object) {
                if (empty($object->steps)) {
                    continue;
                }

                $result = $this->evaluateSteps($object->steps, $valuesByName);

                if ($result === null) {
                    continue;
                }

                $valuesByName[$object->nom] = $result;
                $newValue = new SimulationValue($result, $object->value->format);

                if ($newValue->formatted() !== $object->value->formatted()) {
                    $objects[$index] = $object->withValue($newValue);
                    $changed = true;
                }
            }
        }

        return $objects;
    }

    /**
     * @param  list<SimulationObject>  $objects
     * @param  list<SimulationScenario>  $scenarios
     * @return list<ScenarioResult>
     */
    public function computeAllScenarios(array $objects, array $scenarios): array
    {
        $scenarioResults = [];

        foreach ($scenarios as $scenario) {
            $scenarioObjects = $objects;

            foreach ($scenario->overrides as $override) {
                foreach ($scenarioObjects as $i => $obj) {
                    if ($obj->nom !== $override->param) {
                        continue;
                    }

                    $scenarioObjects[$i] = $obj->withValue(
                        $this->applyOverride($obj->value, $override->operator, $override->value),
                    );
                }
            }

            $computed = $this->computeObjects($scenarioObjects);

            $overriddenParams = collect($scenario->overrides)->map(fn (ScenarioOverride $o): string => $o->param)->all();

            $results = [];
            foreach ($computed as $obj) {
                if (! empty($obj->pipeline) || ! empty($obj->steps) || in_array($obj->nom, $overriddenParams)) {
                    $results[$obj->nom] = $obj->value->formatted();
                }
            }

            $scenarioResults[] = new ScenarioResult(
                scenario: $scenario->nom,
                results: $results,
            );
        }

        return $scenarioResults;
    }

    public function parseNumericValue(string $raw): ?float
    {
        return SimulationValue::parse($raw)->numeric;
    }

    public function formatValue(float $value, string $existingFormat): string
    {
        $parsed = SimulationValue::parse($existingFormat);

        return (new SimulationValue($value, $parsed->format))->formatted();
    }

    public function applyOverride(SimulationValue $currentValue, string $operator, string $overrideValue): SimulationValue
    {
        if ($operator === '=') {
            return SimulationValue::parse($overrideValue);
        }

        $current = $currentValue->numeric;
        $operand = SimulationValue::parse($overrideValue)->numeric;

        if ($current === null || $operand === null) {
            return $currentValue;
        }

        $result = match ($operator) {
            '+' => $current + $operand,
            '-' => $current - $operand,
            '*' => $current * $operand,
            '/' => $operand != 0 ? $current / $operand : $current,
            default => $current,
        };

        return new SimulationValue($result, $currentValue->format);
    }

    /**
     * Calcul mensualite pret a taux fixe : M = C * (r / (1 - (1 + r)^(-n)))
     *
     * @param  array<string, float|null>  $valuesByName
     */
    public function mensualiteCredit(float $capital, array $valuesByName): ?float
    {
        $tauxAnnuel = $valuesByName['taux_interet_annuel'] ?? null;
        $dureeMois = $valuesByName['duree_credit_mois'] ?? null;

        if ($tauxAnnuel === null || $dureeMois === null || $dureeMois == 0) {
            return null;
        }

        if ($tauxAnnuel == 0) {
            return $capital / $dureeMois;
        }

        $r = $tauxAnnuel / 12;

        return $capital * ($r / (1 - pow(1 + $r, -$dureeMois)));
    }

    /**
     * @param  list<SimulationStep>  $steps
     * @param  array<string, float|null>  $valuesByName
     */
    private function evaluateSteps(array $steps, array $valuesByName): ?float
    {
        $tokens = [];

        foreach ($steps as $step) {
            if ($step->type === 'reference') {
                $val = $valuesByName[$step->label] ?? null;
                if ($val === null) {
                    return null;
                }
                $tokens[] = ['type' => 'number', 'value' => $val];
            } elseif ($step->type === 'operator') {
                $tokens[] = ['type' => 'operator', 'value' => trim($step->label)];
            } elseif ($step->type === 'function') {
                $tokens[] = ['type' => 'function', 'value' => $step->label];
            } elseif ($step->type === 'value') {
                $val = $this->parseNumericValue($step->label);
                if ($val === null) {
                    return null;
                }
                $tokens[] = ['type' => 'number', 'value' => $val];
            }
        }

        if (empty($tokens)) {
            return null;
        }

        $result = null;
        $operator = null;

        foreach ($tokens as $token) {
            if ($token['type'] === 'number') {
                if ($result === null) {
                    $result = $token['value'];
                } elseif ($operator !== null) {
                    $result = match ($operator) {
                        '+' => $result + $token['value'],
                        '-' => $result - $token['value'],
                        '*' => $result * $token['value'],
                        '/' => $token['value'] != 0 ? $result / $token['value'] : null,
                        default => $result,
                    };
                    $operator = null;
                }
            } elseif ($token['type'] === 'operator') {
                $operator = $token['value'];
            } elseif ($token['type'] === 'function') {
                if ($result === null) {
                    return null;
                }
                $result = $this->computeFunction($token['value'], $result, $valuesByName);
                if ($result === null) {
                    return null;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, float|null>  $valuesByName
     */
    private function computeFunction(string $name, float $value, array $valuesByName = []): ?float
    {
        return match ($name) {
            'bareme_ir' => $this->baremeIr($value),
            'bareme_is' => $this->baremeIs($value),
            'mensualite_credit' => $this->mensualiteCredit($value, $valuesByName),
            'max_zero' => max(0, $value),
            default => null,
        };
    }

    /**
     * Calcul progressif de l'IR selon le bareme 2025 (1 part fiscale).
     */
    private function baremeIr(float $revenuImposable): float
    {
        $tranches = [
            [11_294, 0.00],
            [28_797, 0.11],
            [82_341, 0.30],
            [177_106, 0.41],
            [PHP_FLOAT_MAX, 0.45],
        ];

        $impot = 0.0;
        $trancheBasse = 0.0;

        foreach ($tranches as [$plafond, $taux]) {
            if ($revenuImposable <= $trancheBasse) {
                break;
            }

            $assiette = min($revenuImposable, $plafond) - $trancheBasse;
            $impot += $assiette * $taux;
            $trancheBasse = $plafond;
        }

        return $impot;
    }

    /**
     * Calcul progressif de l'IS selon le bareme 2025.
     */
    private function baremeIs(float $resultatFiscal): float
    {
        $tranches = [
            [42_500, 0.15],
            [PHP_FLOAT_MAX, 0.25],
        ];

        $impot = 0.0;
        $trancheBasse = 0.0;

        foreach ($tranches as [$plafond, $taux]) {
            if ($resultatFiscal <= $trancheBasse) {
                break;
            }

            $assiette = min($resultatFiscal, $plafond) - $trancheBasse;
            $impot += $assiette * $taux;
            $trancheBasse = $plafond;
        }

        return $impot;
    }
}
