<?php

namespace App\Domains\Analytics\Filament\Widgets\Simulation;

use App\Domains\Analytics\Data\Simulation\CrossoverPoint;
use App\Domains\Analytics\Data\Simulation\ProjectionMonteCarlo;
use App\Domains\Analytics\Data\Simulation\Simulation;
use App\Domains\Analytics\Data\Simulation\SimulationObject;
use App\Domains\Analytics\Data\Simulation\SimulationValue;
use App\Domains\Analytics\Services\SimulationEngine;
use App\Infrastructure\Filament\Widgets\ChartWidget;
use App\Infrastructure\Support\ChartColors;
use Filament\Support\RawJs;

class SimulationScenarioChartWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = null;

    protected CrossoverPoint $crossoverPoint;

    /** @var list<string> */
    protected array $seriesNames = [];

    /** @var class-string */
    public string $scenario = ProjectionMonteCarlo::class;

    #[\Livewire\Attributes\On('simulation-changed')]
    public function onSimulationChanged(string $scenario): void
    {
        $this->scenario = $scenario;
        $this->updateChartData();
    }

    public function getHeading(): ?string
    {
        $simulation = $this->resolveSimulation();

        if (empty($simulation->scenarios)) {
            return $simulation->nom;
        }

        return $simulation->nom.' — Scénarios';
    }

    protected function resolveSimulation(): Simulation
    {
        if (class_exists($this->scenario)) {
            return Simulation::buildFromClass($this->scenario);
        }

        return Simulation::default();
    }

    protected function getData(): array
    {
        $engine = app(SimulationEngine::class);
        $simulation = $this->resolveSimulation();
        $objects = $engine->computeObjects($simulation->objects);
        $scenarioResults = $engine->computeAllScenarios($objects, $simulation->scenarios);

        $visibleNames = $this->getVisiblePipelineNames($simulation);
        $this->seriesNames = $visibleNames;

        if (empty($visibleNames)) {
            $this->crossoverPoint = new CrossoverPoint(index: null, label: null);

            return ['datasets' => [], 'labels' => []];
        }

        $labels = ['Base'];
        $seriesData = [];
        foreach ($visibleNames as $name) {
            $seriesData[$name] = [$this->extractNumericResult($objects, $name)];
        }

        foreach ($scenarioResults as $result) {
            $labels[] = $result->scenario;
            foreach ($visibleNames as $name) {
                $seriesData[$name][] = SimulationValue::parse($result->results[$name] ?? '0')->numeric;
            }
        }

        $datasets = [];
        foreach (array_values($visibleNames) as $i => $name) {
            $datasets[] = [
                'label' => $this->formatSeriesLabel($name, $simulation),
                'data' => $seriesData[$name],
                'borderColor' => ChartColors::at($i),
                'backgroundColor' => ChartColors::withAlpha($i)['bg'],
                'fill' => false,
                'tension' => 0.3,
                'pointRadius' => 0,
            ];
        }

        if (count($visibleNames) === 2) {
            $first = $seriesData[$visibleNames[0]];
            $second = $seriesData[$visibleNames[1]];
            $this->crossoverPoint = $this->findCrossoverIndex($first, $second, $labels);
        } else {
            $this->crossoverPoint = new CrossoverPoint(index: null, label: null);
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        $crossoverIndex = $this->crossoverPoint->index;
        $crossoverLabel = $this->crossoverPoint->label ?? '';

        $annotationJs = '';
        if ($crossoverIndex !== null && count($this->seriesNames) === 2) {
            $annotationJs = <<<JS
                    annotation: {
                        annotations: {
                            crossover: {
                                type: 'line',
                                xMin: {$crossoverIndex},
                                xMax: {$crossoverIndex},
                                borderColor: 'rgba(128, 128, 128, 0.7)',
                                borderWidth: 2,
                                borderDash: [6, 4],
                                label: {
                                    display: true,
                                    content: 'Croisement à {$crossoverLabel}',
                                    position: 'start',
                                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                                    color: '#fff',
                                    font: { size: 11 },
                                    padding: 4,
                                },
                            },
                        },
                    },
            JS;
        }

        return RawJs::make(<<<JS
            {
                scales: {
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 15,
                            maxRotation: 45,
                        },
                    },
                    y: {
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(value),
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ' : ' + new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(context.parsed.y),
                        },
                    },
                    {$annotationJs}
                },
            }
        JS);
    }

    /**
     * @return list<string>
     */
    private function getVisiblePipelineNames(Simulation $simulation): array
    {
        return collect($simulation->objects)
            ->filter(fn (SimulationObject $obj): bool => ! empty($obj->pipeline) && ! empty($obj->steps))
            ->map(fn (SimulationObject $obj): string => $obj->nom)
            ->reject(fn (string $name): bool => in_array($name, $simulation->hiddenFromScenario))
            ->values()
            ->all();
    }

    /**
     * @param  list<SimulationObject>  $objects
     */
    private function extractNumericResult(array $objects, string $name): ?float
    {
        foreach ($objects as $obj) {
            if ($obj->nom === $name) {
                return $obj->value->numeric;
            }
        }

        return null;
    }

    private function formatSeriesLabel(string $name, Simulation $simulation): string
    {
        $display = $name;

        foreach ($simulation->pipelineNames as $pipelineName) {
            $suffix = '_'.str_replace(' ', '', mb_strtolower($pipelineName));
            if (str_ends_with($display, $suffix)) {
                $display = substr($display, 0, -strlen($suffix));
                $display .= " ({$pipelineName})";

                break;
            }
        }

        return ucfirst(str_replace('_', ' ', $display));
    }

    /**
     * @param  list<float|null>  $seriesA
     * @param  list<float|null>  $seriesB
     * @param  list<string>  $labels
     */
    private function findCrossoverIndex(array $seriesA, array $seriesB, array $labels): CrossoverPoint
    {
        for ($i = 0; $i < count($seriesA); $i++) {
            if (($seriesB[$i] ?? 0) >= ($seriesA[$i] ?? 0) && ($seriesB[$i] ?? 0) > 0) {
                return new CrossoverPoint(index: $i, label: $labels[$i] ?? '');
            }
        }

        return new CrossoverPoint(index: null, label: null);
    }
}
