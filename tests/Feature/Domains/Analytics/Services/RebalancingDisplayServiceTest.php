<?php

namespace Tests\Feature\Domains\Analytics\Services;

use App\Domains\Analytics\Data\Simulation\SimulationObject;
use App\Domains\Analytics\Data\Simulation\SimulationScenario;
use App\Domains\Analytics\Data\Simulation\SimulationValue;
use App\Domains\Analytics\Services\RebalancingDisplayService;
use Tests\TestCase;

class RebalancingDisplayServiceTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private RebalancingDisplayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RebalancingDisplayService::class);
    }

    public function test_extracts_pipeline_object_names(): void
    {
        $objects = [
            new SimulationObject('Revenue', SimulationValue::parse('100'), 'CDI', [['label' => 'step', 'type' => 'multiply']]),
            new SimulationObject('Tax', SimulationValue::parse('20'), null, []),
        ];

        $names = $this->service->getPipelineObjectNames($objects, []);

        $this->assertContains('Revenue', $names);
        $this->assertNotContains('Tax', $names);
    }

    public function test_hides_hidden_objects_from_names(): void
    {
        $objects = [
            new SimulationObject('Revenue', SimulationValue::parse('100'), 'CDI', [['label' => 'step', 'type' => 'multiply']]),
            new SimulationObject('Hidden', SimulationValue::parse('50'), 'SASU', [['label' => 'step', 'type' => 'multiply']]),
        ];

        $names = $this->service->getPipelineObjectNames($objects, ['Hidden']);

        $this->assertContains('Revenue', $names);
        $this->assertNotContains('Hidden', $names);
    }

    public function test_computes_percent_difference(): void
    {
        $diff = $this->service->computePercentDiff('100', '120');

        $this->assertStringContainsString('+20,0', $diff ?? '');
    }

    public function test_returns_null_for_zero_base(): void
    {
        $diff = $this->service->computePercentDiff('0', '100');

        $this->assertNull($diff);
    }

    public function test_returns_null_for_negligible_diff(): void
    {
        $diff = $this->service->computePercentDiff('100', '100.005');

        $this->assertNull($diff);
    }

    public function test_computes_negative_percent_difference(): void
    {
        $diff = $this->service->computePercentDiff('100', '80');

        $this->assertStringContainsString('-20,0', $diff ?? '');
    }

    public function test_extracts_overridden_param_names(): void
    {
        $scenarios = [
            new SimulationScenario('Scenario 1', [
                ['param' => 'rate', 'operator' => '=', 'value' => '5'],
                ['param' => 'amount', 'operator' => '+', 'value' => '100'],
            ]),
        ];

        $params = $this->service->getOverriddenParamNames($scenarios);

        $this->assertContains('rate', $params);
        $this->assertContains('amount', $params);
    }

    public function test_returns_empty_params_for_no_scenarios(): void
    {
        $params = $this->service->getOverriddenParamNames([]);

        $this->assertSame([], $params);
    }

    public function test_builds_scenario_table_records(): void
    {
        $objects = [
            new SimulationObject('Revenue', SimulationValue::parse('1000'), 'CDI', []),
        ];

        $scenarios = [
            new SimulationScenario('High Growth', [['param' => 'rate', 'operator' => '=', 'value' => '10']]),
        ];

        $scenarioResults = [
            [
                'scenario' => 'High Growth',
                'results' => ['rate' => '10', 'Revenue' => '1100'],
            ],
        ];

        $records = $this->service->buildScenarioTableRecords(
            $objects,
            $scenarios,
            $scenarioResults,
            [],
            fn (string $param): ?string => '1000'
        );

        $this->assertIsArray($records);
        $this->assertGreaterThan(0, count($records));
        $baseRecord = $records[0];
        $this->assertSame('Base (actuel)', $baseRecord['scenario']);
        $this->assertSame('base', $baseRecord['id']);
    }

    public function test_scenario_record_includes_diff_columns(): void
    {
        $objects = [
            new SimulationObject('Revenue', SimulationValue::parse('1000'), 'CDI', [['label' => 'step', 'type' => 'multiply']]),
        ];

        $scenarios = [
            new SimulationScenario('Scenario', []),
        ];

        $scenarioResults = [
            [
                'scenario' => 'Scenario',
                'results' => ['Revenue' => '1100'],
            ],
        ];

        $records = $this->service->buildScenarioTableRecords(
            $objects,
            $scenarios,
            $scenarioResults,
            [],
            fn (string $param): ?string => '1000'
        );

        $this->assertGreaterThanOrEqual(2, count($records));
        $scenarioRecord = $records[1];
        $this->assertArrayHasKey('_diff_Revenue', $scenarioRecord);
        $this->assertStringContainsString('+10,0', $scenarioRecord['_diff_Revenue'] ?? '');
    }
}
