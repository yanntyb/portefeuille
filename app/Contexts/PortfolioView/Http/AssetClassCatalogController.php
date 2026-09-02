<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Actions\GetClassCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Le catalogue d'une exposition : tous ses instruments, détenus ou non, que la page de listing
 * n'expose pas — celle-ci ne montre que les positions. L'exposition arrive par le défaut de route,
 * comme pour `AssetClassController`.
 *
 * La liste est servie d'un bloc, sans `Inertia::defer` : elle est le contenu entier de la page, et
 * un squelette qui remplirait tout l'écran ne vaudrait pas mieux que l'attente.
 */
class AssetClassCatalogController
{
    public function __construct(private GetClassCatalog $getCatalog) {}

    public function __invoke(): Response
    {
        $userId = auth()->id() ?? 0;
        $exposure = AssetClass::from((string) request()->route('exposure'));

        return Inertia::render('AssetClass/Catalog', [
            'assetClass' => [
                'key' => $exposure->value,
                'label' => $exposure->getLabel(),
                'slug' => $exposure->slug(),
            ],
            'catalog' => ($this->getCatalog)($userId, $exposure),
        ]);
    }
}
