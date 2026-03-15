<?php

namespace App\Data\Simulation;

enum ValueFormat: string
{
    case Euro = 'euro';
    case Percent = 'percent';
    case Plain = 'plain';
}
