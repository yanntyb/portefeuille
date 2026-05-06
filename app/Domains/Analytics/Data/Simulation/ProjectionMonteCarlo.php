<?php

namespace App\Domains\Analytics\Data\Simulation;

class ProjectionMonteCarlo
{
    public static function build(): Simulation
    {
        return new Simulation(
            nom: 'Projection Monte Carlo',
            objects: [],
            scenarios: [],
            pipelineNames: [],
            hiddenFromScenario: [],
        );
    }
}
