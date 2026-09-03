<?php

namespace App\Contexts\PortfolioView\Actions;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioPositions;
use App\Contexts\PortfolioView\Pages\AssetClassPage;
use App\Contexts\PortfolioView\Pages\AssetPage;

/**
 * Le volet portefeuille de l'instantané hors-ligne : une page liste par exposition, une fiche par
 * position détenue, toutes sections résolues. Ce sont les composeurs des pages qui les rendent :
 * le blob et la page sont identiques par construction, plus par recopie.
 */
class BuildPortfolioViewSnapshot
{
    public function __construct(
        private GetPortfolioPositions $positions,
        private AssetClassPage $classPage,
        private AssetPage $assetPage,
    ) {}

    /** @return array{classes: array<string, array<string, mixed>>, assets: array<int, array<string, mixed>>} */
    public function __invoke(int $userId): array
    {
        $classes = [];
        foreach (AssetClass::cases() as $exposure) {
            $classes[$exposure->value] = $this->classPage->for($userId, $exposure)->resolve();
        }

        $assets = [];
        foreach (($this->positions)($userId) as $position) {
            $page = $this->assetPage->for($userId, $position->assetId);

            if ($page !== null) {
                $assets[$position->assetId] = $page->resolve();
            }
        }

        return ['classes' => $classes, 'assets' => $assets];
    }
}
