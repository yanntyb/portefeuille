<?php

namespace App\Data\Simulation;

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
