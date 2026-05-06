<?php

namespace App\Domains\Analytics\Data\Simulation;

enum ValueFormat: string
{
    case Euro = 'euro';
    case Percent = 'percent';
    case Plain = 'plain';
}
