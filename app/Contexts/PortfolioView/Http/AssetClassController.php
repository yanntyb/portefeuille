<?php

namespace App\Contexts\PortfolioView\Http;

use App\Contexts\Market\Enums\AssetClass;
use App\Contexts\PortfolioView\Pages\AssetClassPage;
use Inertia\Response;

/** Une route par exposition, engendrée depuis l'enum dans `routes/web.php` ; `exposure` est un défaut de route. */
class AssetClassController
{
    public function __construct(private AssetClassPage $page) {}

    public function __invoke(): Response
    {
        $exposure = AssetClass::from((string) request()->route('exposure'));

        return $this->page->for(auth()->id() ?? 0, $exposure)->render('AssetClass/Index');
    }
}
