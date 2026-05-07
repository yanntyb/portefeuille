<?php

namespace Tests\Feature\Domains\Analytics\Services;

use App\Domains\Analytics\Data\Simulation\ScenarioOverride;
use App\Domains\Analytics\Data\Simulation\SimulationObject;
use App\Domains\Analytics\Data\Simulation\SimulationScenario;
use App\Domains\Analytics\Data\Simulation\SimulationStep;
use App\Domains\Analytics\Data\Simulation\SimulationValue;
use App\Domains\Analytics\Services\SimulationEngine;
use Tests\TestCase;

class SimulationEngineTest extends TestCase
{
    private SimulationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(SimulationEngine::class);
    }

    public function test_computes_simple_parameters(): void
    {
        $objects = [
            new SimulationObject('salary', SimulationValue::parse('4000'), null, []),
            new SimulationObject('bonus', SimulationValue::parse('500'), null, []),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertCount(2, $computed);
        $this->assertSame('4000', $computed[0]->value->formatted());
        $this->assertSame('500', $computed[1]->value->formatted());
    }

    public function test_computes_pipeline_with_single_reference(): void
    {
        $objects = [
            new SimulationObject('salary', SimulationValue::parse('1000'), null, []),
            new SimulationObject('net_salary', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'salary'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertSame('1000', $computed[1]->value->formatted());
    }

    public function test_computes_pipeline_with_operator(): void
    {
        $objects = [
            new SimulationObject('salary', SimulationValue::parse('1000'), null, []),
            new SimulationObject('taxed_salary', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'salary'),
                new SimulationStep(type: 'operator', label: '+'),
                new SimulationStep(type: 'value', label: '100'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertSame('1100', $computed[1]->value->formatted());
    }

    public function test_handles_missing_reference_gracefully(): void
    {
        $objects = [
            new SimulationObject('result', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'nonexistent'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertSame('0', $computed[0]->value->formatted());
    }

    public function test_handles_division_by_zero(): void
    {
        $objects = [
            new SimulationObject('divisor', SimulationValue::parse('0'), null, []),
            new SimulationObject('result', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'value', label: '100'),
                new SimulationStep(type: 'operator', label: '/'),
                new SimulationStep(type: 'reference', label: 'divisor'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertSame('0', $computed[1]->value->formatted());
    }

    public function test_resolves_iterative_references(): void
    {
        $objects = [
            new SimulationObject('base', SimulationValue::parse('100'), null, []),
            new SimulationObject('step1', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'base'),
                new SimulationStep(type: 'operator', label: '+'),
                new SimulationStep(type: 'value', label: '50'),
            ]),
            new SimulationObject('step2', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'step1'),
                new SimulationStep(type: 'operator', label: '*'),
                new SimulationStep(type: 'value', label: '2'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $this->assertSame('150', $computed[1]->value->formatted());
        $this->assertSame('300', $computed[2]->value->formatted());
    }

    public function test_calculates_ir_tax_correctly(): void
    {
        $objects = [
            new SimulationObject('income', SimulationValue::parse('50000'), null, []),
            new SimulationObject('ir_tax', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'income'),
                new SimulationStep(type: 'function', label: 'bareme_ir'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $irValue = $computed[1]->value->numeric;
        $this->assertGreaterThan(0, $irValue);
        $this->assertLessThan(50000, $irValue);
    }

    public function test_calculates_is_tax_correctly(): void
    {
        $objects = [
            new SimulationObject('profit', SimulationValue::parse('100000'), null, []),
            new SimulationObject('is_tax', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'profit'),
                new SimulationStep(type: 'function', label: 'bareme_is'),
            ]),
        ];

        $computed = $this->engine->computeObjects($objects);

        $isValue = $computed[1]->value->numeric;
        $this->assertGreaterThan(0, $isValue);
        $this->assertLessThan(100000, $isValue);
    }

    public function test_applies_override_operator_equals(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '=', '200');

        $this->assertSame('200', $result->formatted());
    }

    public function test_applies_override_operator_plus(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '+', '50');

        $this->assertSame('150', $result->formatted());
    }

    public function test_applies_override_operator_minus(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '-', '30');

        $this->assertSame('70', $result->formatted());
    }

    public function test_applies_override_operator_multiply(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '*', '1.1');

        $this->assertEqualsWithDelta(110.0, $result->numeric, 0.0001);
    }

    public function test_applies_override_operator_divide(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '/', '2');

        $this->assertSame('50', $result->formatted());
    }

    public function test_applies_override_divide_by_zero_returns_current(): void
    {
        $value = SimulationValue::parse('100');
        $result = $this->engine->applyOverride($value, '/', '0');

        $this->assertSame('100', $result->formatted());
    }

    public function test_computes_all_scenarios_with_single_override(): void
    {
        $objects = [
            new SimulationObject('salary', SimulationValue::parse('4000'), null, []),
            new SimulationObject('net', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'salary'),
            ]),
        ];

        $scenarios = [
            new SimulationScenario('Higher Salary', [
                new ScenarioOverride(param: 'salary', operator: '+', value: '1000'),
            ]),
        ];

        $results = $this->engine->computeAllScenarios($objects, $scenarios);

        $this->assertCount(1, $results);
        $this->assertSame('Higher Salary', $results[0]->scenario);
        $this->assertSame('5000', $results[0]->results['salary']);
        $this->assertSame('5000', $results[0]->results['net']);
    }

    public function test_computes_multiple_scenarios(): void
    {
        $objects = [
            new SimulationObject('base', SimulationValue::parse('1000'), null, []),
            new SimulationObject('result', SimulationValue::parse('0'), 'CDI', [
                new SimulationStep(type: 'reference', label: 'base'),
            ]),
        ];

        $scenarios = [
            new SimulationScenario('Plus 10%', [
                new ScenarioOverride(param: 'base', operator: '*', value: '1.1'),
            ]),
            new SimulationScenario('Plus 20%', [
                new ScenarioOverride(param: 'base', operator: '*', value: '1.2'),
            ]),
        ];

        $results = $this->engine->computeAllScenarios($objects, $scenarios);

        $this->assertCount(2, $results);
        $this->assertSame('1100', $results[0]->results['base']);
        $this->assertSame('1200', $results[1]->results['base']);
    }

    public function test_scenario_includes_overridden_params_in_results(): void
    {
        $objects = [
            new SimulationObject('param1', SimulationValue::parse('100'), null, []),
            new SimulationObject('param2', SimulationValue::parse('200'), null, []),
        ];

        $scenarios = [
            new SimulationScenario('Test', [
                new ScenarioOverride(param: 'param1', operator: '=', value: '150'),
            ]),
        ];

        $results = $this->engine->computeAllScenarios($objects, $scenarios);

        $this->assertArrayHasKey('param1', $results[0]->results);
        $this->assertSame('150', $results[0]->results['param1']);
    }

    public function test_parses_numeric_value_correctly(): void
    {
        $result = $this->engine->parseNumericValue('4500,50 €');
        $this->assertSame(4500.5, $result);
    }

    public function test_parses_numeric_value_handles_invalid(): void
    {
        $result = $this->engine->parseNumericValue('invalid');
        $this->assertNull($result);
    }

    public function test_formats_value_with_existing_format(): void
    {
        $result = $this->engine->formatValue(5000.0, '4000,00 €');
        $this->assertStringContainsString('€', $result);
        $this->assertStringContainsString(',00', $result);
    }

    public function test_mensualite_credit_calculation(): void
    {
        $valuesByName = [
            'taux_interet_annuel' => 0.03,
            'duree_credit_mois' => 120,
        ];

        $result = $this->engine->mensualiteCredit(100000, $valuesByName);

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $result);
        $this->assertLessThan(100000, $result);
    }

    public function test_mensualite_credit_handles_missing_taux(): void
    {
        $valuesByName = ['duree_credit_mois' => 120];
        $result = $this->engine->mensualiteCredit(100000, $valuesByName);

        $this->assertNull($result);
    }

    public function test_mensualite_credit_zero_taux(): void
    {
        $valuesByName = [
            'taux_interet_annuel' => 0.0,
            'duree_credit_mois' => 120,
        ];

        $result = $this->engine->mensualiteCredit(100000, $valuesByName);

        $this->assertSame(100000 / 120, $result);
    }
}
