<?php

namespace App\Domains\Security\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum Sector: string implements HasColor, HasLabel
{
    case Technology = 'technology';
    case Healthcare = 'healthcare';
    case FinancialServices = 'financial_services';
    case CommunicationServices = 'communication_services';
    case ConsumerCyclical = 'consumer_cyclical';
    case ConsumerDefensive = 'consumer_defensive';
    case Industrials = 'industrials';
    case Energy = 'energy';
    case Utilities = 'utilities';
    case RealEstate = 'realestate';
    case BasicMaterials = 'basic_materials';
    case Other = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Technology => 'Technologie',
            self::Healthcare => 'Santé',
            self::FinancialServices => 'Services financiers',
            self::CommunicationServices => 'Services de communication',
            self::ConsumerCyclical => 'Consommation cyclique',
            self::ConsumerDefensive => 'Consommation défensive',
            self::Industrials => 'Industrie',
            self::Energy => 'Énergie',
            self::Utilities => 'Services publics',
            self::RealEstate => 'Immobilier',
            self::BasicMaterials => 'Matériaux de base',
            self::Other => 'Autre',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Technology => 'rgb(59, 130, 246)',
            self::Healthcare => 'rgb(16, 185, 129)',
            self::FinancialServices => 'rgb(245, 158, 11)',
            self::CommunicationServices => 'rgb(239, 68, 68)',
            self::ConsumerCyclical => 'rgb(139, 92, 246)',
            self::ConsumerDefensive => 'rgb(236, 72, 153)',
            self::Industrials => 'rgb(107, 114, 128)',
            self::Energy => 'rgb(249, 115, 22)',
            self::Utilities => 'rgb(20, 184, 166)',
            self::RealEstate => 'rgb(99, 102, 241)',
            self::BasicMaterials => 'rgb(34, 197, 94)',
            self::Other => 'rgb(156, 163, 175)',
        };
    }
}
