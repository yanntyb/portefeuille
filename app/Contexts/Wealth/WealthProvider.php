<?php

namespace App\Contexts\Wealth;

use App\Contexts\Income\Actions\GetIncomeSummary;
use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\Portfolio\Actions\GetPortfolioOverview;
use App\Contexts\Valuation\Actions\BuildEvolutionSeries;
use App\Contexts\Wealth\Infrastructure\AssetClassRegistry;
use App\Contexts\Wealth\Infrastructure\PortfolioAssetClass;
use App\Contexts\Wealth\Ports\AssetClassPort;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WealthProvider extends ServiceProvider
{
    /**
     * L'ordre des classes est un contrat : celui des lignes du résumé et des bandes du graphe. Les
     * expositions viennent d'abord, dans l'ordre des cas de `AssetClass` ; les classes écrites à la
     * main suivent, dans l'ordre où on les passe.
     *
     * Le conteneur ne sait pas résoudre un `AssetClass` en paramètre de constructeur : c'est donc
     * le registre qui instancie, et non un `tag()` de noms de classes.
     *
     * @param  list<class-string<AssetClassPort>>  $extra  classes écrites à la main
     */
    public static function registers(Application $app, array $extra): void
    {
        $app->scoped(
            AssetClassRegistry::class,
            function (Application $app) use ($extra): AssetClassRegistry {
                $exposures = array_map(
                    fn (AssetClass $exposure): AssetClassPort => new PortfolioAssetClass(
                        $exposure,
                        $app->make(GetPortfolioOverview::class),
                        $app->make(BuildEvolutionSeries::class),
                        $app->make(GetIncomeSummary::class),
                    ),
                    AssetClass::cases(),
                );

                return new AssetClassRegistry([
                    ...$exposures,
                    ...array_map(fn (string $class): AssetClassPort => $app->make($class), $extra),
                ]);
            },
        );
    }
}
