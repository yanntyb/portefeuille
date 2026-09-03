<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\PortfolioView\Pages\AssetPage;
use Inertia\Response;

/** La fiche d'un actif, partagée par toutes les expositions ; le fil d'Ariane se déduit de `assetClass`. */
class AssetController
{
    public function __construct(private AssetPage $page) {}

    public function __invoke(int $id): Response
    {
        $page = $this->page->for(auth()->id() ?? 0, $id) ?? abort(404);

        return $page->render('Asset/Show');
    }
}
