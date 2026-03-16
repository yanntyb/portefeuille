<?php

namespace App\Filament\Widgets\Simulation;

use App\Data\Simulation\ScenarioResult;
use App\Data\Simulation\Simulation;
use App\Data\Simulation\SimulationObject;
use App\Data\Simulation\SimulationScenario;
use App\Data\Simulation\SimulationValue;
use App\Services\SimulationEngine;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\Widget;

/**
 * @property-read Schema $editingSchema
 * @property-read Schema $simulationSelectorSchema
 */
class SimulationBoardWidget extends Widget implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.widgets.simulation.simulation-board-widget';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /** @var class-string */
    public string $currentSimulationClass = '';

    /** @var list<array{nom: string, value: string, pipeline: ?string, source: ?string, steps: list<array{label: string, type: string}>}> */
    public array $objects = [];

    public ?int $editingIndex = null;

    public ?string $activePipeline = null;

    /** @var list<string> */
    public array $pipelineNames = ['CDI', 'SASU'];

    /** @var list<string> Pipeline object names hidden from scenario table */
    public array $hiddenFromScenario = [];

    /** @var list<array{nom: string, overrides: list<array{param: string, operator: string, value: string}>}> */
    public array $scenarios = [];

    /** @var list<array{scenario: string, results: array<string, string>}> */
    public array $scenarioResults = [];

    public function mount(): void
    {
        $this->switchSimulation(array_key_first(Simulation::available()));
    }

    public function loadSimulation(Simulation $simulation): void
    {
        $engine = app(SimulationEngine::class);

        $this->pipelineNames = $simulation->pipelineNames;
        $this->hiddenFromScenario = $simulation->hiddenFromScenario;

        $computed = $engine->computeObjects($simulation->objects);
        $this->objects = array_map(fn (SimulationObject $o): array => $o->toArray(), $computed);
        $this->scenarios = array_map(fn (SimulationScenario $s): array => $s->toArray(), $simulation->scenarios);

        $this->editingIndex = null;
        $this->activePipeline = null;

        $this->computeAllScenarios();
    }

    public function switchSimulation(string $class): void
    {
        $simulations = Simulation::available();

        if (! isset($simulations[$class])) {
            return;
        }

        $this->currentSimulationClass = $class;
        $this->loadSimulation($simulations[$class]);
        $this->resetTable();
        $this->dispatch('simulation-changed', scenario: $class);
    }

    /**
     * @return array<class-string, string>
     */
    public function getSimulationOptions(): array
    {
        return collect(Simulation::available())
            ->map(fn (Simulation $s): string => $s->nom)
            ->all();
    }

    public function simulationSelectorSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('currentSimulationClass')
                    ->label('Simulation')
                    ->options($this->getSimulationOptions())
                    ->live()
                    ->afterStateUpdated(fn (string $state) => $this->switchSimulation($state)),
            ]);
    }

    public function toggleEdit(int $index): void
    {
        $this->editingIndex = $this->editingIndex === $index ? null : $index;

        if ($this->editingIndex !== null) {
            $this->cacheSchema('editingSchema', null);
        }
    }

    public function closeEdit(): void
    {
        $this->editingIndex = null;
    }

    public function editingSchema(Schema $schema): Schema
    {
        if ($this->editingIndex === null) {
            return $schema->components([]);
        }

        $sourceOptions = collect($this->objects)
            ->filter(fn (array $object): bool => $object['nom'] !== '' && $object['nom'] !== ($this->objects[$this->editingIndex]['nom'] ?? ''))
            ->pluck('nom', 'nom')
            ->all();

        $pipelineOptions = array_combine($this->pipelineNames, $this->pipelineNames);

        return $schema
            ->components([
                TextInput::make('nom')
                    ->label('Nom'),
                TextInput::make('value')
                    ->label('Valeur'),
                Select::make('pipeline')
                    ->label('Pipeline')
                    ->options($pipelineOptions)
                    ->placeholder('Aucun (paramètre)'),
                Repeater::make('steps')
                    ->label('Expression')
                    ->schema([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'reference' => 'Référence',
                                'operator' => 'Opérateur',
                                'value' => 'Valeur',
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Select $component) => $component
                                ->getContainer()
                                ->getComponent('stepLabelFields')
                                ->getChildSchema()
                                ->fill()),
                        Grid::make(1)
                            ->schema(fn (Get $get): array => match ($get('type')) {
                                'reference' => [
                                    Select::make('label')
                                        ->label('Paramètre')
                                        ->options($sourceOptions)
                                        ->searchable(),
                                ],
                                default => [
                                    TextInput::make('label')
                                        ->label($get('type') === 'operator' ? 'Opérateur' : 'Valeur')
                                        ->placeholder($get('type') === 'operator' ? '+, -, *, /' : '21,45 %'),
                                ],
                            })
                            ->key('stepLabelFields'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('+ step'),
            ])
            ->statePath("objects.{$this->editingIndex}");
    }

    public function addObject(?string $pipeline = null): void
    {
        $this->objects[] = (new SimulationObject(
            nom: '',
            value: SimulationValue::plain(0),
            pipeline: $pipeline,
            source: null,
            steps: [],
        ))->toArray();

        $this->editingIndex = array_key_last($this->objects);
        $this->cacheSchema('editingSchema', null);
    }

    /**
     * @return array{params: list<array{index: int, object: array}>, pipelines: array<string, list<array{index: int, object: array}>>}
     */
    public function getLayout(): array
    {
        $params = [];
        $pipelines = array_fill_keys($this->pipelineNames, []);

        foreach ($this->objects as $index => $object) {
            if (empty($object['pipeline'])) {
                $params[] = ['index' => $index, 'object' => $object];
            } else {
                $pipelines[$object['pipeline']][] = ['index' => $index, 'object' => $object];
            }
        }

        return [
            'params' => $params,
            'pipelines' => $pipelines,
        ];
    }

    public function getSourceValue(string $sourceName): ?string
    {
        foreach ($this->objects as $object) {
            if ($object['nom'] === $sourceName) {
                return $object['value'];
            }
        }

        return null;
    }

    /**
     * @param  string  $property  The Livewire property path that was updated
     */
    public function updated(string $property): void
    {
        if (str_starts_with($property, 'objects.')) {
            $this->recalculate();
        }
    }

    public function recalculate(): void
    {
        $engine = app(SimulationEngine::class);

        $dtoObjects = array_map(fn (array $o): SimulationObject => SimulationObject::fromArray($o), $this->objects);
        $computed = $engine->computeObjects($dtoObjects);
        $this->objects = array_map(fn (SimulationObject $o): array => $o->toArray(), $computed);

        $this->computeAllScenarios();
        $this->resetTable();
    }

    public function computeAllScenarios(): void
    {
        $engine = app(SimulationEngine::class);

        $dtoObjects = array_map(fn (array $o): SimulationObject => SimulationObject::fromArray($o), $this->objects);
        $dtoScenarios = array_map(fn (array $s): SimulationScenario => SimulationScenario::fromArray($s), $this->scenarios);

        $results = $engine->computeAllScenarios($dtoObjects, $dtoScenarios);
        $this->scenarioResults = array_map(fn (ScenarioResult $r): array => $r->toArray(), $results);
    }

    /**
     * @return list<string>
     */
    public function getPipelineObjectNames(): array
    {
        return collect($this->objects)
            ->filter(fn (array $obj): bool => ! empty($obj['pipeline']) && ! empty($obj['steps']))
            ->pluck('nom')
            ->reject(fn (string $name): bool => in_array($name, $this->hiddenFromScenario))
            ->values()
            ->all();
    }

    public function isVisibleInScenario(int $index): bool
    {
        $nom = $this->objects[$index]['nom'] ?? '';

        return ! in_array($nom, $this->hiddenFromScenario);
    }

    public function toggleScenarioVisibility(int $index): void
    {
        $nom = $this->objects[$index]['nom'] ?? '';

        if ($nom === '') {
            return;
        }

        if (in_array($nom, $this->hiddenFromScenario)) {
            $this->hiddenFromScenario = array_values(array_diff($this->hiddenFromScenario, [$nom]));
        } else {
            $this->hiddenFromScenario[] = $nom;
        }
    }

    private function computePercentDiff(string $baseFormatted, string $scenarioFormatted): ?string
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
     * @return list<string>
     */
    private function getOverriddenParamNames(): array
    {
        if (empty($this->scenarios)) {
            return [];
        }

        return collect($this->scenarios[0]['overrides'] ?? [])
            ->pluck('param')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getScenarioTableRecords(): array
    {
        $pipelineNames = $this->getPipelineObjectNames();
        $overriddenParams = $this->getOverriddenParamNames();

        $baseRecord = ['id' => 'base', 'scenario' => 'Base (actuel)', 'description' => null, '_type' => 'base'];

        foreach ($overriddenParams as $param) {
            $baseRecord[$param] = $this->getSourceValue($param) ?? '—';
        }

        foreach ($pipelineNames as $name) {
            $baseRecord[$name] = $this->getSourceValue($name) ?? '—';
        }

        $records = [$baseRecord];

        foreach ($this->scenarioResults as $key => $result) {
            $description = collect($this->scenarios[$key]['overrides'] ?? [])
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

    public function table(Table $table): Table
    {
        $pipelineNames = $this->getPipelineObjectNames();
        $overriddenParams = $this->getOverriddenParamNames();

        $columns = [
            TextColumn::make('scenario')
                ->label('Scénario')
                ->description(fn (array $record): ?string => $record['description'] ?? null),
        ];

        foreach ($overriddenParams as $param) {
            $columns[] = TextColumn::make($param)
                ->label($param)
                ->alignEnd()
                ->fontFamily('mono')
                ->description(fn (array $record): ?string => $record["_diff_{$param}"] ?? null);
        }

        foreach ($pipelineNames as $name) {
            $columns[] = TextColumn::make($name)
                ->label($name)
                ->alignEnd()
                ->fontFamily('mono')
                ->description(fn (array $record): ?string => $record["_diff_{$name}"] ?? null);
        }

        $paramOptions = collect($this->objects)
            ->filter(fn (array $obj): bool => empty($obj['steps']) && $obj['nom'] !== '')
            ->pluck('nom', 'nom')
            ->all();

        return $table
            ->records(fn (): array => $this->getScenarioTableRecords())
            ->columns($columns)
            ->paginated(false)
            ->headerActions([
                Action::make('addScenario')
                    ->label('Ajouter un scénario')
                    ->modalHeading('Nouveau scénario')
                    ->schema([
                        TextInput::make('nom')
                            ->label('Nom')
                            ->placeholder('Ex : Salaire +10 %')
                            ->required(),
                        Repeater::make('overrides')
                            ->label('Transformations')
                            ->schema([
                                Select::make('param')
                                    ->label('Paramètre')
                                    ->options($paramOptions)
                                    ->required()
                                    ->searchable(),
                                Select::make('operator')
                                    ->label('Opération')
                                    ->options([
                                        '=' => '= (remplacer)',
                                        '+' => '+ (ajouter)',
                                        '-' => '- (soustraire)',
                                        '*' => '× (multiplier)',
                                        '/' => '÷ (diviser)',
                                    ])
                                    ->default('=')
                                    ->required(),
                                TextInput::make('value')
                                    ->label('Valeur')
                                    ->placeholder('4 000 € ou 1,10')
                                    ->required(),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('+ transformation'),
                    ])
                    ->action(function (array $data): void {
                        $overrides = [];
                        foreach ($data['overrides'] as $override) {
                            $overrides[] = [
                                'param' => $override['param'],
                                'operator' => $override['operator'],
                                'value' => $override['value'],
                            ];
                        }

                        $this->scenarios[] = [
                            'nom' => $data['nom'],
                            'overrides' => $overrides,
                        ];

                        $this->computeAllScenarios();
                        $this->resetTable();
                    }),
            ])
            ->recordAction('scenarioMenu')
            ->recordActions([
                Action::make('scenarioMenu')
                    ->hidden(fn (array $record): bool => ($record['_type'] ?? '') === 'base')
                    ->extraAttributes(['class' => 'hidden'])
                    ->modalHeading(fn (array $record): string => $record['scenario'] ?? 'Scénario')
                    ->modalDescription(fn (array $record): ?string => $record['description'] ?? null)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->extraModalFooterActions(function (array $record) use ($paramOptions): array {
                        if (($record['_type'] ?? '') === 'base') {
                            return [];
                        }

                        $index = $record['_index'];

                        return [
                            Action::make('menuEditScenario')
                                ->label('Modifier')
                                ->icon('heroicon-o-pencil-square')
                                ->color('gray')
                                ->cancelParentActions()
                                ->fillForm(fn (): array => $this->scenarios[$index] ?? [])
                                ->schema([
                                    TextInput::make('nom')
                                        ->label('Nom')
                                        ->required(),
                                    Repeater::make('overrides')
                                        ->label('Transformations')
                                        ->schema([
                                            Select::make('param')
                                                ->label('Paramètre')
                                                ->options($paramOptions)
                                                ->required()
                                                ->searchable(),
                                            Select::make('operator')
                                                ->label('Opération')
                                                ->options([
                                                    '=' => '= (remplacer)',
                                                    '+' => '+ (ajouter)',
                                                    '-' => '- (soustraire)',
                                                    '*' => '× (multiplier)',
                                                    '/' => '÷ (diviser)',
                                                ])
                                                ->default('=')
                                                ->required(),
                                            TextInput::make('value')
                                                ->label('Valeur')
                                                ->placeholder('4 000 € ou 1,10')
                                                ->required(),
                                        ])
                                        ->columns(3)
                                        ->defaultItems(1)
                                        ->addActionLabel('+ transformation'),
                                ])
                                ->action(function (array $data) use ($index): void {
                                    $overrides = [];
                                    foreach ($data['overrides'] as $override) {
                                        $overrides[] = [
                                            'param' => $override['param'],
                                            'operator' => $override['operator'],
                                            'value' => $override['value'],
                                        ];
                                    }

                                    $this->scenarios[$index] = [
                                        'nom' => $data['nom'],
                                        'overrides' => $overrides,
                                    ];

                                    $this->computeAllScenarios();
                                    $this->resetTable();
                                }),
                            Action::make('menuDeleteScenario')
                                ->label('Supprimer')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->cancelParentActions()
                                ->requiresConfirmation()
                                ->action(function () use ($index): void {
                                    unset($this->scenarios[$index]);
                                    $this->scenarios = array_values($this->scenarios);

                                    unset($this->scenarioResults[$index]);
                                    $this->scenarioResults = array_values($this->scenarioResults);

                                    $this->resetTable();
                                }),
                        ];
                    }),
            ]);
    }

    public function formatDisplayName(string $nom, ?string $pipeline = null): string
    {
        $display = $nom;

        if ($pipeline !== null) {
            foreach ($this->pipelineNames as $pipelineName) {
                $suffix = '_'.str_replace(' ', '', mb_strtolower($pipelineName));
                if (str_ends_with($display, $suffix)) {
                    $display = substr($display, 0, -strlen($suffix));

                    break;
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $display));
    }

    public function findObjectIndexByName(string $name): ?int
    {
        foreach ($this->objects as $index => $object) {
            if ($object['nom'] === $name) {
                return $index;
            }
        }

        return null;
    }

    public function editReferencedParamAction(): Action
    {
        return Action::make('editReferencedParam')
            ->modalHeading(fn (array $arguments): string => $arguments['paramName'] ?? 'Paramètre')
            ->modalDescription(fn (array $arguments): ?string => $this->getSourceValue($arguments['paramName'] ?? '') ?? null)
            ->fillForm(function (array $arguments): array {
                $index = $this->findObjectIndexByName($arguments['paramName'] ?? '');

                if ($index === null) {
                    return [];
                }

                return ['value' => $this->objects[$index]['value']];
            })
            ->schema([
                TextInput::make('value')
                    ->label('Valeur')
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $index = $this->findObjectIndexByName($arguments['paramName'] ?? '');

                if ($index === null) {
                    return;
                }

                $this->objects[$index]['value'] = $data['value'];
                $this->recalculate();
            })
            ->modalSubmitActionLabel('Enregistrer');
    }

    public function objectMenuAction(): Action
    {
        return Action::make('objectMenu')
            ->modalHeading(fn (array $arguments): string => $this->objects[$arguments['index']]['nom'] ?: 'sans nom')
            ->modalDescription(fn (array $arguments): string => $this->objects[$arguments['index']]['value'] ?: '—')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->extraModalFooterActions(function (array $arguments): array {
                $index = $arguments['index'];
                $isPipeline = ! empty($this->objects[$index]['pipeline']);

                $actions = [
                    Action::make('menuEdit')
                        ->label('Modifier')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->cancelParentActions()
                        ->action(fn () => $this->toggleEdit($index)),
                ];

                if ($isPipeline) {
                    $visible = $this->isVisibleInScenario($index);
                    $actions[] = Action::make('menuToggleScenario')
                        ->label($visible ? 'Masquer du scénario' : 'Afficher dans le scénario')
                        ->icon($visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                        ->color('gray')
                        ->cancelParentActions()
                        ->action(fn () => $this->toggleScenarioVisibility($index));
                }

                $actions[] = Action::make('menuDelete')
                    ->label('Supprimer')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->cancelParentActions()
                    ->requiresConfirmation()
                    ->action(function () use ($index): void {
                        unset($this->objects[$index]);
                        $this->objects = array_values($this->objects);

                        if ($this->editingIndex === $index) {
                            $this->editingIndex = null;
                        }
                    });

                return $actions;
            });
    }
}
